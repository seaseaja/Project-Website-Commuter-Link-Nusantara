<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo  = getDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$user = getCurrentUser();

$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);
$error  = '';

$categories = ['Tas','Dompet','Ponsel','Laptop','Tablet','Kamera','Perhiasan',
                'Jam Tangan','Kacamata','Dokumen','Pakaian','Sepatu','Kunci',
                'Uang','Aksesoris','Lainnya'];
$idTypes    = ['KTP','SIM','Paspor','Kartu Pelajar','Kartu Mahasiswa','Kartu Pegawai'];

$statusMap = [
    'diproses'  => ['label' => 'Diproses',  'class' => 'st-open'],
    'ditemukan' => ['label' => 'Ditemukan', 'class' => 'st-matched'],
    'selesai'   => ['label' => 'Selesai',   'class' => 'st-resolved'],
    'ditutup'   => ['label' => 'Ditutup',   'class' => 'st-closed'],
];
$catIcons = [
    'Tas'=>'👜','Dompet'=>'👛','Ponsel'=>'📱','Laptop'=>'💻','Tablet'=>'📟',
    'Kamera'=>'📷','Perhiasan'=>'💍','Jam Tangan'=>'⌚','Kacamata'=>'👓',
    'Dokumen'=>'📄','Pakaian'=>'👕','Sepatu'=>'👟','Kunci'=>'🔑',
    'Uang'=>'💰','Aksesoris'=>'💎','Lainnya'=>'📦',
];

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token_lk'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['csrf_token_lk'])) {
    $_SESSION['csrf_token_lk'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token_lk'];

function checkCSRF(string $token): bool {
    return isset($_SESSION['csrf_token_lk']) && hash_equals($_SESSION['csrf_token_lk'], $token);
}


function setFlashMsg(string $msg): void { $_SESSION['_flash_lk'] = $msg; }
function getFlashMsg(): string {
    $m = $_SESSION['_flash_lk'] ?? '';
    unset($_SESSION['_flash_lk']);
    return $m;
}

if ($action === 'del' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE laporan_kehilangan SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$user['id'], $id]);
    setFlashMsg('Laporan kehilangan berhasil dihapus.');
    header('Location: laporan_kehilangan.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } else {
        $postAction   = $_POST['form_action'] ?? '';
        $namaBarang   = trim($_POST['nama_barang']       ?? '');
        $kategori     = trim($_POST['kategori']          ?? '');
        $deskripsi    = trim($_POST['deskripsi']         ?? '');
        $warna        = trim($_POST['warna']             ?? '');
        $merek        = trim($_POST['merek']             ?? '');
        $lokasiHilang = trim($_POST['lokasi_hilang']     ?? '');
        $noKrl        = trim($_POST['no_krl']            ?? '');
        $waktuHilang  = trim($_POST['waktu_hilang']      ?? '');
        $catatan      = trim($_POST['catatan']           ?? '');
        $status       = $_POST['status']                 ?? 'diproses';
        $idPelJenis   = trim($_POST['id_pelapor_jenis']  ?? '');
        $idPelNo      = trim($_POST['id_pelapor_no']     ?? '');
        $userId       = $user['id'];

        if (empty($namaBarang) || empty($lokasiHilang) || empty($waktuHilang)) {
            $error = 'Field wajib belum lengkap: Nama Barang, Lokasi Terakhir, dan Waktu Hilang harus diisi.';
        } else {
            $waktuMysql = date('Y-m-d H:i:s', strtotime($waktuHilang));

            try {
                if ($postAction === 'create') {
                    $noLaporan = 'LPR-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

                    $stmt = $pdo->prepare("
                        INSERT INTO laporan_kehilangan
                            (no_laporan, user_id, nama_barang, kategori, deskripsi, warna, merek,
                             lokasi_hilang, no_krl, waktu_hilang, catatan, status,
                             ditangani_oleh, id_pelapor_jenis, id_pelapor_no,
                             created_at, created_by)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ");
                    $stmt->execute([
                        $noLaporan, $userId, $namaBarang, $kategori, $deskripsi, $warna, $merek,
                        $lokasiHilang, $noKrl, $waktuMysql, $catatan, $status,
                        $user['id'], $idPelJenis, $idPelNo, $user['id'],
                    ]);

                    setFlashMsg("Laporan [$noLaporan] berhasil dicatat.");
                    header('Location: laporan_kehilangan.php');
                    exit;

                } elseif ($postAction === 'edit' && $id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE laporan_kehilangan SET
                            nama_barang      = ?,
                            kategori         = ?,
                            deskripsi        = ?,
                            warna            = ?,
                            merek            = ?,
                            lokasi_hilang    = ?,
                            no_krl           = ?,
                            waktu_hilang     = ?,
                            catatan          = ?,
                            status           = ?,
                            id_pelapor_jenis = ?,
                            id_pelapor_no    = ?,
                            updated_at       = NOW(),
                            updated_by       = ?
                        WHERE id = ? AND deleted_at IS NULL
                    ");
                    $stmt->execute([
                        $namaBarang, $kategori, $deskripsi, $warna, $merek,
                        $lokasiHilang, $noKrl, $waktuMysql, $catatan, $status,
                        $idPelJenis, $idPelNo, $user['id'], $id,
                    ]);

                    setFlashMsg('Data laporan berhasil diperbarui.');
                    header('Location: laporan_kehilangan.php');
                    exit;
                }

            } catch (PDOException $e) {
                $error = 'Error database: ' . $e->getMessage();
            }
        }
    }
}

$editItem = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM laporan_kehilangan WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$id]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editItem) { header('Location: laporan_kehilangan.php'); exit; }
}

$search   = trim($_GET['q']      ?? '');
$filterSt = trim($_GET['status'] ?? '');

$where  = "lk.deleted_at IS NULL";
$params = [];
if ($search) {
    $like = '%' . $search . '%';
    $where .= " AND (lk.nama_barang LIKE ? OR lk.no_laporan LIKE ? OR lk.lokasi_hilang LIKE ? OR u.nama LIKE ?)";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($filterSt) {
    $where .= " AND lk.status = ?";
    $params[] = $filterSt;
}

$reports = [];
if ($action === 'list') {
    $stmt = $pdo->prepare("
        SELECT lk.*, u.nama AS nama_pelapor, u.email AS email_pelapor
        FROM laporan_kehilangan lk
        LEFT JOIN users u ON lk.user_id = u.id
        WHERE $where
        ORDER BY lk.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$counts = ['all' => 0, 'diproses' => 0, 'ditemukan' => 0, 'selesai' => 0, 'ditutup' => 0];
$cStmt  = $pdo->query("SELECT status, COUNT(*) as c FROM laporan_kehilangan WHERE deleted_at IS NULL GROUP BY status");
foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['c'];
    $counts['all'] += (int)$row['c'];
}

$flash = getFlashMsg();
$pageTitle = match($action) {
    'add'   => 'Tambah Laporan',
    'edit'  => 'Edit Laporan',
    default => 'Laporan Kehilangan',
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> – CommuterLink</title>
  <link rel="icon" href="uploads/favicon.png" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,700;0,900;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg:        #0A1628;
      --bg-2:      #0F1F38;
      --navy:      #0D1B2E;
      --navy-2:    #152640;
      --navy-3:    #1E3357;
      --card:      #132035;
      --card-2:    #192A45;
      --card-3:    #1F3254;
      --amber:     #F59E0B;
      --amber-lt:  #FCD34D;
      --amber-dim: rgba(245,158,11,0.12);
      --blue-lt:   #60A5FA;
      --text:      #EBF4FF;
      --text-2:    #A8BDD6;
      --text-3:    #5A7A9E;
      --border:    rgba(255,255,255,0.07);
      --border-2:  rgba(255,255,255,0.13);
      --danger:    #F87171;
      --success:   #34D399;
      --radius:    14px;
    }
    *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }

    .cl-navbar {
      position:sticky; top:0; z-index:300;
      background:rgba(10,22,40,0.96); backdrop-filter:blur(20px);
      border-bottom:1px solid var(--border);
      padding:0 1.5rem; height:60px;
      display:flex; align-items:center; justify-content:space-between;
    }
    .cl-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
    .cl-brand-icon {
      width:32px; height:32px;
      background:linear-gradient(135deg,var(--amber),var(--amber-lt));
      border-radius:9px; display:flex; align-items:center; justify-content:center;
      font-size:.95rem; box-shadow:0 4px 12px rgba(245,158,11,.35);
    }
    .cl-brand-name { font-family:'Fraunces',serif; font-size:1rem; font-weight:700; color:var(--text); line-height:1; }
    .cl-brand-name em { font-style:italic; color:var(--amber); }
    .cl-brand-sub { font-size:.58rem; color:var(--text-3); text-transform:uppercase; letter-spacing:.1em; }
    .cl-nav-links { display:flex; align-items:center; gap:.2rem; }
    .cl-nav-link {
      display:flex; align-items:center; gap:6px; padding:.45rem .8rem;
      border-radius:8px; font-size:.79rem; font-weight:600;
      color:var(--text-2); text-decoration:none; transition:all .2s;
    }
    .cl-nav-link:hover  { background:var(--card-2); color:var(--text); }
    .cl-nav-link.active { background:var(--amber-dim); color:var(--amber); }
    .cl-nav-link i { font-size:.88rem; }
    .cl-avatar-chip {
      display:flex; align-items:center; gap:8px;
      padding:4px 12px 4px 4px; border:1px solid var(--border);
      border-radius:99px; background:var(--card); text-decoration:none; transition:all .2s;
    }
    .cl-avatar-chip:hover { border-color:var(--amber); }
    .cl-avt {
      width:28px; height:28px;
      background:linear-gradient(135deg,var(--amber),var(--amber-lt));
      border-radius:50%; display:flex; align-items:center; justify-content:center;
      font-size:.7rem; font-weight:700; color:var(--navy);
    }
    .cl-avt-name { font-size:.78rem; font-weight:600; color:var(--text); }

    .page-header {
      background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%);
      border-bottom:1px solid var(--border); padding:1.6rem 2rem;
      position:relative; overflow:hidden;
    }
    .page-header::after {
      content:''; position:absolute; right:-60px; top:-60px;
      width:260px; height:260px;
      background:radial-gradient(circle,rgba(245,158,11,.13) 0%,transparent 70%);
      border-radius:50%; pointer-events:none;
    }
    .ph-inner { position:relative; z-index:1; }
    .bc { display:flex; align-items:center; gap:5px; margin-bottom:.65rem; }
    .bc a,.bc span { font-size:.73rem; color:var(--text-3); text-decoration:none; }
    .bc a:hover { color:var(--amber); }
    .bc i { font-size:.6rem; color:var(--text-3); }
    .ph-title {
      font-family:'Fraunces',serif; font-size:1.55rem; font-weight:900;
      color:var(--text); display:flex; align-items:center; gap:10px; margin-bottom:.25rem;
    }
    .ph-icon {
      width:36px; height:36px; background:var(--amber-dim);
      border:1px solid rgba(245,158,11,.25); border-radius:9px;
      display:flex; align-items:center; justify-content:center;
      color:var(--amber); font-size:1rem;
    }
    .ph-sub { font-size:.8rem; color:var(--text-3); }

    .main-wrap { padding:1.75rem 2rem 4rem; }
    @media(max-width:767px){ .main-wrap{padding:1.25rem 1rem 3rem;} .page-header{padding:1.25rem 1rem;} .cl-navbar{padding:0 1rem;} }

    .stat-strip { display:grid; grid-template-columns:repeat(5,1fr); gap:.7rem; margin-bottom:1.4rem; }
    @media(max-width:991px){.stat-strip{grid-template-columns:repeat(3,1fr);}}
    @media(max-width:575px){.stat-strip{grid-template-columns:repeat(2,1fr);}}
    .ss-item {
      background:var(--card); border:1px solid var(--border); border-radius:12px;
      padding:1rem 1.1rem; display:flex; align-items:center; gap:10px;
      text-decoration:none; transition:all .2s;
    }
    .ss-item:hover { border-color:var(--border-2); transform:translateY(-2px); }
    .ss-item.active { border-color:var(--amber); background:var(--amber-dim); }
    .ss-num { font-family:'Fraunces',serif; font-size:1.55rem; font-weight:700; color:var(--text); line-height:1; }
    .ss-lbl { font-size:.7rem; color:var(--text-3); margin-top:2px; font-weight:500; }
    .ss-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-left:auto; }

    .cl-toolbar { display:flex; align-items:center; gap:.75rem; margin-bottom:1.2rem; flex-wrap:wrap; }
    .cl-search { position:relative; flex:1; min-width:200px; max-width:360px; }
    .cl-search i { position:absolute; left:.9rem; top:50%; transform:translateY(-50%); color:var(--text-3); font-size:.82rem; pointer-events:none; }
    .cl-search input {
      width:100%; padding:.58rem .9rem .58rem 2.35rem;
      background:var(--card); border:1px solid var(--border); border-radius:10px;
      color:var(--text); font-family:'DM Sans',sans-serif; font-size:.82rem;
      outline:none; transition:border-color .2s;
    }
    .cl-search input:focus { border-color:var(--amber); }
    .cl-search input::placeholder { color:var(--text-3); }

    .btn-cl {
      display:inline-flex; align-items:center; gap:6px;
      padding:.58rem 1.1rem; border-radius:10px; border:none; cursor:pointer;
      font-family:'DM Sans',sans-serif; font-size:.81rem; font-weight:700;
      text-decoration:none; transition:all .2s; white-space:nowrap;
    }
    .btn-amber { background:var(--amber); color:var(--navy); box-shadow:0 4px 14px rgba(245,158,11,.25); }
    .btn-amber:hover { background:var(--amber-lt); transform:translateY(-1px); color:var(--navy); box-shadow:0 6px 20px rgba(245,158,11,.38); }
    .btn-ghost { background:transparent; color:var(--text-2); border:1px solid var(--border-2); }
    .btn-ghost:hover { border-color:var(--text-2); color:var(--text); background:var(--card-2); }
    .btn-danger-solid { background:var(--danger); color:#fff; }
    .btn-danger-solid:hover { opacity:.88; color:#fff; }

    .btn-icon {
      width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center;
      border-radius:8px; border:1px solid var(--border); background:var(--card-2);
      color:var(--text-2); font-size:.82rem; text-decoration:none; cursor:pointer; transition:all .2s;
    }
    .btn-icon.edit:hover  { background:rgba(96,165,250,.12); color:var(--blue-lt); border-color:rgba(96,165,250,.25); }
    .btn-icon.match:hover { background:var(--amber-dim); color:var(--amber); border-color:rgba(245,158,11,.3); }
    .btn-icon.del:hover   { background:rgba(248,113,113,.1); color:var(--danger); border-color:rgba(248,113,113,.25); }

    .cl-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .cl-card-head {
      display:flex; align-items:center; justify-content:space-between;
      padding:.95rem 1.4rem; border-bottom:1px solid var(--border);
    }
    .cl-card-title { font-size:.86rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
    .cl-card-title i { color:var(--amber); }
    .count-pill { font-size:.68rem; font-weight:700; background:var(--amber-dim); color:var(--amber); padding:2px 8px; border-radius:99px; }

    .cl-table { width:100%; border-collapse:collapse; }
    .cl-table thead th {
      font-size:.65rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
      color:var(--text-3); padding:.7rem 1rem; border-bottom:1px solid var(--border);
      background:var(--card-2); white-space:nowrap;
    }
    .cl-table tbody td {
      padding:.82rem 1rem; border-bottom:1px solid var(--border);
      font-size:.81rem; color:var(--text-2); vertical-align:middle;
    }
    .cl-table tbody tr:last-child td { border-bottom:none; }
    .cl-table tbody tr:hover td { background:rgba(255,255,255,.02); }
    .row-num { font-size:.72rem; color:var(--text-3); }
    .code-tag {
      font-family:'Courier New',monospace; font-size:.72rem;
      background:rgba(245,158,11,.1); color:var(--amber);
      padding:3px 7px; border-radius:5px; border:1px solid rgba(245,158,11,.2);
    }
    .item-main { font-weight:700; color:var(--text); font-size:.84rem; }
    .item-sub  { font-size:.7rem; color:var(--text-3); margin-top:2px; }
    .rname  { font-weight:600; color:var(--text); font-size:.82rem; }
    .rphone { font-size:.7rem; color:var(--text-3); margin-top:1px; }

    .st-badge {
      display:inline-flex; align-items:center; gap:5px;
      font-size:.67rem; font-weight:700; padding:3px 9px; border-radius:99px;
    }
    .st-badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .st-open     { background:rgba(245,158,11,.12); color:var(--amber); }
    .st-matched  { background:rgba(96,165,250,.12);  color:var(--blue-lt); }
    .st-resolved { background:rgba(52,211,153,.12);  color:var(--success); }
    .st-closed   { background:rgba(255,255,255,.07); color:var(--text-3); }

    .empty-state { text-align:center; padding:4rem 2rem; }
    .empty-icon  { font-size:3.2rem; opacity:.22; margin-bottom:1rem; display:block; }
    .empty-title { font-size:.92rem; font-weight:700; color:var(--text-2); margin-bottom:.35rem; }
    .empty-sub   { font-size:.78rem; color:var(--text-3); margin-bottom:1.2rem; }

    .form-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .form-card-head {
      display:flex; align-items:center; gap:10px;
      padding:1rem 1.5rem; border-bottom:1px solid var(--border); background:var(--card-2);
    }
    .fch-icon {
      width:30px; height:30px; background:var(--amber-dim);
      border:1px solid rgba(245,158,11,.2); border-radius:8px;
      display:flex; align-items:center; justify-content:center; color:var(--amber); font-size:.88rem;
    }
    .fch-title { font-size:.86rem; font-weight:700; color:var(--text); }
    .form-card-body { padding:1.5rem; }

    .sec-label {
      font-size:.63rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase;
      color:var(--text-3); display:flex; align-items:center; gap:8px; margin-bottom:.85rem;
    }
    .sec-label::after { content:''; flex:1; height:1px; background:var(--border); }

    .cl-label { display:block; font-size:.77rem; font-weight:600; color:var(--text-2); margin-bottom:5px; }
    .cl-label .req { color:var(--amber); }

    .cl-input,.cl-select,.cl-textarea {
      width:100%; padding:.66rem .92rem;
      background:var(--bg-2); border:1.5px solid var(--border);
      border-radius:10px; color:var(--text);
      font-family:'DM Sans',sans-serif; font-size:.83rem;
      outline:none; transition:border-color .2s,box-shadow .2s;
    }
    .cl-input:focus,.cl-select:focus,.cl-textarea:focus {
      border-color:var(--amber); box-shadow:0 0 0 3px rgba(245,158,11,.12); background:var(--card-2);
    }
    .cl-input::placeholder,.cl-textarea::placeholder { color:var(--text-3); }
    .cl-select {
      appearance:none; cursor:pointer;
      background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%235A7A9E' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
      background-repeat:no-repeat; background-position:right .9rem center;
    }
    .cl-select option { background:#0D1B2E; }
    .cl-textarea { resize:vertical; min-height:82px; }

    .cl-alert {
      display:flex; align-items:flex-start; gap:9px;
      padding:.82rem 1rem; border-radius:10px;
      font-size:.81rem; font-weight:600; margin-bottom:1.2rem;
    }
    .cl-alert-danger  { background:rgba(248,113,113,.1); color:var(--danger); border:1px solid rgba(248,113,113,.2); }
    .cl-alert-success { background:rgba(52,211,153,.1);  color:var(--success); border:1px solid rgba(52,211,153,.2); }

    .sb-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:1.1rem; }
    .sb-head { display:flex; align-items:center; gap:8px; padding:.88rem 1.2rem; border-bottom:1px solid var(--border); font-size:.81rem; font-weight:700; color:var(--text); }
    .sb-head i { color:var(--amber); }
    .sb-body { padding:1.1rem 1.2rem; }

    .step-r { display:flex; align-items:flex-start; gap:11px; margin-bottom:1rem; }
    .step-r:last-child { margin-bottom:0; }
    .step-n {
      width:25px; height:25px; border-radius:50%; flex-shrink:0; margin-top:1px;
      display:flex; align-items:center; justify-content:center; font-size:.68rem; font-weight:700;
    }
    .step-n.done { background:var(--success); color:var(--navy); }
    .step-n.now  { background:var(--amber);   color:var(--navy); }
    .step-n.wait { background:var(--card-3);  color:var(--text-3); border:1px solid var(--border-2); }
    .step-t { font-size:.81rem; font-weight:700; color:var(--text); }
    .step-d { font-size:.7rem;  color:var(--text-3); margin-top:2px; line-height:1.5; }

    .tip-i { display:flex; align-items:flex-start; gap:8px; margin-bottom:.7rem; }
    .tip-i:last-child { margin-bottom:0; }
    .tip-dot { width:5px; height:5px; border-radius:50%; background:var(--amber); flex-shrink:0; margin-top:6px; }
    .tip-t { font-size:.77rem; color:var(--text-2); line-height:1.55; }
    .tip-t strong { color:var(--amber-lt); }

    .flash-bar {
      position:fixed; bottom:1.5rem; right:1.5rem; z-index:9000;
      background:var(--card-3); border:1px solid rgba(52,211,153,.3);
      color:var(--success); padding:.82rem 1.15rem; border-radius:12px;
      font-size:.81rem; font-weight:600;
      display:flex; align-items:center; gap:8px;
      box-shadow:0 8px 30px rgba(0,0,0,.4); max-width:380px;
      animation:slideUp .3s ease;
    }
    @keyframes slideUp { from{transform:translateY(20px);opacity:0;} to{transform:translateY(0);opacity:1;} }

    .modal-bg {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.62); backdrop-filter:blur(8px);
      z-index:9999; align-items:center; justify-content:center;
    }
    .modal-bg.show { display:flex; }
    .modal-box {
      background:var(--card); border:1px solid var(--border-2);
      border-radius:18px; padding:2rem; max-width:350px; width:90%;
      text-align:center; animation:popIn .25s ease;
    }
    @keyframes popIn { from{transform:scale(.88);opacity:0;} to{transform:scale(1);opacity:1;} }
    .modal-ico   { font-size:2.5rem; margin-bottom:.85rem; }
    .modal-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:700; color:var(--text); margin-bottom:.4rem; }
    .modal-sub   { font-size:.78rem; color:var(--text-3); margin-bottom:1.4rem; line-height:1.55; }

    .fade-up { opacity:0; transform:translateY(14px); animation:fadeUp .4s ease forwards; }
    @keyframes fadeUp { to{opacity:1;transform:translateY(0);} }
    .d1{animation-delay:.05s;} .d2{animation-delay:.1s;} .d3{animation-delay:.15s;}

    @media(max-width:991px){.cl-nav-links{display:none;}}
  </style>
</head>
<body>

<?php if ($flash): ?>
<div class="flash-bar" id="flashBar">
  <i class="bi bi-check-circle-fill"></i>
  <?= htmlspecialchars($flash) ?>
  <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;color:inherit;cursor:pointer;padding:0 0 0 8px;font-size:.9rem;"><i class="bi bi-x"></i></button>
</div>
<?php endif; ?>

<!-- MODAL HAPUS -->
<div class="modal-bg" id="deleteModal">
  <div class="modal-box">
    <div class="modal-ico">🗑️</div>
    <div class="modal-title">Hapus Laporan?</div>
    <div class="modal-sub">Data laporan akan dihapus dari sistem. Tindakan ini tidak bisa dibatalkan.</div>
    <div style="display:flex;gap:.7rem;justify-content:center;">
      <button class="btn-cl btn-ghost" onclick="closeModal()"><i class="bi bi-x-lg"></i> Batal</button>
      <a id="delConfirmBtn" href="#" class="btn-cl btn-danger-solid"><i class="bi bi-trash"></i> Ya, Hapus</a>
    </div>
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
    <a href="laporan_kehilangan.php" class="cl-nav-link active"><i class="bi bi-exclamation-circle"></i> Lap. Hilang</a>
    <a href="barang_temuan.php"      class="cl-nav-link"><i class="bi bi-box-seam"></i> Barang Temuan</a>
    <a href="pencocokan.php"         class="cl-nav-link"><i class="bi bi-puzzle"></i> Pencocokan</a>
    <a href="serah_terima.php" class="cl-nav-link"><i class="bi bi-file-earmark-arrow-down"></i> Serah Terima</a>
  </div>
      <div class="topbar-right">

  </a>
</nav>

<!-- PAGE HEADER -->
<div class="page-header">
  <div class="ph-inner">
    <div class="bc">
      <a href="index_petugas.php"><i class="bi bi-house"></i></a>
      <i class="bi bi-chevron-right"></i>
      <?php if ($action !== 'list'): ?>
        <a href="laporan_kehilangan.php">Laporan Kehilangan</a>
        <i class="bi bi-chevron-right"></i>
        <span><?= $action === 'add' ? 'Tambah' : 'Edit' ?></span>
      <?php else: ?>
        <span>Laporan Kehilangan</span>
      <?php endif; ?>
    </div>
    <div class="ph-title">
      <div class="ph-icon">
        <i class="bi bi-<?= $action === 'add' ? 'plus-lg' : ($action === 'edit' ? 'pencil' : 'exclamation-circle') ?>"></i>
      </div>
      <?= htmlspecialchars($pageTitle) ?>
    </div>
    <div class="ph-sub">
      <?= $action === 'list'
          ? 'Pencatatan &amp; pengelolaan laporan kehilangan barang penumpang KRL.'
          : 'Isi formulir lengkap untuk mempermudah proses pencocokan barang.' ?>
    </div>
  </div>
</div>

<!-- MAIN -->
<div class="main-wrap">

<?php if ($action === 'list'): ?>

  <!-- STAT STRIP -->
  <div class="stat-strip fade-up">
    <?php
    $strips = [
      ['key'=>'all',       'lbl'=>'Semua',    'dot'=>'#5A7A9E', 'href'=>'laporan_kehilangan.php'],
      ['key'=>'diproses',  'lbl'=>'Diproses', 'dot'=>'#F59E0B', 'href'=>'laporan_kehilangan.php?status=diproses'],
      ['key'=>'ditemukan', 'lbl'=>'Ditemukan','dot'=>'#60A5FA', 'href'=>'laporan_kehilangan.php?status=ditemukan'],
      ['key'=>'selesai',   'lbl'=>'Selesai',  'dot'=>'#34D399', 'href'=>'laporan_kehilangan.php?status=selesai'],
      ['key'=>'ditutup',   'lbl'=>'Ditutup',  'dot'=>'#5A7A9E', 'href'=>'laporan_kehilangan.php?status=ditutup'],
    ];
    foreach ($strips as $s):
      $isActive = ($s['key'] === 'all' && !$filterSt) || ($s['key'] === $filterSt);
    ?>
    <a href="<?= $s['href'] ?>" class="ss-item <?= $isActive ? 'active' : '' ?>">
      <div>
        <div class="ss-num"><?= $counts[$s['key']] ?></div>
        <div class="ss-lbl"><?= $s['lbl'] ?></div>
      </div>
      <div class="ss-dot" style="background:<?= $s['dot'] ?>;"></div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- TOOLBAR -->
  <div class="cl-toolbar fade-up d1">
    <form method="GET" class="cl-search">
      <input type="hidden" name="status" value="<?= htmlspecialchars($filterSt) ?>">
      <i class="bi bi-search"></i>
      <input type="text" name="q" placeholder="Cari no. laporan, barang, pelapor, lokasi…" value="<?= htmlspecialchars($search) ?>">
    </form>
    <a href="laporan_kehilangan.php?action=add" class="btn-cl btn-amber">
      <i class="bi bi-plus-lg"></i> Tambah Laporan
    </a>
  </div>

  <?php if ($error): ?>
  <div class="cl-alert cl-alert-danger fade-up"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- TABLE -->
  <div class="cl-card fade-up d2">
    <div class="cl-card-head">
      <div class="cl-card-title">
        <i class="bi bi-list-ul"></i>
        Daftar Laporan Kehilangan
        <span class="count-pill"><?= count($reports) ?> data</span>
      </div>
      <?php if ($filterSt): ?>
      <a href="laporan_kehilangan.php" class="btn-cl btn-ghost" style="font-size:.73rem;padding:.32rem .75rem;">
        <i class="bi bi-x"></i> Reset Filter
      </a>
      <?php endif; ?>
    </div>

    <?php if (empty($reports)): ?>
    <div class="empty-state">
      <span class="empty-icon">📋</span>
      <div class="empty-title">Tidak ada laporan kehilangan</div>
      <div class="empty-sub"><?= $search || $filterSt ? 'Tidak ada hasil untuk pencarian / filter ini.' : 'Belum ada laporan yang masuk ke sistem.' ?></div>
      <a href="laporan_kehilangan.php?action=add" class="btn-cl btn-amber"><i class="bi bi-plus-lg"></i> Buat Laporan Baru</a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="cl-table">
        <thead>
          <tr>
            <th style="width:36px">#</th>
            <th>No. Laporan</th>
            <th>Barang Hilang</th>
            <th>Petugas</th>
            <th>Lokasi Terakhir</th>
            <th>Waktu Hilang</th>
            <th>Status</th>
            <th style="text-align:center;width:100px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reports as $i => $r):
            $st = $statusMap[$r['status']] ?? ['label'=>$r['status'],'class'=>'st-closed'];
          ?>
          <tr>
            <td><span class="row-num"><?= $i+1 ?></span></td>
            <td><span class="code-tag"><?= htmlspecialchars($r['no_laporan']) ?></span></td>
            <td>
              <div style="display:flex;align-items:center;gap:9px;min-width:170px;">
                <span style="font-size:1.3rem;flex-shrink:0;"><?= $catIcons[$r['kategori'] ?? ''] ?? '📦' ?></span>
                <div>
                  <div class="item-main"><?= htmlspecialchars($r['nama_barang']) ?></div>
                  <div class="item-sub">
                    <?= htmlspecialchars($r['kategori'] ?? '-') ?>
                    <?= !empty($r['merek']) ? ' · ' . htmlspecialchars($r['merek']) : '' ?>
                  </div>
                </div>
              </div>
            </td>
            <td>
              <div class="rname"><?= htmlspecialchars($r['nama_pelapor'] ?? '—') ?></div>
              <div class="rphone"><?= htmlspecialchars($r['email_pelapor'] ?? '') ?></div>
            </td>
            <td style="max-width:170px;">
              <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px;"
                   title="<?= htmlspecialchars($r['lokasi_hilang']) ?>">
                <?= htmlspecialchars($r['lokasi_hilang']) ?>
              </div>
            </td>
            <td style="white-space:nowrap;font-size:.78rem;">
              <?= $r['waktu_hilang'] ? date('d/m/Y H:i', strtotime($r['waktu_hilang'])) : '—' ?>
            </td>
            <td><span class="st-badge <?= $st['class'] ?>"><?= $st['label'] ?></span></td>
            <td style="text-align:center;">
              <div style="display:flex;align-items:center;justify-content:center;gap:5px;">
                <a href="laporan_kehilangan.php?action=edit&id=<?= $r['id'] ?>" class="btn-icon edit" title="Edit"><i class="bi bi-pencil"></i></a>
                <a href="pencocokan.php?laporan_id=<?= $r['id'] ?>" class="btn-icon match" title="Cocokkan"><i class="bi bi-puzzle"></i></a>
                <button class="btn-icon del" title="Hapus"
                  onclick="openDelete(<?= $r['id'] ?>, '<?= addslashes(htmlspecialchars($r['nama_barang'])) ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>

  <?php if ($error): ?>
  <div class="cl-alert cl-alert-danger fade-up"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- FORM KIRI -->
    <div class="col-lg-8">
      <div class="form-card fade-up">
        <div class="form-card-head">
          <div class="fch-icon"><i class="bi bi-clipboard-plus"></i></div>
          <div class="fch-title">
            <?= $action === 'edit'
                ? 'Edit Laporan — ' . htmlspecialchars($editItem['no_laporan'] ?? '')
                : 'Form Laporan Kehilangan Baru' ?>
          </div>
        </div>
        <div class="form-card-body">
          <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="form_action" value="<?= $action === 'edit' ? 'edit' : 'create' ?>">

            <!-- BARANG -->
            <div class="sec-label mb-3">Data Barang Hilang</div>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="cl-label">Nama Barang <span class="req">*</span></label>
                <input type="text" name="nama_barang" class="cl-input" required
                  value="<?= htmlspecialchars($editItem['nama_barang'] ?? '') ?>"
                  placeholder="Ponsel iPhone, Tas kulit, Dompet…">
              </div>
              <div class="col-md-6">
                <label class="cl-label">Kategori</label>
                <select name="kategori" class="cl-select">
                  <option value="">-- Pilih Kategori --</option>
                  <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat ?>" <?= ($editItem['kategori'] ?? '') === $cat ? 'selected' : '' ?>>
                    <?= ($catIcons[$cat] ?? '') . ' ' . $cat ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="cl-label">Warna</label>
                <input type="text" name="warna" class="cl-input"
                  value="<?= htmlspecialchars($editItem['warna'] ?? '') ?>"
                  placeholder="Hitam, Merah, Abu-abu…">
              </div>
              <div class="col-md-6">
                <label class="cl-label">Merek / Merk</label>
                <input type="text" name="merek" class="cl-input"
                  value="<?= htmlspecialchars($editItem['merek'] ?? '') ?>"
                  placeholder="Samsung, Eiger, Louis Vuitton…">
              </div>
              <div class="col-12">
                <label class="cl-label">Deskripsi &amp; Ciri Khusus</label>
                <textarea name="deskripsi" class="cl-textarea"
                  placeholder="Kondisi barang, isi tas, stiker, goresan, nomor seri, atau tanda khusus lainnya…"><?= htmlspecialchars($editItem['deskripsi'] ?? '') ?></textarea>
              </div>
            </div>

            <!-- KEJADIAN -->
            <div class="sec-label mb-3">Informasi Kejadian</div>
            <div class="row g-3 mb-4">
              <div class="col-12">
                <label class="cl-label">Lokasi Terakhir Dilihat <span class="req">*</span></label>
                <input type="text" name="lokasi_hilang" class="cl-input" required
                  value="<?= htmlspecialchars($editItem['lokasi_hilang'] ?? '') ?>"
                  placeholder="Gerbong 5 arah Bogor, Stasiun Manggarai peron 2…">
              </div>
              <div class="col-md-4">
                <label class="cl-label">Nomor KRL / Rangkaian</label>
                <input type="text" name="no_krl" class="cl-input"
                  value="<?= htmlspecialchars($editItem['no_krl'] ?? '') ?>"
                  placeholder="1234 / KfW-8083">
              </div>
              <div class="col-md-4">
                <label class="cl-label">Waktu Hilang <span class="req">*</span></label>
                <input type="datetime-local" name="waktu_hilang" class="cl-input" required
                  value="<?= !empty($editItem['waktu_hilang']) ? date('Y-m-d\TH:i', strtotime($editItem['waktu_hilang'])) : '' ?>"
                  max="<?= date('Y-m-d\TH:i') ?>">
              </div>
              <div class="col-md-4">
                <label class="cl-label">Status Laporan</label>
                <select name="status" class="cl-select">
                  <?php foreach ($statusMap as $key => $s): ?>
                  <option value="<?= $key ?>" <?= ($editItem['status'] ?? 'diproses') === $key ? 'selected' : '' ?>><?= $s['label'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="cl-label">Catatan Tambahan beserta Nama Pelapor</label>
                <textarea name="catatan" class="cl-textarea"
                  placeholder="Informasi lain yang membantu proses pencarian…"><?= htmlspecialchars($editItem['catatan'] ?? '') ?></textarea>
              </div>
            </div>

            <!-- IDENTITAS PELAPOR -->
            <div class="sec-label mb-3">Identitas Pelapor <span style="font-size:.6rem;font-weight:400;color:var(--text-3);text-transform:none;letter-spacing:0;">(untuk verifikasi pengambilan)</span></div>
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label class="cl-label">Jenis Identitas</label>
                <select name="id_pelapor_jenis" class="cl-select">
                  <option value="">-- Pilih --</option>
                  <?php foreach ($idTypes as $t): ?>
                  <option value="<?= $t ?>" <?= ($editItem['id_pelapor_jenis'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-8">
                <label class="cl-label">Nomor Identitas</label>
                <input type="text" name="id_pelapor_no" class="cl-input"
                  value="<?= htmlspecialchars($editItem['id_pelapor_no'] ?? '') ?>"
                  placeholder="3271xxxxxxxxxxxx">
              </div>
            </div>

            <!-- SUBMIT -->
            <div style="display:flex;align-items:center;gap:.7rem;padding-top:.75rem;border-top:1px solid var(--border);">
              <button type="submit" class="btn-cl btn-amber">
                <i class="bi bi-check-lg"></i>
                <?= $action === 'edit' ? 'Simpan Perubahan' : 'Simpan Laporan' ?>
              </button>
              <a href="laporan_kehilangan.php" class="btn-cl btn-ghost">
                <i class="bi bi-x-lg"></i> Batal
              </a>
              <?php if ($action === 'edit' && $editItem): ?>
              <div style="margin-left:auto;font-size:.71rem;color:var(--text-3);">
                No: <span style="color:var(--amber);font-weight:700;"><?= htmlspecialchars($editItem['no_laporan']) ?></span>
              </div>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- SIDEBAR KANAN -->
    <div class="col-lg-4">

      <div class="sb-card fade-up d1">
        <div class="sb-head"><i class="bi bi-diagram-3"></i> Alur Penanganan</div>
        <div class="sb-body">
          <div class="step-r">
            <div class="step-n done"><i class="bi bi-check" style="font-size:.72rem;"></i></div>
            <div><div class="step-t">Laporan Masuk</div><div class="step-d">Data barang &amp; waktu hilang tercatat di sistem</div></div>
          </div>
          <div class="step-r">
            <div class="step-n now">2</div>
            <div><div class="step-t">Proses Pencocokan</div><div class="step-d">Petugas mencocokkan dengan database barang temuan</div></div>
          </div>
          <div class="step-r">
            <div class="step-n wait">3</div>
            <div><div class="step-t">Notifikasi Pelapor</div><div class="step-d">Pelapor dihubungi jika kecocokan ditemukan</div></div>
          </div>
          <div class="step-r">
            <div class="step-n wait">4</div>
            <div><div class="step-t">Serah Terima</div><div class="step-d">Barang dikembalikan dengan verifikasi identitas</div></div>
          </div>
        </div>
      </div>

      <div class="sb-card fade-up d2">
        <div class="sb-head"><i class="bi bi-lightbulb"></i> Tips Pencatatan</div>
        <div class="sb-body">
          <div class="tip-i"><div class="tip-dot"></div><div class="tip-t">Isi deskripsi <strong>selengkap mungkin</strong> — ciri fisik, isi tas, stiker, dan nomor seri meningkatkan peluang kecocokan.</div></div>
          <div class="tip-i"><div class="tip-dot"></div><div class="tip-t">Waktu hilang yang akurat membantu mempersempit pencarian di inventori barang temuan.</div></div>
          <div class="tip-i"><div class="tip-dot"></div><div class="tip-t">Nomor KRL/rangkaian memudahkan penelusuran ke unit kereta yang bersangkutan.</div></div>
          <div class="tip-i"><div class="tip-dot"></div><div class="tip-t">Identitas pelapor wajib disiapkan saat pengambilan barang sebagai bukti kepemilikan.</div></div>
        </div>
      </div>

      <div class="sb-card fade-up d3">
        <div class="sb-head"><i class="bi bi-lightning-charge"></i> Aksi Cepat</div>
        <div>
          <a href="barang_temuan.php?action=add" class="sb-quick-link" style="display:flex;align-items:center;gap:10px;padding:.82rem 1.2rem;border-bottom:1px solid var(--border);text-decoration:none;">
            <div style="width:30px;height:30px;background:rgba(96,165,250,.12);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--blue-lt);font-size:.88rem;"><i class="bi bi-box-seam"></i></div>
            <div><div style="font-size:.79rem;font-weight:700;color:var(--text);">Catat Barang Temuan</div><div style="font-size:.69rem;color:var(--text-3);">Input barang yang ditemukan</div></div>
            <i class="bi bi-chevron-right" style="margin-left:auto;color:var(--text-3);font-size:.72rem;"></i>
          </a>
          <a href="pencocokan.php" class="sb-quick-link" style="display:flex;align-items:center;gap:10px;padding:.82rem 1.2rem;text-decoration:none;">
            <div style="width:30px;height:30px;background:var(--amber-dim);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--amber);font-size:.88rem;"><i class="bi bi-puzzle"></i></div>
            <div><div style="font-size:.79rem;font-weight:700;color:var(--text);">Pencocokan Barang</div><div style="font-size:.69rem;color:var(--text-3);">Cocokkan laporan &amp; barang temuan</div></div>
            <i class="bi bi-chevron-right" style="margin-left:auto;color:var(--text-3);font-size:.72rem;"></i>
          </a>
        </div>
      </div>

    </div>
  </div>

<?php endif; ?>
</div><!-- /main-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openDelete(id, name) {
  document.getElementById('delConfirmBtn').href = 'laporan_kehilangan.php?action=del&id=' + id;
  document.getElementById('deleteModal').classList.add('show');
}
function closeModal() {
  document.getElementById('deleteModal').classList.remove('show');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

const fb = document.getElementById('flashBar');
if (fb) setTimeout(() => { fb.style.transition = 'opacity .4s'; fb.style.opacity = '0'; setTimeout(() => fb.remove(), 400); }, 4500);

document.querySelectorAll('.sb-quick-link').forEach(a => {
  a.addEventListener('mouseenter', () => a.style.background = 'rgba(255,255,255,0.025)');
  a.addEventListener('mouseleave', () => a.style.background = '');
});
</script>
</body>
</html>