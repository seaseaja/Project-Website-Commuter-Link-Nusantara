<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user = getCurrentUser();
$role = $user['role'] ?? 'pelapor';
$firstName = explode(' ', $user['nama'] ?? 'User')[0];
$isPetugas = ($role === 'petugas');

// ── DB Connection ──────────────────────────────────────────────────────────
// auth.php sudah include getDB() — pakai fungsi itu langsung
$pdo = getDB();

// ── Query utama: JOIN serah_terima → pencocokan → laporan + barang + users ─
// Status serah terima diturunkan dari:
//   - laporan_kehilangan.status  ('selesai' → selesai, 'ditemukan' → dijadwalkan, 'diproses' → menunggu_konfirmasi, 'ditutup' → dibatalkan)
//   - serah_terima.id ada → 'selesai'
//   - pencocokan.status diverifikasi tapi belum ada serah_terima → 'dijadwalkan'
//   - pencocokan.status menunggu_verifikasi → 'menunggu_konfirmasi'

// Ambil semua pencocokan yang diverifikasi (kandidat serah terima)
// beserta data serah_terima jika sudah ada
$sql = "
    SELECT
        pc.id                           AS pencocokan_id,
        pc.status                       AS pencocokan_status,
        pc.catatan                      AS pencocokan_catatan,
        pc.created_at                   AS pencocokan_created_at,

        lk.id                           AS laporan_id,
        lk.no_laporan,
        lk.nama_barang                  AS laporan_barang,
        lk.kategori                     AS laporan_kategori,
        lk.lokasi_hilang,
        lk.status                       AS laporan_status,

        bt.id                           AS barang_id,
        bt.kode_barang,
        bt.nama_barang                  AS barang_nama,
        bt.kategori                     AS barang_kategori,
        bt.lokasi_ditemukan,

        up.id                           AS pelapor_id,
        up.nama                         AS pelapor_nama,

        uu.id                           AS petugas_id,
        uu.nama                         AS petugas_nama,
        uu.stasiun                      AS petugas_stasiun,

        st.id                           AS st_id,
        st.tanggal_serah_terima,
        st.catatan                      AS st_catatan,
        st.created_at                   AS st_created_at

    FROM pencocokan pc
    JOIN laporan_kehilangan lk  ON lk.id  = pc.laporan_id   AND lk.deleted_at IS NULL
    JOIN barang_temuan      bt  ON bt.id  = pc.barang_id    AND bt.deleted_at IS NULL
    JOIN users              up  ON up.id  = lk.user_id      AND up.deleted_at IS NULL
    JOIN users              uu  ON uu.id  = pc.petugas_id   AND uu.deleted_at IS NULL
    LEFT JOIN serah_terima  st  ON st.pencocokan_id = pc.id AND st.deleted_at IS NULL
    WHERE pc.deleted_at IS NULL
    ORDER BY COALESCE(st.tanggal_serah_terima, pc.created_at) DESC
";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

// ── Ambil history dari log_aktivitas ──────────────────────────────────────
$historyMap = [];
if (!empty($rows)) {
    // Kumpulkan semua no_laporan & kode_barang untuk filter di PHP
    $laporanNos  = array_unique(array_column($rows, 'no_laporan'));
    $kodeBarangs = array_unique(array_column($rows, 'kode_barang'));

    try {
        // Coba log_aktivitas dulu, fallback ke activity_logs
        $logTable = 'log_aktivitas';
        $logCol   = 'aktivitas';
        try {
            $pdo->query("SELECT 1 FROM log_aktivitas LIMIT 1");
        } catch (Exception $e) {
            $logTable = 'activity_logs';
            $logCol   = 'description';
        }

        $actorCol = ($logTable === 'log_aktivitas') ? 'u.nama' : 'u.nama';
        $logRows = $pdo->query(
            "SELECT la.id, la.user_id, la.$logCol AS aktivitas, la.created_at,
                    u.nama AS actor_nama
             FROM $logTable la
             JOIN users u ON u.id = la.user_id
             ORDER BY la.created_at ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logRows as $lr) {
            foreach ($rows as $r) {
                if (strpos($lr['aktivitas'], $r['no_laporan'])  !== false ||
                    strpos($lr['aktivitas'], $r['kode_barang']) !== false ||
                    strpos($lr['aktivitas'], 'pencocokan #' . $r['pencocokan_id']) !== false) {
                    $historyMap[$r['pencocokan_id']][] = [
                        'time'   => $lr['created_at'],
                        'actor'  => $lr['actor_nama'],
                        'note'   => $lr['aktivitas'],
                        'status' => 'info',
                    ];
                    break; // tiap log hanya dimap ke 1 pencocokan
                }
            }
        }
    } catch (Exception $e) {
        // log_aktivitas kosong atau error — lanjut dengan history fallback
    }
}

// ── Normalisasi data ke format tampilan ────────────────────────────────────
// Mapping status:
//   serah_terima ada                          → selesai
//   pencocokan diverifikasi, belum serah      → dijadwalkan
//   pencocokan menunggu_verifikasi            → menunggu_konfirmasi
//   laporan ditutup / pencocokan ditolak      → dibatalkan

$mockData = [];
foreach ($rows as $r) {
    if ($r['st_id']) {
        $status    = 'selesai';
        $selesai_at= $r['tanggal_serah_terima'];
    } elseif ($r['pencocokan_status'] === 'ditolak' || $r['laporan_status'] === 'ditutup') {
        $status    = 'dibatalkan';
        $selesai_at= null;
    } elseif ($r['pencocokan_status'] === 'diverifikasi') {
        $status    = 'dijadwalkan';
        $selesai_at= null;
    } else {
        $status    = 'menunggu_konfirmasi';
        $selesai_at= null;
    }

    // Kode display: gunakan kode_barang sebagai referensi utama
    $kode = 'ST-' . ($r['st_id'] ? str_pad($r['st_id'],4,'0',STR_PAD_LEFT) : 'PC-'.str_pad($r['pencocokan_id'],4,'0',STR_PAD_LEFT));

    // History: dari log + fallback manual entries
    $history = $historyMap[$r['pencocokan_id']] ?? [];

    // Tambah entry otomatis dari created_at pencocokan jika history kosong
    if (empty($history)) {
        $history[] = [
            'time'  => $r['pencocokan_created_at'],
            'actor' => 'Sistem',
            'note'  => 'Pencocokan dibuat untuk laporan ' . $r['no_laporan'] . ' dengan barang ' . $r['kode_barang'] . '.',
            'status'=> 'menunggu_konfirmasi',
        ];
        if ($r['pencocokan_status'] === 'diverifikasi') {
            $history[] = [
                'time'  => $r['st_created_at'] ?? $r['pencocokan_created_at'],
                'actor' => $r['petugas_nama'],
                'note'  => 'Pencocokan diverifikasi oleh petugas.',
                'status'=> 'dijadwalkan',
            ];
        }
        if ($r['st_id']) {
            $history[] = [
                'time'  => $r['tanggal_serah_terima'],
                'actor' => $r['petugas_nama'],
                'note'  => $r['st_catatan'] ?: 'Serah terima selesai dilaksanakan.',
                'status'=> 'selesai',
            ];
        }
    } else {
        // Tambahkan status key ke tiap history dari log (tidak ada di log_aktivitas)
        foreach ($history as &$h) {
            $h['status'] = 'info';
        }
        unset($h);
    }

    $mockData[] = [
        'id'          => (int)$r['pencocokan_id'],
        'st_id'       => $r['st_id'] ? (int)$r['st_id'] : null,
        'kode'        => $kode,
        'laporan_no'  => $r['no_laporan'],
        'kode_barang' => $r['kode_barang'],
        'barang'      => $r['barang_nama'] ?: $r['laporan_barang'],
        'kategori'    => $r['barang_kategori'] ?: $r['laporan_kategori'] ?: '-',
        'pelapor'     => $r['pelapor_nama'],
        'petugas'     => $r['petugas_nama'],
        'stasiun'     => $r['petugas_stasiun'] ?: $r['lokasi_ditemukan'] ?: '-',
        'jadwal'      => $r['tanggal_serah_terima'] ?: $r['pencocokan_created_at'],
        'selesai_at'  => $selesai_at,
        'status'      => $status,
        'catatan'     => $r['st_catatan'] ?: $r['pencocokan_catatan'] ?: '',
        'history'     => $history,
    ];
}

$statusConfig = [
  'menunggu_konfirmasi' => ['label'=>'Menunggu Konfirmasi','color'=>'#F59E0B','bg'=>'rgba(245,158,11,0.12)','icon'=>'bi-hourglass-split'],
  'dijadwalkan'         => ['label'=>'Dijadwalkan',        'color'=>'#3B82F6','bg'=>'rgba(59,130,246,0.12)', 'icon'=>'bi-calendar-check'],
  'selesai'             => ['label'=>'Selesai',             'color'=>'#10B981','bg'=>'rgba(16,185,129,0.12)','icon'=>'bi-check-circle-fill'],
  'dibatalkan'          => ['label'=>'Dibatalkan',          'color'=>'#EF4444','bg'=>'rgba(239,68,68,0.12)', 'icon'=>'bi-x-circle-fill'],
  'info'                => ['label'=>'Aktivitas',           'color'=>'#94A3B8','bg'=>'rgba(148,163,184,0.1)','icon'=>'bi-info-circle'],
];

// ── Stats ─────────────────────────────────────────────────────────────────
$stats = ['total'=>count($mockData),'menunggu'=>0,'dijadwalkan'=>0,'selesai'=>0,'dibatalkan'=>0];
foreach ($mockData as $d) {
    if     ($d['status']==='menunggu_konfirmasi') $stats['menunggu']++;
    elseif ($d['status']==='dijadwalkan')         $stats['dijadwalkan']++;
    elseif ($d['status']==='selesai')             $stats['selesai']++;
    elseif ($d['status']==='dibatalkan')          $stats['dibatalkan']++;
}
// ── POST Handler ──────────────────────────────────────────────────────────
$flashMsg   = '';
$flashType  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isPetugas) {

    // ── Konfirmasi Selesai: insert ke serah_terima ─────────────────────────
    if (isset($_POST['action']) && $_POST['action'] === 'konfirmasi_selesai') {
        $pc_id   = (int)($_POST['pencocokan_id'] ?? 0);
        $catatan = trim($_POST['catatan'] ?? '');
        $tgl     = trim($_POST['tanggal_serah_terima'] ?? date('Y-m-d H:i:s'));

        if ($pc_id) {
            try {
                // Cek apakah sudah ada
                $cek = $pdo->prepare("SELECT id FROM serah_terima WHERE pencocokan_id = ? AND deleted_at IS NULL");
                $cek->execute([$pc_id]);
                if (!$cek->fetch()) {
                    // Ambil pelapor_id dari pencocokan → laporan_kehilangan
                    $plpRow = $pdo->prepare("SELECT lk.user_id FROM pencocokan pc JOIN laporan_kehilangan lk ON lk.id = pc.laporan_id WHERE pc.id = ?");
                    $plpRow->execute([$pc_id]);
                    $plp = $plpRow->fetch(PDO::FETCH_ASSOC);
                    $pelapor_id = $plp ? (int)$plp['user_id'] : 0;

                    $ins = $pdo->prepare("
                        INSERT INTO serah_terima (pencocokan_id, petugas_id, pelapor_id, tanggal_serah_terima, catatan, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$pc_id, $user['id'], $pelapor_id, $tgl, $catatan, $user['id']]);

                    // Update status laporan → selesai
                    $pdo->prepare("UPDATE laporan_kehilangan lk JOIN pencocokan pc ON pc.laporan_id = lk.id SET lk.status = 'selesai', lk.updated_by = ? WHERE pc.id = ?")->execute([$user['id'], $pc_id]);
                    // Update status barang → diserahkan
                    $pdo->prepare("UPDATE barang_temuan bt JOIN pencocokan pc ON pc.barang_id = bt.id SET bt.status = 'diserahkan', bt.updated_by = ? WHERE pc.id = ?")->execute([$user['id'], $pc_id]);
                    // Log aktivitas
                    logActivity("konfirmasi_selesai", "serah_terima", $pc_id, "Konfirmasi serah terima selesai untuk pencocokan #$pc_id");

                    $flashMsg  = 'Serah terima berhasil dikonfirmasi sebagai selesai.';
                    $flashType = 'success';
                } else {
                    $flashMsg  = 'Serah terima untuk pencocokan ini sudah ada.';
                    $flashType = 'warning';
                }
            } catch (Exception $e) {
                $flashMsg  = 'Gagal menyimpan: ' . $e->getMessage();
                $flashType = 'danger';
            }
        }
        header('Location: serah_terima.php?flash=' . urlencode($flashMsg) . '&ftype=' . $flashType);
        exit;
    }

    // ── Buat Serah Terima Manual ────────────────────────────────────────────
    if (isset($_POST['action']) && $_POST['action'] === 'buat_serah_terima') {
        $pc_id   = (int)($_POST['pencocokan_id'] ?? 0);
        $tgl     = trim($_POST['tanggal_serah_terima'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');

        if ($pc_id && $tgl) {
            try {
                $plpRow = $pdo->prepare("SELECT lk.user_id FROM pencocokan pc JOIN laporan_kehilangan lk ON lk.id = pc.laporan_id WHERE pc.id = ?");
                $plpRow->execute([$pc_id]);
                $plp = $plpRow->fetch(PDO::FETCH_ASSOC);
                $pelapor_id = $plp ? (int)$plp['user_id'] : 0;

                $ins = $pdo->prepare("
                    INSERT INTO serah_terima (pencocokan_id, petugas_id, pelapor_id, tanggal_serah_terima, catatan, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$pc_id, $user['id'], $pelapor_id, $tgl, $catatan, $user['id']]);
                $pdo->prepare("UPDATE laporan_kehilangan lk JOIN pencocokan pc ON pc.laporan_id = lk.id SET lk.status = 'selesai', lk.updated_by = ? WHERE pc.id = ?")->execute([$user['id'], $pc_id]);
                $pdo->prepare("UPDATE barang_temuan bt JOIN pencocokan pc ON pc.barang_id = bt.id SET bt.status = 'diserahkan', bt.updated_by = ? WHERE pc.id = ?")->execute([$user['id'], $pc_id]);
                logActivity("buat_serah_terima", "serah_terima", $pc_id, "Buat serah terima manual untuk pencocokan #$pc_id pada $tgl");

                $flashMsg  = 'Serah terima berhasil dibuat.';
                $flashType = 'success';
            } catch (Exception $e) {
                $flashMsg  = 'Gagal menyimpan: ' . $e->getMessage();
                $flashType = 'danger';
            }
        } else {
            $flashMsg  = 'Pencocokan dan tanggal wajib diisi.';
            $flashType = 'warning';
        }
        header('Location: serah_terima.php?flash=' . urlencode($flashMsg) . '&ftype=' . $flashType);
        exit;
    }
}

// Flash message dari redirect
if (isset($_GET['flash'])) {
    $flashMsg  = htmlspecialchars($_GET['flash']);
    $flashType = in_array($_GET['ftype'] ?? '', ['success','warning','danger']) ? $_GET['ftype'] : 'success';
}

// Ambil list pencocokan yang diverifikasi tapi belum ada serah_terima (untuk dropdown form)
$pcList = [];
try {
    $pcList = $pdo->query("
        SELECT pc.id, lk.no_laporan, bt.nama_barang, bt.kode_barang
        FROM pencocokan pc
        JOIN laporan_kehilangan lk ON lk.id = pc.laporan_id
        JOIN barang_temuan bt ON bt.id = pc.barang_id
        LEFT JOIN serah_terima st ON st.pencocokan_id = pc.id AND st.deleted_at IS NULL
        WHERE pc.status = 'diverifikasi'
          AND st.id IS NULL
          AND pc.deleted_at IS NULL
          AND lk.deleted_at IS NULL
        ORDER BY pc.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Serah Terima — CommuterLink Nusantara</title>
<link rel="icon" href="uploads/favicon.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══ CSS VARIABLES ═══ */
:root{--navy:#0B1F3A;--navy-2:#152d52;--navy-3:#1e3d6e;--gold:#F0A500;--gold-lt:#F7C948;--bg:#F0F4F8;--card-bg:#FFFFFF;--text:#1E293B;--text-2:#475569;--text-3:#94A3B8;--border:#E2E8F0;--success:#10B981;--warning:#F59E0B;--danger:#EF4444;--info:#3B82F6;}
[data-theme="dark"]{--bg:#0B1626;--card-bg:#112038;--text:#E8F0FE;--text-2:#94AEC8;--text-3:#506882;--border:rgba(255,255,255,0.07);}
[data-accent="blue"]  {--gold:#3B82F6;--gold-lt:#60A5FA;}
[data-accent="green"] {--gold:#10B981;--gold-lt:#34D399;}
[data-accent="purple"]{--gold:#8B5CF6;--gold-lt:#A78BFA;}
[data-accent="red"]   {--gold:#EF4444;--gold-lt:#FC8181;}
[data-accent="rose"]  {--gold:#EC4899;--gold-lt:#F472B6;}
[data-fontsize="sm"]{font-size:14px;}[data-fontsize="md"]{font-size:16px;}[data-fontsize="lg"]{font-size:18px;}
[data-compact="true"] .page-content{padding:1.25rem;}
[data-compact="true"] .page-banner{padding:1.25rem 1.5rem;}
[data-compact="true"] .stat-card{padding:.9rem 1rem;}
[data-anim="off"] *{animation:none !important;transition:none !important;}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:"Plus Jakarta Sans",sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}

/* ═══ TOPBAR ═══ */
.topbar{position:sticky;top:0;backdrop-filter:blur(14px);border-bottom:1px solid var(--border);padding:.75rem 2rem;display:flex;align-items:center;justify-content:space-between;z-index:100;background:rgba(11,22,38,0.92);transition:background .3s;}
[data-theme="light"] .topbar{background:rgba(240,244,248,0.92);}
.topbar-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.brand-icon-box{width:34px;height:34px;background:var(--gold);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;box-shadow:0 3px 10px rgba(240,165,0,.35);}
.brand-text-main{font-size:.92rem;font-weight:800;line-height:1.1;color:#fff;}
.brand-text-main em{font-style:normal;color:var(--gold);}
.brand-text-sub{font-size:.58rem;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.1em;}
[data-theme="light"] .brand-text-main{color:var(--navy);}
[data-theme="light"] .brand-text-sub{color:var(--text-3);}
.topbar-nav{display:flex;align-items:center;gap:.15rem;}
.tnav-link{display:flex;align-items:center;gap:5px;padding:.4rem .72rem;border-radius:8px;font-size:.77rem;font-weight:600;color:rgba(255,255,255,.55);text-decoration:none;transition:all .15s;white-space:nowrap;}
[data-theme="light"] .tnav-link{color:var(--text-2);}
.tnav-link:hover{background:rgba(255,255,255,.07);color:rgba(255,255,255,.9);}
[data-theme="light"] .tnav-link:hover{background:var(--border);color:var(--text);}
.tnav-link.active{background:rgba(240,165,0,.15);color:var(--gold);}
.tnav-link i{font-size:.82rem;}
.topbar-right{display:flex;align-items:center;gap:.5rem;}
.cl-avt{width:28px;height:28px;background:linear-gradient(135deg,var(--gold),var(--gold-lt));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;color:var(--navy);}
.cl-avatar-chip{display:flex;align-items:center;gap:8px;padding:4px 12px 4px 4px;border:1px solid rgba(255,255,255,.1);border-radius:99px;background:rgba(255,255,255,.05);text-decoration:none;transition:all .2s;}
[data-theme="light"] .cl-avatar-chip{border-color:var(--border);background:var(--card-bg);}
.cl-avatar-chip:hover{border-color:var(--gold);}
.cl-avt-name{font-size:.78rem;font-weight:600;color:#fff;}
[data-theme="light"] .cl-avt-name{color:var(--text);}
.role-chip{font-size:.62rem;font-weight:700;padding:2px 7px;border-radius:99px;background:rgba(240,165,0,.15);color:var(--gold);border:1px solid rgba(240,165,0,.2);}
.nav-icon-btn{width:32px;height:32px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);border-radius:8px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.6);font-size:.9rem;text-decoration:none;transition:all .2s;cursor:pointer;}
[data-theme="light"] .nav-icon-btn{border-color:var(--border);background:var(--card-bg);color:var(--text-2);}
.nav-icon-btn:hover{border-color:var(--gold);color:var(--gold);}

/* ═══ LAYOUT ═══ */
.page-content{padding:2rem;max-width:1280px;margin:0 auto;}
.breadcrumb-nav{display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-3);margin-bottom:1.25rem;}
.breadcrumb-nav a{color:var(--text-3);text-decoration:none;transition:color .2s;}
.breadcrumb-nav a:hover{color:var(--gold);}

/* ═══ BANNER ═══ */
.page-banner{border-radius:18px;padding:1.75rem 2.5rem;margin-bottom:1.75rem;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%);border:1px solid rgba(255,255,255,.07);transition:padding .3s;}
.page-banner::before{content:"";position:absolute;width:280px;height:280px;background:radial-gradient(circle,rgba(240,165,0,.2) 0%,transparent 70%);top:-80px;right:-60px;border-radius:50%;pointer-events:none;}
.page-banner::after{content:"🤝";position:absolute;right:2.5rem;bottom:-8px;font-size:6.5rem;opacity:.09;pointer-events:none;}
.banner-label{font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:.4rem;}
.banner-title{font-size:1.55rem;font-weight:800;color:#fff;letter-spacing:-.5px;}
.banner-title span{color:var(--gold-lt);}
.banner-sub{font-size:.82rem;color:rgba(255,255,255,.45);margin-top:.4rem;}
.banner-actions{margin-top:1.1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;}
.btn-primary{display:inline-flex;align-items:center;gap:6px;padding:.6rem 1.25rem;background:var(--gold);color:var(--navy);border:none;border-radius:10px;font-size:.82rem;font-weight:800;cursor:pointer;font-family:inherit;transition:opacity .2s,transform .15s;}
.btn-primary:hover{opacity:.88;transform:translateY(-1px);}
.btn-secondary{display:inline-flex;align-items:center;gap:6px;padding:.6rem 1.1rem;background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:10px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s;text-decoration:none;}
.btn-secondary:hover{background:rgba(255,255,255,.18);color:#fff;}

/* ═══ STAT GRID ═══ */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.75rem;}
.stat-card{background:var(--card-bg);border-radius:14px;padding:1.1rem 1.3rem;border:1px solid var(--border);display:flex;align-items:center;gap:1rem;transition:transform .2s,box-shadow .2s,background .3s;}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 22px rgba(0,0,0,.12);}
.stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
.stat-info{flex:1;}
.stat-num{font-size:1.6rem;font-weight:800;color:var(--text);line-height:1;}
.stat-label{font-size:.72rem;color:var(--text-3);font-weight:600;margin-top:2px;}

/* ═══ FILTER BAR ═══ */
.filter-bar{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;transition:background .3s;}
.search-wrap{flex:1;min-width:220px;position:relative;}
.search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-3);pointer-events:none;font-size:.85rem;}
.cl-input{width:100%;padding:.58rem .9rem .58rem 2.2rem;border:1.5px solid var(--border);border-radius:9px;font-size:.82rem;background:var(--bg);color:var(--text);outline:none;transition:border-color .2s;font-family:inherit;}
.cl-input:focus{border-color:var(--gold);}
.cl-input::placeholder{color:var(--text-3);}
.cl-select{padding:.58rem .9rem;border:1.5px solid var(--border);border-radius:9px;font-size:.82rem;background:var(--bg);color:var(--text);outline:none;font-family:inherit;cursor:pointer;transition:border-color .2s;}
.cl-select:focus{border-color:var(--gold);}
.filter-count{font-size:.75rem;font-weight:700;color:var(--text-3);white-space:nowrap;margin-left:auto;}
.filter-count span{color:var(--gold);}

/* ═══ TABLE CARD ═══ */
.table-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:background .3s;}
.table-head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.3rem;border-bottom:1px solid var(--border);}
.table-head-title{font-size:.88rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:7px;}
.table-head-title i{color:var(--gold);}
.st-table{width:100%;border-collapse:collapse;}
.st-table th{font-size:.66rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-3);padding:.6rem 1rem;border-bottom:1px solid var(--border);text-align:left;white-space:nowrap;}
.st-table td{padding:.8rem 1rem;font-size:.8rem;border-bottom:1px solid var(--border);vertical-align:middle;}
.st-table tbody tr:last-child td{border-bottom:none;}
.st-table tbody tr{transition:background .15s;cursor:pointer;}
.st-table tbody tr:hover td{background:rgba(255,255,255,.03);}
[data-theme="light"] .st-table tbody tr:hover td{background:var(--bg);}

.kode-cell{font-family:monospace;font-size:.75rem;font-weight:700;color:var(--gold);}
.barang-name{font-weight:700;color:var(--text);line-height:1.3;}
.barang-cat{font-size:.68rem;color:var(--text-3);margin-top:2px;}
.person-name{font-weight:600;color:var(--text);}
.person-role{font-size:.68rem;color:var(--text-3);margin-top:1px;}
.station-badge{display:inline-flex;align-items:center;gap:4px;font-size:.71rem;font-weight:600;padding:3px 8px;border-radius:6px;background:rgba(255,255,255,.06);color:var(--text-2);}
[data-theme="light"] .station-badge{background:var(--bg);}
.jadwal-date{font-weight:600;color:var(--text);font-size:.79rem;}
.jadwal-time{font-size:.68rem;color:var(--text-3);margin-top:2px;}
.status-pill{display:inline-flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;padding:4px 10px;border-radius:99px;white-space:nowrap;}
.action-wrap{display:flex;gap:5px;align-items:center;}
.act-btn{width:30px;height:30px;border-radius:7px;border:1px solid var(--border);background:var(--card-bg);display:flex;align-items:center;justify-content:center;font-size:.8rem;color:var(--text-2);cursor:pointer;transition:all .15s;}
.act-btn:hover{border-color:var(--gold);color:var(--gold);}
.act-btn.danger:hover{border-color:var(--danger);color:var(--danger);}

/* ═══ EMPTY STATE ═══ */
.empty-state{padding:3.5rem 2rem;text-align:center;}
.empty-icon{font-size:3.5rem;opacity:.25;margin-bottom:.9rem;}
.empty-title{font-size:.95rem;font-weight:700;color:var(--text-2);margin-bottom:.35rem;}
.empty-sub{font-size:.8rem;color:var(--text-3);}

/* ═══ MODAL ═══ */
.modal-overlay{position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.6);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .3s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal-box{background:#0E1E35;border:1px solid rgba(255,255,255,.1);border-radius:20px;width:100%;max-width:560px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;transform:scale(.95) translateY(16px);transition:transform .3s;}
[data-theme="light"] .modal-box{background:#fff;border-color:var(--border);}
.modal-overlay.open .modal-box{transform:scale(1) translateY(0);}
.modal-header{padding:1.3rem 1.5rem 1rem;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
[data-theme="light"] .modal-header{border-color:var(--border);}
.modal-title{font-size:.95rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:9px;}
[data-theme="light"] .modal-title{color:var(--text);}
.modal-title i{color:var(--gold);}
.modal-close{width:32px;height:32px;background:rgba(255,255,255,.07);border:none;border-radius:8px;color:rgba(255,255,255,.5);font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,.14);color:#fff;}
.modal-body{flex:1;overflow-y:auto;padding:1.3rem 1.5rem;}
.modal-body::-webkit-scrollbar{width:4px;}.modal-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:4px;}
.modal-footer{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.08);display:flex;gap:.6rem;justify-content:flex-end;flex-shrink:0;}
[data-theme="light"] .modal-footer{border-color:var(--border);}

/* ═══ FORM ELEMENTS ═══ */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.form-group{display:flex;flex-direction:column;gap:5px;margin-bottom:1rem;}
.form-group:last-child{margin-bottom:0;}
.form-label{font-size:.75rem;font-weight:700;color:rgba(255,255,255,.65);}
[data-theme="light"] .form-label{color:var(--text-2);}
.form-label span{color:var(--danger);margin-left:2px;}
.form-control{width:100%;padding:.62rem .9rem;border:1.5px solid rgba(255,255,255,.1);border-radius:9px;font-size:.82rem;background:rgba(255,255,255,.05);color:#fff;outline:none;transition:border-color .2s;font-family:inherit;}
[data-theme="light"] .form-control{background:var(--bg);border-color:var(--border);color:var(--text);}
.form-control:focus{border-color:var(--gold);}
.form-control::placeholder{color:rgba(255,255,255,.25);}
[data-theme="light"] .form-control::placeholder{color:var(--text-3);}
.form-control option{background:#0E1E35;color:#fff;}
[data-theme="light"] .form-control option{background:#fff;color:var(--text);}
textarea.form-control{resize:vertical;min-height:80px;}

/* ═══ DETAIL MODAL ═══ */
.detail-kode{font-family:monospace;font-size:.85rem;font-weight:700;color:var(--gold);letter-spacing:.03em;}
.detail-section{margin-bottom:1.25rem;}
.detail-section-title{font-size:.67rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:.75rem;display:flex;align-items:center;gap:8px;}
[data-theme="light"] .detail-section-title{color:var(--text-3);}
.detail-section-title::after{content:"";flex:1;height:1px;background:rgba(255,255,255,.07);}
[data-theme="light"] .detail-section-title::after{background:var(--border);}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;}
.detail-item{background:rgba(255,255,255,.04);border-radius:9px;padding:.65rem .85rem;}
[data-theme="light"] .detail-item{background:var(--bg);}
.detail-item-label{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.3);margin-bottom:3px;}
[data-theme="light"] .detail-item-label{color:var(--text-3);}
.detail-item-val{font-size:.8rem;font-weight:700;color:rgba(255,255,255,.85);}
[data-theme="light"] .detail-item-val{color:var(--text);}
.detail-item.full{grid-column:1/-1;}
.detail-catatan{background:rgba(255,255,255,.04);border-radius:9px;padding:.8rem .95rem;font-size:.79rem;color:rgba(255,255,255,.6);line-height:1.6;}
[data-theme="light"] .detail-catatan{background:var(--bg);color:var(--text-2);}

/* ═══ MODAL BTN ═══ */
.mbtn-cancel{padding:.6rem 1.1rem;border-radius:9px;border:1.5px solid rgba(255,255,255,.1);background:none;color:rgba(255,255,255,.55);font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;}
.mbtn-cancel:hover{border-color:rgba(255,255,255,.3);color:#fff;}
[data-theme="light"] .mbtn-cancel{border-color:var(--border);color:var(--text-2);}
[data-theme="light"] .mbtn-cancel:hover{border-color:var(--text);color:var(--text);}
.mbtn-submit{padding:.6rem 1.3rem;border-radius:9px;border:none;background:var(--gold);color:var(--navy);font-family:inherit;font-size:.82rem;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:6px;transition:opacity .2s,transform .15s;}
.mbtn-submit:hover{opacity:.88;transform:translateY(-1px);}

/* ═══ HISTORY TIMELINE ═══ */
.history-timeline{display:flex;flex-direction:column;position:relative;padding-left:1.5rem;}
.history-timeline::before{content:"";position:absolute;left:7px;top:8px;bottom:8px;width:2px;background:rgba(255,255,255,.08);border-radius:2px;}
[data-theme="light"] .history-timeline::before{background:var(--border);}
.ht-item{position:relative;padding-bottom:1.1rem;display:flex;gap:.85rem;align-items:flex-start;}
.ht-item:last-child{padding-bottom:0;}
.ht-dot{position:absolute;left:-1.5rem;width:16px;height:16px;border-radius:50%;border:2px solid;display:flex;align-items:center;justify-content:center;flex-shrink:0;top:2px;}
.ht-content{flex:1;min-width:0;}
.ht-header{display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;margin-bottom:3px;}
.ht-status{font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:99px;}
.ht-time{font-size:.67rem;color:var(--text-3);white-space:nowrap;}
.ht-actor{font-size:.73rem;font-weight:600;color:var(--text-2);margin-bottom:2px;}
.ht-note{font-size:.74rem;color:var(--text-3);line-height:1.55;}

/* ═══ SETTINGS PANEL ═══ */
.sp-overlay{position:fixed;inset:0;z-index:8888;background:rgba(0,0,0,0);pointer-events:none;transition:background .35s;}
.sp-overlay.open{background:rgba(0,0,0,.55);pointer-events:all;backdrop-filter:blur(4px);}
.settings-panel{position:fixed;top:0;right:0;bottom:0;z-index:8889;width:360px;max-width:92vw;background:#0E1E35;border-left:1px solid rgba(255,255,255,.09);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .38s cubic-bezier(.22,1,.36,1);box-shadow:-24px 0 80px rgba(0,0,0,.55);}
.settings-panel.open{transform:translateX(0);}
[data-theme="light"] .settings-panel{background:#1A2E4A;}
.sp-header{padding:1.3rem 1.5rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.sp-title{font-size:1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:9px;}.sp-title i{color:#F59E0B;}
.sp-close{width:32px;height:32px;background:rgba(255,255,255,.07);border:none;border-radius:8px;color:rgba(255,255,255,.5);font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.sp-close:hover{background:rgba(255,255,255,.13);color:#fff;}
.sp-body{flex:1;overflow-y:auto;padding:1.25rem 1.5rem;}
.sp-body::-webkit-scrollbar{width:4px;}.sp-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:4px;}
.sp-section{margin-bottom:1.5rem;}
.sp-section-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:.75rem;display:flex;align-items:center;gap:8px;}
.sp-section-label::after{content:"";flex:1;height:1px;background:rgba(255,255,255,.07);}
.theme-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;}
.theme-card{position:relative;padding:.8rem .6rem .65rem;border-radius:12px;border:2px solid rgba(255,255,255,.08);cursor:pointer;background:rgba(255,255,255,.04);text-align:center;transition:all .2s;}
.theme-card:hover{border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.08);}
.theme-card.active{border-color:#F59E0B;background:rgba(245,158,11,.1);}
.theme-card-icon{font-size:1.6rem;margin-bottom:5px;display:block;}
.theme-card-name{font-size:.7rem;font-weight:700;color:rgba(255,255,255,.7);}
.theme-card.active .theme-card-name{color:#F59E0B;}
.theme-check{position:absolute;top:5px;right:5px;width:16px;height:16px;background:#F59E0B;border-radius:50%;display:none;align-items:center;justify-content:center;font-size:.5rem;color:#000;}
.theme-card.active .theme-check{display:flex;}
.accent-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:.6rem;}
.accent-dot{width:100%;aspect-ratio:1;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:transform .18s,border-color .18s;}
.accent-dot:hover{transform:scale(1.15);}
.accent-dot.active{border-color:#fff;box-shadow:0 0 0 3px rgba(255,255,255,.25);}
.accent-label{text-align:center;font-size:.62rem;color:rgba(255,255,255,.45);margin-top:5px;font-weight:600;}
.fontsize-row{display:flex;gap:.5rem;}
.fs-btn{flex:1;padding:.55rem .5rem;border-radius:10px;border:2px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);color:rgba(255,255,255,.55);cursor:pointer;font-family:inherit;font-weight:700;transition:all .18s;text-align:center;}
.fs-btn:hover{border-color:rgba(255,255,255,.22);color:#fff;}
.fs-btn.active{border-color:#F59E0B;background:rgba(245,158,11,.1);color:#F59E0B;}
.fs-btn span{display:block;}.fs-btn .fs-sample{font-weight:400;color:rgba(255,255,255,.3);margin-top:1px;}
.fs-btn.active .fs-sample{color:rgba(245,158,11,.5);}
.sp-toggle-row{display:flex;align-items:center;justify-content:space-between;padding:.75rem 0;border-bottom:1px solid rgba(255,255,255,.06);}
.sp-toggle-row:last-child{border-bottom:none;}
.sp-toggle-info{display:flex;align-items:center;gap:10px;}
.sp-toggle-icon{width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.07);display:flex;align-items:center;justify-content:center;font-size:.9rem;color:rgba(255,255,255,.55);flex-shrink:0;}
.sp-toggle-label{font-size:.82rem;font-weight:700;color:rgba(255,255,255,.85);}
.sp-toggle-sub{font-size:.68rem;color:rgba(255,255,255,.35);margin-top:1px;}
.sp-switch{position:relative;width:40px;height:22px;flex-shrink:0;}
.sp-switch input{opacity:0;width:0;height:0;}
.sp-slider{position:absolute;inset:0;cursor:pointer;background:rgba(255,255,255,.12);border-radius:22px;transition:background .25s;}
.sp-slider::before{content:"";position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:transform .25s;box-shadow:0 1px 4px rgba(0,0,0,.3);}
input:checked+.sp-slider{background:#F59E0B;}
input:checked+.sp-slider::before{transform:translateX(18px);}
.sp-preview{margin:0 0 1rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:1rem;display:flex;align-items:center;gap:12px;}
.sp-preview-thumb{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.sp-preview-text .sp-preview-title{font-size:.82rem;font-weight:700;color:rgba(255,255,255,.8);}
.sp-preview-text .sp-preview-sub{font-size:.7rem;color:rgba(255,255,255,.4);margin-top:2px;}
.sp-footer{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.08);display:flex;gap:.6rem;flex-shrink:0;}
.sp-btn-reset{flex:1;padding:.65rem;border-radius:10px;border:1.5px solid rgba(255,255,255,.1);background:none;color:rgba(255,255,255,.5);font-family:inherit;font-size:.8rem;font-weight:700;cursor:pointer;transition:all .2s;}
.sp-btn-reset:hover{border-color:rgba(255,255,255,.25);color:#fff;background:rgba(255,255,255,.05);}
.sp-btn-apply{flex:2;padding:.65rem;border-radius:10px;border:none;background:#F59E0B;color:#0D1B2E;font-family:inherit;font-size:.8rem;font-weight:800;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;}
.sp-btn-apply:hover{background:#FCD34D;transform:translateY(-1px);}
.sp-toast{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(20px);background:#1E3357;border:1px solid rgba(245,158,11,.35);color:#FCD34D;padding:.65rem 1.2rem;border-radius:99px;font-size:.8rem;font-weight:700;display:flex;align-items:center;gap:8px;z-index:9999;opacity:0;pointer-events:none;transition:all .35s;box-shadow:0 8px 32px rgba(0,0,0,.4);}
.sp-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}

.fade-up{opacity:0;transform:translateY(14px);animation:fadeUp .4s ease forwards;}
@keyframes fadeUp{to{opacity:1;transform:translateY(0);}}
.delay-1{animation-delay:.05s;}.delay-2{animation-delay:.1s;}.delay-3{animation-delay:.15s;}
@media(max-width:1199.98px){.stat-grid{grid-template-columns:repeat(2,1fr);}  }
@media(max-width:991px){.topbar-nav{display:none;}.form-row{grid-template-columns:1fr;}}
@media(max-width:767px){.st-table .hide-mobile{display:none;}}
@media(max-width:575px){.page-content{padding:1rem;}.topbar{padding:.65rem 1rem;}.page-banner{padding:1.25rem 1.5rem;}}
</style>
</head>
<body>

<!-- ═══ TOPBAR ═══ -->
<nav class="topbar">
        <a href="index_petugas.php" class="topbar-brand">
            <div class="brand-icon-box">🚆</div>
        <div><div class="brand-text-main">Commuter<em>Link</em></div><div class="brand-text-sub">Lost &amp; Found</div></div>
    </a>
    <div class="topbar-nav">
        <a href="index_petugas.php"              class="tnav-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a href="laporan_kehilangan.php" class="tnav-link"><i class="bi bi-exclamation-circle"></i> Lap. Hilang</a>
        <a href="barang_temuan.php"      class="tnav-link"><i class="bi bi-box-seam"></i> Barang Temuan</a>
        <a href="pencocokan.php"         class="tnav-link"><i class="bi bi-intersect"></i> Pencocokan</a>
        <a href="serah_terima.php"       class="tnav-link active"><i class="bi bi-arrow-left-right"></i> Serah Terima</a>
    </div>
    <div class="topbar-right">
    </div>
</nav>

<!-- ═══ MAIN CONTENT ═══ -->
<main class="page-content">
    <div class="breadcrumb-nav fade-up">
        <a href="index_petugas.php"><i class="bi bi-house-door"></i> Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size:.55rem;"></i>
        <span>Serah Terima</span>
    </div>

    <?php if($flashMsg): ?>
    <div class="fade-up" style="display:flex;align-items:center;gap:10px;padding:.8rem 1.1rem;border-radius:12px;font-size:.82rem;font-weight:600;margin-bottom:1.25rem;
        background:<?= $flashType==='success'?'rgba(16,185,129,.1)':($flashType==='warning'?'rgba(245,158,11,.1)':'rgba(239,68,68,.1)') ?>;
        color:<?= $flashType==='success'?'#059669':($flashType==='warning'?'#B45309':'#DC2626') ?>;
        border:1px solid <?= $flashType==='success'?'rgba(16,185,129,.25)':($flashType==='warning'?'rgba(245,158,11,.25)':'rgba(239,68,68,.25)') ?>;">
        <i class="bi <?= $flashType==='success'?'bi-check-circle-fill':($flashType==='warning'?'bi-exclamation-triangle-fill':'bi-x-circle-fill') ?>"></i>
        <?= $flashMsg ?>
    </div>
    <?php endif; ?>

    <div class="page-banner fade-up">
        <div class="banner-label">Manajemen Serah Terima</div>
        <div class="banner-title">Proses <span>Serah Terima</span> Barang</div>
        <div class="banner-sub">Kelola jadwal dan proses penyerahan barang temuan kepada pemiliknya di posko stasiun.</div>
        <?php if($isPetugas): ?>
        <div class="banner-actions">
        </div>
        <?php endif; ?>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-grid fade-up delay-1">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(240,165,0,.1);color:var(--gold);font-size:1.3rem;">🤝</div>
            <div class="stat-info"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label">Total Serah Terima</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.12);color:#F59E0B;"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info"><div class="stat-num"><?= $stats['menunggu'] ?></div><div class="stat-label">Menunggu Konfirmasi</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.12);color:#3B82F6;"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-info"><div class="stat-num"><?= $stats['dijadwalkan'] ?></div><div class="stat-label">Dijadwalkan</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.12);color:#10B981;"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-info"><div class="stat-num"><?= $stats['selesai'] ?></div><div class="stat-label">Selesai</div></div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar fade-up delay-2">
        <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" class="cl-input" placeholder="Cari kode, barang, atau nama...">
        </div>
        <select id="filterStatus" class="cl-select">
            <option value="">Semua Status</option>
            <option value="menunggu_konfirmasi">Menunggu Konfirmasi</option>
            <option value="dijadwalkan">Dijadwalkan</option>
            <option value="selesai">Selesai</option>
            <option value="dibatalkan">Dibatalkan</option>
        </select>
        <select id="filterStasiun" class="cl-select">
            <option value="">Semua Stasiun</option>
            <?php
            $stasiuns = array_unique(array_column($mockData,'stasiun'));
            sort($stasiuns);
            foreach($stasiuns as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="filter-count">Menampilkan <span id="rowCount"><?= count($mockData) ?></span> data</div>
    </div>

    <!-- TABLE -->
    <div class="table-card fade-up delay-3">
        <div class="table-head">
            <div class="table-head-title"><i class="bi bi-arrow-left-right"></i> Daftar Serah Terima</div>
            <div style="font-size:.72rem;color:var(--text-3);">Klik baris untuk detail</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="st-table" id="mainTable">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Barang</th>
                        <th class="hide-mobile">Pelapor</th>
                        <?php if($isPetugas): ?><th class="hide-mobile">Petugas</th><?php endif; ?>
                        <th class="hide-mobile">Stasiun</th>
                        <th>Jadwal</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach($mockData as $d):
                        $sc = $statusConfig[$d['status']];
                    ?>
                    <tr data-id="<?= $d['id'] ?>"
                        data-status="<?= $d['status'] ?>"
                        data-stasiun="<?= htmlspecialchars($d['stasiun']) ?>"
                        data-search="<?= strtolower($d['kode'].' '.$d['barang'].' '.$d['pelapor'].' '.$d['petugas']) ?>"
                        onclick="openDetail(<?= $d['id'] ?>)">
                        <td><div class="kode-cell"><?= $d['kode'] ?></div></td>
                        <td>
                            <div class="barang-name"><?= htmlspecialchars($d['barang']) ?></div>
                            <div class="barang-cat"><?= htmlspecialchars($d['kategori']) ?></div>
                        </td>
                        <td class="hide-mobile">
                            <div class="person-name"><?= htmlspecialchars($d['pelapor']) ?></div>
                            <div class="person-role">Pelapor</div>
                        </td>
                        <?php if($isPetugas): ?>
                        <td class="hide-mobile">
                            <div class="person-name"><?= htmlspecialchars($d['petugas']) ?></div>
                            <div class="person-role">Petugas</div>
                        </td>
                        <?php endif; ?>
                        <td class="hide-mobile">
                            <span class="station-badge"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($d['stasiun']) ?></span>
                        </td>
                        <td>
                            <div class="jadwal-date"><?= date('d M Y', strtotime($d['jadwal'])) ?></div>
                            <div class="jadwal-time"><?= date('H:i', strtotime($d['jadwal'])) ?> WIB</div>
                        </td>
                        <td>
                            <span class="status-pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
                                <i class="bi <?= $sc['icon'] ?>"></i> <?= $sc['label'] ?>
                            </span>
                        </td>
                        <td onclick="event.stopPropagation()">
                            <div class="action-wrap">
                                <button class="act-btn" title="Detail" onclick="openDetail(<?= $d['id'] ?>)"><i class="bi bi-eye"></i></button>
                                <?php if($isPetugas && ($d['status']==='menunggu_konfirmasi' || $d['status']==='dijadwalkan')): ?>
                                <button class="act-btn" title="Konfirmasi Selesai" onclick="konfirmasiSelesai(<?= $d['id'] ?>)" style="color:#10B981;border-color:rgba(16,185,129,.3);"><i class="bi bi-check-lg"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="emptyState" class="empty-state" style="display:none;">
            <div class="empty-icon">🔍</div>
            <div class="empty-title">Tidak ada data ditemukan</div>
            <div class="empty-sub">Coba ubah kata kunci atau filter yang digunakan.</div>
        </div>
    </div>
</main>

<footer style="padding:1.1rem 2rem;border-top:1px solid var(--border);font-size:.73rem;color:var(--text-3);text-align:center;margin-top:.5rem;">&copy; <?= date('Y') ?> CommuterLink Nusantara.</footer>

<!-- ═══ DETAIL MODAL ═══ -->
<div class="modal-overlay" id="detailOverlay">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-arrow-left-right"></i> Detail Serah Terima</div>
            <button class="modal-close" onclick="closeDetail()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body" id="detailBody"><!-- filled by JS --></div>
        <div class="modal-footer">
            <button class="mbtn-cancel" onclick="closeDetail()">Tutup</button>
        </div>
    </div>
</div>

<!-- ═══ FORM MODAL (buat serah terima) ═══ -->
<div class="modal-overlay" id="formOverlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="formModalTitle"><i class="bi bi-plus-circle"></i> Buat Serah Terima</div>
            <button class="modal-close" onclick="closeFormModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" action="serah_terima.php">
        <input type="hidden" name="action" value="buat_serah_terima">
        <div class="modal-body">
            <?php if(empty($pcList)): ?>
            <div style="text-align:center;padding:2rem;color:var(--text-3);">
                <div style="font-size:2.5rem;opacity:.3;margin-bottom:.75rem;">🔍</div>
                <div style="font-weight:700;color:var(--text-2);font-size:.88rem;">Tidak ada pencocokan tersedia</div>
                <div style="font-size:.77rem;margin-top:.35rem;">Semua pencocokan yang diverifikasi sudah memiliki serah terima.</div>
            </div>
            <?php else: ?>
            <div class="form-group">
                <label class="form-label">Pencocokan Barang <span>*</span></label>
                <select name="pencocokan_id" id="fPencocokan" class="form-control" required onchange="updateFormInfo(this)">
                    <option value="">— Pilih pencocokan —</option>
                    <?php foreach($pcList as $pc): ?>
                    <option value="<?= $pc['id'] ?>"
                        data-laporan="<?= htmlspecialchars($pc['no_laporan']) ?>"
                        data-barang="<?= htmlspecialchars($pc['nama_barang']) ?>"
                        data-kode="<?= htmlspecialchars($pc['kode_barang']) ?>">
                        <?= htmlspecialchars($pc['no_laporan']) ?> — <?= htmlspecialchars($pc['nama_barang']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Info preview setelah pilih pencocokan -->
            <div id="formInfoPreview" style="display:none;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem;font-size:.78rem;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
                    <div><div style="color:rgba(255,255,255,.35);font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-bottom:2px;">No. Laporan</div><div id="prevLaporan" style="color:#fff;font-weight:700;font-family:monospace;"></div></div>
                    <div><div style="color:rgba(255,255,255,.35);font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-bottom:2px;">Kode Barang</div><div id="prevKode" style="color:var(--gold);font-weight:700;font-family:monospace;"></div></div>
                    <div style="grid-column:1/-1;"><div style="color:rgba(255,255,255,.35);font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-bottom:2px;">Nama Barang</div><div id="prevBarang" style="color:#fff;font-weight:600;"></div></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tanggal Serah Terima <span>*</span></label>
                    <input type="date" name="tanggal_date" id="fTanggal" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Jam <span>*</span></label>
                    <input type="time" name="tanggal_time" id="fJam" class="form-control" required>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Catatan</label>
                <textarea name="catatan" id="fCatatan" class="form-control" placeholder="Catatan proses serah terima (opsional)..."></textarea>
            </div>
            <!-- hidden: gabungkan tanggal + jam sebelum submit -->
            <input type="hidden" name="tanggal_serah_terima" id="fTglFull">
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="mbtn-cancel" onclick="closeFormModal()">Batal</button>
            <?php if(!empty($pcList)): ?>
            <button type="submit" class="mbtn-submit" onclick="combineDatetime()"><i class="bi bi-check-lg"></i> Simpan Serah Terima</button>
            <?php endif; ?>
        </div>
        </form>
    </div>
</div>

<!-- ═══ FORM KONFIRMASI SELESAI (hidden POST form) ═══ -->
<form id="konfirmasiForm" method="POST" action="serah_terima.php" style="display:none;">
    <input type="hidden" name="action" value="konfirmasi_selesai">
    <input type="hidden" name="pencocokan_id" id="kfPcId">
    <input type="hidden" name="tanggal_serah_terima" id="kfTgl">
    <input type="hidden" name="catatan" id="kfCatatan">
</form>

<!-- ═══ SETTINGS PANEL ═══ -->
<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>
<aside class="settings-panel" id="settingsPanel">
    <div class="sp-header"><div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan Tampilan</div><button class="sp-close" onclick="closeSettings()"><i class="bi bi-x-lg"></i></button></div>
    <div class="sp-body">
        <div class="sp-preview"><div class="sp-preview-thumb" id="spPreviewThumb">🤝</div><div class="sp-preview-text"><div class="sp-preview-title" id="spPreviewTitle">Dark · Amber</div><div class="sp-preview-sub" id="spPreviewSub">Tampilan saat ini</div></div></div>
        <div class="sp-section"><div class="sp-section-label">Mode Tema</div><div class="theme-grid">
            <div class="theme-card" data-theme="dark"   onclick="setTheme('dark')"  ><span class="theme-card-icon">🌙</span><div class="theme-card-name">Gelap</div>  <div class="theme-check"><i class="bi bi-check"></i></div></div>
            <div class="theme-card" data-theme="light"  onclick="setTheme('light')" ><span class="theme-card-icon">☀️</span><div class="theme-card-name">Terang</div> <div class="theme-check"><i class="bi bi-check"></i></div></div>
            <div class="theme-card" data-theme="system" onclick="setTheme('system')"><span class="theme-card-icon">💻</span><div class="theme-card-name">Sistem</div> <div class="theme-check"><i class="bi bi-check"></i></div></div>
        </div></div>
        <div class="sp-section"><div class="sp-section-label">Warna Aksen</div><div class="accent-grid">
            <div><div class="accent-dot" data-accent="amber"  style="background:#F59E0B;" onclick="setAccent('amber')" ></div><div class="accent-label">Amber</div></div>
            <div><div class="accent-dot" data-accent="blue"   style="background:#3B82F6;" onclick="setAccent('blue')"  ></div><div class="accent-label">Biru</div></div>
            <div><div class="accent-dot" data-accent="green"  style="background:#10B981;" onclick="setAccent('green')" ></div><div class="accent-label">Hijau</div></div>
            <div><div class="accent-dot" data-accent="purple" style="background:#8B5CF6;" onclick="setAccent('purple')"></div><div class="accent-label">Ungu</div></div>
            <div><div class="accent-dot" data-accent="rose"   style="background:#EC4899;" onclick="setAccent('rose')"  ></div><div class="accent-label">Rose</div></div>
        </div></div>
        <div class="sp-section"><div class="sp-section-label">Ukuran Teks</div><div class="fontsize-row">
            <button class="fs-btn" data-size="sm" onclick="setFontSize('sm')"><span style="font-size:.8rem;">Aa</span><span class="fs-sample" style="font-size:.65rem;">Kecil</span></button>
            <button class="fs-btn" data-size="md" onclick="setFontSize('md')"><span style="font-size:1rem;">Aa</span><span class="fs-sample" style="font-size:.7rem;">Sedang</span></button>
            <button class="fs-btn" data-size="lg" onclick="setFontSize('lg')"><span style="font-size:1.2rem;">Aa</span><span class="fs-sample" style="font-size:.75rem;">Besar</span></button>
        </div></div>
        <div class="sp-section"><div class="sp-section-label">Preferensi Lainnya</div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-layout-sidebar-inset-reverse"></i></div><div><div class="sp-toggle-label">Mode Kompak</div><div class="sp-toggle-sub">Kurangi jarak & padding</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleCompact" onchange="setToggle('compact',this.checked)"><span class="sp-slider"></span></label></div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-magic"></i></div><div><div class="sp-toggle-label">Animasi</div><div class="sp-toggle-sub">Aktifkan transisi & efek gerak</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleAnim" onchange="setToggle('anim',this.checked)"><span class="sp-slider"></span></label></div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-bell"></i></div><div><div class="sp-toggle-label">Notifikasi</div><div class="sp-toggle-sub">Tampilkan badge notifikasi</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleNotif" onchange="setToggle('notif',this.checked)"><span class="sp-slider"></span></label></div>
        </div>
        <div class="sp-section"><div class="sp-section-label">Info Aplikasi</div>
            <div style="background:rgba(255,255,255,.04);border-radius:10px;padding:.85rem 1rem;display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;background:linear-gradient(135deg,#F59E0B,#FCD34D);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">🚆</div>
                <div><div style="font-size:.8rem;font-weight:700;color:rgba(255,255,255,.8);">CommuterLink Nusantara</div><div style="font-size:.68rem;color:rgba(255,255,255,.35);">v2.4.1 · Lost &amp; Found Platform</div></div>
            </div>
        </div>
    </div>
    <div class="sp-footer">
        <button class="sp-btn-reset" onclick="resetSettings()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
        <button class="sp-btn-apply" onclick="saveSettings()"><i class="bi bi-check-lg"></i> Simpan Pengaturan</button>
    </div>
</aside>
<div class="sp-toast" id="spToast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ═══ DATA (PHP → JS) ═══
const allData = <?= json_encode(array_values($mockData)) ?>;
const statusConfig = <?= json_encode($statusConfig) ?>;
const isPetugas = <?= json_encode($isPetugas) ?>;
let currentDetailId = null;

// ═══ FILTER & SEARCH ═══
function applyFilters(){
    const q      = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const stasiun= document.getElementById('filterStasiun').value;
    const rows   = document.querySelectorAll('#tableBody tr');
    let visible  = 0;
    rows.forEach(row => {
        const matchQ = !q      || row.dataset.search.includes(q);
        const matchS = !status || row.dataset.status === status;
        const matchSt= !stasiun|| row.dataset.stasiun === stasiun;
        const show   = matchQ && matchS && matchSt;
        row.style.display = show ? '' : 'none';
        if(show) visible++;
    });
    document.getElementById('rowCount').textContent = visible;
    document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
}
document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('filterStatus').addEventListener('change', applyFilters);
document.getElementById('filterStasiun').addEventListener('change', applyFilters);

// ═══ DETAIL MODAL ═══
function openDetail(id){
    const d = allData.find(x=>x.id===id);
    if(!d) return;
    currentDetailId = id;
    const sc = statusConfig[d.status];
    const fmtDate = s => {
        const dt=new Date(s);
        return dt.toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'})+' · '+dt.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'})+' WIB';
    };
    const fmtShort = s => {
        const dt=new Date(s);
        return dt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'})+' '+dt.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
    };

    // build history HTML
    const historyHtml = (d.history && d.history.length) ? `
    <div class="detail-section" style="margin-bottom:0;">
        <div class="detail-section-title">Riwayat Status</div>
        <div class="history-timeline">
            ${[...d.history].reverse().map((h,i) => {
                const hsc = statusConfig[h.status] || {color:'#94A3B8',bg:'rgba(148,163,184,.12)',label:h.status,icon:'bi-circle'};
                const isLatest = i===0;
                return `<div class="ht-item">
                    <div class="ht-dot" style="background:${isLatest?hsc.color:'transparent'};border-color:${hsc.color};"></div>
                    <div class="ht-content">
                        <div class="ht-header">
                            <span class="ht-status" style="background:${hsc.bg};color:${hsc.color};">${hsc.label}</span>
                            <span class="ht-time"><i class="bi bi-clock" style="font-size:.6rem;"></i> ${fmtShort(h.time)}</span>
                        </div>
                        <div class="ht-actor"><i class="bi bi-person" style="font-size:.65rem;"></i> ${h.actor}</div>
                        ${h.note ? `<div class="ht-note">${h.note}</div>` : ''}
                    </div>
                </div>`;
            }).join('')}
        </div>
    </div>` : '';

    document.getElementById('detailBody').innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem;flex-wrap:wrap;gap:.5rem;">
            <div class="detail-kode">${d.kode}</div>
            <span class="status-pill" style="background:${sc.bg};color:${sc.color};"><i class="bi ${sc.icon}"></i> ${sc.label}</span>
        </div>
        <div class="detail-section">
            <div class="detail-section-title">Informasi Barang</div>
            <div class="detail-grid">
                <div class="detail-item full"><div class="detail-item-label">Nama Barang</div><div class="detail-item-val">${d.barang}</div></div>
                <div class="detail-item"><div class="detail-item-label">Kategori</div><div class="detail-item-val">${d.kategori}</div></div>
                <div class="detail-item"><div class="detail-item-label">No. Laporan</div><div class="detail-item-val" style="font-family:monospace;color:var(--gold);">${d.laporan_no}</div></div>
            </div>
        </div>
        <div class="detail-section">
            <div class="detail-section-title">Pihak Terlibat</div>
            <div class="detail-grid">
                <div class="detail-item"><div class="detail-item-label">Pelapor</div><div class="detail-item-val">${d.pelapor}</div></div>
                <div class="detail-item"><div class="detail-item-label">Petugas</div><div class="detail-item-val">${d.petugas}</div></div>
                <div class="detail-item"><div class="detail-item-label">Stasiun</div><div class="detail-item-val">📍 ${d.stasiun}</div></div>
                <div class="detail-item"><div class="detail-item-label">Jadwal</div><div class="detail-item-val">${fmtDate(d.jadwal)}</div></div>
                ${d.selesai_at ? `<div class="detail-item full"><div class="detail-item-label">Diselesaikan</div><div class="detail-item-val" style="color:#10B981;">✅ ${fmtDate(d.selesai_at)}</div></div>` : ''}
            </div>
        </div>
        ${d.catatan ? `
        <div class="detail-section">
            <div class="detail-section-title">Catatan</div>
            <div class="detail-catatan">${d.catatan}</div>
        </div>` : ''}
        ${historyHtml}
    `;
    document.getElementById('detailOverlay').classList.add('open');
}
function closeDetail(){
    document.getElementById('detailOverlay').classList.remove('open');
    currentDetailId = null;
}

// ═══ FORM MODAL ═══
function openFormModal(){
    const now = new Date();
    document.getElementById('fTanggal').value = now.toISOString().split('T')[0];
    document.getElementById('fJam').value = now.toTimeString().slice(0,5);
    document.getElementById('fPencocokan').value = '';
    document.getElementById('fCatatan').value = '';
    document.getElementById('formInfoPreview').style.display = 'none';
    document.getElementById('formOverlay').classList.add('open');
}
function closeFormModal(){
    document.getElementById('formOverlay').classList.remove('open');
}
function updateFormInfo(sel){
    const opt = sel.options[sel.selectedIndex];
    if(!opt.value){ document.getElementById('formInfoPreview').style.display='none'; return; }
    document.getElementById('prevLaporan').textContent = opt.dataset.laporan || '-';
    document.getElementById('prevKode').textContent    = opt.dataset.kode    || '-';
    document.getElementById('prevBarang').textContent  = opt.dataset.barang  || '-';
    document.getElementById('formInfoPreview').style.display = 'block';
}
function combineDatetime(){
    const tgl = document.getElementById('fTanggal').value;
    const jam = document.getElementById('fJam').value;
    if(tgl && jam) document.getElementById('fTglFull').value = tgl + ' ' + jam + ':00';
}

// ═══ KONFIRMASI SELESAI → POST ke server ═══
function konfirmasiSelesai(id){
    const d = allData.find(x=>x.id===id);
    if(!d) return;
    if(!confirm(`Tandai serah terima barang "${d.barang}" sebagai SELESAI?\n\nData akan disimpan ke database. Aksi ini tidak bisa dibatalkan.`)) return;

    const now = new Date();
    const nowStr = now.getFullYear()+'-'
        +String(now.getMonth()+1).padStart(2,'0')+'-'
        +String(now.getDate()).padStart(2,'0')+' '
        +String(now.getHours()).padStart(2,'0')+':'
        +String(now.getMinutes()).padStart(2,'0')+':00';

    document.getElementById('kfPcId').value    = d.id; // pencocokan_id
    document.getElementById('kfTgl').value     = nowStr;
    document.getElementById('kfCatatan').value = 'Dikonfirmasi selesai oleh petugas.';
    document.getElementById('konfirmasiForm').submit();
}

// ═══ TOAST ═══
function showToast(msg){
    const t = document.getElementById('spToast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'), 2800);
}

// ═══ CLOSE ON OVERLAY CLICK ═══
document.getElementById('detailOverlay').addEventListener('click', function(e){ if(e.target===this) closeDetail(); });
document.getElementById('formOverlay').addEventListener('click',  function(e){ if(e.target===this) closeFormModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape'){ closeDetail(); closeFormModal(); closeSettings(); }});

// ═══ SETTINGS SYSTEM ═══
(function(){
    const DEFAULTS={theme:'dark',accent:'amber',fontSize:'md',compact:false,anim:true,notif:true};
    let S=Object.assign({},DEFAULTS);
    const AC={amber:'#F59E0B',blue:'#3B82F6',green:'#10B981',purple:'#8B5CF6',rose:'#EC4899'};
    const AN={amber:'Amber',blue:'Biru',green:'Hijau',purple:'Ungu',rose:'Rose'};
    const TN={dark:'Gelap',light:'Terang',system:'Sistem'};
    const TI={dark:'🌙',light:'☀️',system:'💻'};
    function load(){try{const s=localStorage.getItem('cl_settings');if(s)S=Object.assign({},DEFAULTS,JSON.parse(s));}catch(e){}}
    function apply(){
        const h=document.documentElement;
        let t=S.theme==='system'?(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):S.theme;
        h.setAttribute('data-theme',t);h.setAttribute('data-accent',S.accent);
        h.setAttribute('data-fontsize',S.fontSize);h.setAttribute('data-compact',S.compact?'true':'false');
        h.setAttribute('data-anim',S.anim?'on':'off');
    }
    function sync(){
        document.querySelectorAll('.theme-card').forEach(c=>c.classList.toggle('active',c.dataset.theme===S.theme));
        document.querySelectorAll('.accent-dot').forEach(d=>d.classList.toggle('active',d.dataset.accent===S.accent));
        document.querySelectorAll('.fs-btn').forEach(b=>b.classList.toggle('active',b.dataset.size===S.fontSize));
        const tc=document.getElementById('toggleCompact');if(tc)tc.checked=S.compact;
        const ta=document.getElementById('toggleAnim');if(ta)ta.checked=S.anim;
        const tn=document.getElementById('toggleNotif');if(tn)tn.checked=S.notif;
        const ac=AC[S.accent]||'#F59E0B';
        document.getElementById('spPreviewThumb').style.background=`linear-gradient(135deg,${ac},rgba(255,255,255,.25))`;
        document.getElementById('spPreviewTitle').textContent=`${TI[S.theme]} ${TN[S.theme]} · ${AN[S.accent]}`;
        document.getElementById('spPreviewSub').textContent=`Font: ${S.fontSize==='sm'?'Kecil':S.fontSize==='lg'?'Besar':'Sedang'} · Kompak: ${S.compact?'Ya':'Tidak'} · Animasi: ${S.anim?'On':'Off'}`;
        const btn=document.querySelector('.sp-btn-apply');if(btn)btn.style.background=ac;
    }
    window.setTheme=t=>{S.theme=t;apply();sync();};
    window.setAccent=a=>{S.accent=a;apply();sync();};
    window.setFontSize=f=>{S.fontSize=f;apply();sync();};
    window.setToggle=(k,v)=>{if(k==='compact')S.compact=v;else if(k==='anim')S.anim=v;else if(k==='notif')S.notif=v;apply();sync();};
    window.openSettings=()=>{sync();document.getElementById('settingsPanel').classList.add('open');document.getElementById('spOverlay').classList.add('open');document.body.style.overflow='hidden';};
    window.closeSettings=()=>{document.getElementById('settingsPanel').classList.remove('open');document.getElementById('spOverlay').classList.remove('open');document.body.style.overflow='';};
    window.saveSettings=()=>{try{localStorage.setItem('cl_settings',JSON.stringify(S));}catch(e){}closeSettings();showToast('✅ Pengaturan tersimpan!');};
    window.resetSettings=()=>{S=Object.assign({},DEFAULTS);apply();sync();try{localStorage.removeItem('cl_settings');}catch(e){}showToast('🔄 Pengaturan direset');};
    document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key===','){e.preventDefault();openSettings();}});
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>{if(S.theme==='system')apply();});
    load();apply();
})();
</script>
</body>
</html>