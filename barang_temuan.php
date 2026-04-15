<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo  = getDB();
$user = getCurrentUser();

if ($user['role'] !== 'petugas') {
    header('Location: index.php?err=forbidden'); exit;
}

$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);
$error  = '';

$categories = ['Tas','Dompet','Ponsel','Laptop','Tablet','Kamera','Perhiasan',
                'Jam Tangan','Kacamata','Dokumen','Pakaian','Sepatu','Kunci',
                'Uang','Aksesoris','Lainnya'];
$catIcons = [
    'Tas'=>'👜','Dompet'=>'👛','Ponsel'=>'📱','Laptop'=>'💻','Tablet'=>'📟',
    'Kamera'=>'📷','Perhiasan'=>'💍','Jam Tangan'=>'⌚','Kacamata'=>'👓',
    'Dokumen'=>'📄','Pakaian'=>'👕','Sepatu'=>'👟','Kunci'=>'🔑',
    'Uang'=>'💰','Aksesoris'=>'💎','Lainnya'=>'📦',
];

$statusMap = [
    'tersimpan'  => ['label' => 'Tersimpan',  'class' => 'st-open'],
    'dicocokkan' => ['label' => 'Dicocokkan', 'class' => 'st-matched'],
    'diklaim'    => ['label' => 'Diklaim',    'class' => 'st-resolved'],
    'diserahkan' => ['label' => 'Diserahkan', 'class' => 'st-closed'],
];

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
function checkCSRF(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
function setFlashMsg(string $msg): void { $_SESSION['_flash_bt'] = $msg; }
function getFlashMsg(): string {
    $m = $_SESSION['_flash_bt'] ?? '';
    unset($_SESSION['_flash_bt']);
    return $m;
}

if ($action === 'del' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE barang_temuan SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$user['id'], $id]);
    setFlashMsg('Data barang temuan berhasil dihapus.');
    header('Location: barang_temuan.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $postAction      = $_POST['form_action'] ?? '';
        $namaBarang      = trim($_POST['nama_barang']       ?? '');
        $kategori        = trim($_POST['kategori']          ?? '');
        $deskripsi       = trim($_POST['deskripsi']         ?? '');
        $warna           = trim($_POST['warna']             ?? '');
        $merek           = trim($_POST['merek']             ?? '');
        $lokasiDitemukan = trim($_POST['lokasi_ditemukan']  ?? '');
        $noKrl           = trim($_POST['no_krl']            ?? '');
        $waktuDitemukan  = trim($_POST['waktu_ditemukan']   ?? '');
        $catatan         = trim($_POST['catatan']           ?? '');
        $status          = $_POST['status']                 ?? 'tersimpan';

        if (empty($namaBarang) || empty($lokasiDitemukan) || empty($waktuDitemukan)) {
            $error = 'Field wajib belum lengkap: Nama Barang, Lokasi Ditemukan, dan Waktu Ditemukan harus diisi.';
        } else {
            $waktuMysql = date('Y-m-d H:i:s', strtotime($waktuDitemukan));

            $fotoPath = null;
            if (!empty($_FILES['foto_barang']['name'])) {
                $ext     = strtolower(pathinfo($_FILES['foto_barang']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp'];
                $maxSize = 3 * 1024 * 1024;
                if (!in_array($ext, $allowed)) {
                    $error = 'Format foto tidak didukung. Gunakan JPG, PNG, atau WEBP.';
                } elseif ($_FILES['foto_barang']['size'] > $maxSize) {
                    $error = 'Ukuran foto terlalu besar. Maksimal 3MB.';
                } else {
                    $folder = __DIR__ . '/uploads/barang/';
                    if (!is_dir($folder)) mkdir($folder, 0755, true);
                    $filename = 'BT_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['foto_barang']['tmp_name'], $folder . $filename)) {
                        $fotoPath = 'uploads/barang/' . $filename;
                    }
                }
            }

            if (!$error) {
                if ($postAction === 'create') {
                    $kodeBarang = 'BT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                    $stmt = $pdo->prepare("
                        INSERT INTO barang_temuan
                            (kode_barang, petugas_id, nama_barang, kategori, deskripsi, warna, merek,
                             lokasi_ditemukan, no_krl, waktu_ditemukan, foto_barang, catatan, status,
                             created_at, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ");
                    $ok = $stmt->execute([
                        $kodeBarang, $user['id'],
                        $namaBarang, $kategori, $deskripsi, $warna, $merek,
                        $lokasiDitemukan, $noKrl, $waktuMysql, $fotoPath, $catatan, $status,
                        $user['id'],
                    ]);
                    if ($ok) {
                        setFlashMsg("Barang temuan [$kodeBarang] berhasil dicatat oleh {$user['nama']}.");
                        header('Location: barang_temuan.php'); exit;
                    } else {
                        $error = 'Gagal menyimpan data. Silakan coba lagi.';
                    }
                } elseif ($postAction === 'edit' && $id > 0) {
                    $fotoSql   = $fotoPath ? ', foto_barang = ?' : '';
                    $fotoParam = $fotoPath ? [$fotoPath] : [];
                    $stmt = $pdo->prepare("
                        UPDATE barang_temuan SET
                            nama_barang = ?, kategori = ?, deskripsi = ?, warna = ?, merek = ?,
                            lokasi_ditemukan = ?, no_krl = ?, waktu_ditemukan = ?,
                            catatan = ?, status = ?
                            $fotoSql,
                            updated_at = NOW(), updated_by = ?
                        WHERE id = ? AND deleted_at IS NULL
                    ");
                    $params = array_merge(
                        [$namaBarang, $kategori, $deskripsi, $warna, $merek,
                         $lokasiDitemukan, $noKrl, $waktuMysql, $catatan, $status],
                        $fotoParam,
                        [$user['id'], $id]
                    );
                    $ok = $stmt->execute($params);
                    if ($ok) {
                        setFlashMsg('Data barang temuan berhasil diperbarui.');
                        header('Location: barang_temuan.php'); exit;
                    } else {
                        $error = 'Gagal memperbarui data. Silakan coba lagi.';
                    }
                }
            }
        }
    }
}

$editItem = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM barang_temuan WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$id]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editItem) { header('Location: barang_temuan.php'); exit; }
}

$search   = trim($_GET['q']      ?? '');
$filterSt = trim($_GET['status'] ?? '');

$where  = "bt.deleted_at IS NULL";
$params = [];
if ($search) {
    $like = '%' . $search . '%';
    $where .= " AND (bt.nama_barang LIKE ? OR bt.kode_barang LIKE ? OR bt.lokasi_ditemukan LIKE ? OR u.nama LIKE ?)";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($filterSt) {
    $where .= " AND bt.status = ?";
    $params[] = $filterSt;
}

$items = [];
if ($action === 'list') {
    $stmt = $pdo->prepare("
        SELECT bt.*, u.nama AS nama_petugas, u.email AS email_petugas
        FROM barang_temuan bt
        LEFT JOIN users u ON bt.petugas_id = u.id
        WHERE $where
        ORDER BY bt.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$counts = ['all'=>0,'tersimpan'=>0,'dicocokkan'=>0,'diklaim'=>0,'diserahkan'=>0];
$cStmt  = $pdo->query("SELECT status, COUNT(*) as c FROM barang_temuan WHERE deleted_at IS NULL GROUP BY status");
foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['c'];
    $counts['all'] += (int)$row['c'];
}

$flash     = getFlashMsg();
$pageTitle = match($action) {
    'add'  => 'Catat Barang Temuan',
    'edit' => 'Edit Barang Temuan',
    default => 'Barang Temuan',
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
    :root{
      --bg:#0A1628;--bg-2:#0F1F38;--navy:#0D1B2E;--navy-2:#152640;--navy-3:#1E3357;
      --card:#132035;--card-2:#192A45;--card-3:#1F3254;
      --amber:#F59E0B;--amber-lt:#FCD34D;--amber-dim:rgba(245,158,11,0.12);
      --blue-lt:#60A5FA;--text:#EBF4FF;--text-2:#A8BDD6;--text-3:#5A7A9E;
      --border:rgba(255,255,255,0.07);--border-2:rgba(255,255,255,0.13);
      --danger:#F87171;--success:#34D399;--purple:#A78BFA;--radius:14px;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

    /* NAVBAR */
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
    .cl-right{display:flex;align-items:center;gap:.6rem;}
    .role-chip{font-size:.63rem;font-weight:700;padding:3px 9px;border-radius:99px;background:rgba(96,165,250,.12);color:var(--blue-lt);border:1px solid rgba(96,165,250,.22);letter-spacing:.06em;}
    .cl-avatar-chip{display:flex;align-items:center;gap:8px;padding:4px 12px 4px 4px;border:1px solid var(--border);border-radius:99px;background:var(--card);text-decoration:none;transition:all .2s;}
    .cl-avatar-chip:hover{border-color:var(--amber);}
    .cl-avt{width:28px;height:28px;background:linear-gradient(135deg,var(--amber),var(--amber-lt));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:var(--navy);}
    .cl-avt-name{font-size:.78rem;font-weight:600;color:var(--text);}

    /* PAGE HEADER */
    .page-header{background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%);border-bottom:1px solid var(--border);padding:1.6rem 2rem;position:relative;overflow:hidden;}
    .page-header::after{content:'';position:absolute;right:-60px;top:-60px;width:260px;height:260px;background:radial-gradient(circle,rgba(96,165,250,.1) 0%,transparent 70%);border-radius:50%;pointer-events:none;}
    .ph-inner{position:relative;z-index:1;}
    .bc{display:flex;align-items:center;gap:5px;margin-bottom:.65rem;}
    .bc a,.bc span{font-size:.73rem;color:var(--text-3);text-decoration:none;}
    .bc a:hover{color:var(--amber);}
    .bc i{font-size:.6rem;color:var(--text-3);}
    .ph-title{font-family:'Fraunces',serif;font-size:1.55rem;font-weight:900;color:var(--text);display:flex;align-items:center;gap:10px;margin-bottom:.25rem;}
    .ph-icon{width:36px;height:36px;background:rgba(96,165,250,.1);border:1px solid rgba(96,165,250,.22);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--blue-lt);font-size:1rem;}
    .ph-sub{font-size:.8rem;color:var(--text-3);}
    .petugas-bar{display:inline-flex;align-items:center;gap:8px;margin-top:.9rem;padding:.52rem 1rem;background:rgba(96,165,250,.07);border:1px solid rgba(96,165,250,.16);border-radius:8px;font-size:.78rem;color:var(--text-3);}
    .petugas-bar strong{color:var(--blue-lt);}

    /* MAIN */
    .main-wrap{padding:1.75rem 2rem 4rem;}
    @media(max-width:767px){.main-wrap{padding:1.25rem 1rem 3rem;}.page-header{padding:1.25rem 1rem;}.cl-navbar{padding:0 1rem;}}

    /* STAT STRIP */
    .stat-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:.7rem;margin-bottom:1.4rem;}
    @media(max-width:991px){.stat-strip{grid-template-columns:repeat(3,1fr);}}
    @media(max-width:575px){.stat-strip{grid-template-columns:repeat(2,1fr);}}
    .ss-item{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1rem 1.1rem;display:flex;align-items:center;gap:10px;text-decoration:none;transition:all .2s;}
    .ss-item:hover{border-color:var(--border-2);transform:translateY(-2px);}
    .ss-item.active{border-color:var(--amber);background:var(--amber-dim);}
    .ss-num{font-family:'Fraunces',serif;font-size:1.55rem;font-weight:700;color:var(--text);line-height:1;}
    .ss-lbl{font-size:.7rem;color:var(--text-3);margin-top:2px;font-weight:500;}
    .ss-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-left:auto;}

    /* TOOLBAR */
    .cl-toolbar{display:flex;align-items:center;gap:.75rem;margin-bottom:1.2rem;flex-wrap:wrap;}
    .cl-search{position:relative;flex:1;min-width:200px;}
    .cl-search i{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:var(--text-3);font-size:.82rem;pointer-events:none;}
    .cl-search input{width:100%;padding:.58rem .9rem .58rem 2.35rem;background:var(--card);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.82rem;outline:none;transition:border-color .2s;}
    .cl-search input:focus{border-color:var(--amber);}
    .cl-search input::placeholder{color:var(--text-3);}

    /* BUTTONS */
    .btn-cl{display:inline-flex;align-items:center;gap:6px;padding:.58rem 1.1rem;border-radius:10px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:.81rem;font-weight:700;text-decoration:none;transition:all .2s;white-space:nowrap;}
    .btn-amber{background:var(--amber);color:var(--navy);box-shadow:0 4px 14px rgba(245,158,11,.25);}
    .btn-amber:hover{background:var(--amber-lt);transform:translateY(-1px);color:var(--navy);box-shadow:0 6px 20px rgba(245,158,11,.38);}
    .btn-ghost{background:transparent;color:var(--text-2);border:1px solid var(--border-2);}
    .btn-ghost:hover{border-color:var(--text-2);color:var(--text);background:var(--card-2);}
    .btn-danger-solid{background:var(--danger);color:#fff;}
    .btn-danger-solid:hover{opacity:.88;color:#fff;}
    .btn-icon{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid var(--border);background:var(--card-2);color:var(--text-2);font-size:.82rem;text-decoration:none;cursor:pointer;transition:all .2s;}
    .btn-icon.edit:hover {background:rgba(96,165,250,.12);color:var(--blue-lt);border-color:rgba(96,165,250,.25);}
    .btn-icon.match:hover{background:var(--amber-dim);color:var(--amber);border-color:rgba(245,158,11,.3);}
    .btn-icon.del:hover  {background:rgba(248,113,113,.1);color:var(--danger);border-color:rgba(248,113,113,.25);}

    /* CARD */
    .cl-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
    .cl-card-head{display:flex;align-items:center;justify-content:space-between;padding:.95rem 1.4rem;border-bottom:1px solid var(--border);}
    .cl-card-title{font-size:.86rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
    .cl-card-title i{color:var(--blue-lt);}
    .count-pill{font-size:.68rem;font-weight:700;background:var(--amber-dim);color:var(--amber);padding:2px 8px;border-radius:99px;}

    /* TABLE */
    .cl-table{width:100%;border-collapse:collapse;}
    .cl-table thead th{font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-3);padding:.7rem 1rem;border-bottom:1px solid var(--border);background:var(--card-2);white-space:nowrap;}
    .cl-table tbody td{padding:.82rem 1rem;border-bottom:1px solid var(--border);font-size:.81rem;color:var(--text-2);vertical-align:middle;}
    .cl-table tbody tr:last-child td{border-bottom:none;}
    .cl-table tbody tr:hover td{background:rgba(255,255,255,.02);}
    .row-num{font-size:.72rem;color:var(--text-3);}
    .code-tag{font-family:'Courier New',monospace;font-size:.72rem;background:rgba(245,158,11,.1);color:var(--amber);padding:3px 7px;border-radius:5px;border:1px solid rgba(245,158,11,.2);}
    .item-main{font-weight:700;color:var(--text);font-size:.84rem;}
    .item-sub{font-size:.7rem;color:var(--text-3);margin-top:2px;}
    .rname{font-weight:600;color:var(--text);font-size:.82rem;}
    .rsub{font-size:.68rem;color:var(--blue-lt);margin-top:1px;font-weight:600;}
    .foto-thumb{width:42px;height:42px;object-fit:cover;border-radius:8px;border:1px solid var(--border);cursor:pointer;transition:transform .15s;}
    .foto-thumb:hover{transform:scale(1.08);}
    .no-foto{width:42px;height:42px;border-radius:8px;border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:var(--text-3);}

    /* STATUS */
    .st-badge{display:inline-flex;align-items:center;gap:5px;font-size:.67rem;font-weight:700;padding:3px 9px;border-radius:99px;}
    .st-badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
    .st-open    {background:rgba(96,165,250,.12);color:var(--blue-lt);}
    .st-matched {background:rgba(245,158,11,.12);color:var(--amber);}
    .st-resolved{background:rgba(52,211,153,.12);color:var(--success);}
    .st-closed  {background:rgba(167,139,250,.1);color:var(--purple);}

    /* EMPTY */
    .empty-state{text-align:center;padding:4rem 2rem;}
    .empty-icon{font-size:3.2rem;opacity:.22;margin-bottom:1rem;display:block;}
    .empty-title{font-size:.92rem;font-weight:700;color:var(--text-2);margin-bottom:.35rem;}
    .empty-sub{font-size:.78rem;color:var(--text-3);margin-bottom:1.2rem;}

    /* FORM */
    .form-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
    .form-card-head{display:flex;align-items:center;gap:10px;padding:1rem 1.5rem;border-bottom:1px solid var(--border);background:var(--card-2);}
    .fch-icon{width:30px;height:30px;background:rgba(96,165,250,.1);border:1px solid rgba(96,165,250,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--blue-lt);font-size:.88rem;}
    .fch-title{font-size:.86rem;font-weight:700;color:var(--text);}
    .form-card-body{padding:1.5rem;}
    .sec-label{font-size:.63rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-3);display:flex;align-items:center;gap:8px;margin-bottom:.85rem;}
    .sec-label::after{content:'';flex:1;height:1px;background:var(--border);}
    .cl-label{display:block;font-size:.77rem;font-weight:600;color:var(--text-2);margin-bottom:5px;}
    .cl-label .req{color:var(--amber);}
    .cl-input,.cl-select,.cl-textarea{width:100%;padding:.66rem .92rem;background:var(--bg-2);border:1.5px solid var(--border);border-radius:10px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.83rem;outline:none;transition:border-color .2s,box-shadow .2s;}
    .cl-input:focus,.cl-select:focus,.cl-textarea:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(245,158,11,.12);background:var(--card-2);}
    .cl-input::placeholder,.cl-textarea::placeholder{color:var(--text-3);}
    .cl-select{appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%235A7A9E' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .9rem center;}
    .cl-select option{background:#0D1B2E;}
    .cl-textarea{resize:vertical;min-height:82px;}
    .cl-alert{display:flex;align-items:flex-start;gap:9px;padding:.82rem 1rem;border-radius:10px;font-size:.81rem;font-weight:600;margin-bottom:1.2rem;}
    .cl-alert-danger{background:rgba(248,113,113,.1);color:var(--danger);border:1px solid rgba(248,113,113,.2);}
    .foto-upload-area{position:relative;}
    .foto-upload-area input[type="file"]{opacity:0;position:absolute;inset:0;cursor:pointer;z-index:2;width:100%;height:100%;}
    .foto-upload-label{display:flex;align-items:center;justify-content:center;gap:9px;border:2px dashed var(--border);border-radius:10px;padding:1rem;cursor:pointer;color:var(--text-3);font-size:.82rem;transition:all .2s;background:var(--bg-2);}
    .foto-upload-label:hover{border-color:var(--blue-lt);color:var(--blue-lt);}
    .foto-upload-label i{font-size:1.1rem;}

    /* SIDEBAR */
    .sb-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:1.1rem;}
    .sb-head{display:flex;align-items:center;gap:8px;padding:.88rem 1.2rem;border-bottom:1px solid var(--border);font-size:.81rem;font-weight:700;color:var(--text);}
    .sb-head i{color:var(--blue-lt);}
    .sb-body{padding:1.1rem 1.2rem;}
    .tip-i{display:flex;align-items:flex-start;gap:8px;margin-bottom:.7rem;}
    .tip-i:last-child{margin-bottom:0;}
    .tip-dot{width:5px;height:5px;border-radius:50%;background:var(--blue-lt);flex-shrink:0;margin-top:6px;}
    .tip-t{font-size:.77rem;color:var(--text-2);line-height:1.55;}
    .tip-t strong{color:var(--amber-lt);}

    /* FLASH & MODAL */
    .flash-bar{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9000;background:var(--card-3);border:1px solid rgba(52,211,153,.3);color:var(--success);padding:.82rem 1.15rem;border-radius:12px;font-size:.81rem;font-weight:600;display:flex;align-items:center;gap:8px;box-shadow:0 8px 30px rgba(0,0,0,.4);max-width:380px;animation:slideUp .3s ease;}
    @keyframes slideUp{from{transform:translateY(20px);opacity:0;}to{transform:translateY(0);opacity:1;}}
    .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.62);backdrop-filter:blur(8px);z-index:9999;align-items:center;justify-content:center;}
    .modal-bg.show{display:flex;}
    .modal-box{background:var(--card);border:1px solid var(--border-2);border-radius:18px;padding:2rem;max-width:350px;width:90%;text-align:center;animation:popIn .25s ease;}
    @keyframes popIn{from{transform:scale(.88);opacity:0;}to{transform:scale(1);opacity:1;}}
    .modal-ico{font-size:2.5rem;margin-bottom:.85rem;}
    .modal-title{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:700;color:var(--text);margin-bottom:.4rem;}
    .modal-sub{font-size:.78rem;color:var(--text-3);margin-bottom:1.4rem;line-height:1.55;}
    .lb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:10000;align-items:center;justify-content:center;}
    .lb-overlay.show{display:flex;}
    .lb-overlay img{max-width:90vw;max-height:85vh;border-radius:10px;}
    .lb-close{position:fixed;top:1.2rem;right:1.5rem;font-size:1.8rem;color:#fff;cursor:pointer;opacity:.7;line-height:1;}
    .lb-close:hover{opacity:1;}
    .fade-up{opacity:0;transform:translateY(14px);animation:fadeUp .4s ease forwards;}
    @keyframes fadeUp{to{opacity:1;transform:translateY(0);}}
    .d1{animation-delay:.05s;}.d2{animation-delay:.1s;}.d3{animation-delay:.15s;}
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
    <div class="modal-title">Hapus Barang Temuan?</div>
    <div class="modal-sub">Data barang akan dihapus dari sistem (soft delete). Tindakan ini tidak bisa dibatalkan.</div>
    <div style="display:flex;gap:.7rem;justify-content:center;">
      <button class="btn-cl btn-ghost" onclick="closeModal()"><i class="bi bi-x-lg"></i> Batal</button>
      <a id="delConfirmBtn" href="#" class="btn-cl btn-danger-solid"><i class="bi bi-trash"></i> Ya, Hapus</a>
    </div>
  </div>
</div>

<!-- LIGHTBOX -->
<div class="lb-overlay" id="lightbox" onclick="this.classList.remove('show')">
  <span class="lb-close">✕</span>
  <img id="lbImg" src="" alt="Foto Barang">
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
    <a href="barang_temuan.php"      class="cl-nav-link active"><i class="bi bi-box-seam"></i> Barang Temuan</a>
    <a href="pencocokan.php"         class="cl-nav-link"><i class="bi bi-puzzle"></i> Pencocokan</a>
    <a href="serah_terima.php"       class="cl-nav-link"><i class="bi bi-file-earmark-arrow-down"></i> Serah Terima</a>
  </div>
  <div class="cl-right">
    
  </div>
</nav>

<!-- PAGE HEADER -->
<div class="page-header">
  <div class="ph-inner">
    <div class="bc">
      <a href="index_petugas.php"><i class="bi bi-house"></i></a>
      <i class="bi bi-chevron-right"></i>
      <?php if ($action !== 'list'): ?>
        <a href="barang_temuan.php">Barang Temuan</a>
        <i class="bi bi-chevron-right"></i>
        <span><?= $action === 'add' ? 'Catat Baru' : 'Edit' ?></span>
      <?php else: ?>
        <span>Barang Temuan</span>
      <?php endif; ?>
    </div>
    <div class="ph-title">
      <div class="ph-icon">
        <i class="bi bi-<?= $action === 'add' ? 'plus-lg' : ($action === 'edit' ? 'pencil' : 'box-seam') ?>"></i>
      </div>
      <?= htmlspecialchars($pageTitle) ?>
    </div>
    <div class="ph-sub">
      <?= $action === 'list'
          ? 'Pencatatan barang yang ditemukan petugas di area KRL &amp; stasiun.'
          : 'Isi data barang temuan secara lengkap untuk mempermudah pencocokan.' ?>
    </div>
    <?php if ($action === 'list'): ?>
    <div class="petugas-bar">
      <i class="bi bi-shield-check"></i>
      Dicatat oleh: <strong><?= htmlspecialchars($user['nama']) ?></strong>
      &nbsp;·&nbsp; Setiap barang otomatis tercatat atas nama Anda
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- MAIN -->
<div class="main-wrap">

<?php if ($action === 'list'): ?>

  <div class="stat-strip fade-up">
    <?php
    $strips = [
      ['key'=>'all',        'lbl'=>'Semua',      'dot'=>'#5A7A9E','href'=>'barang_temuan.php'],
      ['key'=>'tersimpan',  'lbl'=>'Tersimpan',  'dot'=>'#60A5FA','href'=>'barang_temuan.php?status=tersimpan'],
      ['key'=>'dicocokkan', 'lbl'=>'Dicocokkan', 'dot'=>'#F59E0B','href'=>'barang_temuan.php?status=dicocokkan'],
      ['key'=>'diklaim',    'lbl'=>'Diklaim',    'dot'=>'#34D399','href'=>'barang_temuan.php?status=diklaim'],
      ['key'=>'diserahkan', 'lbl'=>'Diserahkan', 'dot'=>'#A78BFA','href'=>'barang_temuan.php?status=diserahkan'],
    ];
    foreach ($strips as $s):
      $isActive = ($s['key'] === 'all' && !$filterSt) || ($s['key'] === $filterSt);
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

  <div class="cl-toolbar fade-up d1">
    <form method="GET" class="cl-search">
      <input type="hidden" name="status" value="<?= htmlspecialchars($filterSt) ?>">
      <i class="bi bi-search"></i>
      <input type="text" name="q" placeholder="Cari kode, nama barang, lokasi, atau nama petugas…" value="<?= htmlspecialchars($search) ?>">
    </form>
    <a href="barang_temuan.php?action=add" class="btn-cl btn-amber">
      <i class="bi bi-plus-lg"></i> Catat Barang Temuan
    </a>
  </div>

  <?php if ($error): ?>
  <div class="cl-alert cl-alert-danger fade-up"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="cl-card fade-up d2">
    <div class="cl-card-head">
      <div class="cl-card-title">
        <i class="bi bi-list-ul"></i>
        Daftar Barang Temuan
        <span class="count-pill"><?= count($items) ?> data</span>
      </div>
      <?php if ($filterSt): ?>
      <a href="barang_temuan.php" class="btn-cl btn-ghost" style="font-size:.73rem;padding:.32rem .75rem;">
        <i class="bi bi-x"></i> Reset Filter
      </a>
      <?php endif; ?>
    </div>

    <?php if (empty($items)): ?>
    <div class="empty-state">
      <span class="empty-icon">📦</span>
      <div class="empty-title">Belum ada barang temuan</div>
      <div class="empty-sub"><?= $search || $filterSt ? 'Tidak ada hasil untuk pencarian / filter ini.' : 'Belum ada barang yang dicatat oleh petugas.' ?></div>
      <a href="barang_temuan.php?action=add" class="btn-cl btn-amber"><i class="bi bi-plus-lg"></i> Catat Barang Baru</a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="cl-table">
        <thead>
          <tr>
            <th style="width:36px">#</th>
            <th>Foto</th>
            <th>Kode Barang</th>
            <th>Nama Barang</th>
            <th>Lokasi Ditemukan</th>
            <th>Waktu</th>
            <th>Petugas</th>
            <th>Status</th>
            <th style="text-align:center;width:100px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $i => $row):
            $st = $statusMap[$row['status']] ?? ['label'=>$row['status'],'class'=>'st-closed'];
          ?>
          <tr>
            <td><span class="row-num"><?= $i + 1 ?></span></td>
            <td>
              <?php if ($row['foto_barang']): ?>
                <img src="<?= htmlspecialchars($row['foto_barang']) ?>" class="foto-thumb"
                     onclick="openLightbox(this.src)" title="Klik untuk perbesar">
              <?php else: ?>
                <div class="no-foto"><?= $catIcons[$row['kategori'] ?? ''] ?? '📦' ?></div>
              <?php endif; ?>
            </td>
            <td><span class="code-tag"><?= htmlspecialchars($row['kode_barang']) ?></span></td>
            <td>
              <div style="display:flex;align-items:center;gap:9px;min-width:160px;">
                <span style="font-size:1.2rem;flex-shrink:0;"><?= $catIcons[$row['kategori'] ?? ''] ?? '📦' ?></span>
                <div>
                  <div class="item-main"><?= htmlspecialchars($row['nama_barang']) ?></div>
                  <div class="item-sub">
                    <?= htmlspecialchars($row['kategori'] ?? '-') ?>
                    <?= $row['merek'] ? ' · ' . htmlspecialchars($row['merek']) : '' ?>
                    <?= $row['warna'] ? ' · ' . htmlspecialchars($row['warna']) : '' ?>
                  </div>
                </div>
              </div>
            </td>
            <td style="max-width:160px;">
              <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;" title="<?= htmlspecialchars($row['lokasi_ditemukan']) ?>">
                <?= htmlspecialchars($row['lokasi_ditemukan']) ?>
              </div>
              <?php if ($row['no_krl']): ?>
              <div class="item-sub">KRL <?= htmlspecialchars($row['no_krl']) ?></div>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;font-size:.78rem;">
              <?= $row['waktu_ditemukan'] ? date('d/m/Y H:i', strtotime($row['waktu_ditemukan'])) : '—' ?>
            </td>
            <td>
              <div class="rname"><?= htmlspecialchars($row['nama_petugas'] ?? '—') ?></div>
              <div class="rsub"><i class="bi bi-shield-check" style="font-size:.6rem;"></i> Petugas</div>
            </td>
            <td><span class="st-badge <?= $st['class'] ?>"><?= $st['label'] ?></span></td>
            <td style="text-align:center;">
              <div style="display:flex;align-items:center;justify-content:center;gap:5px;">
                <a href="barang_temuan.php?action=edit&id=<?= $row['id'] ?>" class="btn-icon edit" title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>
                <a href="pencocokan.php?barang_id=<?= $row['id'] ?>" class="btn-icon match" title="Cocokkan">
                  <i class="bi bi-puzzle"></i>
                </a>
                <button class="btn-icon del" title="Hapus"
                  onclick="openDelete(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['nama_barang'])) ?>')">
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
    <div class="col-lg-8">
      <div class="form-card fade-up">
        <div class="form-card-head">
          <div class="fch-icon"><i class="bi bi-box-seam"></i></div>
          <div class="fch-title">
            <?= $action === 'edit'
                ? 'Edit Barang — ' . htmlspecialchars($editItem['kode_barang'] ?? '')
                : 'Form Pencatatan Barang Temuan' ?>
          </div>
          <?php if ($action === 'add'): ?>
          <div style="margin-left:auto;display:flex;align-items:center;gap:7px;background:rgba(96,165,250,.08);border:1px solid rgba(96,165,250,.18);border-radius:8px;padding:5px 12px;font-size:.74rem;color:var(--text-3);">
            <i class="bi bi-shield-check" style="color:var(--blue-lt);"></i>
            Dicatat oleh: <strong style="color:var(--blue-lt);"><?= htmlspecialchars($user['nama']) ?></strong>
          </div>
          <?php endif; ?>
        </div>
        <div class="form-card-body">
          <form method="POST" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="form_action" value="<?= $action === 'edit' ? 'edit' : 'create' ?>">

            <div class="sec-label mb-3">Data Barang Temuan</div>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="cl-label">Nama Barang <span class="req">*</span></label>
                <input type="text" name="nama_barang" class="cl-input" required
                  value="<?= htmlspecialchars($editItem['nama_barang'] ?? '') ?>"
                  placeholder="Dompet kulit, Ponsel Samsung, Tas ransel…">
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
                  placeholder="Hitam, Coklat, Abu-abu…">
              </div>
              <div class="col-md-6">
                <label class="cl-label">Merek / Merk</label>
                <input type="text" name="merek" class="cl-input"
                  value="<?= htmlspecialchars($editItem['merek'] ?? '') ?>"
                  placeholder="Samsung, Eiger, Nike…">
              </div>
              <div class="col-12">
                <label class="cl-label">Deskripsi & Ciri Khusus</label>
                <textarea name="deskripsi" class="cl-textarea"
                  placeholder="Kondisi barang, isi dompet, stiker, goresan, nomor seri, tanda khusus…"><?= htmlspecialchars($editItem['deskripsi'] ?? '') ?></textarea>
              </div>
            </div>

            <div class="sec-label mb-3">Lokasi & Waktu Penemuan</div>
            <div class="row g-3 mb-4">
              <div class="col-12">
                <label class="cl-label">Lokasi Ditemukan <span class="req">*</span></label>
                <input type="text" name="lokasi_ditemukan" class="cl-input" required
                  value="<?= htmlspecialchars($editItem['lokasi_ditemukan'] ?? '') ?>"
                  placeholder="Gerbong 5, Stasiun Manggarai peron 2, Toilet stasiun…">
              </div>
              <div class="col-md-6">
                <label class="cl-label">Nomor KRL / Rangkaian</label>
                <input type="text" name="no_krl" class="cl-input"
                  value="<?= htmlspecialchars($editItem['no_krl'] ?? '') ?>"
                  placeholder="1234 / KfW-8083">
              </div>
              <div class="col-md-6">
                <label class="cl-label">Waktu Ditemukan <span class="req">*</span></label>
                <input type="datetime-local" name="waktu_ditemukan" class="cl-input" required
                  value="<?= $editItem['waktu_ditemukan'] ? date('Y-m-d\TH:i', strtotime($editItem['waktu_ditemukan'])) : '' ?>"
                  max="<?= date('Y-m-d\TH:i') ?>">
              </div>
            </div>

            <div class="sec-label mb-3">Foto & Status</div>
            <div class="row g-3 mb-4">
              <div class="col-md-8">
                <label class="cl-label">
                  Foto Barang
                  <span style="font-size:.62rem;font-weight:400;color:var(--text-3);text-transform:none;letter-spacing:0;">
                    (opsional · JPG/PNG/WEBP · maks 3MB<?= $action === 'edit' ? ' · kosongkan jika tidak ganti' : '' ?>)
                  </span>
                </label>
                <div class="foto-upload-area">
                  <div class="foto-upload-label" id="fotoLabel">
                    <i class="bi bi-camera"></i>
                    <?= $action === 'edit' && ($editItem['foto_barang'] ?? '') ? 'Klik untuk ganti foto' : 'Klik untuk pilih foto' ?>
                  </div>
                  <input type="file" name="foto_barang" accept="image/jpeg,image/png,image/webp" onchange="previewFoto(this)">
                </div>
                <?php if ($action === 'edit' && !empty($editItem['foto_barang'])): ?>
                <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
                  <img src="<?= htmlspecialchars($editItem['foto_barang']) ?>"
                       style="height:50px;border-radius:6px;border:1px solid var(--border);"
                       onclick="openLightbox(this.src)" class="foto-thumb">
                  <span style="font-size:.72rem;color:var(--text-3);">Foto saat ini</span>
                </div>
                <?php endif; ?>
                <img id="fotoPreview" src="" alt="Preview" style="display:none;width:100%;max-height:140px;object-fit:cover;border-radius:8px;margin-top:8px;border:1px solid var(--border);">
              </div>
              <div class="col-md-4">
                <label class="cl-label">Status Barang</label>
                <select name="status" class="cl-select">
                  <?php foreach ($statusMap as $key => $s): ?>
                  <option value="<?= $key ?>" <?= ($editItem['status'] ?? 'tersimpan') === $key ? 'selected' : '' ?>>
                    <?= $s['label'] ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="cl-label">Catatan Petugas</label>
                <textarea name="catatan" class="cl-textarea"
                  placeholder="Kondisi saat ditemukan, siapa yang menyerahkan, info tambahan…"><?= htmlspecialchars($editItem['catatan'] ?? '') ?></textarea>
              </div>
            </div>

            <div style="display:flex;align-items:center;gap:.7rem;padding-top:.75rem;border-top:1px solid var(--border);">
              <button type="submit" class="btn-cl btn-amber">
                <i class="bi bi-check-lg"></i>
                <?= $action === 'edit' ? 'Simpan Perubahan' : 'Simpan Barang Temuan' ?>
              </button>
              <a href="barang_temuan.php" class="btn-cl btn-ghost">
                <i class="bi bi-x-lg"></i> Batal
              </a>
              <?php if ($action === 'edit' && $editItem): ?>
              <div style="margin-left:auto;font-size:.71rem;color:var(--text-3);">
                Kode: <span style="color:var(--amber);font-weight:700;"><?= htmlspecialchars($editItem['kode_barang']) ?></span>
              </div>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="sb-card fade-up">
        <div class="sb-head"><i class="bi bi-shield-check"></i> Identitas Pencatat</div>
        <div class="sb-body">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:1rem;">
            <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--amber),var(--amber-lt));display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;color:var(--navy);flex-shrink:0;">
              <?= strtoupper(substr($user['nama'], 0, 1)) ?>
            </div>
            <div>
              <div style="font-size:.84rem;font-weight:700;color:var(--text);"><?= htmlspecialchars($user['nama']) ?></div>
              <div style="font-size:.72rem;color:var(--blue-lt);font-weight:600;"><i class="bi bi-shield-check" style="font-size:.65rem;"></i> Petugas KRL</div>
              <div style="font-size:.7rem;color:var(--text-3);"><?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>
          </div>
          <div style="font-size:.73rem;color:var(--text-3);background:var(--card-2);padding:.65rem .85rem;border-radius:8px;border:1px solid var(--border);">
            <i class="bi bi-info-circle" style="color:var(--blue-lt);"></i>
            Field <code style="color:var(--amber);font-size:.68rem;">petugas_id</code> akan otomatis terisi dengan ID Anda (<strong style="color:var(--text);"><?= $user['id'] ?></strong>).
          </div>
        </div>
      </div>
      <div class="sb-card fade-up d1">
        <div class="sb-head"><i class="bi bi-lightbulb"></i> Tips Pencatatan</div>
        <div class="sb-body">
          <div class="tip-i"><div class="tip-dot"></div><div class="tip-t">Isi deskripsi <strong>selengkap mungkin</strong> — ciri fisik, isi, stiker, dan nomor seri meningkatkan peluang pencocokan.</div></div>
          <div class="tip-i"><div class="tip-dot"></div><div class="tip-t">Sertakan <strong>nomor KRL/rangkaian</strong> untuk membantu penelusuran ke unit kereta.</div></div>
          <div class="tip-i"><div class="tip-dot"></div><div class="tip-t">Foto yang jelas dari beberapa sudut sangat membantu proses verifikasi kepemilikan.</div></div>
          <div class="tip-i"><div class="tip-dot"></div><div class="tip-t">Status awal selalu <strong>Tersimpan</strong> — ubah ke Dicocokkan saat sudah ada laporan yang cocok.</div></div>
        </div>
      </div>
      <div class="sb-card fade-up d2">
        <div class="sb-head"><i class="bi bi-lightning-charge"></i> Aksi Cepat</div>
        <div>
          <a href="laporan_kehilangan.php" style="display:flex;align-items:center;gap:10px;padding:.82rem 1.2rem;border-bottom:1px solid var(--border);text-decoration:none;"
             onmouseenter="this.style.background='rgba(255,255,255,.025)'" onmouseleave="this.style.background=''">
            <div style="width:30px;height:30px;background:var(--amber-dim);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--amber);font-size:.88rem;"><i class="bi bi-exclamation-circle"></i></div>
            <div><div style="font-size:.79rem;font-weight:700;color:var(--text);">Lihat Laporan Hilang</div><div style="font-size:.69rem;color:var(--text-3);">Cek laporan yang perlu dicocokkan</div></div>
            <i class="bi bi-chevron-right" style="margin-left:auto;color:var(--text-3);font-size:.72rem;"></i>
          </a>
          <a href="pencocokan.php" style="display:flex;align-items:center;gap:10px;padding:.82rem 1.2rem;text-decoration:none;"
             onmouseenter="this.style.background='rgba(255,255,255,.025)'" onmouseleave="this.style.background=''">
            <div style="width:30px;height:30px;background:rgba(96,165,250,.1);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--blue-lt);font-size:.88rem;"><i class="bi bi-puzzle"></i></div>
            <div><div style="font-size:.79rem;font-weight:700;color:var(--text);">Pencocokan Barang</div><div style="font-size:.69rem;color:var(--text-3);">Cocokkan dengan laporan kehilangan</div></div>
            <i class="bi bi-chevron-right" style="margin-left:auto;color:var(--text-3);font-size:.72rem;"></i>
          </a>
        </div>
      </div>
    </div>
  </div>

<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openDelete(id, name) {
  document.getElementById('delConfirmBtn').href = 'barang_temuan.php?action=del&id=' + id;
  document.getElementById('deleteModal').classList.add('show');
}
function closeModal() {
  document.getElementById('deleteModal').classList.remove('show');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
const fb = document.getElementById('flashBar');
if (fb) setTimeout(()=>{ fb.style.transition='opacity .4s'; fb.style.opacity='0'; setTimeout(()=>fb.remove(),400); }, 4500);
function previewFoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const p = document.getElementById('fotoPreview');
      const l = document.getElementById('fotoLabel');
      p.src = e.target.result;
      p.style.display = 'block';
      l.innerHTML = '<i class="bi bi-check-circle" style="color:var(--success)"></i> ' + input.files[0].name;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function openLightbox(src) {
  document.getElementById('lbImg').src = src;
  document.getElementById('lightbox').classList.add('show');
}
</script>
</body>
</html>