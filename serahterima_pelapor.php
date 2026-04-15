<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$sessionUser = getCurrentUser();
$user        = $sessionUser;
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=:id LIMIT 1");
    $stmt->execute([':id' => $sessionUser['id']]);
    $dbRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dbRow) { unset($dbRow['password']); $user = $dbRow; }
} catch (Exception $e) {}

if (($user['role'] ?? '') === 'petugas') {
    header('Location: index_petugas.php'); exit;
}

$firstName = explode(' ', $user['nama'] ?? 'User')[0];
$uid       = (int)($user['id'] ?? 0);
$flash     = $flashType = '';

/* ══════════════════════════════════════════════════
   POST HANDLERS
══════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = $_POST['action']       ?? '';
    $pencocokanId = (int)($_POST['pencocokan_id'] ?? 0);
    $laporanIdPost = (int)($_POST['laporan_id']   ?? 0);

    /* ── 1. Ajukan Bukti Kepemilikan ── */
    if ($action === 'ajukan_bukti' && ($pencocokanId || $laporanIdPost)) {
        $filePath = null;

        if (!empty($_FILES['bukti_foto']['tmp_name'])) {
            $f    = $_FILES['bukti_foto'];
            $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','pdf'];
            if (!in_array($ext, $allowed)) {
                $flash = "Format file tidak didukung. Gunakan JPG, PNG, WEBP, atau PDF.";
                $flashType = 'error';
            } elseif ($f['size'] > 5 * 1024 * 1024) {
                $flash = "Ukuran file maksimal 5 MB.";
                $flashType = 'error';
            } else {
                $dir = 'uploads/bukti/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $name = 'BUKTI_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dir . $name)) {
                    $filePath = $dir . $name;
                }
            }
        } else {
            $flash = "File bukti kepemilikan wajib diupload.";
            $flashType = 'error';
        }

        if (!$flash) {
            try {
                $pdo = getDB();

                /* Jika tidak ada pencocokan_id, cari atau buat dari laporan_id */
                if (!$pencocokanId && $laporanIdPost) {
                    /* Pastikan laporan milik user ini dan statusnya ditemukan */
                    $cekLap = $pdo->prepare("
                        SELECT id FROM laporan_kehilangan
                        WHERE id=:lid AND user_id=:uid AND status='ditemukan' AND deleted_at IS NULL LIMIT 1
                    ");
                    $cekLap->execute([':lid' => $laporanIdPost, ':uid' => $uid]);
                    if (!$cekLap->fetch()) {
                        $flash = "Laporan tidak ditemukan."; $flashType = 'error';
                    } else {
                        /* Cek apakah sudah ada pencocokan */
                        $cekP = $pdo->prepare("SELECT id FROM pencocokan WHERE laporan_id=:lid AND deleted_at IS NULL LIMIT 1");
                        $cekP->execute([':lid' => $laporanIdPost]);
                        $rowP = $cekP->fetch(PDO::FETCH_ASSOC);
                        if ($rowP) {
                            $pencocokanId = (int)$rowP['id'];
                        } else {
                            /* Buat pencocokan baru — cari barang_id dari barang_temuan yang statusnya dicocokkan */
                            $cekBt = $pdo->prepare("
                                SELECT id FROM barang_temuan
                                WHERE status='dicocokkan' AND deleted_at IS NULL
                                ORDER BY updated_at DESC LIMIT 1
                            ");
                            $cekBt->execute();
                            $rowBt = $cekBt->fetch(PDO::FETCH_ASSOC);
                            $barangIdNew = $rowBt ? (int)$rowBt['id'] : 0;
                            $ins = $pdo->prepare("
                                INSERT INTO pencocokan (laporan_id, barang_id, petugas_id, status, catatan, created_by)
                                VALUES (:lid, :bid, :ptid, 'diverifikasi', 'Dibuat otomatis saat pelapor upload bukti', :uid)
                            ");
                            $ins->execute([
                                ':lid'  => $laporanIdPost,
                                ':bid'  => $barangIdNew,
                                ':ptid' => $uid,
                                ':uid'  => $uid,
                            ]);
                            $pencocokanId = (int)$pdo->lastInsertId();
                        }
                    }
                }

                if (!$flash && $pencocokanId) {
                    /* Verifikasi pencocokan milik laporan user ini */
                    $chk = $pdo->prepare("
                        SELECT p.id FROM pencocokan p
                        JOIN laporan_kehilangan lk ON lk.id = p.laporan_id
                        WHERE p.id = :pid AND lk.user_id = :uid
                          AND p.deleted_at IS NULL AND lk.deleted_at IS NULL
                        LIMIT 1
                    ");
                    $chk->execute([':pid' => $pencocokanId, ':uid' => $uid]);
                    if (!$chk->fetch()) {
                        $flash = "Data pencocokan tidak valid."; $flashType = 'error';
                    } else {
                        /* Cek apakah sudah ada bukti sebelumnya */
                        $cekAda = $pdo->prepare("SELECT id FROM bukti_kepemilikan WHERE pencocokan_id=:pid AND user_id=:uid AND deleted_at IS NULL LIMIT 1");
                        $cekAda->execute([':pid' => $pencocokanId, ':uid' => $uid]);
                        if ($cekAda->fetch()) {
                            $pdo->prepare("
                                UPDATE bukti_kepemilikan
                                SET file_bukti=:fb, status_verifikasi='menunggu', updated_at=NOW(), updated_by=:uid
                                WHERE pencocokan_id=:pid AND user_id=:uid AND deleted_at IS NULL
                            ")->execute([':fb' => $filePath, ':pid' => $pencocokanId, ':uid' => $uid]);
                        } else {
                            $pdo->prepare("
                                INSERT INTO bukti_kepemilikan (pencocokan_id, user_id, file_bukti, status_verifikasi, created_by)
                                VALUES (:pid, :uid, :fb, 'menunggu', :uid2)
                            ")->execute([':pid' => $pencocokanId, ':uid' => $uid, ':fb' => $filePath, ':uid2' => $uid]);
                        }
                        $flash = "✅ Bukti kepemilikan berhasil diajukan! Petugas akan memverifikasi dalam 1×24 jam.";
                        $flashType = 'success';
                    }
                }
            } catch (Exception $e) {
                $flash = "❌ Gagal: " . $e->getMessage(); $flashType = 'error';
            }
        }

    /* ── 2. Konfirmasi Pengambilan Barang ── */
    } elseif ($action === 'konfirmasi_ambil' && $pencocokanId) {
        try {
            $pdo = getDB();
            /* Pastikan bukti sudah valid (disetujui) */
            $chk = $pdo->prepare("
                SELECT bk.id FROM bukti_kepemilikan bk
                JOIN pencocokan p ON p.id = bk.pencocokan_id
                JOIN laporan_kehilangan lk ON lk.id = p.laporan_id
                WHERE bk.pencocokan_id = :pid
                  AND bk.user_id = :uid
                  AND bk.status_verifikasi = 'valid'
                  AND bk.deleted_at IS NULL
                LIMIT 1
            ");
            $chk->execute([':pid' => $pencocokanId, ':uid' => $uid]);
            if (!$chk->fetch()) {
                $flash = "Bukti kepemilikan belum diverifikasi petugas."; $flashType = 'error';
            } else {
                $pdo->beginTransaction();

                /* Insert ke serah_terima */
                $pdo->prepare("
                    INSERT INTO serah_terima (pencocokan_id, petugas_id, pelapor_id, tanggal_serah_terima, catatan, created_by)
                    SELECT :pid, p.petugas_id, :uid, NOW(), 'Dikonfirmasi oleh pelapor', :uid2
                    FROM pencocokan p WHERE p.id = :pid2
                ")->execute([':pid' => $pencocokanId, ':uid' => $uid, ':uid2' => $uid, ':pid2' => $pencocokanId]);

                /* Update status laporan jadi selesai */
                $pdo->prepare("
                    UPDATE laporan_kehilangan lk
                    JOIN pencocokan p ON p.laporan_id = lk.id
                    SET lk.status = 'selesai', lk.updated_at = NOW(), lk.updated_by = :uid
                    WHERE p.id = :pid AND lk.user_id = :uid2
                ")->execute([':uid' => $uid, ':pid' => $pencocokanId, ':uid2' => $uid]);

                /* Update status barang temuan jadi diserahkan */
                $pdo->prepare("
                    UPDATE barang_temuan bt
                    JOIN pencocokan p ON p.barang_id = bt.id
                    SET bt.status = 'diserahkan', bt.updated_at = NOW()
                    WHERE p.id = :pid
                ")->execute([':pid' => $pencocokanId]);

                $pdo->commit();
                $flash = "🎉 Serah terima dikonfirmasi! Terima kasih telah menggunakan CommuterLink.";
                $flashType = 'success';
            }
        } catch (Exception $e) {
            if (isset($pdo)) try { $pdo->rollBack(); } catch (Exception $ignored) {}
            $flash = "❌ Gagal: " . $e->getMessage(); $flashType = 'error';
        }
    }
}

/* ══════════════════════════════════════════════════
   LOAD DATA — menggunakan struktur DB yang ada
══════════════════════════════════════════════════ */

/* Pencocokan yang sudah ada tapi belum diajukan bukti */
$siapDiajukan = [];
/* Bukti yang sudah diajukan (semua status) */
$buktiList    = [];
/* Siap diambil (bukti = valid, belum ada serah_terima) */
$siapDiambil  = [];
/* Riwayat selesai */
$riwayatSelesai = [];

try {
    $pdo = getDB();

    /* ── Laporan ditemukan milik user: gabungkan yang punya pencocokan & tidak ── */
    $siapDiajukan = $pdo->prepare("
        SELECT
            p.id        AS pencocokan_id,
            lk.id       AS laporan_id,
            lk.no_laporan, lk.nama_barang, lk.lokasi_hilang,
            lk.status   AS status_laporan,
            bt.id       AS barang_id,
            bt.nama_barang  AS nama_temuan,
            bt.lokasi_ditemukan,
            bt.foto_barang  AS foto_temuan,
            u_pt.nama       AS petugas_nama,
            u_pt.no_telepon AS petugas_telp
        FROM laporan_kehilangan lk
        LEFT JOIN pencocokan p
               ON p.laporan_id = lk.id AND p.deleted_at IS NULL
        LEFT JOIN barang_temuan bt
               ON bt.id = p.barang_id  AND bt.deleted_at IS NULL
        LEFT JOIN users u_pt
               ON u_pt.id = p.petugas_id
        LEFT JOIN bukti_kepemilikan bk
               ON bk.pencocokan_id = p.id AND bk.user_id = :uid AND bk.deleted_at IS NULL
        WHERE lk.user_id    = :uid2
          AND lk.status     = 'ditemukan'
          AND lk.deleted_at IS NULL
          AND bk.id IS NULL
        ORDER BY lk.updated_at DESC
    ");
    $siapDiajukan->execute([':uid' => $uid, ':uid2' => $uid]);
    $siapDiajukan = $siapDiajukan->fetchAll(PDO::FETCH_ASSOC);

    /* ── Semua bukti yang diajukan (exclude laporan yg sudah selesai) ── */
    $buktiList = $pdo->prepare("
        SELECT bk.id AS bukti_id, bk.pencocokan_id, bk.file_bukti,
               bk.status_verifikasi, bk.catatan_petugas,
               bk.created_at AS tgl_bukti, bk.updated_at AS tgl_update,
               lk.no_laporan, lk.nama_barang, lk.lokasi_hilang, lk.status AS status_laporan,
               bt.lokasi_ditemukan, bt.foto_barang AS foto_temuan,
               u_pt.nama AS petugas_nama, u_pt.no_telepon AS petugas_telp,
               st.id AS serah_terima_id
        FROM bukti_kepemilikan bk
        JOIN pencocokan p ON p.id = bk.pencocokan_id
        JOIN laporan_kehilangan lk ON lk.id = p.laporan_id
        LEFT JOIN barang_temuan bt ON bt.id = p.barang_id
        LEFT JOIN users u_pt       ON u_pt.id = p.petugas_id
        LEFT JOIN serah_terima st  ON st.pencocokan_id = bk.pencocokan_id
        WHERE bk.user_id = :uid
          AND bk.deleted_at IS NULL
          AND lk.deleted_at IS NULL
          AND lk.status NOT IN ('selesai', 'ditutup')
          AND st.id IS NULL
        ORDER BY bk.updated_at DESC
    ");
    $buktiList->execute([':uid' => $uid]);
    $buktiList = $buktiList->fetchAll(PDO::FETCH_ASSOC);

    /*
     * ── Siap diambil ──
     * Kondisi: bukti sudah valid ATAU laporan status=ditemukan & serah_terima sudah ada
     * tapi konfirmasi pelapor belum dilakukan (laporan belum selesai)
     * Juga tangkap kasus di mana petugas langsung membuat serah_terima
     * tanpa update status_verifikasi di bukti_kepemilikan
     */
    $siapDiambil = $pdo->prepare("
        SELECT bk.id AS bukti_id, bk.pencocokan_id, bk.file_bukti,
               lk.no_laporan, lk.nama_barang, lk.lokasi_hilang,
               bt.lokasi_ditemukan, bt.kode_barang,
               u_pt.nama AS petugas_nama, u_pt.no_telepon AS petugas_telp
        FROM bukti_kepemilikan bk
        JOIN pencocokan p ON p.id = bk.pencocokan_id
        JOIN laporan_kehilangan lk ON lk.id = p.laporan_id
        LEFT JOIN barang_temuan bt ON bt.id = p.barang_id
        LEFT JOIN users u_pt       ON u_pt.id = p.petugas_id
        LEFT JOIN serah_terima st  ON st.pencocokan_id = bk.pencocokan_id
        WHERE bk.user_id = :uid
          AND bk.deleted_at IS NULL
          AND lk.deleted_at IS NULL
          AND lk.status = 'ditemukan'
          AND st.id IS NULL
          AND (
              bk.status_verifikasi = 'valid'
              OR p.status = 'diverifikasi'
          )
        ORDER BY bk.updated_at DESC
    ");
    $siapDiambil->execute([':uid' => $uid]);
    $siapDiambil = $siapDiambil->fetchAll(PDO::FETCH_ASSOC);

    /* ── Riwayat selesai ── */
    $riwayatSelesai = $pdo->prepare("
        SELECT lk.no_laporan, lk.nama_barang, lk.lokasi_hilang,
               lk.updated_at AS tgl_selesai,
               bt.lokasi_ditemukan,
               st.tanggal_serah_terima, st.catatan,
               u_pt.nama AS petugas_nama
        FROM laporan_kehilangan lk
        JOIN pencocokan p ON p.laporan_id = lk.id AND p.deleted_at IS NULL
        JOIN barang_temuan bt ON bt.id = p.barang_id AND bt.deleted_at IS NULL
        LEFT JOIN serah_terima st ON st.pencocokan_id = p.id
        LEFT JOIN users u_pt ON u_pt.id = st.petugas_id
        WHERE lk.user_id = :uid AND lk.status = 'selesai' AND lk.deleted_at IS NULL
        ORDER BY lk.updated_at DESC
    ");
    $riwayatSelesai->execute([':uid' => $uid]);
    $riwayatSelesai = $riwayatSelesai->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $flash = "⚠️ Error database: " . $e->getMessage();
    $flashType = 'error';
}

$tabSiapDiajukan = count($siapDiajukan);
$tabBukti        = count($buktiList);
$tabSiapDiambil  = count($siapDiambil);
$tabSelesai      = count($riwayatSelesai);

$activeTab = $_GET['tab'] ?? ($tabSiapDiambil > 0 ? 'siap' : ($tabSiapDiajukan > 0 ? 'ajukan' : 'status'));
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Serah Terima Barang — CommuterLink Nusantara</title>
<link rel="icon" href="uploads/favicon.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --navy:#0D1B2E;--navy-2:#152640;--navy-3:#1E3357;--navy-4:#253F6A;
  --amber:#F59E0B;--amber-lt:#FCD34D;
  --bg:#0A1628;--bg-2:#0F1F38;--card:#132035;--card-2:#192A45;
  --text:#F0F6FF;--text-2:#A8BDD6;--text-3:#5A7A9E;--white:#FFFFFF;
  --border:rgba(255,255,255,0.07);--border-2:rgba(255,255,255,0.12);
  --success:#10B981;--danger:#F87171;--info:#60A5FA;--warning:#F59E0B;
  --card-r:16px;
}
[data-theme="light"]{--bg:#F0F6FF;--bg-2:#E4EEF9;--card:#FFFFFF;--card-2:#F5F9FF;--text:#0D1B2E;--text-2:#2A4263;--text-3:#6B89A8;--border:rgba(13,27,46,0.08);--border-2:rgba(13,27,46,0.14);}
[data-theme="light"] .top-nav{background:rgba(240,246,255,0.92);}
[data-theme="light"] .page-hero{background:linear-gradient(135deg,#1E3357 0%,#253F6A 60%,#2B4D80 100%);}
[data-theme="light"] .form-ctrl{background:#f8faff;border-color:rgba(13,27,46,0.15);color:#0D1B2E;}
[data-theme="light"] .tab-bar{background:#fff;border-color:rgba(13,27,46,0.08);}
[data-accent="blue"]  {--amber:#3B82F6;--amber-lt:#60A5FA;}
[data-accent="green"] {--amber:#10B981;--amber-lt:#34D399;}
[data-accent="purple"]{--amber:#8B5CF6;--amber-lt:#A78BFA;}
[data-accent="rose"]  {--amber:#EC4899;--amber-lt:#F472B6;}
[data-fontsize="sm"]{font-size:14px;}[data-fontsize="md"]{font-size:16px;}[data-fontsize="lg"]{font-size:18px;}
[data-compact="true"] .page-wrap{padding:1rem 1.25rem 7rem;}
[data-anim="off"] *{animation:none!important;transition:none!important;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}

/* TOP NAV */
.top-nav{position:sticky;top:0;z-index:200;background:rgba(10,22,40,0.92);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);padding:0 1.5rem;height:62px;display:flex;align-items:center;justify-content:space-between;gap:1rem;transition:background .3s;}
.nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.brand-gem{width:34px;height:34px;background:linear-gradient(135deg,var(--amber),var(--amber-lt));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;box-shadow:0 4px 14px rgba(245,158,11,.4);}
.brand-name{font-family:'Fraunces',serif;font-size:1.05rem;font-weight:700;color:var(--white);line-height:1;}
.brand-name em{font-style:italic;color:var(--amber);}
.brand-sub{font-size:.6rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.1em;}
.nav-actions{display:flex;align-items:center;gap:.5rem;}
.nav-icon-btn{width:36px;height:36px;border:1px solid var(--border);background:var(--card);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--text-2);font-size:.95rem;text-decoration:none;transition:all .2s;position:relative;cursor:pointer;}
.nav-icon-btn:hover{border-color:var(--amber);color:var(--amber);background:rgba(245,158,11,.1);}
.notif-pip{position:absolute;top:7px;right:7px;width:7px;height:7px;background:#F87171;border-radius:50%;border:1.5px solid var(--bg);}
.nav-avatar{width:36px;height:36px;background:linear-gradient(135deg,var(--amber),var(--amber-lt));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;color:var(--navy);border:none;cursor:pointer;text-decoration:none;}

/* HERO */
.page-hero{background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 60%,var(--navy-4) 100%);padding:2rem 1.5rem 1.75rem;position:relative;overflow:hidden;}
.page-hero::before{content:'';position:absolute;width:300px;height:300px;background:radial-gradient(circle,rgba(245,158,11,.15) 0%,transparent 70%);top:-80px;right:-60px;border-radius:50%;}
.page-hero::after{content:'📦';position:absolute;right:1.5rem;bottom:-10px;font-size:6rem;opacity:.07;pointer-events:none;}
.hero-eyebrow{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--amber);margin-bottom:.35rem;}
.hero-title{font-family:'Fraunces',serif;font-size:1.75rem;font-weight:900;color:var(--white);line-height:1.15;margin-bottom:.4rem;}
.hero-title em{font-style:italic;color:var(--amber-lt);}
.hero-sub{font-size:.82rem;color:rgba(255,255,255,.55);line-height:1.6;margin-bottom:1.25rem;max-width:520px;}
.hero-pills{display:flex;gap:.6rem;flex-wrap:wrap;}
.hero-pill{display:inline-flex;align-items:center;gap:6px;padding:.4rem .85rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid;}
.hp-blue{background:rgba(96,165,250,.12);color:var(--info);border-color:rgba(96,165,250,.2);}
.hp-amber{background:rgba(245,158,11,.12);color:var(--amber);border-color:rgba(245,158,11,.2);}
.hp-green{background:rgba(16,185,129,.12);color:var(--success);border-color:rgba(16,185,129,.2);}

/* PAGE */
.page-wrap{padding:1.5rem 1.5rem 7.5rem;max-width:900px;margin:0 auto;}

/* ALERT */
.flash-bar{display:flex;align-items:center;gap:.75rem;padding:.85rem 1.1rem;border-radius:12px;font-size:.83rem;font-weight:600;margin-bottom:1.25rem;}
.fb-success{background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.2);}
.fb-error  {background:rgba(248,113,113,.1);color:#DC2626;border:1px solid rgba(248,113,113,.15);}

/* TAB BAR */
.tab-bar{display:flex;gap:2px;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:4px;margin-bottom:1.5rem;overflow-x:auto;scrollbar-width:none;}
.tab-bar::-webkit-scrollbar{display:none;}
.tab-btn{display:flex;align-items:center;gap:6px;padding:.55rem 1rem;border-radius:10px;border:none;background:none;color:var(--text-3);font-family:'DM Sans',sans-serif;font-size:.8rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:all .2s;}
.tab-btn:hover{color:var(--text-2);background:rgba(255,255,255,.05);}
.tab-btn.active{background:rgba(245,158,11,.12);color:var(--amber);}
.tab-badge{background:var(--amber);color:var(--navy);font-size:.58rem;font-weight:800;padding:1px 6px;border-radius:99px;min-width:18px;text-align:center;}
.tab-badge.red{background:var(--danger);color:#fff;}

/* CARD */
.cl-card{background:var(--card);border:1px solid var(--border);border-radius:var(--card-r);overflow:hidden;margin-bottom:1rem;}
.card-head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border);}
.card-head-title{font-size:.88rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.card-head-title i{color:var(--amber);}
.card-body{padding:1.25rem;}

/* KLAIM CARD */
.klaim-card{background:var(--card);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:1rem;transition:border-color .2s;}
.klaim-card:hover{border-color:var(--border-2);}
.klaim-header{background:linear-gradient(135deg,var(--navy-2),var(--navy-3));padding:1.1rem 1.25rem;position:relative;overflow:hidden;}
.klaim-header::after{content:'';position:absolute;right:-20px;top:-20px;width:100px;height:100px;background:radial-gradient(circle,rgba(245,158,11,.18) 0%,transparent 70%);border-radius:50%;}
.klaim-no{font-size:.67rem;font-weight:700;color:var(--amber);background:rgba(245,158,11,.15);padding:2px 9px;border-radius:6px;display:inline-block;margin-bottom:.35rem;}
.klaim-name{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:700;color:var(--white);margin-bottom:.3rem;}
.klaim-meta{font-size:.73rem;color:rgba(255,255,255,.5);display:flex;gap:1rem;flex-wrap:wrap;}
.klaim-body{padding:1.1rem 1.25rem;}

/* STATUS BADGE */
.status-badge{display:inline-flex;align-items:center;gap:5px;padding:.35rem .85rem;border-radius:99px;font-size:.72rem;font-weight:700;}
.sb-menunggu  {background:rgba(245,158,11,.12);color:var(--warning);}
.sb-valid     {background:rgba(16,185,129,.12);color:var(--success);}
.sb-tidak_valid{background:rgba(248,113,113,.1);color:var(--danger);}
.sb-selesai   {background:rgba(16,185,129,.12);color:var(--success);}

/* INFO ROWS */
.info-row{display:flex;align-items:center;gap:.75rem;padding:.55rem 0;border-bottom:1px solid var(--border);}
.info-row:last-child{border-bottom:none;}
.info-row.top{align-items:flex-start;}
.info-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
.info-label{font-size:.68rem;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;}
.info-val{font-size:.82rem;font-weight:600;color:var(--text-2);margin-top:1px;}

/* FORM */
.form-ctrl{width:100%;padding:.65rem .95rem;border:1.5px solid var(--border-2);border-radius:10px;background:var(--card-2);color:var(--text);font-family:'DM Sans',sans-serif;font-size:.83rem;outline:none;transition:border-color .2s,box-shadow .2s;}
.form-ctrl:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(245,158,11,.12);}
.form-ctrl::placeholder{color:var(--text-3);}
.form-lbl{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-3);display:block;margin-bottom:.35rem;}
.upload-area{border:2px dashed var(--border-2);border-radius:12px;padding:1.5rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;position:relative;}
.upload-area:hover,.upload-area.drag{border-color:var(--amber);background:rgba(245,158,11,.04);}
.upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;}
.upload-icon-wrap{font-size:1.8rem;color:var(--text-3);margin-bottom:.5rem;display:block;}
.upload-text{font-size:.78rem;color:var(--text-3);line-height:1.5;}
.upload-preview-wrap{display:none;margin-top:.75rem;}
.upload-preview-wrap img{max-height:120px;border-radius:8px;border:1px solid var(--border);}

/* BUTTONS */
.btn-amber{display:inline-flex;align-items:center;gap:7px;padding:.7rem 1.4rem;background:var(--amber);color:var(--navy);border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.83rem;font-weight:700;cursor:pointer;transition:transform .15s,box-shadow .15s;text-decoration:none;}
.btn-amber:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(245,158,11,.35);color:var(--navy);}
.btn-ghost{display:inline-flex;align-items:center;gap:7px;padding:.65rem 1.25rem;background:transparent;color:var(--text-2);border:1.5px solid var(--border-2);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.83rem;font-weight:600;cursor:pointer;transition:all .2s;text-decoration:none;}
.btn-ghost:hover{border-color:var(--amber);color:var(--amber);}

/* SERAH TERIMA CARD */
.serah-card{background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(16,185,129,.03));border:1.5px solid rgba(16,185,129,.2);border-radius:16px;padding:1.4rem;margin-bottom:1rem;position:relative;overflow:hidden;}
.serah-card::before{content:'✅';position:absolute;right:1rem;top:50%;transform:translateY(-50%);font-size:4rem;opacity:.08;}
.serah-label{font-size:.67rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--success);margin-bottom:.3rem;}
.serah-name{font-family:'Fraunces',serif;font-size:1.2rem;font-weight:700;color:var(--text);margin-bottom:.6rem;}
.serah-info{font-size:.78rem;color:var(--text-2);line-height:1.8;margin-bottom:1rem;}
.serah-info strong{color:var(--text);font-weight:700;}
.konfirmasi-box{background:rgba(245,158,11,.08);border:1.5px solid rgba(245,158,11,.2);border-radius:12px;padding:1rem 1.15rem;}
.konfirmasi-title{font-size:.82rem;font-weight:700;color:var(--amber);margin-bottom:.5rem;display:flex;align-items:center;gap:7px;}
.konfirmasi-checklist{list-style:none;padding:0;margin:0 0 .85rem;}
.konfirmasi-checklist li{font-size:.78rem;color:var(--text-2);padding:.3rem 0;display:flex;align-items:flex-start;gap:8px;}
.konfirmasi-checklist li::before{content:'○';color:var(--amber);font-size:.8rem;flex-shrink:0;margin-top:1px;}

/* STEPS */
.steps{display:flex;flex-direction:column;gap:0;margin:.75rem 0 1rem;}
.step-item{display:flex;gap:1rem;position:relative;}
.step-item:not(:last-child)::before{content:'';position:absolute;left:18px;top:38px;bottom:-8px;width:2px;background:var(--border-2);}
.step-item.done::before{background:var(--success);}
.step-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;border:2px solid var(--border-2);background:var(--card-2);color:var(--text-3);z-index:1;}
.step-item.done .step-icon{background:rgba(16,185,129,.15);border-color:var(--success);color:var(--success);}
.step-item.current .step-icon{background:rgba(245,158,11,.15);border-color:var(--amber);color:var(--amber);animation:pulseStep .9s ease infinite;}
@keyframes pulseStep{0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,.4);}50%{box-shadow:0 0 0 6px rgba(245,158,11,0);}}
.step-content{padding:.35rem 0 1.2rem;}
.step-title{font-size:.85rem;font-weight:700;color:var(--text-3);}
.step-item.done .step-title,.step-item.current .step-title{color:var(--text);}
.step-desc{font-size:.73rem;color:var(--text-3);margin-top:2px;line-height:1.5;}

/* RIWAYAT */
.riwayat-row{display:flex;align-items:center;gap:.85rem;padding:.85rem 1.1rem;border-bottom:1px solid var(--border);transition:background .15s;}
.riwayat-row:last-child{border-bottom:none;}
.riwayat-icon{width:38px;height:38px;border-radius:10px;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;color:var(--success);font-size:1rem;flex-shrink:0;}
.riwayat-name{font-size:.85rem;font-weight:700;color:var(--text);}
.riwayat-sub{font-size:.72rem;color:var(--text-3);margin-top:2px;}

/* HOW-TO */
.howto{background:var(--card-2);border:1px solid var(--border);border-radius:14px;padding:1.25rem;margin-bottom:1rem;}
.howto-title{font-size:.78rem;font-weight:700;color:var(--amber);display:flex;align-items:center;gap:6px;margin-bottom:.85rem;}
.howto-steps{display:flex;gap:.75rem;flex-wrap:wrap;}
.howto-step{flex:1;min-width:120px;text-align:center;padding:.75rem .5rem;}
.hs-num{width:32px;height:32px;background:rgba(245,158,11,.12);border-radius:50%;border:1.5px solid rgba(245,158,11,.25);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:var(--amber);margin:0 auto .5rem;}
.hs-label{font-size:.72rem;font-weight:600;color:var(--text-2);line-height:1.4;}

/* EMPTY */
.empty-state{text-align:center;padding:3.5rem 1.5rem;color:var(--text-3);}
.empty-state i{font-size:2.8rem;opacity:.25;display:block;margin-bottom:1rem;}
.empty-title{font-size:.9rem;font-weight:700;color:var(--text-2);margin-bottom:.35rem;}
.empty-sub{font-size:.78rem;line-height:1.6;}

/* BOTTOM NAV */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:150;background:rgba(10,22,40,0.96);backdrop-filter:blur(20px);border-top:1px solid var(--border);display:flex;padding-bottom:env(safe-area-inset-bottom,0);}
[data-theme="light"] .bottom-nav{background:rgba(240,246,255,0.96);}
.bn-item{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:.6rem 0.15rem;text-decoration:none;color:var(--text-3);font-size:.58rem;font-weight:600;line-height:1.3;transition:color .2s;position:relative;}
.bn-item i{font-size:1.15rem;margin-bottom:2px;display:block;}
.bn-item.active{color:var(--amber);}
.bn-item.active::before{content:'';position:absolute;top:0;left:15%;right:15%;height:2.5px;background:var(--amber);border-radius:0 0 3px 3px;}
.bn-item:hover{color:var(--amber-lt);}
.bn-pip{position:absolute;top:6px;right:calc(50% - 14px);width:6px;height:6px;background:var(--danger);border-radius:50%;border:1.5px solid var(--bg);}

/* SETTINGS PANEL */
.sp-overlay{position:fixed;inset:0;z-index:8888;background:rgba(0,0,0,0);pointer-events:none;transition:background .35s;}
.sp-overlay.open{background:rgba(0,0,0,.55);pointer-events:all;backdrop-filter:blur(4px);}
.settings-panel{position:fixed;top:0;right:0;bottom:0;z-index:8889;width:340px;max-width:92vw;background:#0E1E35;border-left:1px solid rgba(255,255,255,.09);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .38s cubic-bezier(.22,1,.36,1);box-shadow:-24px 0 80px rgba(0,0,0,.55);}
.settings-panel.open{transform:translateX(0);}
.sp-header{padding:1.2rem 1.4rem 1rem;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;}
.sp-title{font-size:.95rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:8px;}
.sp-title i{color:#F59E0B;}
.sp-close{width:30px;height:30px;background:rgba(255,255,255,.07);border:none;border-radius:7px;color:rgba(255,255,255,.5);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.9rem;}
.sp-body{flex:1;overflow-y:auto;padding:1.1rem 1.4rem;}
.sp-section{margin-bottom:1.3rem;}
.sp-section-label{font-size:.63rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:.65rem;display:flex;align-items:center;gap:7px;}
.sp-section-label::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.07);}
.theme-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;}
.theme-card{padding:.7rem .5rem .6rem;border-radius:10px;border:2px solid rgba(255,255,255,.08);cursor:pointer;background:rgba(255,255,255,.04);text-align:center;transition:all .2s;position:relative;}
.theme-card.active{border-color:#F59E0B;background:rgba(245,158,11,.1);}
.theme-card-icon{font-size:1.4rem;margin-bottom:4px;display:block;}
.theme-card-name{font-size:.65rem;font-weight:700;color:rgba(255,255,255,.65);}
.theme-card.active .theme-card-name{color:#F59E0B;}
.theme-check{position:absolute;top:4px;right:4px;width:14px;height:14px;background:#F59E0B;border-radius:50%;display:none;align-items:center;justify-content:center;font-size:.45rem;color:#000;}
.theme-card.active .theme-check{display:flex;}
.accent-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:.5rem;}
.accent-dot{width:100%;aspect-ratio:1;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:transform .18s;}
.accent-dot:hover{transform:scale(1.12);}
.accent-dot.active{border-color:#fff;box-shadow:0 0 0 3px rgba(255,255,255,.2);}
.accent-label{text-align:center;font-size:.58rem;color:rgba(255,255,255,.4);margin-top:4px;font-weight:600;}
.fontsize-row{display:flex;gap:.45rem;}
.fs-btn{flex:1;padding:.5rem .4rem;border-radius:9px;border:2px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);color:rgba(255,255,255,.5);cursor:pointer;font-family:inherit;font-weight:700;transition:all .18s;text-align:center;}
.fs-btn.active{border-color:#F59E0B;background:rgba(245,158,11,.1);color:#F59E0B;}
.sp-toggle-row{display:flex;align-items:center;justify-content:space-between;padding:.65rem 0;border-bottom:1px solid rgba(255,255,255,.06);}
.sp-toggle-row:last-child{border-bottom:none;}
.sp-toggle-label{font-size:.8rem;font-weight:700;color:rgba(255,255,255,.8);}
.sp-switch{position:relative;width:38px;height:21px;}
.sp-switch input{opacity:0;width:0;height:0;}
.sp-slider{position:absolute;inset:0;cursor:pointer;background:rgba(255,255,255,.12);border-radius:21px;transition:background .25s;}
.sp-slider::before{content:'';position:absolute;height:15px;width:15px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:transform .25s;}
input:checked+.sp-slider{background:#F59E0B;}
input:checked+.sp-slider::before{transform:translateX(17px);}
.sp-footer{padding:.9rem 1.4rem;border-top:1px solid rgba(255,255,255,.08);display:flex;gap:.5rem;}
.sp-btn-reset{flex:1;padding:.6rem;border-radius:9px;border:1.5px solid rgba(255,255,255,.1);background:none;color:rgba(255,255,255,.45);font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;}
.sp-btn-apply{flex:2;padding:.6rem;border-radius:9px;border:none;background:#F59E0B;color:#0D1B2E;font-family:inherit;font-size:.78rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;}
.sp-toast{position:fixed;bottom:5.5rem;left:50%;transform:translateX(-50%) translateY(10px);background:#1E3357;border:1px solid rgba(245,158,11,.3);color:#FCD34D;padding:.6rem 1.1rem;border-radius:99px;font-size:.78rem;font-weight:700;z-index:9999;opacity:0;pointer-events:none;transition:all .3s;}
.sp-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}

.fade-up{opacity:0;transform:translateY(14px);animation:fadeUp .35s ease forwards;}
@keyframes fadeUp{to{opacity:1;transform:translateY(0);}}
.d1{animation-delay:.05s;}.d2{animation-delay:.1s;}.d3{animation-delay:.15s;}
</style>
</head>
<body>

<!-- SETTINGS PANEL -->
<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>
<aside class="settings-panel" id="settingsPanel">
    <div class="sp-header"><div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan</div><button class="sp-close" onclick="closeSettings()"><i class="bi bi-x-lg"></i></button></div>
    <div class="sp-body">
        <div class="sp-section"><div class="sp-section-label">Mode Tema</div><div class="theme-grid"><div class="theme-card" data-theme="dark" onclick="setTheme('dark')"><span class="theme-card-icon">🌙</span><div class="theme-card-name">Gelap</div><div class="theme-check"><i class="bi bi-check"></i></div></div><div class="theme-card" data-theme="light" onclick="setTheme('light')"><span class="theme-card-icon">☀️</span><div class="theme-card-name">Terang</div><div class="theme-check"><i class="bi bi-check"></i></div></div><div class="theme-card" data-theme="system" onclick="setTheme('system')"><span class="theme-card-icon">💻</span><div class="theme-card-name">Sistem</div><div class="theme-check"><i class="bi bi-check"></i></div></div></div></div>
        <div class="sp-section"><div class="sp-section-label">Aksen</div><div class="accent-grid"><div><div class="accent-dot" data-accent="amber" style="background:#F59E0B;" onclick="setAccent('amber')"></div><div class="accent-label">Amber</div></div><div><div class="accent-dot" data-accent="blue" style="background:#3B82F6;" onclick="setAccent('blue')"></div><div class="accent-label">Biru</div></div><div><div class="accent-dot" data-accent="green" style="background:#10B981;" onclick="setAccent('green')"></div><div class="accent-label">Hijau</div></div><div><div class="accent-dot" data-accent="purple" style="background:#8B5CF6;" onclick="setAccent('purple')"></div><div class="accent-label">Ungu</div></div><div><div class="accent-dot" data-accent="rose" style="background:#EC4899;" onclick="setAccent('rose')"></div><div class="accent-label">Rose</div></div></div></div>
        <div class="sp-section"><div class="sp-section-label">Ukuran Teks</div><div class="fontsize-row"><button class="fs-btn" data-size="sm" onclick="setFontSize('sm')"><span style="font-size:.78rem;">Aa</span></button><button class="fs-btn" data-size="md" onclick="setFontSize('md')"><span style="font-size:.95rem;">Aa</span></button><button class="fs-btn" data-size="lg" onclick="setFontSize('lg')"><span style="font-size:1.1rem;">Aa</span></button></div></div>
        <div class="sp-section"><div class="sp-section-label">Lainnya</div><div class="sp-toggle-row"><div><div class="sp-toggle-label">Animasi</div></div><label class="sp-switch"><input type="checkbox" id="toggleAnim" onchange="setToggle('anim',this.checked)"><span class="sp-slider"></span></label></div></div>
    </div>
    <div class="sp-footer"><button class="sp-btn-reset" onclick="resetSettings()">Reset</button><button class="sp-btn-apply" id="spApplyBtn" onclick="saveSettings()"><i class="bi bi-check-lg"></i> Simpan</button></div>
</aside>
<div class="sp-toast" id="spToast"></div>


<!-- HERO -->
<div class="page-hero">
    <div class="hero-eyebrow"><i class="bi bi-box-arrow-in-down-right"></i> Serah Terima Barang</div>
    <h1 class="hero-title">Ambil Barang<em> Milikmu</em></h1>
    <p class="hero-sub">Upload bukti kepemilikan, tunggu verifikasi petugas, lalu ambil barangmu di stasiun.</p>
    <div class="hero-pills">
        <?php if ($tabBukti - $tabSiapDiambil > 0): ?>
        <span class="hero-pill hp-amber"><i class="bi bi-clock-history"></i> <?= $tabBukti - $tabSiapDiambil ?> sedang diverifikasi</span>
        <?php endif; ?>
        <?php if ($tabSiapDiambil > 0): ?>
        <span class="hero-pill hp-green"><i class="bi bi-check-circle-fill"></i> <?= $tabSiapDiambil ?> siap diambil!</span>
        <?php endif; ?>
        <?php if ($tabSiapDiajukan > 0): ?>
        <span class="hero-pill hp-blue"><i class="bi bi-bell-fill"></i> <?= $tabSiapDiajukan ?> menunggu bukti</span>
        <?php endif; ?>
    </div>
</div>

<div class="page-wrap">

    <?php if ($flash): ?>
    <div class="flash-bar fb-<?= $flashType ?> fade-up">
        <i class="bi bi-<?= $flashType==='success'?'check-circle-fill':'x-circle-fill' ?>"></i>
        <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>

    <!-- HOW-TO -->
    <div class="howto fade-up">
        <div class="howto-title"><i class="bi bi-info-circle-fill"></i> Cara Klaim Barang</div>
        <div class="howto-steps">
            <div class="howto-step"><div class="hs-num">1</div><div class="hs-label">Barang dicocokkan petugas</div></div>
            <div class="howto-step"><div class="hs-num">2</div><div class="hs-label">Upload bukti kepemilikan (KTP / foto)</div></div>
            <div class="howto-step"><div class="hs-num">3</div><div class="hs-label">Petugas verifikasi bukti</div></div>
            <div class="howto-step"><div class="hs-num">4</div><div class="hs-label">Ambil di stasiun & konfirmasi</div></div>
        </div>
    </div>

    <!-- TAB BAR -->
    <div class="tab-bar fade-up d1">
        <button class="tab-btn <?= $activeTab==='ajukan'?'active':'' ?>" onclick="switchTab('ajukan',event)">
            <i class="bi bi-file-earmark-arrow-up"></i> Upload Bukti
            <?php if ($tabSiapDiajukan > 0): ?><span class="tab-badge"><?= $tabSiapDiajukan ?></span><?php endif; ?>
        </button>
        <button class="tab-btn <?= $activeTab==='status'?'active':'' ?>" onclick="switchTab('status',event)">
            <i class="bi bi-clock-history"></i> Status Bukti
            <?php if ($tabBukti > 0): ?><span class="tab-badge"><?= $tabBukti ?></span><?php endif; ?>
        </button>
        <button class="tab-btn <?= $activeTab==='siap'?'active':'' ?>" onclick="switchTab('siap',event)">
            <i class="bi bi-bag-check-fill"></i> Siap Diambil
            <?php if ($tabSiapDiambil > 0): ?><span class="tab-badge red"><?= $tabSiapDiambil ?></span><?php endif; ?>
        </button>
        <button class="tab-btn <?= $activeTab==='riwayat'?'active':'' ?>" onclick="switchTab('riwayat',event)">
            <i class="bi bi-check2-all"></i> Selesai
            <?php if ($tabSelesai > 0): ?><span class="tab-badge"><?= $tabSelesai ?></span><?php endif; ?>
        </button>
    </div>

    <!-- ══════════ TAB: UPLOAD BUKTI ══════════ -->
    <div id="tab-ajukan" class="tab-content fade-up d2" style="display:<?= $activeTab==='ajukan'?'block':'none' ?>;">
        <?php if (empty($siapDiajukan)): ?>
        <div class="empty-state">
            <i class="bi bi-search-heart"></i>
            <div class="empty-title">Tidak Ada Barang yang Menunggu Bukti</div>
            <p class="empty-sub">Setelah petugas mencocokkan barang temuanmu, kamu bisa upload bukti kepemilikan di sini.<br>Pantau status di <a href="track_pelapor.php" style="color:var(--amber);">halaman lacak</a>.</p>
        </div>
        <?php else: ?>
        <?php foreach ($siapDiajukan as $lp): ?>
        <div class="klaim-card">
            <div class="klaim-header">
                <div class="klaim-no"><?= htmlspecialchars($lp['no_laporan']) ?></div>
                <div class="klaim-name"><?= htmlspecialchars($lp['nama_barang']) ?></div>
                <div class="klaim-meta">
                    <span><i class="bi bi-geo-alt"></i> Hilang: <?= htmlspecialchars($lp['lokasi_hilang']) ?></span>
                    <?php if (!empty($lp['lokasi_ditemukan'])): ?>
                    <span><i class="bi bi-geo-alt-fill"></i> Ditemukan: <?= htmlspecialchars($lp['lokasi_ditemukan']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="klaim-body">
                <!-- Progress Steps -->
                <div class="steps">
                    <div class="step-item done"><div class="step-icon"><i class="bi bi-check-lg"></i></div><div class="step-content"><div class="step-title">Laporan Diterima</div></div></div>
                    <div class="step-item done"><div class="step-icon"><i class="bi bi-check-lg"></i></div><div class="step-content"><div class="step-title">Barang Ditemukan & Dicocokkan</div></div></div>
                    <div class="step-item current">
                        <div class="step-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <div class="step-content">
                            <div class="step-title">Upload Bukti Kepemilikan</div>
                            <div class="step-desc">Upload foto KTP atau bukti kepemilikan lainnya agar petugas bisa memverifikasi.</div>
                        </div>
                    </div>
                    <div class="step-item"><div class="step-icon"><i class="bi bi-bag-check"></i></div><div class="step-content"><div class="step-title">Pengambilan Barang</div></div></div>
                </div>

                <?php if (!empty($lp['petugas_nama'])): ?>
                <div style="background:rgba(96,165,250,.07);border:1px solid rgba(96,165,250,.15);border-radius:10px;padding:.75rem 1rem;margin-bottom:.85rem;font-size:.78rem;color:var(--text-2);">
                    <i class="bi bi-person-badge" style="color:var(--info);"></i> Dicocokkan oleh: <strong><?= htmlspecialchars($lp['petugas_nama']) ?></strong>
                    <?php if (!empty($lp['petugas_telp'])): ?> &nbsp;·&nbsp; <i class="bi bi-telephone"></i> <?= htmlspecialchars($lp['petugas_telp']) ?><?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Form upload bukti -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="ajukan_bukti">
                    <input type="hidden" name="pencocokan_id" value="<?= (int)$lp['pencocokan_id'] ?>">
                    <input type="hidden" name="laporan_id"    value="<?= (int)$lp['laporan_id'] ?>">
                    <?php $uid_elm = $lp['pencocokan_id'] ? 'p'.(int)$lp['pencocokan_id'] : 'l'.(int)$lp['laporan_id']; ?>
                    <div style="margin-bottom:1rem;">
                        <label class="form-lbl">Foto Bukti Kepemilikan <span style="color:var(--amber);">*</span></label>
                        <div class="upload-area"
                             id="ua-<?= $uid_elm ?>"
                             ondragover="event.preventDefault();this.classList.add('drag')"
                             ondragleave="this.classList.remove('drag')"
                             ondrop="handleDrop(event,'<?= $uid_elm ?>')">
                            <input type="file" name="bukti_foto" accept="image/*,.pdf"
                                   onchange="previewUpload(this,'<?= $uid_elm ?>')" required>
                            <span class="upload-icon-wrap"><i class="bi bi-cloud-arrow-up"></i></span>
                            <div class="upload-text">
                                <strong style="color:var(--text-2);">Foto KTP / bukti kepemilikan</strong><br>
                                Seret atau klik untuk upload · JPG, PNG, WEBP, PDF · Maks 5 MB
                            </div>
                            <div class="upload-preview-wrap" id="prev-<?= $uid_elm ?>">
                                <img id="prev-img-<?= $uid_elm ?>" src="" alt="" style="display:none;">
                                <div id="prev-name-<?= $uid_elm ?>" style="font-size:.72rem;color:var(--text-3);margin-top:.35rem;"></div>
                            </div>
                        </div>
                        <div style="font-size:.68rem;color:var(--text-3);margin-top:.35rem;">Contoh: foto KTP, foto bersama barang saat masih dimiliki, struk pembelian.</div>
                    </div>
                    <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
                        <button type="submit" class="btn-amber"><i class="bi bi-send-check"></i> Upload Bukti Kepemilikan</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ══════════ TAB: STATUS BUKTI ══════════ -->
    <div id="tab-status" class="tab-content fade-up d2" style="display:<?= $activeTab==='status'?'block':'none' ?>;">
        <?php if (empty($buktiList)): ?>
        <div class="empty-state">
            <i class="bi bi-clipboard-x"></i>
            <div class="empty-title">Belum Ada Bukti Diajukan</div>
            <p class="empty-sub">Upload bukti kepemilikan dari tab "Upload Bukti" setelah barang ditemukan.</p>
        </div>
        <?php else: ?>
        <?php foreach ($buktiList as $bk): ?>
        <div class="klaim-card">
            <div class="klaim-header">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                    <div>
                        <div class="klaim-no"><?= htmlspecialchars($bk['no_laporan']) ?></div>
                        <div class="klaim-name"><?= htmlspecialchars($bk['nama_barang']) ?></div>
                    </div>
                    <?php
                    $svIcon = ['menunggu'=>'clock-history','valid'=>'check-circle-fill','tidak_valid'=>'x-circle-fill'];
                    $svText = ['menunggu'=>'Menunggu Verifikasi','valid'=>'Bukti Valid','tidak_valid'=>'Tidak Valid'];
                    ?>
                    <span class="status-badge sb-<?= $bk['status_verifikasi'] ?>">
                        <i class="bi bi-<?= $svIcon[$bk['status_verifikasi']] ?? 'question' ?>"></i>
                        <?= $svText[$bk['status_verifikasi']] ?? $bk['status_verifikasi'] ?>
                    </span>
                </div>
            </div>
            <div class="klaim-body">
                <div class="info-row">
                    <div class="info-icon" style="background:rgba(245,158,11,.1);color:var(--amber);"><i class="bi bi-calendar3"></i></div>
                    <div><div class="info-label">Tanggal Upload</div><div class="info-val"><?= date('d M Y, H:i', strtotime($bk['tgl_bukti'])) ?> WIB</div></div>
                </div>
                <?php if (!empty($bk['lokasi_ditemukan'])): ?>
                <div class="info-row">
                    <div class="info-icon" style="background:rgba(96,165,250,.1);color:var(--info);"><i class="bi bi-geo-alt-fill"></i></div>
                    <div><div class="info-label">Lokasi Barang</div><div class="info-val"><?= htmlspecialchars($bk['lokasi_ditemukan']) ?></div></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($bk['petugas_nama'])): ?>
                <div class="info-row">
                    <div class="info-icon" style="background:rgba(167,139,250,.1);color:#A78BFA;"><i class="bi bi-person-badge"></i></div>
                    <div><div class="info-label">Petugas</div><div class="info-val"><?= htmlspecialchars($bk['petugas_nama']) ?><?php if (!empty($bk['petugas_telp'])): ?> · <?= htmlspecialchars($bk['petugas_telp']) ?><?php endif; ?></div></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($bk['file_bukti'])): ?>
                <div class="info-row top">
                    <div class="info-icon" style="background:rgba(52,211,153,.1);color:#34D399;margin-top:2px;"><i class="bi bi-paperclip"></i></div>
                    <div>
                        <div class="info-label">File Bukti</div>
                        <?php $ext = strtolower(pathinfo($bk['file_bukti'], PATHINFO_EXTENSION)); ?>
                        <?php if (in_array($ext,['jpg','jpeg','png','webp'])): ?>
                        <img src="<?= htmlspecialchars($bk['file_bukti']) ?>" alt="Bukti" style="max-height:80px;border-radius:7px;margin-top:4px;border:1px solid var(--border);">
                        <?php else: ?>
                        <a href="<?= htmlspecialchars($bk['file_bukti']) ?>" target="_blank" class="info-val" style="color:var(--amber);">Lihat PDF <i class="bi bi-box-arrow-up-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($bk['catatan_petugas'])): ?>
                <div style="background:rgba(<?= $bk['status_verifikasi']==='tidak_valid' ? '248,113,113,.08' : '16,185,129,.06' ?>);border:1px solid rgba(<?= $bk['status_verifikasi']==='tidak_valid' ? '248,113,113,.2' : '16,185,129,.15' ?>);border-radius:10px;padding:.75rem 1rem;margin-top:.75rem;">
                    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:<?= $bk['status_verifikasi']==='tidak_valid' ? 'var(--danger)' : 'var(--success)' ?>;margin-bottom:.3rem;"><i class="bi bi-chat-square-dots"></i> Catatan Petugas</div>
                    <div style="font-size:.8rem;color:var(--text-2);"><?= nl2br(htmlspecialchars($bk['catatan_petugas'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($bk['status_verifikasi'] === 'tidak_valid'): ?>
                <div style="margin-top:.85rem;">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="ajukan_bukti">
                        <input type="hidden" name="pencocokan_id" value="<?= (int)$bk['pencocokan_id'] ?>">
                        <button type="button" class="btn-ghost" onclick="this.closest('.klaim-card').querySelector('.reupload-form').style.display='block';this.style.display='none';">
                            <i class="bi bi-arrow-repeat"></i> Upload Ulang Bukti
                        </button>
                        <div class="reupload-form" style="display:none;margin-top:.85rem;">
                            <div style="margin-bottom:.65rem;">
                                <label class="form-lbl">Bukti Baru <span style="color:var(--amber);">*</span></label>
                                <div class="upload-area"><input type="file" name="bukti_foto" accept="image/*,.pdf" required><span class="upload-icon-wrap"><i class="bi bi-cloud-arrow-up"></i></span><div class="upload-text">Upload bukti kepemilikan baru</div></div>
                            </div>
                            <button type="submit" class="btn-amber"><i class="bi bi-send-check"></i> Kirim Ulang</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                <?php if ($bk['status_verifikasi'] === 'valid' && $bk['status_laporan'] === 'ditemukan'): ?>
                <div style="margin-top:.75rem;">
                    <a href="?tab=siap" class="btn-amber"><i class="bi bi-bag-check-fill"></i> Lihat Instruksi Pengambilan</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ══════════ TAB: SIAP DIAMBIL ══════════ -->
    <div id="tab-siap" class="tab-content fade-up d2" style="display:<?= $activeTab==='siap'?'block':'none' ?>;">
        <?php if (empty($siapDiambil)): ?>
        <div class="empty-state">
            <i class="bi bi-bag-x"></i>
            <div class="empty-title">Belum Ada Barang Siap Diambil</div>
            <p class="empty-sub">Setelah bukti kepemilikan kamu diverifikasi petugas, instruksi pengambilan akan muncul di sini.</p>
        </div>
        <?php else: ?>
        <?php foreach ($siapDiambil as $s): ?>
        <div class="serah-card">
            <div class="serah-label"><i class="bi bi-check-circle-fill"></i> Bukti Diverifikasi — Siap Diambil!</div>
            <div class="serah-name"><?= htmlspecialchars($s['nama_barang']) ?></div>
            <div class="serah-info">
                <strong>No. Laporan:</strong> <?= htmlspecialchars($s['no_laporan']) ?><br>
                <?php if (!empty($s['kode_barang'])): ?>
                <strong>Kode Barang:</strong> <?= htmlspecialchars($s['kode_barang']) ?><br>
                <?php endif; ?>
                <?php if (!empty($s['lokasi_ditemukan'])): ?>
                <strong>Lokasi Pengambilan:</strong> <?= htmlspecialchars($s['lokasi_ditemukan']) ?><br>
                <?php endif; ?>
                <?php if (!empty($s['petugas_nama'])): ?>
                <strong>Hubungi Petugas:</strong> <?= htmlspecialchars($s['petugas_nama']) ?>
                <?php if (!empty($s['petugas_telp'])): ?> ·
                    <a href="tel:<?= htmlspecialchars($s['petugas_telp']) ?>" style="color:var(--amber);"><?= htmlspecialchars($s['petugas_telp']) ?></a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="konfirmasi-box">
                <div class="konfirmasi-title"><i class="bi bi-clipboard-check"></i> Konfirmasi Pengambilan</div>
                <ul class="konfirmasi-checklist">
                    <li>Pergi ke <?= htmlspecialchars($s['lokasi_ditemukan'] ?: 'loket Lost & Found stasiun') ?></li>
                    <li>Tunjukkan KTP dan nomor laporan: <strong><?= htmlspecialchars($s['no_laporan']) ?></strong></li>
                    <li>Periksa barang dan pastikan kondisinya sesuai</li>
                    <li>Tandatangani berita acara serah terima dengan petugas</li>
                    <li>Klik tombol konfirmasi di bawah setelah menerima barang</li>
                </ul>
                <form method="POST" onsubmit="return confirmHandover('<?= htmlspecialchars($s['no_laporan']) ?>')">
                    <input type="hidden" name="action" value="konfirmasi_ambil">
                    <input type="hidden" name="pencocokan_id" value="<?= (int)$s['pencocokan_id'] ?>">
                    <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
                        <button type="submit" class="btn-amber"><i class="bi bi-bag-check-fill"></i> Saya Sudah Menerima Barang</button>
                        <?php if (!empty($s['petugas_telp'])): ?>
                        <a href="https://wa.me/62<?= ltrim(preg_replace('/[^0-9]/','',$s['petugas_telp']),'0') ?>?text=Halo%2C+saya+ingin+mengambil+barang+no.+laporan+<?= urlencode($s['no_laporan']) ?>"
                           target="_blank" class="btn-ghost">
                            <i class="bi bi-whatsapp"></i> Hubungi via WA
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ══════════ TAB: RIWAYAT SELESAI ══════════ -->
    <div id="tab-riwayat" class="tab-content fade-up d2" style="display:<?= $activeTab==='riwayat'?'block':'none' ?>;">
        <?php if (empty($riwayatSelesai)): ?>
        <div class="empty-state">
            <i class="bi bi-check2-all"></i>
            <div class="empty-title">Belum Ada Riwayat Selesai</div>
            <p class="empty-sub">Barang yang sudah kamu ambil dan dikonfirmasi akan tercatat di sini.</p>
        </div>
        <?php else: ?>
        <div class="cl-card">
            <div class="card-head">
                <div class="card-head-title"><i class="bi bi-check2-all"></i> Riwayat Serah Terima</div>
                <span style="font-size:.72rem;color:var(--text-3);"><?= count($riwayatSelesai) ?> item</span>
            </div>
            <div>
            <?php foreach ($riwayatSelesai as $r): ?>
            <div class="riwayat-row">
                <div class="riwayat-icon"><i class="bi bi-bag-check-fill"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="riwayat-name"><?= htmlspecialchars($r['nama_barang']) ?></div>
                    <div class="riwayat-sub">
                        <span style="font-size:.62rem;background:rgba(245,158,11,.1);color:var(--amber);padding:1px 7px;border-radius:5px;font-weight:700;"><?= htmlspecialchars($r['no_laporan']) ?></span>
                        &nbsp;
                        <?php if (!empty($r['lokasi_ditemukan'])): ?><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($r['lokasi_ditemukan']) ?> &nbsp;·&nbsp; <?php endif; ?>
                        <?php $tgl = $r['tanggal_serah_terima'] ?? $r['tgl_selesai']; ?>
                        Selesai <?= date('d M Y', strtotime($tgl)) ?>
                        <?php if (!empty($r['petugas_nama'])): ?> · Petugas: <?= htmlspecialchars($r['petugas_nama']) ?><?php endif; ?>
                    </div>
                </div>
                <span class="status-badge sb-selesai"><i class="bi bi-check-circle-fill"></i> Selesai</span>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <div style="background:linear-gradient(135deg,var(--navy-2),var(--navy-3));border-radius:14px;padding:1.5rem;text-align:center;margin-top:1rem;">
            <div style="font-size:1.5rem;margin-bottom:.5rem;">🎉</div>
            <div style="font-family:'Fraunces',serif;font-size:1.1rem;font-weight:700;color:var(--white);margin-bottom:.3rem;">Terima kasih sudah menggunakan CommuterLink!</div>
            <p style="font-size:.78rem;color:rgba(255,255,255,.5);margin-bottom:1rem;">Semoga barang yang ditemukan kembali memberimu kebahagiaan.</p>
            <a href="index_pelapor.php" class="btn-amber"><i class="bi bi-house"></i> Kembali ke Beranda</a>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /page-wrap -->

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
    <a href="index_pelapor.php"     class="bn-item"><i class="bi bi-house"></i><span>Beranda</span></a>
    <a href="stations.php"          class="bn-item"><i class="bi bi-train-front"></i><span>Stasiun</span></a>
    <a href="serahterima_pelapor.php" class="bn-item active" style="position:relative;">
        <i class="bi bi-bag-check-fill"></i><span>Serah Terima</span>
        <?php if ($tabSiapDiambil > 0): ?><span class="bn-pip"></span><?php endif; ?>
    </a>
    <a href="faq.php"               class="bn-item"><i class="bi bi-question-circle"></i><span>FAQ</span></a>
    <a href="about.php"             class="bn-item"><i class="bi bi-info-circle"></i><span>Tentang</span></a>
</nav>

<script>
/* TAB SWITCH */
function switchTab(name, e) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).style.display = 'block';
    if (e && e.currentTarget) e.currentTarget.classList.add('active');
    history.replaceState(null, '', '?tab=' + name);
}

/* UPLOAD PREVIEW */
function previewUpload(input, id) {
    const file = input.files[0];
    if (!file) return;
    const prev = document.getElementById('prev-' + id);
    const img  = document.getElementById('prev-img-' + id);
    const nm   = document.getElementById('prev-name-' + id);
    if (prev) prev.style.display = 'block';
    if (nm)   nm.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
    if (img && file.type.startsWith('image/')) {
        const r = new FileReader();
        r.onload = e => { img.src = e.target.result; img.style.display = 'block'; };
        r.readAsDataURL(file);
    }
}
function handleDrop(e, id) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (!file) return;
    const ua  = document.getElementById('ua-' + id);
    const inp = ua ? ua.querySelector('input[type=file]') : null;
    if (!inp) return;
    ua.classList.remove('drag');
    const dt = new DataTransfer();
    dt.items.add(file);
    inp.files = dt.files;
    previewUpload(inp, id);
}

/* KONFIRMASI SERAH TERIMA */
function confirmHandover(no) {
    return confirm('Konfirmasi bahwa kamu sudah menerima barang dengan no. laporan ' + no + ' dari petugas?\n\nTindakan ini tidak dapat dibatalkan.');
}

/* SETTINGS */
(function(){
    const DEFAULTS={theme:'dark',accent:'amber',fontSize:'md',compact:false,anim:true,notif:true};
    let S=Object.assign({},DEFAULTS);
    const AC={amber:'#F59E0B',blue:'#3B82F6',green:'#10B981',purple:'#8B5CF6',rose:'#EC4899'};
    function load(){try{const s=localStorage.getItem('cl_settings');if(s)S=Object.assign({},DEFAULTS,JSON.parse(s));}catch(e){}}
    function apply(){
        const h=document.documentElement;
        let t=S.theme==='system'?(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):S.theme;
        h.setAttribute('data-theme',t);h.setAttribute('data-accent',S.accent);
        h.setAttribute('data-fontsize',S.fontSize);h.setAttribute('data-compact',S.compact?'true':'false');
        h.setAttribute('data-anim',S.anim?'on':'off');
        const pip=document.getElementById('notifPip');if(pip)pip.style.display=S.notif?'':'none';
    }
    function sync(){
        document.querySelectorAll('.theme-card').forEach(c=>c.classList.toggle('active',c.dataset.theme===S.theme));
        document.querySelectorAll('.accent-dot').forEach(d=>d.classList.toggle('active',d.dataset.accent===S.accent));
        document.querySelectorAll('.fs-btn').forEach(b=>b.classList.toggle('active',b.dataset.size===S.fontSize));
        const ta=document.getElementById('toggleAnim');if(ta)ta.checked=S.anim;
        const ab=document.getElementById('spApplyBtn');if(ab)ab.style.background=AC[S.accent]||'#F59E0B';
    }
    window.setTheme=t=>{S.theme=t;apply();sync();};
    window.setAccent=a=>{S.accent=a;apply();sync();};
    window.setFontSize=f=>{S.fontSize=f;apply();sync();};
    window.setToggle=(k,v)=>{if(k==='anim')S.anim=v;apply();sync();};
    window.openSettings=()=>{sync();document.getElementById('settingsPanel').classList.add('open');document.getElementById('spOverlay').classList.add('open');document.body.style.overflow='hidden';};
    window.closeSettings=()=>{document.getElementById('settingsPanel').classList.remove('open');document.getElementById('spOverlay').classList.remove('open');document.body.style.overflow='';};
    window.saveSettings=()=>{try{localStorage.setItem('cl_settings',JSON.stringify(S));}catch(e){}closeSettings();const t=document.getElementById('spToast');t.textContent='✅ Tersimpan!';t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2500);};
    window.resetSettings=()=>{S=Object.assign({},DEFAULTS);apply();sync();try{localStorage.removeItem('cl_settings');}catch(e){}};
    document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key===','){e.preventDefault();openSettings();}if(e.key==='Escape')closeSettings();});
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>{if(S.theme==='system')apply();});
    load();apply();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>