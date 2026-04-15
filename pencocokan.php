<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo  = getDB();
$user = getCurrentUser();

if ($user['role'] !== 'petugas') {
    header('Location: index_petugas.php?err=forbidden'); exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
function checkCSRF(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function setFlashMsg(string $msg): void { $_SESSION['_flash_pc'] = $msg; }
function getFlashMsg(): string {
    $m = $_SESSION['_flash_pc'] ?? '';
    unset($_SESSION['_flash_pc']);
    return $m;
}

function calcMatchScore(array $found, array $lost): array {
    $score    = 0;
    $criteria = [];

    $foundNama = strtolower(trim($found['nama_barang'] ?? ''));
    $lostNama  = strtolower(trim($lost['nama_barang'] ?? ''));
    if ($foundNama && $lostNama) {
        similar_text($foundNama, $lostNama, $pct);
        if ($pct >= 80)      { $score += 40; $criteria[] = 'Nama barang sangat mirip (+40)'; }
        elseif ($pct >= 60)  { $score += 25; $criteria[] = 'Nama barang cukup mirip (+25)'; }
        elseif ($pct >= 40)  { $score += 10; $criteria[] = 'Nama barang agak mirip (+10)'; }
        else {
            $fWords = explode(' ', $foundNama);
            $lWords = explode(' ', $lostNama);
            $common = count(array_intersect($fWords, $lWords));
            if ($common > 0) { $score += $common * 8; $criteria[] = "Kata kunci nama cocok ({$common} kata, +" . ($common*8) . ')'; }
        }
    }

    $foundDesk = strtolower(trim($found['deskripsi'] ?? ''));
    $lostDesk  = strtolower(trim($lost['deskripsi']  ?? ''));
    if ($foundDesk && $lostDesk) {
        $fWords = array_filter(explode(' ', $foundDesk), fn($w) => strlen($w) > 3);
        $lWords = array_filter(explode(' ', $lostDesk),  fn($w) => strlen($w) > 3);
        $common = count(array_intersect($fWords, $lWords));
        if ($common >= 3)     { $score += 20; $criteria[] = "Deskripsi banyak kesamaan (+20)"; }
        elseif ($common >= 1) { $score += 10; $criteria[] = "Deskripsi ada kesamaan (+10)"; }
    }

    if (!empty($found['waktu_ditemukan']) && !empty($lost['waktu_hilang'])) {
        $diff = abs(strtotime($found['waktu_ditemukan']) - strtotime($lost['waktu_hilang']));
        if ($diff <= 86400)       { $score += 25; $criteria[] = 'Tanggal sangat berdekatan ≤1 hari (+25)'; }
        elseif ($diff <= 86400*3) { $score += 15; $criteria[] = 'Tanggal berdekatan ≤3 hari (+15)'; }
    }

    $foundLoc = strtolower(trim($found['lokasi_ditemukan'] ?? ''));
    $lostLoc  = strtolower(trim($lost['lokasi_hilang']    ?? ''));
    if ($foundLoc && $lostLoc) {
        $fWords = array_filter(explode(' ', $foundLoc), fn($w) => strlen($w) > 3);
        $lWords = array_filter(explode(' ', $lostLoc),  fn($w) => strlen($w) > 3);
        if (count(array_intersect($fWords, $lWords)) > 0) {
            $score += 15; $criteria[] = 'Lokasi berdekatan (+15)';
        }
    }

    return ['score' => min($score, 100), 'criteria' => $criteria];
}

/* POST HANDLER */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCSRF($_POST['csrf_token'] ?? '')) {
        die('Token keamanan tidak valid.');
    }

    $postAction = $_POST['form_action'] ?? '';

    if ($postAction === 'create_match') {
        $barangId  = intval($_POST['barang_id']  ?? 0);
        $laporanId = intval($_POST['laporan_id'] ?? 0);
        $catatan   = trim($_POST['catatan']       ?? '');
        $score     = intval($_POST['match_score'] ?? 0);
        $pid       = $user['id'];

        if ($barangId > 0 && $laporanId > 0) {
            $cek = $pdo->prepare("SELECT id FROM pencocokan WHERE laporan_id = ? AND barang_id = ? AND deleted_at IS NULL LIMIT 1");
            $cek->execute([$laporanId, $barangId]);
            if ($cek->fetch()) {
                setFlashMsg('⚠️ Pencocokan ini sudah pernah dibuat sebelumnya.');
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO pencocokan (laporan_id, barang_id, petugas_id, status, catatan, created_at, created_by)
                    VALUES (?, ?, ?, 'menunggu_verifikasi', ?, NOW(), ?)
                ");
                if ($stmt->execute([$laporanId, $barangId, $pid, $catatan, $pid])) {
                    $pdo->prepare("UPDATE barang_temuan SET status='dicocokkan', updated_by=? WHERE id=?")->execute([$pid, $barangId]);
                    $pdo->prepare("UPDATE laporan_kehilangan SET status='ditemukan', updated_by=? WHERE id=?")->execute([$pid, $laporanId]);
                    setFlashMsg('✓ Pencocokan berhasil dibuat. Status barang & laporan diperbarui.');
                } else {
                    setFlashMsg('❌ Gagal menyimpan pencocokan. Silakan coba lagi.');
                }
            }
        }
        header('Location: pencocokan.php'); exit;
    }

    if ($postAction === 'update_status') {
        $matchId    = intval($_POST['match_id'] ?? 0);
        $newStatus  = $_POST['status'] ?? '';
        $pid        = $user['id'];
        $validStats = ['menunggu_verifikasi', 'diverifikasi', 'ditolak'];

        if ($matchId > 0 && in_array($newStatus, $validStats)) {
            $pdo->prepare("UPDATE pencocokan SET status=?, updated_by=?, updated_at=NOW() WHERE id=? AND deleted_at IS NULL")
                ->execute([$newStatus, $pid, $matchId]);

            if ($newStatus === 'ditolak') {
                $row = $pdo->prepare("SELECT laporan_id, barang_id FROM pencocokan WHERE id=? LIMIT 1");
                $row->execute([$matchId]);
                if ($r = $row->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->prepare("UPDATE barang_temuan SET status='tersimpan', updated_by=? WHERE id=?")->execute([$pid, $r['barang_id']]);
                    $pdo->prepare("UPDATE laporan_kehilangan SET status='diproses', updated_by=? WHERE id=?")->execute([$pid, $r['laporan_id']]);
                }
            }
            if ($newStatus === 'diverifikasi') {
                $row = $pdo->prepare("SELECT laporan_id, barang_id FROM pencocokan WHERE id=? LIMIT 1");
                $row->execute([$matchId]);
                if ($r = $row->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->prepare("UPDATE barang_temuan SET status='diklaim', updated_by=? WHERE id=?")->execute([$pid, $r['barang_id']]);
                    $pdo->prepare("UPDATE laporan_kehilangan SET status='selesai', updated_by=? WHERE id=?")->execute([$pid, $r['laporan_id']]);
                }
            }
            setFlashMsg('✓ Status pencocokan berhasil diperbarui.');
        }
        header('Location: pencocokan.php'); exit;
    }

    if ($postAction === 'delete_match') {
        $matchId = intval($_POST['match_id'] ?? 0);
        $pid     = $user['id'];
        if ($matchId > 0) {
            $row = $pdo->prepare("SELECT laporan_id, barang_id FROM pencocokan WHERE id=? LIMIT 1");
            $row->execute([$matchId]);
            if ($r = $row->fetch(PDO::FETCH_ASSOC)) {
                $pdo->prepare("UPDATE barang_temuan SET status='tersimpan', updated_by=? WHERE id=?")->execute([$pid, $r['barang_id']]);
                $pdo->prepare("UPDATE laporan_kehilangan SET status='diproses', updated_by=? WHERE id=?")->execute([$pid, $r['laporan_id']]);
            }
            $pdo->prepare("UPDATE pencocokan SET deleted_at=NOW(), deleted_by=? WHERE id=?")->execute([$pid, $matchId]);
            setFlashMsg('✓ Pencocokan dihapus dan status dikembalikan.');
        }
        header('Location: pencocokan.php'); exit;
    }
}

/* AMBIL DATA PENCOCOKAN */
$filterStatus = trim($_GET['status'] ?? '');
$search       = trim($_GET['q']      ?? '');

$where  = "p.deleted_at IS NULL";
$params = [];
if ($filterStatus) { $where .= " AND p.status = ?"; $params[] = $filterStatus; }
if ($search) {
    $like   = '%' . $search . '%';
    $where .= " AND (bt.nama_barang LIKE ? OR lk.nama_barang LIKE ? OR u_lap.nama LIKE ? OR bt.kode_barang LIKE ? OR lk.no_laporan LIKE ?)";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$matches = $pdo->prepare("
    SELECT
        p.*,
        bt.kode_barang, bt.nama_barang AS barang_nama,
        bt.deskripsi AS barang_deskripsi, bt.foto_barang,
        bt.lokasi_ditemukan, bt.waktu_ditemukan,
        lk.no_laporan, lk.nama_barang AS laporan_nama,
        lk.lokasi_hilang, lk.waktu_hilang, lk.deskripsi AS laporan_deskripsi,
        u_lap.nama AS nama_pelapor,
        u_pet.nama AS nama_petugas
    FROM pencocokan p
    JOIN barang_temuan bt       ON p.barang_id  = bt.id
    JOIN laporan_kehilangan lk  ON p.laporan_id = lk.id
    LEFT JOIN users u_lap ON lk.user_id    = u_lap.id
    LEFT JOIN users u_pet ON p.petugas_id  = u_pet.id
    WHERE $where
    ORDER BY p.created_at DESC
    LIMIT 100
");
$matches->execute($params);
$matchList = $matches->fetchAll(PDO::FETCH_ASSOC);

/* COUNT PER STATUS */
$counts = ['all' => 0, 'menunggu_verifikasi' => 0, 'diverifikasi' => 0, 'ditolak' => 0];
$cStmt  = $pdo->query("SELECT status, COUNT(*) c FROM pencocokan WHERE deleted_at IS NULL GROUP BY status");
foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['c'];
    $counts['all'] += (int)$row['c'];
}

/* DATA UNTUK FORM MANUAL */
$barangList = $pdo->query("
    SELECT id, kode_barang, nama_barang, deskripsi, lokasi_ditemukan, waktu_ditemukan
    FROM barang_temuan
    WHERE deleted_at IS NULL AND status = 'tersimpan'
    ORDER BY created_at DESC LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$laporanList = $pdo->query("
    SELECT lk.id, lk.no_laporan, lk.nama_barang, lk.deskripsi,
           lk.waktu_hilang, lk.lokasi_hilang, u.nama AS nama_pelapor
    FROM laporan_kehilangan lk
    LEFT JOIN users u ON lk.user_id = u.id
    WHERE lk.deleted_at IS NULL AND lk.status = 'diproses'
    ORDER BY lk.created_at DESC LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

/* SARAN OTOMATIS */
$suggestions = [];
foreach ($laporanList as $laporan) {
    foreach ($barangList as $barang) {
        $result = calcMatchScore($barang, $laporan);
        if ($result['score'] >= 30) {
            $cek = $pdo->prepare("SELECT id FROM pencocokan WHERE laporan_id=? AND barang_id=? AND deleted_at IS NULL LIMIT 1");
            $cek->execute([$laporan['id'], $barang['id']]);
            if (!$cek->fetch()) {
                $suggestions[] = [
                    'barang'   => $barang,
                    'laporan'  => $laporan,
                    'score'    => $result['score'],
                    'criteria' => implode(', ', $result['criteria']),
                ];
            }
        }
    }
}
usort($suggestions, fn($a, $b) => $b['score'] - $a['score']);
$suggestions = array_slice($suggestions, 0, 9);

/* STATUS MAP */
$statusMap = [
    'menunggu_verifikasi' => ['label' => 'Menunggu Verifikasi', 'class' => 'st-wait',     'dot' => '#F59E0B'],
    'diverifikasi'        => ['label' => 'Diverifikasi',        'class' => 'st-verified',  'dot' => '#34D399'],
    'ditolak'             => ['label' => 'Ditolak',             'class' => 'st-rejected',  'dot' => '#F87171'],
];

$flash = getFlashMsg();

$preBarangId  = intval($_GET['barang_id']  ?? 0);
$preLaporanId = intval($_GET['laporan_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pencocokan – CommuterLink</title>
  <link rel="icon" href="uploads/favicon.png" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,700;0,900;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:#0A1628;--bg-2:#0F1F38;--navy:#0D1B2E;--navy-2:#152640;--navy-3:#1E3357;
      --card:#132035;--card-2:#192A45;--card-3:#1F3254;
      --amber:#F59E0B;--amber-lt:#FCD34D;--amber-dim:rgba(245,158,11,0.12);
      --blue-lt:#60A5FA;--text:#EBF4FF;--text-2:#A8BDD6;--text-3:#5A7A9E;
      --border:rgba(255,255,255,0.07);--border-2:rgba(255,255,255,0.13);
      --danger:#F87171;--success:#34D399;--purple:#A78BFA;--radius:14px;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
    .cl-navbar{position:sticky;top:0;z-index:300;background:rgba(10,22,40,0.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);padding:0 1.5rem;height:60px;display:flex;align-items:center;justify-content:space-between;}
    .cl-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
    .cl-brand-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--amber),var(--amber-lt));border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.95rem;box-shadow:0 4px 12px rgba(245,158,11,.35);}
    .cl-brand-name{font-family:'Fraunces',serif;font-size:1rem;font-weight:700;color:var(--text);line-height:1;}
    .cl-brand-name em{font-style:italic;color:var(--amber);}
    .cl-brand-sub{font-size:.58rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.1em;}
    .cl-nav-links{display:flex;align-items:center;gap:.2rem;}
    .cl-nav-link{display:flex;align-items:center;gap:6px;padding:.45rem .8rem;border-radius:8px;font-size:.79rem;font-weight:600;color:var(--text-2);text-decoration:none;transition:all .2s;}
    .cl-nav-link:hover{background:var(--card-2);color:var(--text);}
    .cl-nav-link.active{background:var(--amber-dim);color:var(--amber);}
    .cl-nav-link i{font-size:.88rem;}
    .role-chip{font-size:.63rem;font-weight:700;padding:3px 9px;border-radius:99px;background:rgba(96,165,250,.12);color:var(--blue-lt);border:1px solid rgba(96,165,250,.22);letter-spacing:.06em;}
    .cl-avatar-chip{display:flex;align-items:center;gap:8px;padding:4px 12px 4px 4px;border:1px solid var(--border);border-radius:99px;background:var(--card);text-decoration:none;transition:all .2s;}
    .cl-avatar-chip:hover{border-color:var(--amber);}
    .cl-avt{width:28px;height:28px;background:linear-gradient(135deg,var(--amber),var(--amber-lt));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:var(--navy);}
    .cl-avt-name{font-size:.78rem;font-weight:600;color:var(--text);}
    .page-header{background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%);border-bottom:1px solid var(--border);padding:1.6rem 2rem;position:relative;overflow:hidden;}
    .page-header::after{content:'';position:absolute;right:-60px;top:-60px;width:260px;height:260px;background:radial-gradient(circle,rgba(167,139,250,.1) 0%,transparent 70%);border-radius:50%;pointer-events:none;}
    .ph-inner{position:relative;z-index:1;}
    .bc{display:flex;align-items:center;gap:5px;margin-bottom:.65rem;}
    .bc a,.bc span{font-size:.73rem;color:var(--text-3);text-decoration:none;}
    .bc a:hover{color:var(--amber);}
    .bc i{font-size:.6rem;color:var(--text-3);}
    .ph-title{font-family:'Fraunces',serif;font-size:1.55rem;font-weight:900;color:var(--text);display:flex;align-items:center;gap:10px;margin-bottom:.25rem;}
    .ph-icon{width:36px;height:36px;background:rgba(167,139,250,.1);border:1px solid rgba(167,139,250,.22);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--purple);font-size:1rem;}
    .ph-sub{font-size:.8rem;color:var(--text-3);}
    .main-wrap{padding:1.75rem 2rem 4rem;}
    @media(max-width:767px){.main-wrap{padding:1.25rem 1rem 3rem;}.page-header{padding:1.25rem 1rem;}.cl-navbar{padding:0 1rem;}}
    .stat-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:.7rem;margin-bottom:1.4rem;}
    @media(max-width:767px){.stat-strip{grid-template-columns:repeat(2,1fr);}}
    .ss-item{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1rem 1.1rem;display:flex;align-items:center;gap:10px;text-decoration:none;transition:all .2s;cursor:pointer;}
    .ss-item:hover{border-color:var(--border-2);transform:translateY(-2px);}
    .ss-item.active{border-color:var(--amber);background:var(--amber-dim);}
    .ss-num{font-family:'Fraunces',serif;font-size:1.55rem;font-weight:700;color:var(--text);line-height:1;}
    .ss-lbl{font-size:.7rem;color:var(--text-3);margin-top:2px;font-weight:500;}
    .ss-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-left:auto;}
    .cl-toolbar{display:flex;align-items:center;gap:.75rem;margin-bottom:1.2rem;flex-wrap:wrap;}
    .cl-search{position:relative;flex:1;min-width:200px;}
    .cl-search i{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:var(--text-3);font-size:.82rem;pointer-events:none;}
    .cl-search input{width:100%;padding:.58rem .9rem .58rem 2.35rem;background:var(--card);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.82rem;outline:none;transition:border-color .2s;}
    .cl-search input:focus{border-color:var(--amber);}
    .cl-search input::placeholder{color:var(--text-3);}
    .btn-cl{display:inline-flex;align-items:center;gap:6px;padding:.58rem 1.1rem;border-radius:10px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:.81rem;font-weight:700;text-decoration:none;transition:all .2s;white-space:nowrap;}
    .btn-amber{background:var(--amber);color:var(--navy);box-shadow:0 4px 14px rgba(245,158,11,.25);}
    .btn-amber:hover{background:var(--amber-lt);transform:translateY(-1px);color:var(--navy);}
    .btn-ghost{background:transparent;color:var(--text-2);border:1px solid var(--border-2);}
    .btn-ghost:hover{border-color:var(--text-2);color:var(--text);background:var(--card-2);}
    .btn-success{background:rgba(52,211,153,.15);color:var(--success);border:1px solid rgba(52,211,153,.3);}
    .btn-success:hover{background:rgba(52,211,153,.28);}
    .btn-danger{background:rgba(248,113,113,.12);color:var(--danger);border:1px solid rgba(248,113,113,.28);}
    .btn-danger:hover{background:rgba(248,113,113,.24);}
    .btn-purple{background:rgba(167,139,250,.12);color:var(--purple);border:1px solid rgba(167,139,250,.28);}
    .btn-purple:hover{background:rgba(167,139,250,.24);}
    .cl-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
    .cl-card-head{display:flex;align-items:center;justify-content:space-between;padding:.95rem 1.4rem;border-bottom:1px solid var(--border);}
    .cl-card-title{font-size:.86rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
    .cl-card-title i{color:var(--purple);}
    .count-pill{font-size:.68rem;font-weight:700;background:var(--amber-dim);color:var(--amber);padding:2px 8px;border-radius:99px;}
    .suggest-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;padding:1.2rem;}
    .suggest-card{background:var(--card-2);border:1px solid var(--border);border-radius:12px;padding:1rem;transition:.2s;position:relative;overflow:hidden;}
    .suggest-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;}
    .suggest-card.high::before{background:var(--success);}
    .suggest-card.medium::before{background:var(--amber);}
    .suggest-card.low::before{background:var(--danger);}
    .suggest-card:hover{border-color:var(--border-2);transform:translateY(-1px);}
    .score-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem;}
    .score-num{font-family:'Fraunces',serif;font-size:1.3rem;font-weight:700;}
    .score-num.high{color:var(--success);}.score-num.medium{color:var(--amber);}.score-num.low{color:var(--danger);}
    .score-bar{height:5px;background:var(--border);border-radius:99px;overflow:hidden;margin-bottom:.75rem;}
    .score-bar-fill{height:100%;border-radius:99px;transition:width .4s;}
    .score-bar-fill.high{background:var(--success);}.score-bar-fill.medium{background:var(--amber);}.score-bar-fill.low{background:var(--danger);}
    .match-pair{display:grid;grid-template-columns:1fr auto 1fr;gap:.5rem;align-items:center;margin-bottom:.75rem;}
    .pair-box{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:.6rem .75rem;}
    .pair-label{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:2px;}
    .pair-name{font-size:.8rem;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .pair-sub{font-size:.68rem;color:var(--text-3);margin-top:1px;}
    .pair-arrow{color:var(--text-3);font-size:1rem;}
    .criteria-text{font-size:.72rem;color:var(--text-3);margin-bottom:.75rem;display:flex;align-items:flex-start;gap:5px;}
    .criteria-text i{flex-shrink:0;margin-top:2px;}
    .match-item{padding:1.1rem 1.4rem;border-bottom:1px solid var(--border);transition:background .12s;}
    .match-item:last-child{border-bottom:none;}
    .match-item:hover{background:rgba(255,255,255,.015);}
    .match-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem;}
    .match-meta{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;}
    .match-time{font-size:.71rem;color:var(--text-3);}
    .match-score-badge{font-family:'Fraunces',serif;font-size:.9rem;font-weight:700;padding:2px 10px;border-radius:8px;}
    .msb-high{background:rgba(52,211,153,.12);color:var(--success);}
    .msb-medium{background:var(--amber-dim);color:var(--amber);}
    .msb-low{background:rgba(248,113,113,.1);color:var(--danger);}
    .match-body{display:grid;grid-template-columns:1fr auto 1fr;gap:.75rem;align-items:center;margin-bottom:.75rem;}
    @media(max-width:600px){.match-body{grid-template-columns:1fr;}.pair-arrow{display:none;}}
    .match-box{border:1px solid var(--border);border-radius:10px;padding:.75rem 1rem;}
    .match-box.found{background:rgba(52,211,153,.04);border-color:rgba(52,211,153,.15);}
    .match-box.lost{background:rgba(248,113,113,.04);border-color:rgba(248,113,113,.15);}
    .match-box-label{font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px;}
    .match-box.found .match-box-label{color:var(--success);}
    .match-box.lost  .match-box-label{color:var(--danger);}
    .match-box-name{font-weight:700;color:var(--text);font-size:.84rem;}
    .match-box-sub{font-size:.71rem;color:var(--text-3);margin-top:2px;}
    .match-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;}
    .st-badge{display:inline-flex;align-items:center;gap:5px;font-size:.67rem;font-weight:700;padding:3px 9px;border-radius:99px;}
    .st-badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
    .st-wait{background:rgba(245,158,11,.12);color:var(--amber);}
    .st-verified{background:rgba(52,211,153,.12);color:var(--success);}
    .st-rejected{background:rgba(248,113,113,.1);color:var(--danger);}
    .form-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;position:sticky;top:72px;}
    .form-card-head{display:flex;align-items:center;gap:10px;padding:.95rem 1.2rem;border-bottom:1px solid var(--border);background:var(--card-2);}
    .fch-icon{width:28px;height:28px;background:rgba(167,139,250,.1);border:1px solid rgba(167,139,250,.2);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--purple);font-size:.82rem;}
    .fch-title{font-size:.83rem;font-weight:700;color:var(--text);}
    .form-body{padding:1.1rem;}
    .cl-label{display:block;font-size:.77rem;font-weight:600;color:var(--text-2);margin-bottom:5px;}
    .cl-label .req{color:var(--amber);}
    .cl-select,.cl-textarea,.cl-input{width:100%;padding:.62rem .88rem;background:var(--bg-2);border:1.5px solid var(--border);border-radius:9px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.82rem;outline:none;transition:border-color .2s,box-shadow .2s;}
    .cl-select:focus,.cl-textarea:focus,.cl-input:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(245,158,11,.1);}
    .cl-select{appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%235A7A9E' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .9rem center;}
    .cl-select option{background:#0D1B2E;}
    .cl-textarea{resize:vertical;min-height:70px;}
    .score-preview{display:none;background:var(--card-2);border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;margin-bottom:.9rem;}
    .sp-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.45rem;}
    .sp-label{font-size:.75rem;font-weight:700;color:var(--text-2);}
    .sp-score{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:700;}
    .sp-criteria{font-size:.71rem;color:var(--text-3);margin-top:.4rem;line-height:1.5;}
    .sp-bar{height:5px;background:var(--border);border-radius:99px;overflow:hidden;}
    .sp-bar-fill{height:100%;border-radius:99px;transition:width .35s;}
    .empty-state{text-align:center;padding:4rem 2rem;}
    .empty-icon{font-size:3rem;opacity:.2;margin-bottom:1rem;display:block;}
    .empty-title{font-size:.92rem;font-weight:700;color:var(--text-2);margin-bottom:.35rem;}
    .empty-sub{font-size:.78rem;color:var(--text-3);}
    .flash-bar{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9000;background:var(--card-3);border:1px solid rgba(52,211,153,.3);color:var(--success);padding:.82rem 1.15rem;border-radius:12px;font-size:.81rem;font-weight:600;display:flex;align-items:center;gap:8px;box-shadow:0 8px 30px rgba(0,0,0,.4);max-width:400px;animation:slideUp .3s ease;}
    .flash-bar.warn{border-color:rgba(245,158,11,.3);color:var(--amber);}
    @keyframes slideUp{from{transform:translateY(20px);opacity:0;}to{transform:translateY(0);opacity:1;}}
    .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(8px);z-index:9999;align-items:center;justify-content:center;}
    .modal-bg.show{display:flex;}
    .modal-box{background:var(--card);border:1px solid var(--border-2);border-radius:18px;padding:2rem;max-width:350px;width:90%;text-align:center;animation:popIn .25s ease;}
    @keyframes popIn{from{transform:scale(.88);opacity:0;}to{transform:scale(1);opacity:1;}}
    .modal-ico{font-size:2.5rem;margin-bottom:.85rem;}
    .modal-title{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:700;color:var(--text);margin-bottom:.4rem;}
    .modal-sub{font-size:.78rem;color:var(--text-3);margin-bottom:1.4rem;line-height:1.55;}
    .fade-up{opacity:0;transform:translateY(14px);animation:fadeUp .4s ease forwards;}
    @keyframes fadeUp{to{opacity:1;transform:translateY(0);}}
    .d1{animation-delay:.05s;}.d2{animation-delay:.1s;}.d3{animation-delay:.15s;}
    @media(max-width:991px){.cl-nav-links{display:none;}}
    .code-tag{font-family:'Courier New',monospace;font-size:.72rem;background:rgba(245,158,11,.1);color:var(--amber);padding:3px 7px;border-radius:5px;border:1px solid rgba(245,158,11,.2);}
  </style>
</head>
<body>

<?php if ($flash): ?>
<div class="flash-bar <?= str_starts_with($flash,'⚠️') ? 'warn' : '' ?>" id="flashBar">
  <i class="bi bi-<?= str_starts_with($flash,'⚠️') ? 'exclamation-triangle' : 'check-circle-fill' ?>"></i>
  <?= htmlspecialchars($flash) ?>
  <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;color:inherit;cursor:pointer;font-size:.9rem;padding:0 0 0 8px;"><i class="bi bi-x"></i></button>
</div>
<?php endif; ?>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="modal-bg" id="delModal">
  <div class="modal-box">
    <div class="modal-ico">🔗</div>
    <div class="modal-title">Hapus Pencocokan?</div>
    <div class="modal-sub">Status barang dan laporan akan dikembalikan ke kondisi semula. Tindakan ini tidak bisa dibatalkan.</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="form_action" value="delete_match">
      <input type="hidden" name="match_id" id="delMatchId" value="">
      <div style="display:flex;gap:.7rem;justify-content:center;">
        <button type="button" class="btn-cl btn-ghost" onclick="closeDelModal()"><i class="bi bi-x-lg"></i> Batal</button>
        <button type="submit" class="btn-cl btn-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>

<!-- NAVBAR -->
<nav class="cl-navbar">
  <a href="index_petugas.php" class="cl-brand">
    <div class="cl-brand-icon">🚆</div>
    <div>
      <div class="cl-brand-name">Commuter<em>Link</em></div>
      <div class="cl-brand-sub">Lost &amp; Found</div>
    </div>
  </a>
  <div class="cl-nav-links">
    <a href="index_petugas.php"              class="cl-nav-link"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="laporan_kehilangan.php" class="cl-nav-link"><i class="bi bi-exclamation-circle"></i> Lap. Hilang</a>
    <a href="barang_temuan.php"      class="cl-nav-link"><i class="bi bi-box-seam"></i> Barang Temuan</a>
    <a href="pencocokan.php"         class="cl-nav-link active"><i class="bi bi-puzzle"></i> Pencocokan</a>
    <a href="serah_terima.php"       class="cl-nav-link"><i class="bi bi-file-earmark-arrow-down"></i> Serah Terima</a>
  </div>
  <div style="display:flex;align-items:center;gap:.6rem;">
    </a>
  </div>
</nav>

<!-- PAGE HEADER -->
<div class="page-header">
  <div class="ph-inner">
    <div class="bc">
      <a href="index_petugas.php"><i class="bi bi-house"></i></a>
      <i class="bi bi-chevron-right"></i>
      <span>Pencocokan Barang</span>
    </div>
    <div class="ph-title">
      <div class="ph-icon"><i class="bi bi-puzzle"></i></div>
      Pencocokan Barang
    </div>
    <div class="ph-sub">Sistem pencocokan otomatis &amp; manual antara barang temuan dan laporan kehilangan.</div>
  </div>
</div>

<!-- MAIN -->
<div class="main-wrap">

  <!-- STAT STRIP -->
  <div class="stat-strip fade-up">
    <?php
    $strips = [
      ['key'=>'all',                 'lbl'=>'Semua',              'dot'=>'#5A7A9E','href'=>'pencocokan.php'],
      ['key'=>'menunggu_verifikasi', 'lbl'=>'Menunggu Verifikasi','dot'=>'#F59E0B','href'=>'pencocokan.php?status=menunggu_verifikasi'],
      ['key'=>'diverifikasi',        'lbl'=>'Diverifikasi',       'dot'=>'#34D399','href'=>'pencocokan.php?status=diverifikasi'],
      ['key'=>'ditolak',             'lbl'=>'Ditolak',            'dot'=>'#F87171','href'=>'pencocokan.php?status=ditolak'],
    ];
    foreach ($strips as $s):
      $isActive = ($s['key']==='all' && !$filterStatus) || ($s['key']===$filterStatus);
    ?>
    <a href="<?= $s['href'] ?>" class="ss-item <?= $isActive?'active':'' ?>">
      <div>
        <div class="ss-num"><?= $counts[$s['key']] ?></div>
        <div class="ss-lbl"><?= $s['lbl'] ?></div>
      </div>
      <div class="ss-dot" style="background:<?= $s['dot'] ?>;"></div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- SARAN OTOMATIS -->
  <?php if (!empty($suggestions)): ?>
  <div class="cl-card fade-up d1 mb-4">
    <div class="cl-card-head">
      <div class="cl-card-title">
        <i class="bi bi-stars" style="color:var(--amber);"></i>
        Saran Pencocokan Otomatis
        <span class="count-pill"><?= count($suggestions) ?> kandidat</span>
      </div>
      <span style="font-size:.72rem;color:var(--text-3);">Berdasarkan nama, deskripsi, tanggal &amp; lokasi</span>
    </div>
    <div class="suggest-grid">
      <?php foreach ($suggestions as $s):
        $scoreClass = $s['score'] >= 70 ? 'high' : ($s['score'] >= 50 ? 'medium' : 'low');
      ?>
      <div class="suggest-card <?= $scoreClass ?>">
        <div class="score-header">
          <div>
            <span class="score-num <?= $scoreClass ?>"><?= $s['score'] ?>%</span>
            <span style="font-size:.7rem;color:var(--text-3);margin-left:4px;">Kecocokan</span>
          </div>
          <span class="st-badge <?= $scoreClass==='high'?'st-verified':($scoreClass==='medium'?'st-wait':'st-rejected') ?>">
            <?= $scoreClass==='high'?'Tinggi':($scoreClass==='medium'?'Sedang':'Rendah') ?>
          </span>
        </div>
        <div class="score-bar">
          <div class="score-bar-fill <?= $scoreClass ?>" style="width:<?= $s['score'] ?>%"></div>
        </div>
        <div class="match-pair">
          <div class="pair-box">
            <div class="pair-label" style="color:var(--success);">📦 Temuan</div>
            <div class="pair-name"><?= htmlspecialchars($s['barang']['nama_barang']) ?></div>
            <div class="pair-sub"><?= htmlspecialchars($s['barang']['kode_barang']) ?> · <?= htmlspecialchars($s['barang']['lokasi_ditemukan']) ?></div>
          </div>
          <div class="pair-arrow"><i class="bi bi-arrow-left-right"></i></div>
          <div class="pair-box">
            <div class="pair-label" style="color:var(--danger);">🔍 Hilang</div>
            <div class="pair-name"><?= htmlspecialchars($s['laporan']['nama_barang']) ?></div>
            <div class="pair-sub"><?= htmlspecialchars($s['laporan']['nama_pelapor'] ?? '—') ?></div>
          </div>
        </div>
        <div class="criteria-text">
          <i class="bi bi-info-circle"></i>
          <?= htmlspecialchars($s['criteria'] ?: 'Tidak ada kriteria spesifik') ?>
        </div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="form_action" value="create_match">
          <input type="hidden" name="barang_id"   value="<?= $s['barang']['id'] ?>">
          <input type="hidden" name="laporan_id"  value="<?= $s['laporan']['id'] ?>">
          <input type="hidden" name="match_score" value="<?= $s['score'] ?>">
          <input type="hidden" name="catatan"     value="Auto-suggested: <?= htmlspecialchars($s['criteria']) ?>">
          <button type="submit" class="btn-cl btn-amber w-100" style="justify-content:center;font-size:.78rem;padding:.5rem;">
            <i class="bi bi-link-45deg"></i> Konfirmasi Pencocokan Ini
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- RIWAYAT PENCOCOKAN -->
    <div class="col-lg-8">
      <div class="cl-toolbar fade-up d1">
        <form method="GET" class="cl-search">
          <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
          <i class="bi bi-search"></i>
          <input type="text" name="q" placeholder="Cari kode barang, laporan, nama pelapor…" value="<?= htmlspecialchars($search) ?>">
        </form>
        <?php if ($filterStatus): ?>
        <a href="pencocokan.php" class="btn-cl btn-ghost" style="font-size:.73rem;padding:.38rem .8rem;">
          <i class="bi bi-x"></i> Reset Filter
        </a>
        <?php endif; ?>
      </div>

      <div class="cl-card fade-up d2">
        <div class="cl-card-head">
          <div class="cl-card-title">
            <i class="bi bi-list-check"></i>
            Riwayat Pencocokan
            <span class="count-pill"><?= count($matchList) ?> data</span>
          </div>
        </div>

        <?php if (empty($matchList)): ?>
        <div class="empty-state">
          <span class="empty-icon">🔗</span>
          <div class="empty-title">Belum ada riwayat pencocokan</div>
          <div class="empty-sub"><?= $search || $filterStatus ? 'Tidak ada hasil untuk filter ini.' : 'Buat pencocokan baru menggunakan form di sebelah kanan.' ?></div>
        </div>
        <?php else: ?>
        <?php foreach ($matchList as $m):
          $st     = $statusMap[$m['status']] ?? ['label'=>$m['status'],'class'=>'st-wait','dot'=>'#5A7A9E'];
          // ✅ FIX: gunakan ?? 0 supaya tidak undefined array key warning
          $skor   = (int)($m['skor_kecocokan'] ?? 0);
          $sClass = $skor >= 70 ? 'msb-high' : ($skor >= 50 ? 'msb-medium' : 'msb-low');
          $isAuto = str_contains($m['catatan'] ?? '', 'Auto-suggested');
        ?>
        <div class="match-item">
          <div class="match-header">
            <div class="match-meta">
              <span class="st-badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
              <?php if ($skor > 0): /* ✅ FIX: cek $skor (sudah aman) bukan $m['skor_kecocokan'] */ ?>
              <span class="match-score-badge <?= $sClass ?>">
                <?= $skor ?>% Cocok
              </span>
              <?php endif; ?>
              <?php if ($isAuto): ?>
              <span style="font-size:.65rem;background:rgba(167,139,250,.1);color:var(--purple);padding:2px 7px;border-radius:99px;border:1px solid rgba(167,139,250,.2);">
                <i class="bi bi-stars"></i> Auto
              </span>
              <?php endif; ?>
            </div>
            <span class="match-time">
              <?= $m['created_at'] ? date('d/m/Y H:i', strtotime($m['created_at'])) : '—' ?>
              · <?= htmlspecialchars($m['nama_petugas'] ?? 'Petugas') ?>
            </span>
          </div>

          <div class="match-body">
            <div class="match-box found">
              <div class="match-box-label">📦 Barang Temuan</div>
              <div class="match-box-name"><?= htmlspecialchars($m['barang_nama']) ?></div>
              <div class="match-box-sub">
                <span class="code-tag"><?= htmlspecialchars($m['kode_barang']) ?></span>
                · <?= htmlspecialchars(mb_substr($m['barang_deskripsi'] ?? '', 0, 40)) . (mb_strlen($m['barang_deskripsi'] ?? '') > 40 ? '…' : '') ?>
              </div>
            </div>
            <div class="pair-arrow text-center"><i class="bi bi-arrow-left-right" style="color:var(--purple);font-size:1.2rem;"></i></div>
            <div class="match-box lost">
              <div class="match-box-label">🔍 Laporan Hilang</div>
              <div class="match-box-name"><?= htmlspecialchars($m['laporan_nama']) ?></div>
              <div class="match-box-sub">
                <span class="code-tag"><?= htmlspecialchars($m['no_laporan']) ?></span>
                · <?= htmlspecialchars($m['nama_pelapor'] ?? '—') ?>
              </div>
            </div>
          </div>

          <?php if (!empty($m['catatan']) && !$isAuto): ?>
          <div style="font-size:.74rem;color:var(--text-3);margin-bottom:.75rem;display:flex;align-items:flex-start;gap:5px;">
            <i class="bi bi-chat-text" style="flex-shrink:0;margin-top:2px;"></i>
            <?= htmlspecialchars($m['catatan']) ?>
          </div>
          <?php endif; ?>

          <div class="match-actions">
            <?php if ($m['status'] === 'menunggu_verifikasi'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="form_action" value="update_status">
              <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
              <input type="hidden" name="status" value="diverifikasi">
              <button type="submit" class="btn-cl btn-success" style="font-size:.75rem;padding:.4rem .85rem;">
                <i class="bi bi-check-circle"></i> Verifikasi
              </button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="form_action" value="update_status">
              <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
              <input type="hidden" name="status" value="ditolak">
              <button type="submit" class="btn-cl btn-danger" style="font-size:.75rem;padding:.4rem .85rem;">
                <i class="bi bi-x-circle"></i> Tolak
              </button>
            </form>
            <?php elseif ($m['status'] === 'diverifikasi'): ?>
            <a href="serah_terima.php?pencocokan_id=<?= $m['id'] ?>" class="btn-cl btn-purple" style="font-size:.75rem;padding:.4rem .85rem;">
              <i class="bi bi-box-arrow-right"></i> Proses Serah Terima
            </a>
            <?php elseif ($m['status'] === 'ditolak'): ?>
            <span style="font-size:.74rem;color:var(--text-3);"><i class="bi bi-info-circle"></i> Status barang &amp; laporan telah dikembalikan</span>
            <?php endif; ?>
            <button class="btn-cl btn-ghost" style="font-size:.75rem;padding:.4rem .85rem;margin-left:auto;"
              onclick="openDelModal(<?= $m['id'] ?>)">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- FORM PENCOCOKAN MANUAL -->
    <div class="col-lg-4">
      <div class="form-card fade-up d1">
        <div class="form-card-head">
          <div class="fch-icon"><i class="bi bi-sliders"></i></div>
          <div class="fch-title">Pencocokan Manual</div>
        </div>
        <div class="form-body">
          <form method="POST" id="manualForm" autocomplete="off">
            <input type="hidden" name="csrf_token"  value="<?= $csrf ?>">
            <input type="hidden" name="form_action" value="create_match">
            <input type="hidden" name="match_score" id="scoreInput" value="0">

            <div class="mb-3">
              <label class="cl-label">Barang Temuan <span class="req">*</span></label>
              <select name="barang_id" class="cl-select" id="barangSel" required onchange="calcScore()">
                <option value="">-- Pilih Barang Temuan --</option>
                <?php foreach ($barangList as $b): ?>
                <option value="<?= $b['id'] ?>"
                  <?= $preBarangId === $b['id'] ? 'selected' : '' ?>
                  data-nama="<?= htmlspecialchars(strtolower($b['nama_barang'])) ?>"
                  data-desk="<?= htmlspecialchars(strtolower($b['deskripsi'] ?? '')) ?>"
                  data-waktu="<?= $b['waktu_ditemukan'] ?>"
                  data-lokasi="<?= htmlspecialchars(strtolower($b['lokasi_ditemukan'] ?? '')) ?>">
                  [<?= htmlspecialchars($b['kode_barang']) ?>] <?= htmlspecialchars($b['nama_barang']) ?>
                  – <?= htmlspecialchars($b['lokasi_ditemukan']) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <?php if (empty($barangList)): ?>
              <div style="font-size:.72rem;color:var(--amber);margin-top:5px;"><i class="bi bi-info-circle"></i> Tidak ada barang dengan status 'Tersimpan'</div>
              <?php endif; ?>
            </div>

            <div class="mb-3">
              <label class="cl-label">Laporan Kehilangan <span class="req">*</span></label>
              <select name="laporan_id" class="cl-select" id="laporanSel" required onchange="calcScore()">
                <option value="">-- Pilih Laporan --</option>
                <?php foreach ($laporanList as $l): ?>
                <option value="<?= $l['id'] ?>"
                  <?= $preLaporanId === $l['id'] ? 'selected' : '' ?>
                  data-nama="<?= htmlspecialchars(strtolower($l['nama_barang'])) ?>"
                  data-desk="<?= htmlspecialchars(strtolower($l['deskripsi'] ?? '')) ?>"
                  data-waktu="<?= $l['waktu_hilang'] ?>"
                  data-lokasi="<?= htmlspecialchars(strtolower($l['lokasi_hilang'] ?? '')) ?>">
                  [<?= htmlspecialchars($l['no_laporan']) ?>] <?= htmlspecialchars($l['nama_barang']) ?>
                  – <?= htmlspecialchars($l['nama_pelapor'] ?? '') ?>
                </option>
                <?php endforeach; ?>
              </select>
              <?php if (empty($laporanList)): ?>
              <div style="font-size:.72rem;color:var(--amber);margin-top:5px;"><i class="bi bi-info-circle"></i> Tidak ada laporan dengan status 'Diproses'</div>
              <?php endif; ?>
            </div>

            <!-- SCORE PREVIEW -->
            <div class="score-preview mb-3" id="scorePreview">
              <div class="sp-header">
                <span class="sp-label">Estimasi Kecocokan</span>
                <span class="sp-score" id="spScore">0%</span>
              </div>
              <div class="sp-bar">
                <div class="sp-bar-fill" id="spBar" style="width:0%"></div>
              </div>
              <div class="sp-criteria" id="spCriteria">—</div>
            </div>

            <div class="mb-3">
              <label class="cl-label">Catatan Petugas</label>
              <textarea name="catatan" class="cl-textarea" placeholder="Alasan pencocokan manual, ciri yang dikonfirmasi, dll."></textarea>
            </div>

            <button type="submit" class="btn-cl btn-amber w-100" style="justify-content:center;">
              <i class="bi bi-link-45deg"></i> Buat Pencocokan
            </button>
          </form>

          <div style="margin-top:1rem;padding:.75rem;background:var(--card-2);border:1px solid var(--border);border-radius:8px;font-size:.72rem;color:var(--text-3);line-height:1.6;">
            <div style="font-weight:700;color:var(--text-2);margin-bottom:.35rem;"><i class="bi bi-info-circle" style="color:var(--purple);"></i> Alur setelah pencocokan</div>
            <div>1. Status barang → <strong style="color:var(--amber);">Dicocokkan</strong></div>
            <div>2. Status laporan → <strong style="color:var(--blue-lt);">Ditemukan</strong></div>
            <div>3. Petugas klik <strong style="color:var(--success);">Verifikasi</strong> setelah konfirmasi pemilik</div>
            <div>4. Lanjut ke proses <strong style="color:var(--purple);">Serah Terima</strong></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const fb = document.getElementById('flashBar');
if (fb) setTimeout(()=>{ fb.style.transition='opacity .4s'; fb.style.opacity='0'; setTimeout(()=>fb.remove(),400); }, 4500);

function openDelModal(id) {
  document.getElementById('delMatchId').value = id;
  document.getElementById('delModal').classList.add('show');
}
function closeDelModal() {
  document.getElementById('delModal').classList.remove('show');
}
document.getElementById('delModal').addEventListener('click', e => {
  if (e.target === document.getElementById('delModal')) closeDelModal();
});

function calcScore() {
  const bSel = document.getElementById('barangSel');
  const lSel = document.getElementById('laporanSel');
  const prev = document.getElementById('scorePreview');
  if (!bSel.value || !lSel.value) { prev.style.display='none'; return; }

  const bo = bSel.options[bSel.selectedIndex];
  const lo = lSel.options[lSel.selectedIndex];
  let score=0, criteria=[];

  const bNama = (bo.dataset.nama||'').split(' ').filter(w=>w.length>2);
  const lNama = (lo.dataset.nama||'').split(' ').filter(w=>w.length>2);
  const namaCommon = bNama.filter(w=>lNama.includes(w)).length;
  if (namaCommon>=2)       { score+=40; criteria.push('Nama sangat mirip (+40)'); }
  else if (namaCommon===1) { score+=20; criteria.push('Nama cukup mirip (+20)'); }

  const bDesk = (bo.dataset.desk||'').split(' ').filter(w=>w.length>3);
  const lDesk = (lo.dataset.desk||'').split(' ').filter(w=>w.length>3);
  const deskCommon = bDesk.filter(w=>lDesk.includes(w)).length;
  if (deskCommon>=3)      { score+=20; criteria.push('Deskripsi banyak kesamaan (+20)'); }
  else if (deskCommon>=1) { score+=10; criteria.push('Deskripsi ada kesamaan (+10)'); }

  if (bo.dataset.waktu && lo.dataset.waktu) {
    const diff = Math.abs(new Date(bo.dataset.waktu) - new Date(lo.dataset.waktu)) / (1000*60*60*24);
    if (diff<=1)      { score+=25; criteria.push('Tanggal ≤1 hari (+25)'); }
    else if (diff<=3) { score+=15; criteria.push('Tanggal ≤3 hari (+15)'); }
  }

  const bLok = (bo.dataset.lokasi||'').split(' ').filter(w=>w.length>3);
  const lLok = (lo.dataset.lokasi||'').split(' ').filter(w=>w.length>3);
  if (bLok.filter(w=>lLok.includes(w)).length>0) { score+=15; criteria.push('Lokasi berdekatan (+15)'); }

  score = Math.min(score, 100);
  prev.style.display='block';
  document.getElementById('spScore').textContent = score+'%';
  document.getElementById('spScore').style.color = score>=70?'var(--success)':score>=50?'var(--amber)':'var(--danger)';
  const bar = document.getElementById('spBar');
  bar.style.width = score+'%';
  bar.style.background = score>=70?'var(--success)':score>=50?'var(--amber)':'var(--danger)';
  document.getElementById('spCriteria').textContent = criteria.join(' · ') || 'Tidak ada kriteria yang cocok terdeteksi';
  document.getElementById('scoreInput').value = score;
}

window.addEventListener('load', calcScore);
</script>
</body>
</html>