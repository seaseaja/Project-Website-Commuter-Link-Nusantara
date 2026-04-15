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

$role      = $user['role'] ?? 'pelapor';
$firstName = explode(' ', $user['nama'] ?? 'User')[0];

/* Redirect petugas ke halaman mereka */
if ($role === 'petugas') {
    header('Location: index_petugas.php');
    exit;
}

/* ── Ambil laporan milik user ── */
$reports = [];
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT no_laporan, nama_barang, deskripsi, lokasi_hilang,
               waktu_hilang, foto_barang, status, created_at, updated_at
        FROM   laporan_kehilangan
        WHERE  user_id = :uid AND deleted_at IS NULL
        ORDER  BY created_at DESC
    ");
    $stmt->execute([':uid' => $user['id']]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

/* ── Count per status ── */
$counts = ['total' => count($reports), 'diproses' => 0, 'ditemukan' => 0, 'selesai' => 0, 'ditutup' => 0];
foreach ($reports as $r) {
    $s = $r['status'] ?? '';
    if (isset($counts[$s])) $counts[$s]++;
}

/* ── Active report dari query string ── */
$activeNo = $_GET['no'] ?? ($reports[0]['no_laporan'] ?? null);
$active   = null;
foreach ($reports as $r) {
    if ($r['no_laporan'] === $activeNo) { $active = $r; break; }
}
if (!$active && !empty($reports)) $active = $reports[0];

/* ── Status helpers ── */
function statusLabel(string $s): string {
    return ['diproses'=>'Diproses','ditemukan'=>'Ditemukan','selesai'=>'Selesai','ditutup'=>'Ditutup'][$s] ?? ucfirst($s);
}
function statusColor(string $s): string {
    return ['diproses'=>'#F59E0B','ditemukan'=>'#3B82F6','selesai'=>'#10B981','ditutup'=>'#6B7280'][$s] ?? '#F59E0B';
}
function statusBg(string $s): string {
    return ['diproses'=>'rgba(245,158,11,0.12)','ditemukan'=>'rgba(59,130,246,0.12)',
            'selesai'=>'rgba(16,185,129,0.12)','ditutup'=>'rgba(107,114,128,0.12)'][$s] ?? 'rgba(245,158,11,0.12)';
}
function statusStep(string $s): int {
    return ['diproses'=>2,'ditemukan'=>3,'selesai'=>5,'ditutup'=>-1][$s] ?? 2; // 1=Diterima 2=Diproses 3=Ditemukan 4=Selesai; >4=semua done
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Laporan — CommuterLink Nusantara</title>
    <link rel="icon" href="uploads/favicon.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <style>
        :root {
            --navy:   #0D1B2E; --navy-2: #152640; --navy-3: #1E3357; --navy-4: #253F6A;
            --blue:   #2563EB; --blue-lt: #3B82F6;
            --amber:  #F59E0B; --amber-lt: #FCD34D; --amber-pale: #FFFBEB;
            --bg:     #0A1628; --bg-2: #0F1F38; --card: #132035; --card-2: #192A45;
            --text:   #F0F6FF; --text-2: #A8BDD6; --text-3: #5A7A9E; --white: #FFFFFF;
            --border: rgba(255,255,255,0.07); --border-2: rgba(255,255,255,0.12);
            --success:#10B981; --danger: #F87171; --info: #60A5FA; --card-r: 16px;
        }
        [data-theme="light"] {
            --bg:#F0F6FF; --bg-2:#E4EEF9; --card:#FFFFFF; --card-2:#F5F9FF;
            --text:#0D1B2E; --text-2:#2A4263; --text-3:#6B89A8;
            --border:rgba(13,27,46,0.08); --border-2:rgba(13,27,46,0.14);
            --navy:#0D1B2E; --navy-2:#152640; --navy-3:#1E3357;
        }
        [data-theme="light"] .top-nav { background: rgba(240,246,255,0.92); }
        [data-theme="light"] .track-hero { background: linear-gradient(135deg,#1E3357 0%,#253F6A 50%,#2B4D80 100%); }
        [data-accent="blue"]   { --amber:#3B82F6; --amber-lt:#60A5FA; }
        [data-accent="green"]  { --amber:#10B981; --amber-lt:#34D399; }
        [data-accent="purple"] { --amber:#8B5CF6; --amber-lt:#A78BFA; }
        [data-accent="rose"]   { --amber:#EC4899; --amber-lt:#F472B6; }
        [data-fontsize="sm"] { font-size:14px; } [data-fontsize="md"] { font-size:16px; } [data-fontsize="lg"] { font-size:18px; }
        [data-compact="true"] .page-wrap { padding:1rem 1.25rem 7rem; }
        [data-anim="off"] * { animation:none!important; transition:none!important; }

        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; transition:background .3s,color .3s; }

        /* ── TOP NAV ── */
        .top-nav { position:sticky; top:0; z-index:200; background:rgba(10,22,40,0.92); backdrop-filter:blur(20px); border-bottom:1px solid var(--border); padding:0 2rem; height:62px; display:flex; align-items:center; justify-content:space-between; gap:1rem; transition:background .3s; }
        .nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .brand-gem { width:34px; height:34px; background:linear-gradient(135deg,var(--amber),var(--amber-lt)); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; box-shadow:0 4px 14px rgba(245,158,11,.4); }
        .brand-name { font-family:'Fraunces',serif; font-size:1.05rem; font-weight:700; color:var(--white); line-height:1; }
        .brand-name em { font-style:italic; color:var(--amber); }
        .brand-sub { font-size:.6rem; color:var(--text-3); text-transform:uppercase; letter-spacing:.1em; }
        .nav-actions { display:flex; align-items:center; gap:.5rem; }
        .nav-icon-btn { width:36px; height:36px; border:1px solid var(--border); background:var(--card); border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--text-2); font-size:.95rem; text-decoration:none; transition:all .2s; position:relative; cursor:pointer; border:none; }
        .nav-icon-btn { border:1px solid var(--border); }
        .nav-icon-btn:hover { border-color:var(--amber); color:var(--amber); background:rgba(245,158,11,.1); }
        .notif-pip { position:absolute; top:7px; right:7px; width:6px; height:6px; background:var(--danger); border-radius:50%; border:1.5px solid var(--bg); }
        .user-chip { display:flex; align-items:center; gap:8px; padding:4px 12px 4px 4px; border:1px solid var(--border); border-radius:99px; background:var(--card); text-decoration:none; transition:all .2s; }
        .user-chip:hover { border-color:var(--amber); background:rgba(245,158,11,.08); }
        .chip-avatar { width:28px; height:28px; background:linear-gradient(135deg,var(--amber),var(--amber-lt)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:700; color:var(--navy); }
        .chip-name { font-size:.78rem; font-weight:600; color:var(--text); }

        /* ── PAGE ── */
        .page-wrap { max-width:1160px; margin:0 auto; padding:2rem 1.5rem 7rem; }

        /* ── HERO ── */
        .track-hero { position:relative; background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 50%,var(--navy-4) 100%); border-radius:24px; padding:2.25rem 3rem; margin-bottom:1.75rem; overflow:hidden; border:1px solid var(--border-2); }
        .track-hero::before { content:''; position:absolute; inset:0; background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
        .hero-glow { position:absolute; right:-80px; top:-80px; width:320px; height:320px; background:radial-gradient(circle,rgba(245,158,11,.18) 0%,transparent 70%); border-radius:50%; pointer-events:none; }
        .hero-radar { position:absolute; right:2.5rem; bottom:-10px; font-size:7rem; opacity:.06; pointer-events:none; line-height:1; }
        .hero-tag { display:inline-flex; align-items:center; gap:6px; background:rgba(245,158,11,.15); border:1px solid rgba(245,158,11,.3); color:var(--amber-lt); font-size:.7rem; font-weight:600; letter-spacing:.1em; text-transform:uppercase; padding:4px 10px; border-radius:99px; margin-bottom:.9rem; position:relative; z-index:1; }
        .hero-title { font-family:'Fraunces',serif; font-size:2.2rem; font-weight:900; color:var(--white); line-height:1.1; letter-spacing:-1px; margin-bottom:.4rem; position:relative; z-index:1; }
        .hero-title em { font-style:italic; color:var(--amber); }
        .hero-sub { font-size:.85rem; color:var(--text-2); position:relative; z-index:1; }

        /* ── FILTER PILLS ── */
        .filter-bar { display:flex; gap:.6rem; margin-bottom:1.5rem; flex-wrap:wrap; }
        .filter-pill { display:inline-flex; align-items:center; gap:6px; padding:.4rem 1rem; border-radius:99px; border:1.5px solid var(--border); background:var(--card); font-size:.78rem; font-weight:600; color:var(--text-3); cursor:pointer; transition:all .2s; white-space:nowrap; }
        .filter-pill .fp-dot { width:7px; height:7px; border-radius:50%; background:currentColor; flex-shrink:0; }
        .filter-pill:hover { border-color:var(--amber); color:var(--amber-lt); }
        .filter-pill.active { border-color:var(--amber); background:rgba(245,158,11,.12); color:var(--amber-lt); }
        .filter-pill .fp-count { background:rgba(255,255,255,.1); padding:1px 7px; border-radius:99px; font-size:.68rem; }
        .filter-pill.active .fp-count { background:rgba(245,158,11,.2); }

        /* ── STATS BAND ── */
        .stats-band { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.75rem; }
        .stat-card { background:var(--card); border:1px solid var(--border); border-radius:var(--card-r); padding:1.25rem 1.4rem; display:flex; align-items:center; gap:1rem; transition:transform .2s,border-color .2s; }
        .stat-card:hover { transform:translateY(-2px); border-color:var(--border-2); }
        .stat-card.active-card { border-color:var(--amber); box-shadow:0 0 0 1px rgba(245,158,11,.15); }
        .stat-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-left:auto; }
        .stat-num { font-family:'Fraunces',serif; font-size:1.9rem; font-weight:700; color:var(--white); line-height:1; }
        .stat-label { font-size:.72rem; color:var(--text-3); margin-top:3px; font-weight:500; }

        /* ── MAIN GRID ── */
        .main-grid { display:grid; grid-template-columns:380px 1fr; gap:1.5rem; align-items:start; }

        /* ── SEARCH ── */
        .search-wrap { position:relative; margin-bottom:1rem; }
        .search-wrap i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--text-3); font-size:.9rem; pointer-events:none; }
        .search-input { width:100%; padding:.65rem .9rem .65rem 2.5rem; border:1.5px solid var(--border); border-radius:12px; background:var(--card-2); color:var(--text); font-family:'DM Sans',sans-serif; font-size:.85rem; outline:none; transition:border-color .2s,box-shadow .2s; }
        .search-input:focus { border-color:var(--amber); box-shadow:0 0 0 3px rgba(245,158,11,.12); }
        .search-input::placeholder { color:var(--text-3); }

        /* ── REPORT LIST ── */
        .report-list { display:flex; flex-direction:column; gap:.5rem; max-height:calc(100vh - 340px); overflow-y:auto; padding-right:2px; scrollbar-width:thin; scrollbar-color:var(--border-2) transparent; }
        .report-list::-webkit-scrollbar { width:4px; }
        .report-list::-webkit-scrollbar-thumb { background:var(--border-2); border-radius:4px; }
        .rp-item { background:var(--card); border:1.5px solid var(--border); border-radius:14px; padding:.9rem 1.1rem; cursor:pointer; transition:all .2s; text-decoration:none; display:block; }
        .rp-item:hover { border-color:var(--border-2); background:var(--card-2); }
        .rp-item.active { border-color:var(--amber); background:rgba(245,158,11,.06); }
        .rp-no { font-size:.68rem; font-weight:700; color:var(--amber); font-family:'DM Sans',sans-serif; letter-spacing:.04em; background:rgba(245,158,11,.1); padding:2px 8px; border-radius:6px; display:inline-block; margin-bottom:.35rem; }
        .rp-name { font-family:'Fraunces',serif; font-size:.95rem; font-weight:700; color:var(--text); }
        .rp-meta { font-size:.72rem; color:var(--text-3); margin-top:.25rem; display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
        .rp-meta i { font-size:.65rem; }
        .rp-footer { display:flex; align-items:center; justify-content:space-between; margin-top:.5rem; }
        .rp-date { font-size:.68rem; color:var(--text-3); }
        .status-chip { font-size:.67rem; font-weight:700; padding:3px 10px; border-radius:99px; display:inline-flex; align-items:center; gap:4px; }
        .empty-list { text-align:center; padding:3rem 1rem; color:var(--text-3); }
        .empty-list i { font-size:2.5rem; opacity:.35; margin-bottom:.75rem; display:block; }

        /* ── DETAIL PANEL ── */
        .detail-panel { display:flex; flex-direction:column; gap:1.25rem; position:sticky; top:80px; }
        .dp-header { background:linear-gradient(135deg,var(--navy-2),var(--navy-3)); border:1px solid var(--border-2); border-radius:20px; padding:1.75rem 2rem; position:relative; overflow:hidden; }
        .dp-header::before { content:''; position:absolute; right:-50px; top:-50px; width:200px; height:200px; background:radial-gradient(circle,rgba(245,158,11,.12) 0%,transparent 70%); border-radius:50%; }
        .dp-no { font-size:.72rem; font-weight:700; color:var(--amber); background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.2); padding:4px 12px; border-radius:8px; display:inline-block; margin-bottom:.75rem; letter-spacing:.04em; position:relative; z-index:1; }
        .dp-name { font-family:'Fraunces',serif; font-size:1.5rem; font-weight:900; color:var(--white); letter-spacing:-.5px; margin-bottom:.3rem; position:relative; z-index:1; }
        .dp-date { font-size:.78rem; color:rgba(255,255,255,.45); position:relative; z-index:1; }
        .dp-status-badge { display:inline-flex; align-items:center; gap:6px; font-size:.75rem; font-weight:700; padding:5px 14px; border-radius:99px; margin-top:.75rem; position:relative; z-index:1; }
        .dp-status-badge .pulse { width:7px; height:7px; border-radius:50%; animation:pulse 2s ease infinite; }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }

        /* ── PROGRESS TRACK ── */
        .cl-card { background:var(--card); border:1px solid var(--border); border-radius:var(--card-r); overflow:hidden; }
        .card-head { padding:1rem 1.4rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
        .card-head-title { font-family:'Fraunces',serif; font-size:.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
        .card-head-title i { color:var(--amber); }
        .card-body { padding:1.4rem; }

        .progress-track { display:flex; flex-direction:column; gap:0; }
        .pt-step { display:flex; gap:1rem; position:relative; padding-bottom:1.5rem; }
        .pt-step:last-child { padding-bottom:0; }
        .pt-step::before { content:''; position:absolute; left:17px; top:34px; bottom:0; width:2px; background:var(--border); }
        .pt-step:last-child::before { display:none; }
        .pt-step.done::before { background:var(--amber); }
        .pt-icon { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:.9rem; position:relative; z-index:1; border:2px solid var(--border); background:var(--card-2); color:var(--text-3); transition:all .3s; }
        .pt-step.done .pt-icon { background:var(--amber); border-color:var(--amber); color:var(--navy); }
        .pt-step.current .pt-icon { background:rgba(245,158,11,.15); border-color:var(--amber); color:var(--amber); animation:pulseBorder 2s ease infinite; }
        @keyframes pulseBorder { 0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,.4);} 50%{box-shadow:0 0 0 6px rgba(245,158,11,0);} }
        .pt-label { font-size:.83rem; font-weight:700; color:var(--text-3); margin-top:.4rem; }
        .pt-step.done .pt-label, .pt-step.current .pt-label { color:var(--text); }
        .pt-desc { font-size:.72rem; color:var(--text-3); margin-top:2px; line-height:1.5; }
        .pt-step.current .pt-desc { color:var(--text-2); }

        /* ── INFO ROWS ── */
        .info-row { display:flex; gap:.75rem; padding:.7rem 0; border-bottom:1px solid var(--border); align-items:flex-start; }
        .info-row:last-child { border-bottom:none; }
        .info-icon { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }
        .info-label { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-3); margin-bottom:2px; }
        .info-val { font-size:.83rem; font-weight:600; color:var(--text); line-height:1.5; }

        /* ── FOTO ── */
        .foto-box { border-radius:14px; overflow:hidden; border:1px solid var(--border); background:var(--card-2); }
        .foto-box img { width:100%; display:block; object-fit:cover; max-height:220px; }
        .foto-placeholder { height:140px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.5rem; color:var(--text-3); font-size:.8rem; }
        .foto-placeholder i { font-size:2rem; opacity:.35; }

        /* ── MAP ── */
        #leafletMap { height:220px; border-radius:0 0 var(--card-r) var(--card-r); }
        .map-container { border-radius:var(--card-r); overflow:hidden; }

        /* ── CTA CARD ── */
        .cta-help { background:linear-gradient(135deg,rgba(245,158,11,.08),rgba(37,99,235,.06)); border:1px solid rgba(245,158,11,.2); border-radius:14px; padding:1.25rem 1.4rem; display:flex; align-items:center; gap:1rem; }
        .cta-help-icon { width:44px; height:44px; background:rgba(245,158,11,.12); color:var(--amber); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
        .cta-help-btn { margin-left:auto; padding:.55rem 1.1rem; background:var(--amber); color:var(--navy); border:none; border-radius:9px; font-size:.78rem; font-weight:700; cursor:pointer; font-family:inherit; transition:background .2s; text-decoration:none; white-space:nowrap; }
        .cta-help-btn:hover { background:var(--amber-lt); color:var(--navy); }

        /* ── NO SELECTION ── */
        .no-selection { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:320px; color:var(--text-3); text-align:center; gap:1rem; background:var(--card); border:1px solid var(--border); border-radius:var(--card-r); }
        .no-selection i { font-size:3rem; opacity:.25; }
        .no-selection p { font-size:.85rem; max-width:220px; line-height:1.6; }

        /* ── BOTTOM NAV ── */
        .bottom-nav { position:fixed; bottom:0; left:0; right:0; z-index:300; background:var(--card); border-top:1px solid var(--border); backdrop-filter:blur(20px); display:flex; align-items:stretch; padding-bottom:env(safe-area-inset-bottom); box-shadow:0 -8px 32px rgba(0,0,0,.25); }
        [data-theme="light"] .bottom-nav { background:rgba(255,255,255,.95); box-shadow:0 -4px 24px rgba(13,27,46,.08); }
        .bn-item { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; padding:.6rem .15rem .55rem; text-decoration:none; color:var(--text-3); font-size:.58rem; font-weight:600; transition:color .2s; position:relative; border:none; background:none; cursor:pointer; }
        .bn-item i { font-size:1.25rem; transition:transform .2s; line-height:1; }
        .bn-item:hover { color:var(--amber); }
        .bn-item:hover i { transform:translateY(-2px); }
        .bn-item.active { color:var(--amber); }
        .bn-item.active::after { content:''; position:absolute; top:0; left:20%; right:20%; height:2.5px; background:var(--amber); border-radius:0 0 3px 3px; }

        .fade-up { opacity:0; transform:translateY(14px); animation:fadeUp .4s ease forwards; }
        @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }
        .delay-1 { animation-delay:.05s; } .delay-2 { animation-delay:.1s; } .delay-3 { animation-delay:.15s; }

        @media(max-width:991px) { .main-grid { grid-template-columns:1fr; } .stats-band { grid-template-columns:repeat(2,1fr); } .detail-panel { position:static; } }
        @media(max-width:767px) { .page-wrap { padding:1.25rem 1rem 6rem; } .top-nav { padding:0 1rem; } .track-hero { padding:1.75rem 1.5rem; } .hero-title { font-size:1.8rem; } .stats-band { grid-template-columns:repeat(2,1fr); } }
        @media(max-width:480px) { .stats-band { grid-template-columns:1fr 1fr; } }
    </style>

    <?php /* ── SETTINGS PANEL CSS ── */ ?>
    <style>
        .sp-overlay{position:fixed;inset:0;z-index:8888;background:rgba(0,0,0,0);pointer-events:none;transition:background .35s;}
        .sp-overlay.open{background:rgba(0,0,0,.55);pointer-events:all;backdrop-filter:blur(4px);}
        .settings-panel{position:fixed;top:0;right:0;bottom:0;z-index:8889;width:360px;max-width:92vw;background:#0E1E35;border-left:1px solid rgba(255,255,255,.09);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .38s cubic-bezier(.22,1,.36,1);box-shadow:-24px 0 80px rgba(0,0,0,.55);}
        .settings-panel.open{transform:translateX(0);}
        .sp-header{padding:1.3rem 1.5rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
        .sp-title{font-size:1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:9px;}
        .sp-title i{color:#F59E0B;}
        .sp-close{width:32px;height:32px;background:rgba(255,255,255,.07);border:none;border-radius:8px;color:rgba(255,255,255,.5);font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s;}
        .sp-close:hover{background:rgba(255,255,255,.13);color:#fff;}
        .sp-body{flex:1;overflow-y:auto;padding:1.25rem 1.5rem;}
        .sp-section{margin-bottom:1.5rem;}
        .sp-section-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:.75rem;display:flex;align-items:center;gap:8px;}
        .sp-section-label::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.07);}
        .theme-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;}
        .theme-card{position:relative;padding:.8rem .6rem .65rem;border-radius:12px;border:2px solid rgba(255,255,255,.08);cursor:pointer;background:rgba(255,255,255,.04);text-align:center;transition:all .2s;}
        .theme-card.active{border-color:#F59E0B;background:rgba(245,158,11,.1);}
        .theme-card-icon{font-size:1.6rem;margin-bottom:5px;display:block;}
        .theme-card-name{font-size:.7rem;font-weight:700;color:rgba(255,255,255,.7);}
        .theme-card.active .theme-card-name{color:#F59E0B;}
        .theme-check{position:absolute;top:5px;right:5px;width:16px;height:16px;background:#F59E0B;border-radius:50%;display:none;align-items:center;justify-content:center;font-size:.5rem;color:#000;}
        .theme-card.active .theme-check{display:flex;}
        .accent-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:.6rem;}
        .accent-dot{width:100%;aspect-ratio:1;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:transform .18s;}
        .accent-dot:hover{transform:scale(1.15);}
        .accent-dot.active{border-color:#fff;box-shadow:0 0 0 3px rgba(255,255,255,.25);}
        .accent-label{text-align:center;font-size:.62rem;color:rgba(255,255,255,.45);margin-top:5px;font-weight:600;}
        .fontsize-row{display:flex;gap:.5rem;}
        .fs-btn{flex:1;padding:.55rem .5rem;border-radius:10px;border:2px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);color:rgba(255,255,255,.55);cursor:pointer;font-family:inherit;font-weight:700;transition:all .18s;text-align:center;}
        .fs-btn.active{border-color:#F59E0B;background:rgba(245,158,11,.1);color:#F59E0B;}
        .sp-toggle-row{display:flex;align-items:center;justify-content:space-between;padding:.75rem 0;border-bottom:1px solid rgba(255,255,255,.06);}
        .sp-toggle-row:last-child{border-bottom:none;}
        .sp-toggle-info{display:flex;align-items:center;gap:10px;}
        .sp-toggle-icon{width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.07);display:flex;align-items:center;justify-content:center;font-size:.9rem;color:rgba(255,255,255,.55);flex-shrink:0;}
        .sp-toggle-label{font-size:.82rem;font-weight:700;color:rgba(255,255,255,.85);}
        .sp-toggle-sub{font-size:.68rem;color:rgba(255,255,255,.35);margin-top:1px;}
        .sp-switch{position:relative;width:40px;height:22px;flex-shrink:0;}
        .sp-switch input{opacity:0;width:0;height:0;}
        .sp-slider{position:absolute;inset:0;cursor:pointer;background:rgba(255,255,255,.12);border-radius:22px;transition:background .25s;}
        .sp-slider::before{content:'';position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:transform .25s;}
        input:checked+.sp-slider{background:#F59E0B;}
        input:checked+.sp-slider::before{transform:translateX(18px);}
        .sp-preview{margin:0 0 1rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:1rem;display:flex;align-items:center;gap:12px;}
        .sp-preview-thumb{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
        .sp-preview-text .sp-preview-title{font-size:.82rem;font-weight:700;color:rgba(255,255,255,.8);}
        .sp-preview-text .sp-preview-sub{font-size:.7rem;color:rgba(255,255,255,.4);margin-top:2px;}
        .sp-footer{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.08);display:flex;gap:.6rem;flex-shrink:0;}
        .sp-btn-reset{flex:1;padding:.65rem;border-radius:10px;border:1.5px solid rgba(255,255,255,.1);background:none;color:rgba(255,255,255,.5);font-family:inherit;font-size:.8rem;font-weight:700;cursor:pointer;transition:all .2s;}
        .sp-btn-reset:hover{border-color:rgba(255,255,255,.25);color:#fff;}
        .sp-btn-apply{flex:2;padding:.65rem;border-radius:10px;border:none;background:#F59E0B;color:#0D1B2E;font-family:inherit;font-size:.8rem;font-weight:800;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;}
        .sp-btn-apply:hover{background:#FCD34D;}
        .sp-toast{position:fixed;bottom:5rem;left:50%;transform:translateX(-50%) translateY(20px);background:#1E3357;border:1px solid rgba(245,158,11,.35);color:#FCD34D;padding:.65rem 1.2rem;border-radius:99px;font-size:.8rem;font-weight:700;z-index:9999;opacity:0;pointer-events:none;transition:all .35s;}
        .sp-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
    </style>
</head>
<body>

<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>
<aside class="settings-panel" id="settingsPanel" role="dialog" aria-label="Pengaturan Tampilan">
    <div class="sp-header"><div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan Tampilan</div><button class="sp-close" onclick="closeSettings()"><i class="bi bi-x-lg"></i></button></div>
    <div class="sp-body">
        <div class="sp-preview"><div class="sp-preview-thumb" id="spPreviewThumb">🚆</div><div class="sp-preview-text"><div class="sp-preview-title" id="spPreviewTitle">Dark · Amber</div><div class="sp-preview-sub" id="spPreviewSub">Tampilan saat ini</div></div></div>
        <div class="sp-section"><div class="sp-section-label">Mode Tema</div><div class="theme-grid"><div class="theme-card" data-theme="dark" onclick="setTheme('dark')"><span class="theme-card-icon">🌙</span><div class="theme-card-name">Gelap</div><div class="theme-check"><i class="bi bi-check"></i></div></div><div class="theme-card" data-theme="light" onclick="setTheme('light')"><span class="theme-card-icon">☀️</span><div class="theme-card-name">Terang</div><div class="theme-check"><i class="bi bi-check"></i></div></div><div class="theme-card" data-theme="system" onclick="setTheme('system')"><span class="theme-card-icon">💻</span><div class="theme-card-name">Sistem</div><div class="theme-check"><i class="bi bi-check"></i></div></div></div></div>
        <div class="sp-section"><div class="sp-section-label">Warna Aksen</div><div class="accent-grid"><div><div class="accent-dot" data-accent="amber" style="background:#F59E0B;" onclick="setAccent('amber')"></div><div class="accent-label">Amber</div></div><div><div class="accent-dot" data-accent="blue" style="background:#3B82F6;" onclick="setAccent('blue')"></div><div class="accent-label">Biru</div></div><div><div class="accent-dot" data-accent="green" style="background:#10B981;" onclick="setAccent('green')"></div><div class="accent-label">Hijau</div></div><div><div class="accent-dot" data-accent="purple" style="background:#8B5CF6;" onclick="setAccent('purple')"></div><div class="accent-label">Ungu</div></div><div><div class="accent-dot" data-accent="rose" style="background:#EC4899;" onclick="setAccent('rose')"></div><div class="accent-label">Rose</div></div></div></div>
        <div class="sp-section"><div class="sp-section-label">Ukuran Teks</div><div class="fontsize-row"><button class="fs-btn" data-size="sm" onclick="setFontSize('sm')"><span style="font-size:.8rem;">Aa</span></button><button class="fs-btn" data-size="md" onclick="setFontSize('md')"><span style="font-size:1rem;">Aa</span></button><button class="fs-btn" data-size="lg" onclick="setFontSize('lg')"><span style="font-size:1.2rem;">Aa</span></button></div></div>
        <div class="sp-section"><div class="sp-section-label">Lainnya</div><div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-magic"></i></div><div><div class="sp-toggle-label">Animasi</div><div class="sp-toggle-sub">Aktifkan transisi & efek</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleAnim" onchange="setToggle('anim',this.checked)"><span class="sp-slider"></span></label></div><div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-bell"></i></div><div><div class="sp-toggle-label">Notifikasi Badge</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleNotif" onchange="setToggle('notif',this.checked)"><span class="sp-slider"></span></label></div></div>
    </div>
    <div class="sp-footer"><button class="sp-btn-reset" onclick="resetSettings()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button><button class="sp-btn-apply" id="spApplyBtn" onclick="saveSettings()"><i class="bi bi-check-lg"></i> Simpan</button></div>
</aside>
<div class="sp-toast" id="spToast"></div>

<!-- TOP NAV -->
<nav class="top-nav">
    <a href="index_pelapor.php" class="nav-brand">
        <div class="brand-gem">🚆</div>
        <div><div class="brand-name">Commuter<em>Link</em></div><div class="brand-sub">Lost &amp; Found</div></div>
    </a>
    <div class="nav-actions">
        <a href="notifikasi.php" class="nav-icon-btn"><i class="bi bi-bell"></i><span class="notif-pip" id="notifPip"></span></a>
        <button onclick="openSettings()" class="nav-icon-btn"><i class="bi bi-sliders"></i></button>
        <a href="profile.php" class="user-chip">
            <div class="chip-avatar">
                <?php if (!empty($user['avatar']) && file_exists(__DIR__.'/'.$user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                <?php else: ?>
                    <?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <span class="chip-name"><?= htmlspecialchars($firstName) ?></span>
        </a>
        <a href="logout.php" class="nav-icon-btn" style="color:var(--danger);"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<div class="page-wrap">

    <!-- HERO -->
    <div class="track-hero fade-up">
        <div class="hero-glow"></div>
        <div class="hero-radar">📡</div>
        <div class="hero-tag"><i class="bi bi-broadcast"></i> Real-time Tracking</div>
        <h1 class="hero-title">Lacak <em>Laporanmu</em></h1>
        <p class="hero-sub">Pantau status kehilangan barangmu secara langsung — dari pelaporan hingga barang kembali ke tanganmu.</p>
    </div>

    <!-- FILTER PILLS -->
    <div class="filter-bar fade-up delay-1">
        <button class="filter-pill active" data-filter="semua" onclick="filterReports('semua',this)">
            <span class="fp-dot" style="background:var(--amber);"></span>
            Semua <span class="fp-count"><?= $counts['total'] ?></span>
        </button>
        <button class="filter-pill" data-filter="diproses" onclick="filterReports('diproses',this)">
            <span class="fp-dot" style="background:#F59E0B;"></span>
            Diproses <span class="fp-count"><?= $counts['diproses'] ?></span>
        </button>
        <button class="filter-pill" data-filter="ditemukan" onclick="filterReports('ditemukan',this)">
            <span class="fp-dot" style="background:#3B82F6;"></span>
            Ditemukan <span class="fp-count"><?= $counts['ditemukan'] ?></span>
        </button>
        <button class="filter-pill" data-filter="selesai" onclick="filterReports('selesai',this)">
            <span class="fp-dot" style="background:#10B981;"></span>
            Selesai <span class="fp-count"><?= $counts['selesai'] ?></span>
        </button>
        <?php if ($counts['ditutup'] > 0): ?>
        <button class="filter-pill" data-filter="ditutup" onclick="filterReports('ditutup',this)">
            <span class="fp-dot" style="background:#6B7280;"></span>
            Ditutup <span class="fp-count"><?= $counts['ditutup'] ?></span>
        </button>
        <?php endif; ?>
    </div>

    <!-- STATS BAND -->
    <div class="stats-band fade-up delay-1">
        <div class="stat-card active-card">
            <div>
                <div class="stat-num"><?= $counts['total'] ?></div>
                <div class="stat-label">Total Laporan</div>
            </div>
            <div class="stat-dot" style="background:var(--amber);"></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-num"><?= $counts['diproses'] ?></div>
                <div class="stat-label">Diproses</div>
            </div>
            <div class="stat-dot" style="background:#F59E0B;"></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-num"><?= $counts['ditemukan'] ?></div>
                <div class="stat-label">Ditemukan</div>
            </div>
            <div class="stat-dot" style="background:#3B82F6;"></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-num"><?= $counts['selesai'] ?></div>
                <div class="stat-label">Selesai</div>
            </div>
            <div class="stat-dot" style="background:#10B981;"></div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="main-grid fade-up delay-2">

        <!-- LEFT: LIST -->
        <div>
            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Cari no. laporan, barang, lokasi..." oninput="searchReports(this.value)">
            </div>

            <div class="report-list" id="reportList">
                <?php if (empty($reports)): ?>
                <div class="empty-list">
                    <i class="bi bi-inbox"></i>
                    <p>Belum ada laporan. <a href="index_pelapor.php#form-laporan" style="color:var(--amber);">Buat laporan sekarang</a></p>
                </div>
                <?php else: ?>
                <?php foreach ($reports as $r):
                    $isActive = $r['no_laporan'] === ($active['no_laporan'] ?? '');
                    $sc = statusColor($r['status']);
                    $sb = statusBg($r['status']);
                ?>
                <a href="?no=<?= urlencode($r['no_laporan']) ?>"
                   class="rp-item <?= $isActive ? 'active' : '' ?>"
                   data-status="<?= htmlspecialchars($r['status']) ?>"
                   data-search="<?= strtolower(htmlspecialchars($r['no_laporan'].' '.$r['nama_barang'].' '.$r['lokasi_hilang'])) ?>"
                   onclick="selectReport(event, this, <?= htmlspecialchars(json_encode($r)) ?>)">
                    <div><span class="rp-no"><?= htmlspecialchars($r['no_laporan']) ?></span></div>
                    <div class="rp-name"><?= htmlspecialchars($r['nama_barang']) ?></div>
                    <div class="rp-meta">
                        <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($r['lokasi_hilang']) ?></span>
                        <span><i class="bi bi-calendar3"></i> <?= date('d M Y', strtotime($r['waktu_hilang'] ?: $r['created_at'])) ?></span>
                    </div>
                    <div class="rp-footer">
                        <span class="rp-date">Dilaporkan <?= date('d/m/Y', strtotime($r['created_at'])) ?></span>
                        <span class="status-chip" style="background:<?= $sb ?>;color:<?= $sc ?>;">
                            <span style="width:5px;height:5px;border-radius:50%;background:<?= $sc ?>;display:inline-block;"></span>
                            <?= statusLabel($r['status']) ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($reports)): ?>
            <div style="margin-top:1rem;text-align:center;">
                <a href="index_pelapor.php#form-laporan" style="display:inline-flex;align-items:center;gap:6px;padding:.6rem 1.25rem;background:rgba(245,158,11,.1);color:var(--amber);border:1px solid rgba(245,158,11,.2);border-radius:10px;font-size:.8rem;font-weight:700;text-decoration:none;transition:all .2s;" onmouseover="this.style.background='rgba(245,158,11,.18)'" onmouseout="this.style.background='rgba(245,158,11,.1)'">
                    <i class="bi bi-plus-lg"></i> Tambah Laporan Baru
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: DETAIL -->
        <div class="detail-panel" id="detailPanel">
            <?php if ($active): ?>

            <!-- HEADER CARD -->
            <div class="dp-header">
                <div class="dp-no"><?= htmlspecialchars($active['no_laporan']) ?></div>
                <div class="dp-name"><?= htmlspecialchars($active['nama_barang']) ?></div>
                <div class="dp-date"><i class="bi bi-clock" style="font-size:.7rem;"></i> Dilaporkan <?= date('d M Y, H:i', strtotime($active['created_at'])) ?></div>
                <?php $sc = statusColor($active['status']); $sb = statusBg($active['status']); ?>
                <div class="dp-status-badge" style="background:<?= $sb ?>;color:<?= $sc ?>;">
                    <span class="pulse" style="background:<?= $sc ?>;"></span>
                    <?= statusLabel($active['status']) ?>
                </div>
            </div>

            <!-- PROGRESS TRACKER -->
            <div class="cl-card">
                <div class="card-head"><div class="card-head-title"><i class="bi bi-diagram-3"></i> Progres Laporan</div></div>
                <div class="card-body">
                    <?php
                    $step = statusStep($active['status']);
                    $ditutup = $active['status'] === 'ditutup';
                    $steps = [
                        ['icon'=>'bi-file-earmark-check','label'=>'Laporan Diterima','desc'=>'Laporan kamu sudah masuk ke sistem dan sedang menunggu tinjauan petugas.'],
                        ['icon'=>'bi-search-heart','label'=>'Sedang Diproses','desc'=>'Petugas aktif mencari dan mencocokkan barang di stasiun terkait.'],
                        ['icon'=>'bi-box-seam','label'=>'Barang Ditemukan','desc'=>'Barangmu berhasil ditemukan! Segera hubungi petugas untuk pengambilan.'],
                        ['icon'=>'bi-check-circle','label'=>'Selesai','desc'=>'Barang sudah dikembalikan ke pemilik. Laporan ditutup dengan sukses.'],
                    ];
                    ?>
                    <?php if ($ditutup): ?>
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;background:rgba(107,114,128,.08);border:1px solid rgba(107,114,128,.2);border-radius:10px;font-size:.8rem;color:#9CA3AF;">
                        <i class="bi bi-x-circle-fill" style="font-size:1.1rem;"></i>
                        <span>Laporan ini telah ditutup. Hubungi petugas jika masih membutuhkan bantuan.</span>
                    </div>
                    <?php else: ?>
                    <div class="progress-track">
                        <?php foreach ($steps as $i => $s):
                            $sn = $i + 1;
                            $cls = $sn < $step ? 'done' : ($sn === $step ? 'current' : '');
                        ?>
                        <div class="pt-step <?= $cls ?>">
                            <div class="pt-icon">
                                <?php if ($sn < $step): ?>
                                    <i class="bi bi-check-lg"></i>
                                <?php else: ?>
                                    <i class="bi <?= $s['icon'] ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="pt-label"><?= $s['label'] ?></div>
                                <div class="pt-desc"><?= $s['desc'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DETAIL INFO -->
            <div class="cl-card">
                <div class="card-head"><div class="card-head-title"><i class="bi bi-card-list"></i> Detail Barang</div></div>
                <div class="card-body">
                    <div class="info-row">
                        <div class="info-icon" style="background:rgba(245,158,11,.1);color:var(--amber);"><i class="bi bi-bag"></i></div>
                        <div><div class="info-label">Nama Barang</div><div class="info-val"><?= htmlspecialchars($active['nama_barang']) ?></div></div>
                    </div>
                    <?php if (!empty($active['deskripsi'])): ?>
                    <div class="info-row">
                        <div class="info-icon" style="background:rgba(96,165,250,.1);color:#60A5FA;"><i class="bi bi-card-text"></i></div>
                        <div><div class="info-label">Deskripsi</div><div class="info-val"><?= nl2br(htmlspecialchars($active['deskripsi'])) ?></div></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <div class="info-icon" style="background:rgba(244,114,182,.1);color:#F472B6;"><i class="bi bi-geo-alt-fill"></i></div>
                        <div><div class="info-label">Lokasi Kehilangan</div><div class="info-val"><?= htmlspecialchars($active['lokasi_hilang']) ?></div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon" style="background:rgba(52,211,153,.1);color:#34D399;"><i class="bi bi-calendar-event"></i></div>
                        <div><div class="info-label">Waktu Kehilangan</div><div class="info-val"><?= date('d M Y', strtotime($active['waktu_hilang'] ?: $active['created_at'])) ?></div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon" style="background:rgba(167,139,250,.1);color:#A78BFA;"><i class="bi bi-clock-history"></i></div>
                        <div><div class="info-label">Terakhir Diperbarui</div><div class="info-val"><?= date('d M Y, H:i', strtotime($active['updated_at'] ?: $active['created_at'])) ?> WIB</div></div>
                    </div>
                </div>
            </div>

            <!-- FOTO -->
            <?php if (!empty($active['foto_barang'])): ?>
            <div class="cl-card">
                <div class="card-head"><div class="card-head-title"><i class="bi bi-image"></i> Foto Barang</div></div>
                <div class="foto-box" style="border-radius:0 0 var(--card-r) var(--card-r);border:none;">
                    <img src="<?= htmlspecialchars($active['foto_barang']) ?>" alt="Foto <?= htmlspecialchars($active['nama_barang']) ?>" style="width:100%;max-height:260px;object-fit:cover;">
                </div>
            </div>
            <?php endif; ?>

            <!-- MAP -->
            <div class="cl-card map-container">
                <div class="card-head"><div class="card-head-title"><i class="bi bi-map"></i> Peta Lokasi</div></div>
                <div id="leafletMap"></div>
            </div>

            <!-- CTA HELP -->
            <div class="cta-help">
                <div class="cta-help-icon"><i class="bi bi-headset"></i></div>
                <div>
                    <div style="font-size:.83rem;font-weight:700;color:var(--text);">Butuh bantuan?</div>
                    <div style="font-size:.73rem;color:var(--text-3);margin-top:2px;">Hubungi petugas Lost &amp; Found langsung</div>
                </div>
                <a href="stations.php" class="cta-help-btn">Info Stasiun</a>
            </div>

            <?php else: ?>
            <div class="no-selection">
                <i class="bi bi-radar"></i>
                <strong style="color:var(--text-2);font-size:.9rem;">Pilih laporan</strong>
                <p>Klik salah satu laporan di sebelah kiri untuk melihat detail dan status tracking.</p>
                <a href="index_pelapor.php#form-laporan" style="display:inline-flex;align-items:center;gap:6px;padding:.6rem 1.25rem;background:var(--amber);color:var(--navy);border-radius:10px;font-size:.8rem;font-weight:700;text-decoration:none;margin-top:.5rem;">
                    <i class="bi bi-plus-lg"></i> Buat Laporan
                </a>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /main-grid -->
</div><!-- /page-wrap -->

<!-- BOTTOM NAV -->
<nav class="bottom-nav" role="navigation" aria-label="Navigasi bawah">
    <a href="index_pelapor.php" class="bn-item" title="Beranda"><i class="bi bi-house"></i><span>Beranda</span></a>
    <a href="stations.php"      class="bn-item" title="Stasiun"><i class="bi bi-train-front"></i><span>Stasiun</span></a>
    <a href="news.php"          class="bn-item" title="Berita"><i class="bi bi-newspaper"></i><span>Berita</span></a>
    <a href="faq.php"           class="bn-item" title="FAQ"><i class="bi bi-question-circle"></i><span>FAQ</span></a>
    <a href="about.php"         class="bn-item" title="Tentang"><i class="bi bi-info-circle"></i><span>Tentang</span></a>
</nav>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* ── LEAFLET MAP ── */
<?php if ($active): ?>
(function(){
    const mapEl = document.getElementById('leafletMap');
    if (!mapEl) return;

    /* Koordinat default: Bekasi Station jika tidak diketahui */
    const defaultCoords = [-6.2406, 106.9921];

    const locationCoords = {
        'stasiun bekasi':    [-6.2406, 106.9921],
        'bekasi':            [-6.2406, 106.9921],
        'manggarai':         [-6.2139, 106.8501],
        'bogor':             [-6.5942, 106.7893],
        'jakarta kota':      [-6.1376, 106.8130],
        'depok':             [-6.3946, 106.8229],
        'citayam':           [-6.4476, 106.8285],
        'cawang':            [-6.2445, 106.8680],
        'pasar minggu':      [-6.2829, 106.8409],
        'tanah abang':       [-6.1874, 106.8128],
        'duri':              [-6.1697, 106.7897],
        'angke':             [-6.1528, 106.7973],
        'tangerang':         [-6.1783, 106.6319],
        'cikarang':          [-6.2558, 107.1406],
    };

    const locRaw = <?= json_encode(strtolower($active['lokasi_hilang'])) ?>;
    let coords = defaultCoords;
    for (const [key, val] of Object.entries(locationCoords)) {
        if (locRaw.includes(key)) { coords = val; break; }
    }

    const map = L.map('leafletMap', { zoomControl:true, scrollWheelZoom:false }).setView(coords, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution:'© OpenStreetMap',
        maxZoom:18
    }).addTo(map);

    const icon = L.divIcon({
        className:'',
        html:`<div style="background:#F59E0B;width:14px;height:14px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.4);"></div>`,
        iconSize:[14,14], iconAnchor:[7,7]
    });

    const marker = L.marker(coords, {icon}).addTo(map);
    marker.bindPopup(`
        <div style="font-family:'DM Sans',sans-serif;padding:4px 2px;min-width:160px;">
            <div style="font-size:.75rem;font-weight:700;color:#F59E0B;margin-bottom:3px;">📍 Lokasi Hilang</div>
            <div style="font-size:.82rem;font-weight:600;color:#1E293B;"><?= htmlspecialchars($active['lokasi_hilang']) ?></div>
            <div style="font-size:.7rem;color:#64748B;margin-top:3px;"><?= date('d M Y', strtotime($active['waktu_hilang'] ?: $active['created_at'])) ?></div>
        </div>
    `).openPopup();
})();
<?php endif; ?>

/* ── FILTER REPORTS ── */
let currentFilter = 'semua';
function filterReports(filter, btn) {
    currentFilter = filter;
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    applyFilters();
}

function searchReports(q) { applyFilters(q); }

function applyFilters(q) {
    q = (q || document.getElementById('searchInput').value).toLowerCase();
    document.querySelectorAll('.rp-item').forEach(item => {
        const statusMatch = currentFilter === 'semua' || item.dataset.status === currentFilter;
        const searchMatch = !q || item.dataset.search.includes(q);
        item.style.display = (statusMatch && searchMatch) ? '' : 'none';
    });
}

/* ── SELECT REPORT (SPA-style update on mobile) ── */
function selectReport(e, el, data) {
    if (window.innerWidth > 991) return; /* let normal navigation handle desktop */
    /* on mobile, just navigate normally */
}
</script>

<script>
(function(){
    const DEFAULTS={theme:'dark',accent:'amber',fontSize:'md',compact:false,anim:true,notif:true};
    let S=Object.assign({},DEFAULTS);
    const ACCENT_COLORS={amber:'#F59E0B',blue:'#3B82F6',green:'#10B981',purple:'#8B5CF6',rose:'#EC4899'};
    const ACCENT_NAMES={amber:'Amber',blue:'Biru',green:'Hijau',purple:'Ungu',rose:'Rose'};
    const THEME_NAMES={dark:'Gelap',light:'Terang',system:'Sistem'};
    const THEME_ICONS={dark:'🌙',light:'☀️',system:'💻'};
    function loadSettings(){try{const s=localStorage.getItem('cl_settings');if(s)S=Object.assign({},DEFAULTS,JSON.parse(s));}catch(e){}}
    function applySettings(){
        const html=document.documentElement;
        let t=S.theme==='system'?(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):S.theme;
        html.setAttribute('data-theme',t);html.setAttribute('data-accent',S.accent);
        html.setAttribute('data-fontsize',S.fontSize);html.setAttribute('data-compact',S.compact?'true':'false');
        html.setAttribute('data-anim',S.anim?'on':'off');
        const pip=document.getElementById('notifPip');if(pip)pip.style.display=S.notif?'':'none';
    }
    function syncPanel(){
        document.querySelectorAll('.theme-card').forEach(c=>c.classList.toggle('active',c.dataset.theme===S.theme));
        document.querySelectorAll('.accent-dot').forEach(d=>d.classList.toggle('active',d.dataset.accent===S.accent));
        document.querySelectorAll('.fs-btn').forEach(b=>b.classList.toggle('active',b.dataset.size===S.fontSize));
        const ta=document.getElementById('toggleAnim'),tn=document.getElementById('toggleNotif');
        if(ta)ta.checked=S.anim;if(tn)tn.checked=S.notif;
        const ac=ACCENT_COLORS[S.accent]||'#F59E0B';
        document.getElementById('spPreviewThumb').style.background=`linear-gradient(135deg,${ac},rgba(255,255,255,.25))`;
        document.getElementById('spPreviewTitle').textContent=`${THEME_ICONS[S.theme]} ${THEME_NAMES[S.theme]} · ${ACCENT_NAMES[S.accent]}`;
        document.getElementById('spPreviewSub').textContent=`Font: ${S.fontSize==='sm'?'Kecil':S.fontSize==='lg'?'Besar':'Sedang'}`;
        const ab=document.getElementById('spApplyBtn');if(ab)ab.style.background=ac;
    }
    window.setTheme    = t=>{S.theme=t;applySettings();syncPanel();};
    window.setAccent   = a=>{S.accent=a;applySettings();syncPanel();};
    window.setFontSize = f=>{S.fontSize=f;applySettings();syncPanel();};
    window.setToggle   = (k,v)=>{if(k==='anim')S.anim=v;else if(k==='notif')S.notif=v;applySettings();syncPanel();};
    window.openSettings=()=>{syncPanel();document.getElementById('settingsPanel').classList.add('open');document.getElementById('spOverlay').classList.add('open');document.body.style.overflow='hidden';};
    window.closeSettings=()=>{document.getElementById('settingsPanel').classList.remove('open');document.getElementById('spOverlay').classList.remove('open');document.body.style.overflow='';};
    window.saveSettings=()=>{try{localStorage.setItem('cl_settings',JSON.stringify(S));}catch(e){}closeSettings();showToast('✅ Pengaturan tersimpan!');};
    window.resetSettings=()=>{S=Object.assign({},DEFAULTS);applySettings();syncPanel();try{localStorage.removeItem('cl_settings');}catch(e){}showToast('🔄 Reset ke default');};
    function showToast(m){const t=document.getElementById('spToast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2800);}
    document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key===','){e.preventDefault();openSettings();}if(e.key==='Escape')closeSettings();});
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>{if(S.theme==='system')applySettings();});
    loadSettings();applySettings();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>