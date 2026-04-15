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
} catch (Exception $e) { /* fallback ke session */ }

$role      = $user['role'] ?? 'pelapor';
$firstName = explode(' ', $user['nama'] ?? 'User')[0];

$hour = (int)date('H');
if ($hour < 11)      $greeting = 'Selamat pagi';
elseif ($hour < 15)  $greeting = 'Selamat siang';
elseif ($hour < 18)  $greeting = 'Selamat sore';
else                 $greeting = 'Selamat malam';
$greetingShort = explode(' ', $greeting)[1];

$formSuccess = false;
$formError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_laporan'])) {
    $namaBarang   = trim($_POST['nama_barang']   ?? '');
    $deskripsi    = trim($_POST['deskripsi']     ?? '');
    $lokasiHilang = trim($_POST['lokasi_hilang'] ?? '');
    $waktuHilang  = trim($_POST['waktu_hilang']  ?? '');

    if (!$namaBarang || !$lokasiHilang || !$waktuHilang) {
        $formError = 'Mohon lengkapi semua field yang wajib diisi.';
    } else {
        $fotoPath = null;
        if (!empty($_FILES['foto_barang']['name'])) {
            $uploadDir = __DIR__ . '/uploads/laporan/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['foto_barang']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed)) {
                $formError = 'Format foto tidak didukung. Gunakan JPG, PNG, atau WEBP.';
            } elseif ($_FILES['foto_barang']['size'] > 5 * 1024 * 1024) {
                $formError = 'Ukuran foto maksimal 5 MB.';
            } else {
                $newName  = 'LPR_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($_FILES['foto_barang']['tmp_name'], $uploadDir . $newName);
                $fotoPath = 'uploads/laporan/' . $newName;
            }
        }
        if (!$formError) {
            try {
                $noLaporan = 'LPR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $pdo = getDB();
                $stmt = $pdo->prepare("
                    INSERT INTO laporan_kehilangan
                        (no_laporan, user_id, nama_barang, deskripsi, lokasi_hilang,
                         waktu_hilang, foto_barang, status, created_by)
                    VALUES (:no,:uid,:nama,:desk,:lokasi,:waktu,:foto,'diproses',:uid2)
                ");
                $stmt->execute([
                    ':no'    => $noLaporan, ':uid'   => $user['id'],
                    ':nama'  => $namaBarang, ':desk'  => $deskripsi ?: null,
                    ':lokasi'=> $lokasiHilang, ':waktu' => $waktuHilang,
                    ':foto'  => $fotoPath,   ':uid2'  => $user['id'],
                ]);
                $formSuccess = true;
            } catch (Exception $e) {
                $formError = 'Terjadi kesalahan sistem. Coba lagi nanti.';
            }
        }
    }
}

$myReports = [];
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT no_laporan, nama_barang, lokasi_hilang, waktu_hilang, status, created_at
        FROM laporan_kehilangan
        WHERE user_id = :uid AND deleted_at IS NULL
        ORDER BY created_at DESC LIMIT 10
    ");
    $stmt->execute([':uid' => $user['id']]);
    $myReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$statusCount = ['diproses' => 0, 'ditemukan' => 0, 'selesai' => 0];
foreach ($myReports as $r) {
    if (isset($statusCount[$r['status']])) $statusCount[$r['status']]++;
}

/* ── Cek laporan yang sudah ditemukan & menunggu klaim (untuk notif banner) ── */
$siapKlaimCount = 0;
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM laporan_kehilangan lk
        LEFT JOIN klaim_kepemilikan kk ON kk.no_laporan = lk.no_laporan
        WHERE lk.user_id = :uid
          AND lk.status  = 'ditemukan'
          AND lk.deleted_at IS NULL
          AND kk.id IS NULL
    ");
    $stmt->execute([':uid' => $user['id']]);
    $siapKlaimCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CommuterLink Nusantara</title>
    <link rel="icon" href="uploads/favicon.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy:      #0D1B2E; --navy-2:    #152640; --navy-3:    #1E3357; --navy-4:    #253F6A;
            --blue:      #2563EB; --blue-lt:   #3B82F6; --blue-pale: #EFF6FF;
            --amber:     #F59E0B; --amber-lt:  #FCD34D; --amber-pale:#FFFBEB;
            --bg:        #0A1628; --bg-2:      #0F1F38; --card:      #132035; --card-2:    #192A45;
            --text:      #F0F6FF; --text-2:    #A8BDD6; --text-3:    #5A7A9E; --white:     #FFFFFF;
            --border:    rgba(255,255,255,0.07); --border-2:  rgba(255,255,255,0.12);
            --success:   #10B981; --danger:    #F87171; --info:      #60A5FA; --card-r:    16px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; transition: background 0.3s, color 0.3s; }

        /* ── LIGHT THEME ── */
        [data-theme="light"] {
            --bg:     #F0F6FF; --bg-2:   #E4EEF9; --card:   #FFFFFF; --card-2: #F5F9FF;
            --text:   #0D1B2E; --text-2: #2A4263; --text-3: #6B89A8;
            --border: rgba(13,27,46,0.08); --border-2: rgba(13,27,46,0.14);
            --navy:   #0D1B2E; --navy-2: #152640; --navy-3: #1E3357;
        }
        [data-theme="light"] .top-nav { background: rgba(240,246,255,0.92); }
        [data-theme="light"] .hero-section { background: linear-gradient(135deg,#1E3357 0%,#253F6A 50%,#2B4D80 100%); }
        [data-theme="light"] .cl-input { background: var(--bg-2); }
        [data-theme="light"] .cl-input:focus { background: #fff; }
        [data-theme="light"] .stat-pill { box-shadow: 0 2px 12px rgba(13,27,46,0.08); }
        [data-theme="light"] .laporan-item:hover { background: var(--bg-2); }

        /* ── ACCENT COLORS ── */
        [data-accent="blue"]   { --amber: #3B82F6; --amber-lt: #60A5FA; --amber-pale: #EFF6FF; }
        [data-accent="green"]  { --amber: #10B981; --amber-lt: #34D399; --amber-pale: #ECFDF5; }
        [data-accent="purple"] { --amber: #8B5CF6; --amber-lt: #A78BFA; --amber-pale: #F5F3FF; }
        [data-accent="red"]    { --amber: #EF4444; --amber-lt: #FC8181; --amber-pale: #FEF2F2; }
        [data-accent="rose"]   { --amber: #EC4899; --amber-lt: #F472B6; --amber-pale: #FDF2F8; }

        /* ── FONT SIZES ── */
        [data-fontsize="sm"] { font-size: 14px; }
        [data-fontsize="md"] { font-size: 16px; }
        [data-fontsize="lg"] { font-size: 18px; }

        /* ── COMPACT MODE ── */
        [data-compact="true"] .page-wrap  { padding: 1rem 1.25rem 2.5rem; }
        [data-compact="true"] .hero-section { padding: 1.5rem 2rem; }
        [data-compact="true"] .card-body  { padding: 1rem 1.2rem; }
        [data-compact="true"] .stat-pill  { padding: 0.85rem 1rem; }
        [data-compact="true"] .laporan-item { padding: 0.7rem 1.2rem; }
        [data-compact="true"] .ql-item   { padding: 0.65rem 1.2rem; }

        /* ── ANIMATIONS OFF ── */
        [data-anim="off"] * { animation: none !important; transition: none !important; }

        /* TOP NAV */
        .top-nav {
            position: sticky; top: 0; z-index: 200;
            background: rgba(10,22,40,0.92); backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border); padding: 0 2rem; height: 62px;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            transition: background 0.3s;
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .brand-gem { width: 34px; height: 34px; background: linear-gradient(135deg, var(--amber), var(--amber-lt)); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; box-shadow: 0 4px 14px rgba(245,158,11,0.4); }
        .brand-name { font-family: 'Fraunces', serif; font-size: 1.05rem; font-weight: 700; color: var(--white); line-height: 1; }
        .brand-name em { font-style: italic; color: var(--amber); }
        .brand-sub { font-size: 0.6rem; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.1em; }
        .nav-actions { display: flex; align-items: center; gap: 0.5rem; }
        .nav-icon-btn { width: 36px; height: 36px; border: 1px solid var(--border); background: var(--card); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--text-2); font-size: 0.95rem; text-decoration: none; transition: all 0.2s; position: relative; cursor: pointer; }
        .nav-icon-btn:hover { border-color: var(--amber); color: var(--amber); background: rgba(245,158,11,0.1); }
        .notif-pip { position: absolute; top: 7px; right: 7px; width: 6px; height: 6px; background: var(--danger); border-radius: 50%; border: 1.5px solid var(--bg); }
        .user-chip { display: flex; align-items: center; gap: 8px; padding: 4px 12px 4px 4px; border: 1px solid var(--border); border-radius: 99px; background: var(--card); text-decoration: none; transition: all 0.2s; }
        .user-chip:hover { border-color: var(--amber); background: rgba(245,158,11,0.08); }
        .chip-avatar { width: 28px; height: 28px; background: linear-gradient(135deg, var(--amber), var(--amber-lt)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 700; color: var(--navy); }
        .chip-name { font-size: 0.78rem; font-weight: 600; color: var(--text); }

        /* PAGE */
        .page-wrap { max-width: 1160px; margin: 0 auto; padding: 2rem 1.5rem 4rem; transition: padding 0.3s; }

        /* ── NOTIF BANNER (barang ditemukan) ── */
        .klaim-banner {
            display: flex; align-items: center; gap: 1rem; padding: 1rem 1.4rem;
            background: linear-gradient(135deg, rgba(16,185,129,0.12), rgba(16,185,129,0.06));
            border: 1.5px solid rgba(16,185,129,0.25); border-radius: 14px;
            margin-bottom: 1.5rem; animation: pulseBanner 2s ease infinite;
        }
        @keyframes pulseBanner { 0%,100%{border-color:rgba(16,185,129,0.25);} 50%{border-color:rgba(16,185,129,0.5);} }
        .banner-icon { width: 42px; height: 42px; background: rgba(16,185,129,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .banner-body { flex: 1; min-width: 0; }
        .banner-title { font-size: 0.88rem; font-weight: 700; color: var(--success); }
        .banner-sub   { font-size: 0.74rem; color: var(--text-2); margin-top: 2px; }
        .banner-cta   { display: inline-flex; align-items: center; gap: 6px; background: var(--success); color: var(--white); padding: 0.55rem 1.1rem; border-radius: 9px; font-size: 0.78rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: all 0.2s; }
        .banner-cta:hover { background: #059669; color: var(--white); transform: translateY(-1px); }

        /* HERO */
        .hero-section { position: relative; background: linear-gradient(135deg, var(--navy-2) 0%, var(--navy-3) 50%, var(--navy-4) 100%); border-radius: 24px; padding: 2.5rem 3rem; margin-bottom: 2rem; overflow: hidden; border: 1px solid var(--border-2); transition: padding 0.3s; }
        .hero-section::before { content: ''; position: absolute; inset: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
        .hero-glow { position: absolute; right: -100px; top: -100px; width: 400px; height: 400px; background: radial-gradient(circle, rgba(245,158,11,0.15) 0%, rgba(37,99,235,0.1) 40%, transparent 70%); border-radius: 50%; pointer-events: none; }
        .hero-glow-2 { position: absolute; left: -60px; bottom: -80px; width: 280px; height: 280px; background: radial-gradient(circle, rgba(37,99,235,0.12) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
        .hero-train { position: absolute; right: 2.5rem; bottom: -4px; font-size: 8rem; opacity: 0.07; pointer-events: none; }
        .hero-tag { display: inline-flex; align-items: center; gap: 6px; background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); color: var(--amber-lt); font-size: 0.7rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; padding: 4px 10px; border-radius: 99px; margin-bottom: 0.9rem; }
        .hero-greeting { font-family: 'Fraunces', serif; font-size: 2.4rem; font-weight: 900; color: var(--white); line-height: 1.1; letter-spacing: -1px; margin-bottom: 0.5rem; }
        .hero-greeting em { font-style: italic; color: var(--amber); }
        .hero-sub { font-size: 0.88rem; color: var(--text-2); margin-bottom: 1.75rem; max-width: 440px; line-height: 1.65; }
        .hero-cta { display: inline-flex; align-items: center; gap: 7px; background: var(--amber); color: var(--navy); padding: 0.7rem 1.4rem; border-radius: 10px; font-size: 0.83rem; font-weight: 700; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; border: none; cursor: pointer; box-shadow: 0 4px 16px rgba(245,158,11,0.3); }
        .hero-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(245,158,11,0.45); color: var(--navy); }
        .hero-cta-ghost { display: inline-flex; align-items: center; gap: 7px; background: rgba(255,255,255,0.06); color: var(--text-2); padding: 0.7rem 1.2rem; border-radius: 10px; font-size: 0.83rem; font-weight: 600; text-decoration: none; border: 1px solid var(--border-2); transition: all 0.2s; }
        .hero-cta-ghost:hover { background: rgba(255,255,255,0.1); color: var(--white); border-color: rgba(255,255,255,0.2); }

        /* STATS */
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .stat-pill { background: var(--card); border: 1px solid var(--border); border-radius: var(--card-r); padding: 1.2rem 1.4rem; display: flex; align-items: center; gap: 1rem; transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; text-decoration: none; }
        .stat-pill:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.3); border-color: var(--border-2); }
        .stat-icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .si-amber { background: rgba(245,158,11,0.12); color: var(--amber); }
        .si-blue  { background: rgba(37,99,235,0.15);  color: var(--blue-lt); }
        .si-teal  { background: rgba(16,185,129,0.12); color: #34D399; }
        .si-green { background: rgba(16,185,129,0.18); color: var(--success); }
        .stat-num  { font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 700; color: var(--white); line-height: 1; }
        .stat-label { font-size: 0.73rem; color: var(--text-3); margin-top: 2px; font-weight: 500; }

        /* CONTENT GRID */
        .content-grid { display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem; align-items: start; }

        /* CARDS */
        .cl-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--card-r); overflow: hidden; transition: background 0.3s, border-color 0.3s; }
        .card-head { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-head-title { font-family: 'Fraunces', serif; font-size: 1.05rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .card-head-title i { color: var(--amber); font-size: 1rem; }
        .card-link { font-size: 0.75rem; font-weight: 600; color: var(--blue-lt); text-decoration: none; display: flex; align-items: center; gap: 4px; }
        .card-link:hover { color: var(--amber); }
        .card-body { padding: 1.5rem; transition: padding 0.3s; }

        /* FORM */
        .form-section-label { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-3); margin-bottom: 0.6rem; margin-top: 1.4rem; }
        .form-section-label:first-child { margin-top: 0; }
        .cl-input { width: 100%; padding: 0.72rem 1rem; border: 1.5px solid var(--border); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.85rem; color: var(--text); background: var(--bg-2); transition: border-color 0.2s, box-shadow 0.2s; outline: none; }
        .cl-input:focus { border-color: var(--amber); box-shadow: 0 0 0 3px rgba(245,158,11,0.15); background: var(--card-2); }
        .cl-input::placeholder { color: var(--text-3); }
        .cl-input option { background: var(--navy-2); color: var(--text); }
        .cl-textarea { resize: vertical; min-height: 90px; }
        .cl-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%235A7A9E' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.9rem center; }
        .upload-zone { border: 2px dashed var(--border); border-radius: 12px; padding: 2rem 1.5rem; text-align: center; cursor: pointer; transition: all 0.2s; background: var(--bg-2); position: relative; }
        .upload-zone:hover, .upload-zone.dragover { border-color: var(--amber); background: rgba(245,158,11,0.06); }
        .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-icon { width: 52px; height: 52px; background: rgba(245,158,11,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.5rem; color: var(--amber); transition: transform 0.2s; }
        .upload-zone:hover .upload-icon { transform: scale(1.1); }
        .upload-title { font-size: 0.85rem; font-weight: 700; color: var(--text); }
        .upload-sub   { font-size: 0.73rem; color: var(--text-3); margin-top: 4px; }
        .upload-preview { display: none; margin-top: 1rem; position: relative; }
        .upload-preview img { width: 100%; max-height: 180px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); }
        .preview-remove { position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; background: rgba(0,0,0,0.7); color: white; border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.7rem; }
        .tip-row { display: flex; align-items: flex-start; gap: 10px; background: rgba(245,158,11,0.07); border: 1px solid rgba(245,158,11,0.2); border-radius: 10px; padding: 0.9rem 1rem; margin-bottom: 1.25rem; }
        .tip-icon { color: var(--amber); font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
        .tip-text { font-size: 0.78rem; color: var(--text-2); line-height: 1.5; }
        .tip-text strong { color: var(--amber-lt); }
        .btn-submit { width: 100%; padding: 0.85rem; background: var(--amber); color: var(--navy); border: none; border-radius: 11px; font-family: 'DM Sans', sans-serif; font-size: 0.88rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 16px rgba(245,158,11,0.25); }
        .btn-submit:hover { background: var(--amber-lt); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(245,158,11,0.4); }
        .form-alert { padding: 0.85rem 1rem; border-radius: 10px; font-size: 0.82rem; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 1.25rem; }
        .alert-success { background: rgba(16,185,129,0.1); color: #34D399; border: 1px solid rgba(16,185,129,0.2); }
        .alert-error   { background: rgba(248,113,113,0.1); color: var(--danger); border: 1px solid rgba(248,113,113,0.2); }

        /* LAPORAN LIST */
        .laporan-list { display: flex; flex-direction: column; }
        .laporan-item { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); transition: background 0.15s; text-decoration: none; }
        .laporan-item:last-child { border-bottom: none; }
        .laporan-item:hover { background: var(--card-2); }
        .lp-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .lp-body { flex: 1; min-width: 0; }
        .lp-name { font-size: 0.83rem; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lp-meta { font-size: 0.71rem; color: var(--text-3); margin-top: 2px; }
        .lp-badge { font-size: 0.67rem; font-weight: 700; padding: 3px 9px; border-radius: 99px; white-space: nowrap; flex-shrink: 0; }
        .b-diproses  { background: rgba(245,158,11,0.12);  color: var(--amber-lt); }
        .b-ditemukan { background: rgba(96,165,250,0.12);  color: var(--info); }
        .b-selesai   { background: rgba(16,185,129,0.12);  color: #34D399; }
        .empty-state { text-align: center; padding: 3rem 2rem; color: var(--text-3); }
        .empty-icon  { font-size: 3rem; margin-bottom: 0.75rem; opacity: 0.3; }
        .empty-title { font-size: 0.88rem; font-weight: 700; color: var(--text-2); margin-bottom: 0.35rem; }
        .empty-sub   { font-size: 0.78rem; }

        /* SIDEBAR */
        .quick-links { display: flex; flex-direction: column; }
        .ql-item { display: flex; align-items: center; gap: 12px; padding: 0.9rem 1.4rem; border-bottom: 1px solid var(--border); text-decoration: none; transition: background 0.15s; }
        .ql-item:last-child { border-bottom: none; }
        .ql-item:hover { background: var(--card-2); }
        .ql-icon { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; }
        .ql-text  { font-size: 0.82rem; font-weight: 600; color: var(--text); }
        .ql-sub   { font-size: 0.7rem; color: var(--text-3); }
        .ql-arrow { margin-left: auto; color: var(--text-3); font-size: 0.8rem; }
        .ql-badge { margin-left: auto; background: var(--danger); color: #fff; font-size: 0.6rem; font-weight: 800; padding: 2px 7px; border-radius: 99px; }
        .steps-list { display: flex; flex-direction: column; gap: 1.1rem; }
        .step-item  { display: flex; align-items: flex-start; gap: 12px; }
        .step-num   { width: 26px; height: 26px; background: var(--amber); color: var(--navy); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 700; flex-shrink: 0; margin-top: 2px; }
        .step-title { font-size: 0.82rem; font-weight: 700; color: var(--text); }
        .step-desc  { font-size: 0.73rem; color: var(--text-3); margin-top: 2px; line-height: 1.5; }
        .contact-card-body { padding: 1.5rem; text-align: center; }
        .cs-avatar { width: 56px; height: 56px; background: linear-gradient(135deg, var(--amber), var(--amber-lt)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 0.9rem; box-shadow: 0 8px 20px rgba(245,158,11,0.3); }
        .cs-title  { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 700; color: var(--text); }
        .cs-hours  { font-size: 0.75rem; color: var(--text-3); margin-top: 4px; margin-bottom: 1.2rem; }
        .btn-call  { display: flex; align-items: center; justify-content: center; gap: 7px; width: 100%; padding: 0.7rem; background: var(--amber); color: var(--navy); border-radius: 10px; font-size: 0.83rem; font-weight: 700; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 14px rgba(245,158,11,0.25); }
        .btn-call:hover { background: var(--amber-lt); color: var(--navy); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(245,158,11,0.4); }

        /* MODAL */
        .modal-success { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); align-items: center; justify-content: center; }
        .modal-success.show { display: flex; }
        .modal-box { background: var(--card); border: 1px solid var(--border-2); border-radius: 20px; padding: 2.5rem 2rem; max-width: 400px; width: 90%; text-align: center; animation: popIn 0.3s ease; }
        @keyframes popIn { from { transform: scale(0.85); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-check { width: 72px; height: 72px; background: rgba(245,158,11,0.12); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.25rem; }
        .modal-title { font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 700; color: var(--text); margin-bottom: 0.5rem; }
        .modal-sub   { font-size: 0.83rem; color: var(--text-3); line-height: 1.6; margin-bottom: 1.5rem; }
        .modal-close { width: 100%; padding: 0.75rem; background: var(--amber); color: var(--navy); border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .modal-close:hover { background: var(--amber-lt); }

        /* RESPONSIVE */
        @media (max-width: 991px)  { .content-grid { grid-template-columns: 1fr; } .hero-greeting { font-size: 1.85rem; } .hero-section { padding: 2rem 1.75rem; } }
        @media (max-width: 767px)  { .stats-row { grid-template-columns: repeat(3,1fr); gap:0.75rem; } .page-wrap { padding: 1.25rem 1rem 6rem; } .top-nav { padding: 0 1rem; } }
        @media (max-width: 480px)  { .stats-row { grid-template-columns: 1fr; } .hero-greeting { font-size: 1.5rem; } .hero-section { padding: 1.5rem 1.25rem; } .card-body { padding: 1.2rem; } }

        .fade-up { opacity: 0; transform: translateY(18px); animation: fadeUp 0.45s ease forwards; }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
        .delay-1 { animation-delay: 0.05s; } .delay-2 { animation-delay: 0.1s; }
        .delay-3 { animation-delay: 0.15s; } .delay-4 { animation-delay: 0.2s; }

        .page-footer { margin-top: 4rem; border-top: 1px solid var(--border); padding: 1.5rem 2rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.73rem; color: var(--text-3); flex-wrap: wrap; gap: 0.75rem; }
        .footer-links { display: flex; gap: 1.5rem; }
        .footer-links a { color: var(--text-3); text-decoration: none; transition: color 0.2s; }
        .footer-links a:hover { color: var(--amber); }

        /* ── BOTTOM NAV ── */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 300;
            background: var(--card); border-top: 1px solid var(--border);
            backdrop-filter: blur(20px);
            display: flex; align-items: stretch;
            padding: 0; padding-bottom: env(safe-area-inset-bottom);
            box-shadow: 0 -8px 32px rgba(0,0,0,0.25);
            transition: background 0.3s, border-color 0.3s;
        }
        [data-theme="light"] .bottom-nav { background: rgba(255,255,255,0.95); box-shadow: 0 -4px 24px rgba(13,27,46,0.08); }
        .bn-item {
            flex: 1; display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 4px; padding: 0.6rem 0.15rem 0.55rem;
            text-decoration: none; color: var(--text-3);
            font-size: 0.58rem; font-weight: 600; letter-spacing: 0.02em;
            transition: color 0.2s; position: relative; border: none; background: none; cursor: pointer;
        }
        .bn-item i { font-size: 1.25rem; transition: transform 0.2s, color 0.2s; line-height: 1; }
        .bn-item:hover { color: var(--amber); }
        .bn-item:hover i { transform: translateY(-2px); }
        .bn-item.active { color: var(--amber); }
        .bn-item.active i { color: var(--amber); }
        .bn-item.active::after {
            content: ''; position: absolute; top: 0; left: 20%; right: 20%;
            height: 2.5px; background: var(--amber); border-radius: 0 0 3px 3px;
        }
        .bn-pip { position: absolute; top: 6px; right: calc(50% - 14px);
            width: 6px; height: 6px; background: var(--danger);
            border-radius: 50%; border: 1.5px solid var(--card); }
    </style>

    <?php /* Settings Panel CSS — shared */ ?>
    <style>
        .sp-overlay { position: fixed; inset: 0; z-index: 8888; background: rgba(0,0,0,0); pointer-events: none; transition: background 0.35s; }
        .sp-overlay.open { background: rgba(0,0,0,0.55); pointer-events: all; backdrop-filter: blur(4px); }
        .settings-panel { position: fixed; top: 0; right: 0; bottom: 0; z-index: 8889; width: 360px; max-width: 92vw; background: #0E1E35; border-left: 1px solid rgba(255,255,255,0.09); display: flex; flex-direction: column; transform: translateX(100%); transition: transform 0.38s cubic-bezier(0.22, 1, 0.36, 1); box-shadow: -24px 0 80px rgba(0,0,0,0.55); }
        .settings-panel.open { transform: translateX(0); }
        [data-theme="light"] .settings-panel { background: #1A2E4A; border-color: rgba(255,255,255,0.12); }
        .sp-header { padding: 1.3rem 1.5rem 1.1rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .sp-title { font-size: 1rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 9px; letter-spacing: -0.3px; }
        .sp-title i { color: #F59E0B; font-size: 1.1rem; }
        .sp-close { width: 32px; height: 32px; background: rgba(255,255,255,0.07); border: none; border-radius: 8px; color: rgba(255,255,255,0.5); font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s, color 0.2s; }
        .sp-close:hover { background: rgba(255,255,255,0.13); color: #fff; }
        .sp-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; scroll-behavior: smooth; }
        .sp-body::-webkit-scrollbar { width: 4px; }
        .sp-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 4px; }
        .sp-section { margin-bottom: 1.5rem; }
        .sp-section-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.35); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 8px; }
        .sp-section-label::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.07); }
        .theme-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.6rem; }
        .theme-card { position: relative; padding: 0.8rem 0.6rem 0.65rem; border-radius: 12px; border: 2px solid rgba(255,255,255,0.08); cursor: pointer; background: rgba(255,255,255,0.04); text-align: center; transition: all 0.2s; }
        .theme-card:hover { border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.08); }
        .theme-card.active { border-color: #F59E0B; background: rgba(245,158,11,0.1); }
        .theme-card-icon { font-size: 1.6rem; margin-bottom: 5px; display: block; }
        .theme-card-name { font-size: 0.7rem; font-weight: 700; color: rgba(255,255,255,0.7); }
        .theme-card.active .theme-card-name { color: #F59E0B; }
        .theme-check { position: absolute; top: 5px; right: 5px; width: 16px; height: 16px; background: #F59E0B; border-radius: 50%; display: none; align-items: center; justify-content: center; font-size: 0.5rem; color: #000; }
        .theme-card.active .theme-check { display: flex; }
        .accent-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.6rem; }
        .accent-dot { width: 100%; aspect-ratio: 1; border-radius: 50%; cursor: pointer; border: 3px solid transparent; transition: transform 0.18s, border-color 0.18s; position: relative; }
        .accent-dot:hover { transform: scale(1.15); }
        .accent-dot.active { border-color: #fff; box-shadow: 0 0 0 3px rgba(255,255,255,0.25); }
        .accent-label { text-align: center; font-size: 0.62rem; color: rgba(255,255,255,0.45); margin-top: 5px; font-weight: 600; }
        .fontsize-row { display: flex; gap: 0.5rem; }
        .fs-btn { flex: 1; padding: 0.55rem 0.5rem; border-radius: 10px; border: 2px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.55); cursor: pointer; font-family: inherit; font-weight: 700; transition: all 0.18s; text-align: center; }
        .fs-btn:hover { border-color: rgba(255,255,255,0.22); color: #fff; }
        .fs-btn.active { border-color: #F59E0B; background: rgba(245,158,11,0.1); color: #F59E0B; }
        .fs-btn span { display: block; }
        .fs-btn .fs-sample { font-weight: 400; color: rgba(255,255,255,0.3); margin-top: 1px; }
        .fs-btn.active .fs-sample { color: rgba(245,158,11,0.5); }
        .sp-toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .sp-toggle-row:last-child { border-bottom: none; }
        .sp-toggle-info { display: flex; align-items: center; gap: 10px; }
        .sp-toggle-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(255,255,255,0.07); display: flex; align-items: center; justify-content: center; font-size: 0.9rem; color: rgba(255,255,255,0.55); flex-shrink: 0; }
        .sp-toggle-label { font-size: 0.82rem; font-weight: 700; color: rgba(255,255,255,0.85); }
        .sp-toggle-sub { font-size: 0.68rem; color: rgba(255,255,255,0.35); margin-top: 1px; }
        .sp-switch { position: relative; width: 40px; height: 22px; flex-shrink: 0; }
        .sp-switch input { opacity: 0; width: 0; height: 0; }
        .sp-slider { position: absolute; inset: 0; cursor: pointer; background: rgba(255,255,255,0.12); border-radius: 22px; transition: background 0.25s; }
        .sp-slider::before { content: ''; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: transform 0.25s; box-shadow: 0 1px 4px rgba(0,0,0,0.3); }
        input:checked + .sp-slider { background: #F59E0B; }
        input:checked + .sp-slider::before { transform: translateX(18px); }
        .sp-preview { margin: 0 0 1rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; padding: 1rem; display: flex; align-items: center; gap: 12px; }
        .sp-preview-thumb { width: 48px; height: 48px; border-radius: 10px; background: linear-gradient(135deg, var(--sp-accent, #F59E0B), rgba(255,255,255,0.2)); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .sp-preview-text .sp-preview-title { font-size: 0.82rem; font-weight: 700; color: rgba(255,255,255,0.8); }
        .sp-preview-text .sp-preview-sub { font-size: 0.7rem; color: rgba(255,255,255,0.4); margin-top: 2px; }
        .sp-footer { padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); display: flex; gap: 0.6rem; flex-shrink: 0; }
        .sp-btn-reset { flex: 1; padding: 0.65rem; border-radius: 10px; border: 1.5px solid rgba(255,255,255,0.1); background: none; color: rgba(255,255,255,0.5); font-family: inherit; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .sp-btn-reset:hover { border-color: rgba(255,255,255,0.25); color: #fff; background: rgba(255,255,255,0.05); }
        .sp-btn-apply { flex: 2; padding: 0.65rem; border-radius: 10px; border: none; background: #F59E0B; color: #0D1B2E; font-family: inherit; font-size: 0.8rem; font-weight: 800; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .sp-btn-apply:hover { background: #FCD34D; transform: translateY(-1px); }
        .sp-toast { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%) translateY(20px); background: #1E3357; border: 1px solid rgba(245,158,11,0.35); color: #FCD34D; padding: 0.65rem 1.2rem; border-radius: 99px; font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; gap: 8px; z-index: 9999; opacity: 0; pointer-events: none; transition: all 0.35s; box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        .sp-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    </style>
</head>
<body>

<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>

<aside class="settings-panel" id="settingsPanel" role="dialog" aria-label="Pengaturan Tampilan">
    <div class="sp-header">
        <div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan Tampilan</div>
        <button class="sp-close" onclick="closeSettings()" title="Tutup"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sp-body">
        <div class="sp-preview" id="spPreview">
            <div class="sp-preview-thumb" id="spPreviewThumb">🚆</div>
            <div class="sp-preview-text">
                <div class="sp-preview-title" id="spPreviewTitle">Dark · Amber</div>
                <div class="sp-preview-sub" id="spPreviewSub">Tampilan saat ini</div>
            </div>
        </div>
        <div class="sp-section">
            <div class="sp-section-label">Mode Tema</div>
            <div class="theme-grid">
                <div class="theme-card" data-theme="dark"   onclick="setTheme('dark')"><span class="theme-card-icon">🌙</span><div class="theme-card-name">Gelap</div><div class="theme-check"><i class="bi bi-check"></i></div></div>
                <div class="theme-card" data-theme="light"  onclick="setTheme('light')"><span class="theme-card-icon">☀️</span><div class="theme-card-name">Terang</div><div class="theme-check"><i class="bi bi-check"></i></div></div>
                <div class="theme-card" data-theme="system" onclick="setTheme('system')"><span class="theme-card-icon">💻</span><div class="theme-card-name">Sistem</div><div class="theme-check"><i class="bi bi-check"></i></div></div>
            </div>
        </div>
        <div class="sp-section">
            <div class="sp-section-label">Warna Aksen</div>
            <div class="accent-grid" id="accentGrid">
                <div><div class="accent-dot" data-accent="amber"  style="background:#F59E0B;" onclick="setAccent('amber')"  title="Amber"></div><div class="accent-label">Amber</div></div>
                <div><div class="accent-dot" data-accent="blue"   style="background:#3B82F6;" onclick="setAccent('blue')"   title="Biru"></div><div class="accent-label">Biru</div></div>
                <div><div class="accent-dot" data-accent="green"  style="background:#10B981;" onclick="setAccent('green')"  title="Hijau"></div><div class="accent-label">Hijau</div></div>
                <div><div class="accent-dot" data-accent="purple" style="background:#8B5CF6;" onclick="setAccent('purple')" title="Ungu"></div><div class="accent-label">Ungu</div></div>
                <div><div class="accent-dot" data-accent="rose"   style="background:#EC4899;" onclick="setAccent('rose')"   title="Rose"></div><div class="accent-label">Rose</div></div>
            </div>
        </div>
        <div class="sp-section">
            <div class="sp-section-label">Ukuran Teks</div>
            <div class="fontsize-row">
                <button class="fs-btn" data-size="sm" onclick="setFontSize('sm')"><span style="font-size:0.8rem;">Aa</span><span class="fs-sample" style="font-size:0.65rem;">Kecil</span></button>
                <button class="fs-btn" data-size="md" onclick="setFontSize('md')"><span style="font-size:1rem;">Aa</span><span class="fs-sample" style="font-size:0.7rem;">Sedang</span></button>
                <button class="fs-btn" data-size="lg" onclick="setFontSize('lg')"><span style="font-size:1.2rem;">Aa</span><span class="fs-sample" style="font-size:0.75rem;">Besar</span></button>
            </div>
        </div>
        <div class="sp-section">
            <div class="sp-section-label">Preferensi Lainnya</div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-layout-sidebar-inset-reverse"></i></div><div><div class="sp-toggle-label">Mode Kompak</div><div class="sp-toggle-sub">Kurangi jarak & padding elemen</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleCompact" onchange="setToggle('compact', this.checked)"><span class="sp-slider"></span></label></div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-magic"></i></div><div><div class="sp-toggle-label">Animasi</div><div class="sp-toggle-sub">Aktifkan transisi & efek gerak</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleAnim" onchange="setToggle('anim', this.checked)"><span class="sp-slider"></span></label></div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-bell"></i></div><div><div class="sp-toggle-label">Notifikasi</div><div class="sp-toggle-sub">Tampilkan badge notifikasi</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleNotif" onchange="setToggle('notif', this.checked)"><span class="sp-slider"></span></label></div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-eye-slash"></i></div><div><div class="sp-toggle-label">Sembunyikan Statistik</div><div class="sp-toggle-sub">Sembunyikan kartu statistik dashboard</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleHideStats" onchange="setToggle('hideStats', this.checked)"><span class="sp-slider"></span></label></div>
        </div>
        <div class="sp-section">
            <div class="sp-section-label">Info Aplikasi</div>
            <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:0.85rem 1rem;display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;background:linear-gradient(135deg,#F59E0B,#FCD34D);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">🚆</div>
                <div><div style="font-size:0.8rem;font-weight:700;color:rgba(255,255,255,0.8);">CommuterLink Nusantara</div><div style="font-size:0.68rem;color:rgba(255,255,255,0.35);">v2.4.1 · Lost &amp; Found Platform</div></div>
            </div>
        </div>
    </div>
    <div class="sp-footer">
        <button class="sp-btn-reset" onclick="resetSettings()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
        <button class="sp-btn-apply" onclick="saveSettings()"><i class="bi bi-check-lg"></i> Simpan Pengaturan</button>
    </div>
</aside>

<div class="sp-toast" id="spToast"></div>

<?php if ($formSuccess): ?>
<div class="modal-success show" id="successModal">
    <div class="modal-box">
        <div class="modal-check">✅</div>
        <div class="modal-title">Laporan Terkirim!</div>
        <div class="modal-sub">Laporan kehilanganmu sudah kami terima dan sedang diproses oleh tim petugas. Kamu bisa melacak statusnya kapan saja.</div>
        <button class="modal-close" onclick="document.getElementById('successModal').classList.remove('show')">Oke, Mengerti</button>
    </div>
</div>
<?php endif; ?>

<nav class="top-nav">
    <a href="index_pelapor.php" class="nav-brand">
        <div class="brand-gem">🚆</div>
        <div>
            <div class="brand-name">Commuter<em>Link</em></div>
            <div class="brand-sub">Lost & Found</div>
        </div>
    </a>
    <div class="nav-actions">
            <?php if ($siapKlaimCount > 0): ?>
            <span class="notif-pip" style="background:var(--success);"></span>
            <?php endif; ?>
        </a>
        <a href="notifikasi.php" class="nav-icon-btn" title="Notifikasi" id="notifBtn"><i class="bi bi-bell"></i><span class="notif-pip" id="notifPip"></span></a>
        <button onclick="openSettings()" class="nav-icon-btn" title="Pengaturan Tampilan" style="border:none;"><i class="bi bi-sliders"></i></button>
        <a href="profile.php" class="user-chip" title="Lihat & Edit Profil">
            <div class="chip-avatar" id="navChipAvatar">
                <?php if (!empty($user['avatar']) && file_exists(__DIR__.'/'.$user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= filemtime(__DIR__.'/'.$user['avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                <?php else: ?>
                    <?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <span class="chip-name"><?= htmlspecialchars($firstName) ?></span>
        </a>
        <a href="logout.php" class="nav-icon-btn" title="Keluar" style="color:var(--danger);"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<div class="page-wrap">

    <!-- Hero -->
    <div class="hero-section fade-up">
        <div class="hero-glow"></div>
        <div class="hero-glow-2"></div>
        <div class="hero-train">🚆</div>
        <div class="hero-tag"><i class="bi bi-circle-fill" style="font-size:6px;color:var(--amber);"></i> <?= date('l, d F Y') ?></div>
        <div class="hero-greeting">Selamat <?= $greetingShort ?>,<br><em><?= htmlspecialchars($firstName) ?>!</em></div>
        <div class="hero-sub">Kehilangan barang di kereta? Kami siap bantu temukan. Laporkan sekarang dan pantau perkembangannya.</div>
        <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
            <a href="#form-laporan" class="hero-cta"><i class="bi bi-plus-lg"></i> Buat Laporan Baru</a>
            <a href="track_pelapor.php" class="hero-cta-ghost"><i class="bi bi-search"></i> Lacak Laporan Saya</a>
            <a href="serahterima_pelapor.php" class="hero-cta-ghost"><i class="bi bi-bag-check"></i> Serah Terima</a>
        </div>
    </div>

    <!-- Banner notif barang siap klaim -->
    <?php if ($siapKlaimCount > 0): ?>
    <div class="klaim-banner fade-up">
        <div class="banner-icon">📦</div>
        <div class="banner-body">
            <div class="banner-title">🎉 Barang kamu ditemukan!</div>
            <div class="banner-sub"><?= $siapKlaimCount ?> laporan sudah dicocokkan petugas dan siap untuk diklaim. Segera ajukan klaim kepemilikan.</div>
        </div>
        <a href="serahterima_pelapor.php?tab=klaim" class="banner-cta">
            <i class="bi bi-bag-check-fill"></i> Klaim Sekarang
        </a>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row fade-up delay-1" id="statsRow">
        <div class="stat-pill">
            <div class="stat-icon-wrap si-amber"><i class="bi bi-file-text"></i></div>
            <div><div class="stat-num"><?= count($myReports) ?></div><div class="stat-label">Total Laporan</div></div>
        </div>
        <div class="stat-pill">
            <div class="stat-icon-wrap si-blue"><i class="bi bi-hourglass-split"></i></div>
            <div><div class="stat-num"><?= $statusCount['diproses'] ?></div><div class="stat-label">Sedang Diproses</div></div>
        </div>
        <a href="serahterima_pelapor.php" class="stat-pill" style="text-decoration:none;">
            <div class="stat-icon-wrap si-teal"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-num"><?= $statusCount['selesai'] + $statusCount['ditemukan'] ?></div>
                <div class="stat-label">Ditemukan / Selesai</div>
            </div>
        </a>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <div style="display:flex; flex-direction:column; gap:1.5rem;">

            <!-- Form -->
            <div class="cl-card fade-up delay-2" id="form-laporan">
                <div class="card-head">
                    <div class="card-head-title"><i class="bi bi-pencil-square"></i> Buat Laporan Kehilangan</div>
                </div>
                <div class="card-body">
                    <?php if ($formError): ?>
                    <div class="form-alert alert-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($formError) ?></div>
                    <?php endif; ?>
                    <div class="tip-row">
                        <i class="bi bi-lightbulb-fill tip-icon"></i>
                        <div class="tip-text"><strong>Tips:</strong> Semakin detail deskripsi barangmu (warna, merek, ciri khusus), semakin mudah petugas mencocokkan dengan barang temuan yang ada.</div>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-section-label">Informasi Barang</div>
                        <div style="margin-bottom:0.9rem;">
                            <label style="font-size:0.78rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:5px;">Nama Barang <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="nama_barang" class="cl-input" placeholder="Contoh: Tas Ransel Hitam Merk Eiger" value="<?= htmlspecialchars($_POST['nama_barang'] ?? '') ?>" required>
                        </div>
                        <div style="margin-bottom:0.9rem;">
                            <label style="font-size:0.78rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:5px;">Deskripsi Barang</label>
                            <textarea name="deskripsi" class="cl-input cl-textarea" placeholder="Jelaskan ciri-ciri barang: warna, ukuran, kondisi, isi, atau tanda khusus..."><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>
                        </div>
                        <div class="form-section-label">Lokasi & Waktu Kejadian</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.9rem;">
                            <div>
                                <label style="font-size:0.78rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:5px;">Lokasi Hilang <span style="color:var(--danger)">*</span></label>
                                <select name="lokasi_hilang" class="cl-input cl-select" required>
                                    <option value="" disabled <?= !isset($_POST['lokasi_hilang']) ? 'selected' : '' ?>>Pilih Stasiun...</option>
                                    <optgroup label="Bogor Line">
                                        <option value="Stasiun Bogor">Stasiun Bogor</option>
                                        <option value="Stasiun Citayam">Stasiun Citayam</option>
                                        <option value="Stasiun Depok">Stasiun Depok</option>
                                        <option value="Stasiun Manggarai">Stasiun Manggarai</option>
                                        <option value="Stasiun Gambir">Stasiun Gambir</option>
                                        <option value="Stasiun Jakarta Kota">Stasiun Jakarta Kota</option>
                                    </optgroup>
                                    <optgroup label="Bekasi Line">
                                        <option value="Stasiun Bekasi">Stasiun Bekasi</option>
                                        <option value="Stasiun Jatinegara">Stasiun Jatinegara</option>
                                        <option value="Stasiun Angke">Stasiun Angke</option>
                                    </optgroup>
                                    <optgroup label="Tangerang Line">
                                        <option value="Stasiun Tangerang">Stasiun Tangerang</option>
                                        <option value="Stasiun Duri">Stasiun Duri</option>
                                    </optgroup>
                                    <optgroup label="Cikarang Line">
                                        <option value="Stasiun Cikarang">Stasiun Cikarang</option>
                                        <option value="Stasiun Tambun">Stasiun Tambun</option>
                                    </optgroup>
                                    <option value="Di dalam Kereta">Di dalam Kereta</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:0.78rem;font-weight:600;color:var(--text-2);display:block;margin-bottom:5px;">Waktu Hilang <span style="color:var(--danger)">*</span></label>
                                <input type="datetime-local" name="waktu_hilang" class="cl-input" value="<?= htmlspecialchars($_POST['waktu_hilang'] ?? '') ?>" max="<?= date('Y-m-d\TH:i') ?>" required>
                            </div>
                        </div>
                        <div class="form-section-label">Foto Barang (Opsional)</div>
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" name="foto_barang" id="fotoInput" accept="image/jpeg,image/png,image/webp" onchange="previewPhoto(this)">
                            <div class="upload-icon"><i class="bi bi-camera"></i></div>
                            <div class="upload-title">Klik atau Drag & Drop foto di sini</div>
                            <div class="upload-sub">Format: JPG, PNG, WEBP — Maks. 5 MB</div>
                            <div class="upload-preview" id="uploadPreview">
                                <img id="previewImg" src="" alt="Preview">
                                <button type="button" class="preview-remove" onclick="removePhoto(event)"><i class="bi bi-x"></i></button>
                            </div>
                        </div>
                        <div style="margin-top:1.5rem;">
                            <button type="submit" name="submit_laporan" class="btn-submit">
                                <i class="bi bi-send"></i> Kirim Laporan Kehilangan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Riwayat -->
            <div class="cl-card fade-up delay-3">
                <div class="card-head">
                    <div class="card-head-title"><i class="bi bi-clock-history"></i> Laporan Saya</div>
                    <a href="track_pelapor.php" class="card-link">Lihat semua <i class="bi bi-arrow-right"></i></a>
                </div>
                <?php if (empty($myReports)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <div class="empty-title">Belum ada laporan</div>
                    <div class="empty-sub">Laporan kehilanganmu akan muncul di sini</div>
                </div>
                <?php else: ?>
                <div class="laporan-list">
                    <?php foreach ($myReports as $r):
                        $bc = match($r['status']) { 'ditemukan'=>'b-ditemukan','selesai'=>'b-selesai', default=>'b-diproses' };
                        $bl = match($r['status']) { 'ditemukan'=>'🔵 Ditemukan','selesai'=>'✅ Selesai', default=>'🟡 Diproses' };
                        $ic = match($r['status']) { 'ditemukan'=>'background:rgba(96,165,250,0.12);color:var(--info);','selesai'=>'background:rgba(16,185,129,0.12);color:#34D399;', default=>'background:rgba(245,158,11,0.12);color:var(--amber);' };
                        /* Laporan ditemukan → link ke serahterima, lainnya ke track */
                        $itemHref = ($r['status'] === 'ditemukan')
                            ? 'serahterima_pelapor.php?tab=klaim'
                            : 'track_pelapor.php?no=' . urlencode($r['no_laporan']);
                    ?>
                    <a href="<?= $itemHref ?>" class="laporan-item">
                        <div class="lp-icon" style="<?= $ic ?>">
                            <i class="bi bi-<?= $r['status'] === 'ditemukan' ? 'bag-check' : 'bag' ?>"></i>
                        </div>
                        <div class="lp-body">
                            <div class="lp-name"><?= htmlspecialchars($r['nama_barang']) ?></div>
                            <div class="lp-meta">
                                <i class="bi bi-geo-alt" style="font-size:0.65rem;"></i> <?= htmlspecialchars($r['lokasi_hilang']) ?>
                                &nbsp;·&nbsp;
                                <i class="bi bi-clock" style="font-size:0.65rem;"></i> <?= date('d M Y · H:i', strtotime($r['created_at'])) ?>
                            </div>
                        </div>
                        <span class="lp-badge <?= $bc ?>"><?= $bl ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /LEFT -->

        <!-- RIGHT SIDEBAR -->
        <div style="display:flex; flex-direction:column; gap:1.25rem;">
            <div class="cl-card fade-up delay-2">
                <div class="card-head"><div class="card-head-title"><i class="bi bi-lightning-charge-fill"></i> Menu Cepat</div></div>
                <div class="quick-links">
                    <a href="serahterima_pelapor.php" class="ql-item">
                        <div class="ql-icon" style="background:rgba(16,185,129,0.15);color:var(--success);"><i class="bi bi-bag-check-fill"></i></div>
                        <div>
                            <div class="ql-text">Serah Terima Barang</div>
                            <div class="ql-sub">Klaim & ambil barangmu</div>
                        </div>
                        <?php if ($siapKlaimCount > 0): ?>
                        <span class="ql-badge"><?= $siapKlaimCount ?></span>
                        <?php else: ?>
                        <i class="bi bi-chevron-right ql-arrow"></i>
                        <?php endif; ?>
                    </a>
                    <a href="stations.php" class="ql-item"><div class="ql-icon" style="background:rgba(37,99,235,0.15);color:var(--blue-lt);"><i class="bi bi-train-front"></i></div><div><div class="ql-text">Info Stasiun</div><div class="ql-sub">Lokasi & kontak L&F</div></div><i class="bi bi-chevron-right ql-arrow"></i></a>
                    <button onclick="openSettings()" class="ql-item" style="width:100%;border:none;background:none;text-align:left;cursor:pointer;"><div class="ql-icon" style="background:rgba(245,158,11,0.08);color:var(--amber);"><i class="bi bi-sliders"></i></div><div><div class="ql-text">Pengaturan Tampilan</div><div class="ql-sub">Tema, warna, & ukuran teks</div></div><i class="bi bi-chevron-right ql-arrow"></i></button>
                    <a href="faq.php" class="ql-item"><div class="ql-icon" style="background:rgba(245,158,11,0.08);color:var(--amber-lt);"><i class="bi bi-question-circle"></i></div><div><div class="ql-text">FAQ</div><div class="ql-sub">Pertanyaan yang sering diajukan</div></div><i class="bi bi-chevron-right ql-arrow"></i></a>
                    <a href="profile.php" class="ql-item"><div class="ql-icon" style="background:rgba(255,255,255,0.06);color:var(--text-2);"><i class="bi bi-person-circle"></i></div><div><div class="ql-text">Profil Saya</div><div class="ql-sub">Kelola akun & notifikasi</div></div><i class="bi bi-chevron-right ql-arrow"></i></a>
                </div>
            </div>

            <div class="cl-card fade-up delay-3">
                <div class="card-head"><div class="card-head-title"><i class="bi bi-info-circle-fill"></i> Cara Kerja</div></div>
                <div class="card-body">
                    <div class="steps-list">
                        <div class="step-item"><div class="step-num">1</div><div><div class="step-title">Buat Laporan</div><div class="step-desc">Isi form dengan detail barang dan lokasi kehilangan. Sertakan foto jika ada.</div></div></div>
                        <div class="step-item"><div class="step-num">2</div><div><div class="step-title">Petugas Mencocokkan</div><div class="step-desc">Tim kami mencocokkan laporan dengan daftar barang temuan di stasiun.</div></div></div>
                        <div class="step-item"><div class="step-num">3</div><div><div class="step-title">Notifikasi Ditemukan</div><div class="step-desc">Kamu akan mendapat notifikasi jika barang berhasil dicocokkan.</div></div></div>
                        <div class="step-item"><div class="step-num">4</div><div><div class="step-title">Klaim & Ambil Barang</div><div class="step-desc">Ajukan klaim di halaman <a href="serahterima_pelapor.php" style="color:var(--amber);font-weight:700;">Serah Terima</a> lalu ambil di posko L&F stasiun.</div></div></div>
                    </div>
                </div>
            </div>

            <div class="cl-card fade-up delay-4">
                <div class="contact-card-body">
                    <div class="cs-avatar">🎧</div>
                    <div class="cs-title">Butuh Bantuan?</div>
                    <div class="cs-hours">Senin–Jumat · 07.00–21.00 WIB</div>
                    <a href="tel:021-121" class="btn-call"><i class="bi bi-telephone-fill"></i> Hubungi 021-121</a>
                    <div style="margin-top:0.75rem;font-size:0.72rem;color:var(--text-3);">Atau datang langsung ke posko L&F di stasiunmu</div>
                </div>
            </div>
        </div>
    </div><!-- /content-grid -->
</div><!-- /page-wrap -->

<footer class="page-footer" style="background:var(--bg);">
    <span>&copy; <?= date('Y') ?> CommuterLink Nusantara. All Rights Reserved.</span>
    <div class="footer-links">
        <a href="#">Kebijakan Privasi</a>
        <a href="#">Syarat & Ketentuan</a>
        <a href="#">Hubungi Kami</a>
    </div>
</footer>

<script>
function previewPhoto(input) {
    const preview = document.getElementById('uploadPreview');
    const img = document.getElementById('previewImg');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}
function removePhoto(e) {
    e.stopPropagation();
    document.getElementById('fotoInput').value = '';
    document.getElementById('uploadPreview').style.display = 'none';
    document.getElementById('previewImg').src = '';
}
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('dragover');
    const input = document.getElementById('fotoInput');
    input.files = e.dataTransfer.files; previewPhoto(input);
});
document.querySelectorAll('a[href="#form-laporan"]').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        document.getElementById('form-laporan').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});
const modal = document.getElementById('successModal');
if (modal) modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
</script>

<script>
(function () {
    const DEFAULTS = { theme: 'dark', accent: 'amber', fontSize: 'md', compact: false, anim: true, notif: true, hideStats: false };
    let S = Object.assign({}, DEFAULTS);

    const ACCENT_COLORS = { amber: '#F59E0B', blue: '#3B82F6', green: '#10B981', purple: '#8B5CF6', rose: '#EC4899' };
    const ACCENT_NAMES  = { amber: 'Amber', blue: 'Biru', green: 'Hijau', purple: 'Ungu', rose: 'Rose' };
    const THEME_NAMES   = { dark: 'Gelap', light: 'Terang', system: 'Sistem' };
    const THEME_ICONS   = { dark: '🌙', light: '☀️', system: '💻' };

    function loadSettings() {
        try { const saved = localStorage.getItem('cl_settings'); if (saved) S = Object.assign({}, DEFAULTS, JSON.parse(saved)); } catch(e) {}
    }
    function applySettings() {
        const html = document.documentElement;
        let resolvedTheme = S.theme;
        if (S.theme === 'system') resolvedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        html.setAttribute('data-theme',    resolvedTheme);
        html.setAttribute('data-accent',   S.accent);
        html.setAttribute('data-fontsize', S.fontSize);
        html.setAttribute('data-compact',  S.compact ? 'true' : 'false');
        html.setAttribute('data-anim',     S.anim ? 'on' : 'off');
        document.querySelectorAll('#notifPip').forEach(p => { p.style.display = S.notif ? '' : 'none'; });
        const statsRow = document.getElementById('statsRow');
        if (statsRow) statsRow.style.display = S.hideStats ? 'none' : '';
    }
    function syncPanel() {
        document.querySelectorAll('.theme-card').forEach(c => c.classList.toggle('active', c.dataset.theme === S.theme));
        document.querySelectorAll('.accent-dot').forEach(d => d.classList.toggle('active', d.dataset.accent === S.accent));
        document.querySelectorAll('.fs-btn').forEach(b => b.classList.toggle('active', b.dataset.size === S.fontSize));
        const tc = document.getElementById('toggleCompact'); if (tc) tc.checked = S.compact;
        const ta = document.getElementById('toggleAnim');    if (ta) ta.checked = S.anim;
        const tn = document.getElementById('toggleNotif');   if (tn) tn.checked = S.notif;
        const th = document.getElementById('toggleHideStats'); if (th) th.checked = S.hideStats;
        const accentColor = ACCENT_COLORS[S.accent] || '#F59E0B';
        document.getElementById('spPreviewThumb').style.background = `linear-gradient(135deg, ${accentColor}, rgba(255,255,255,0.25))`;
        document.getElementById('spPreviewTitle').textContent = `${THEME_ICONS[S.theme]} ${THEME_NAMES[S.theme]} · ${ACCENT_NAMES[S.accent]}`;
        document.getElementById('spPreviewSub').textContent = `Font: ${S.fontSize === 'sm' ? 'Kecil' : S.fontSize === 'lg' ? 'Besar' : 'Sedang'} · Kompak: ${S.compact ? 'Ya' : 'Tidak'} · Animasi: ${S.anim ? 'On' : 'Off'}`;
        const applyBtn = document.querySelector('.sp-btn-apply');
        if (applyBtn) applyBtn.style.background = accentColor;
    }

    window.setTheme    = t => { S.theme    = t; applySettings(); syncPanel(); };
    window.setAccent   = a => { S.accent   = a; applySettings(); syncPanel(); };
    window.setFontSize = f => { S.fontSize = f; applySettings(); syncPanel(); };
    window.setToggle   = (key, val) => {
        if      (key === 'compact')   S.compact   = val;
        else if (key === 'anim')      S.anim      = val;
        else if (key === 'notif')     S.notif     = val;
        else if (key === 'hideStats') S.hideStats = val;
        applySettings(); syncPanel();
    };
    window.openSettings  = () => { syncPanel(); document.getElementById('settingsPanel').classList.add('open'); document.getElementById('spOverlay').classList.add('open'); document.body.style.overflow = 'hidden'; };
    window.closeSettings = () => { document.getElementById('settingsPanel').classList.remove('open'); document.getElementById('spOverlay').classList.remove('open'); document.body.style.overflow = ''; };
    window.saveSettings  = () => { try { localStorage.setItem('cl_settings', JSON.stringify(S)); } catch(e) {} closeSettings(); showToast('✅ Pengaturan tersimpan!'); };
    window.resetSettings = () => { S = Object.assign({}, DEFAULTS); applySettings(); syncPanel(); try { localStorage.removeItem('cl_settings'); } catch(e) {} showToast('🔄 Pengaturan direset ke default'); };

    function showToast(msg) { const t = document.getElementById('spToast'); t.textContent = msg; t.classList.add('show'); setTimeout(() => t.classList.remove('show'), 2800); }

    document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === ',') { e.preventDefault(); openSettings(); } if (e.key === 'Escape') closeSettings(); });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (S.theme === 'system') applySettings(); });

    loadSettings();
    applySettings();
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- BOTTOM NAV -->
<nav class="bottom-nav" role="navigation" aria-label="Navigasi bawah">
    <a href="index_pelapor.php" class="bn-item active" title="Beranda">
        <i class="bi bi-house-fill"></i>
        <span>Beranda</span>
    </a>
    <a href="stations.php" class="bn-item" title="Stasiun">
        <i class="bi bi-train-front"></i>
        <span>Stasiun</span>
    </a>
    <a href="serahterima_pelapor.php" class="bn-item" title="Serah Terima" style="position:relative;">
        <i class="bi bi-bag-check"></i>
        <span>Serah Terima</span>
        <?php if ($siapKlaimCount > 0): ?>
        <span class="bn-pip"></span>
        <?php endif; ?>
    </a>
    <a href="news.php" class="bn-item" title="Berita">
        <i class="bi bi-newspaper"></i>
        <span>Berita</span>
    </a>
    <a href="faq.php" class="bn-item" title="FAQ">
        <i class="bi bi-question-circle"></i>
        <span>FAQ</span>
    </a>
    <a href="about.php" class="bn-item" title="Tentang">
        <i class="bi bi-info-circle"></i>
        <span>Tentang</span>
    </a>
</nav>

</body>
</html>