<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$pdo  = getDB();
$user = getCurrentUser();
$role = $user['role'] ?? 'pelapor';
$firstName = explode(' ', $user['nama'] ?? 'User')[0];

$stationGroups = [
  ['line'=>'Bogor Line','color'=>'#EF4444','bg'=>'rgba(239,68,68,0.1)','emoji'=>'🔴','stations'=>[
    ['name'=>'Stasiun Bogor','kode'=>'BOO','alamat'=>'Jl. Mayor Oking No.1, Bogor Tengah','jam'=>'04.00–23.00','telp'=>'0251-8321376','petugas'=>'Bpk. Sudirman','item'=>12],
    ['name'=>'Stasiun Citayam','kode'=>'CTA','alamat'=>'Jl. Raya Citayam, Depok','jam'=>'04.30–22.30','telp'=>'021-7777001','petugas'=>'Ibu Ratna','item'=>3],
    ['name'=>'Stasiun Depok','kode'=>'DPK','alamat'=>'Jl. Stasiun Depok, Pancoran Mas','jam'=>'04.00–23.00','telp'=>'021-7520550','petugas'=>'Bpk. Hendra','item'=>7],
    ['name'=>'Stasiun Manggarai','kode'=>'MRI','alamat'=>'Jl. Minangkabau, Jakarta Selatan','jam'=>'04.00–24.00','telp'=>'021-8290288','petugas'=>'Bpk. Agus','item'=>18],
    ['name'=>'Stasiun Gambir','kode'=>'GMR','alamat'=>'Jl. Medan Merdeka Timur, Jakarta Pusat','jam'=>'05.00–22.00','telp'=>'021-3458820','petugas'=>'Ibu Sari','item'=>9],
    ['name'=>'Stasiun Jakarta Kota','kode'=>'JAKK','alamat'=>'Jl. Stasiun Kota No.1, Jakarta Barat','jam'=>'04.00–24.00','telp'=>'021-6920062','petugas'=>'Bpk. Wahyu','item'=>14],
  ]],
  ['line'=>'Bekasi Line','color'=>'#3B82F6','bg'=>'rgba(59,130,246,0.1)','emoji'=>'🔵','stations'=>[
    ['name'=>'Stasiun Bekasi','kode'=>'BKS','alamat'=>'Jl. Stasiun Bekasi, Bekasi Timur','jam'=>'04.00–23.00','telp'=>'021-8802358','petugas'=>'Bpk. Darmawan','item'=>8],
    ['name'=>'Stasiun Jatinegara','kode'=>'JNG','alamat'=>'Jl. Jatinegara Barat, Jakarta Timur','jam'=>'04.30–23.30','telp'=>'021-8193451','petugas'=>'Ibu Dewi','item'=>5],
    ['name'=>'Stasiun Angke','kode'=>'AK','alamat'=>'Jl. Angke, Tambora, Jakarta Barat','jam'=>'05.00–22.00','telp'=>'021-6913271','petugas'=>'Bpk. Rudi','item'=>2],
  ]],
  ['line'=>'Tangerang Line','color'=>'#F59E0B','bg'=>'rgba(245,158,11,0.1)','emoji'=>'🟡','stations'=>[
    ['name'=>'Stasiun Tangerang','kode'=>'TNG','alamat'=>'Jl. Daan Mogot, Tangerang','jam'=>'04.30–22.30','telp'=>'021-5522901','petugas'=>'Bpk. Fauzi','item'=>5],
    ['name'=>'Stasiun Duri','kode'=>'DU','alamat'=>'Jl. Duri, Tambora, Jakarta Barat','jam'=>'05.00–22.00','telp'=>'021-5672341','petugas'=>'Ibu Ningsih','item'=>3],
  ]],
  ['line'=>'Cikarang Line','color'=>'#10B981','bg'=>'rgba(16,185,129,0.1)','emoji'=>'🟢','stations'=>[
    ['name'=>'Stasiun Cikarang','kode'=>'CKR','alamat'=>'Jl. Cikarang Baru, Bekasi','jam'=>'04.30–22.30','telp'=>'021-8901234','petugas'=>'Bpk. Susanto','item'=>3],
    ['name'=>'Stasiun Tambun','kode'=>'TB','alamat'=>'Jl. Raya Tambun, Bekasi Timur','jam'=>'05.00–21.00','telp'=>'021-8830012','petugas'=>'Ibu Marlina','item'=>1],
  ]],
];

$lineFilter = $_GET['line'] ?? '';
$search     = trim($_GET['q'] ?? '');
$totalItem  = 0;
foreach($stationGroups as $g) foreach($g['stations'] as $s) $totalItem += $s['item'];
$totalStations = array_sum(array_map(fn($g)=>count($g['stations']), $stationGroups));
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daftar Stasiun — CommuterLink Nusantara</title>
<link rel="icon" href="uploads/favicon.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* CSS VARIABLES */
:root {
    --navy:#0B1F3A; --navy-2:#152d52; --navy-3:#1e3d6e;
    --gold:#F0A500; --gold-lt:#F7C948;
    --bg:#F0F4F8; --card-bg:#FFFFFF;
    --text:#1E293B; --text-2:#475569; --text-3:#94A3B8; --border:#E2E8F0;
    --success:#10B981; --danger:#EF4444; --info:#3B82F6; --warning:#F59E0B;
}
[data-theme="dark"]  { --bg:#0B1626; --card-bg:#112038; --text:#E8F0FE; --text-2:#94AEC8; --text-3:#506882; --border:rgba(255,255,255,0.07); }
[data-accent="blue"]   { --gold:#3B82F6; --gold-lt:#60A5FA; }
[data-accent="green"]  { --gold:#10B981; --gold-lt:#34D399; }
[data-accent="purple"] { --gold:#8B5CF6; --gold-lt:#A78BFA; }
[data-accent="red"]    { --gold:#EF4444; --gold-lt:#FC8181; }
[data-accent="rose"]   { --gold:#EC4899; --gold-lt:#F472B6; }
[data-fontsize="sm"]   { font-size:14px; }
[data-fontsize="md"]   { font-size:16px; }
[data-fontsize="lg"]   { font-size:18px; }
[data-compact="true"] .page-content { padding:1.25rem; }
[data-compact="true"] .page-banner  { padding:1.25rem 1.5rem; }
[data-compact="true"] .station-card { border-radius:10px; }
[data-anim="off"] * { animation:none !important; transition:none !important; }

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; transition:background .3s,color .3s; }


/* PAGE LAYOUT */
.page-content { padding:2rem; max-width:1280px; margin:0 auto; }

/* BREADCRUMB */
.breadcrumb-nav { display:flex; align-items:center; gap:6px; font-size:.75rem; color:var(--text-3); margin-bottom:1.25rem; }
.breadcrumb-nav a { color:var(--text-3); text-decoration:none; transition:color .2s; }
.breadcrumb-nav a:hover { color:var(--gold); }

/* PAGE BANNER */
.page-banner { border-radius:18px; padding:1.75rem 2.5rem; margin-bottom:1.75rem; position:relative; overflow:hidden; background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%); border:1px solid rgba(255,255,255,.07); transition:padding .3s; }
.page-banner::before { content:''; position:absolute; width:280px; height:280px; background:radial-gradient(circle,rgba(240,165,0,.2) 0%,transparent 70%); top:-80px; right:-60px; border-radius:50%; pointer-events:none; }
.page-banner::after { content:'🚉'; position:absolute; right:2.5rem; bottom:-8px; font-size:6.5rem; opacity:.08; pointer-events:none; }
.banner-label { font-size:.68rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--gold); margin-bottom:.4rem; }
.banner-title { font-size:1.55rem; font-weight:800; color:#fff; letter-spacing:-.5px; }
.banner-title span { color:var(--gold-lt); }
.banner-sub { font-size:.82rem; color:rgba(255,255,255,.45); margin-top:.4rem; }

/* SUMMARY STAT CARDS — identik reports.php */
.stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.75rem; max-width:680px; }
.stat-card { background:var(--card-bg); border:1px solid var(--border); border-radius:14px; padding:1.15rem 1.2rem; transition:transform .2s,box-shadow .2s,background .3s,border-color .3s; }
.stat-card:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(0,0,0,.1); }
[data-theme="dark"] .stat-card:hover { box-shadow:0 10px 28px rgba(0,0,0,.3); }
.stat-icon { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; margin-bottom:.9rem; }
.ic-gold   { background:rgba(240,165,0,.12); color:var(--gold); }
.ic-blue   { background:rgba(59,130,246,.12); color:#3B82F6; }
.ic-green  { background:rgba(16,185,129,.12); color:#10B981; }
.stat-num  { font-size:1.8rem; font-weight:800; color:var(--text); line-height:1; }
.stat-label { font-size:.72rem; font-weight:600; color:var(--text-3); margin-top:4px; }

/* FILTER BAR  */
.filter-bar { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1.5rem; align-items:center; background:var(--card-bg); border:1px solid var(--border); border-radius:14px; padding:1rem 1.25rem; transition:background .3s,border-color .3s; }
.search-wrap { flex:1; min-width:220px; position:relative; }
.search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-3); pointer-events:none; }
.cl-input { width:100%; padding:.6rem 1rem .6rem 2.4rem; border:1.5px solid var(--border); border-radius:10px; font-size:.83rem; background:var(--bg); color:var(--text); outline:none; transition:border-color .2s; font-family:inherit; }
.cl-input:focus { border-color:var(--gold); }
.cl-input::placeholder { color:var(--text-3); }
.line-tab { display:inline-flex; align-items:center; gap:6px; padding:.45rem .9rem; border-radius:99px; border:1.5px solid var(--border); background:transparent; font-size:.78rem; font-weight:700; color:var(--text-2); cursor:pointer; text-decoration:none; transition:all .2s; white-space:nowrap; }
.line-tab:hover { border-color:var(--gold); color:var(--gold); }
.line-tab.active { background:var(--gold); color:var(--navy); border-color:var(--gold); font-weight:800; }
.line-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }

/* LINE SECTION */
.line-section { margin-bottom:2rem; }
.line-header { display:flex; align-items:center; gap:12px; margin-bottom:1rem; flex-wrap:wrap; }
.line-badge { display:inline-flex; align-items:center; gap:8px; padding:.5rem 1.1rem; border-radius:99px; font-size:.8rem; font-weight:700; }
.line-meta { font-size:.75rem; color:var(--text-3); }

/* STATION CARDS — mirip chart-card reports.php */
.stations-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(290px,1fr)); gap:1rem; }
.station-card { background:var(--card-bg); border:1px solid var(--border); border-radius:14px; overflow:hidden; transition:transform .2s,box-shadow .2s,border-color .2s,background .3s; cursor:pointer; }
.station-card:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(0,0,0,.1); border-color:rgba(240,165,0,.35); }
[data-theme="dark"] .station-card:hover { box-shadow:0 10px 28px rgba(0,0,0,.3); }
.sc-top { padding:1.1rem 1.2rem; display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
.sc-name { font-size:.88rem; font-weight:800; color:var(--text); line-height:1.2; }
.sc-kode-sub { font-size:.65rem; font-weight:700; color:var(--text-3); margin-top:2px; }
.sc-kode-tag { padding:3px 9px; border-radius:6px; background:var(--bg); font-size:.7rem; font-weight:800; color:var(--text-2); font-family:monospace; border:1px solid var(--border); }
.sc-body { padding:.9rem 1.2rem; border-top:1px solid var(--border); background:var(--bg); display:flex; flex-direction:column; gap:.55rem; transition:background .3s; }
.sc-row { display:flex; align-items:flex-start; gap:8px; font-size:.75rem; color:var(--text-2); }
.sc-row i { font-size:.78rem; color:var(--text-3); flex-shrink:0; margin-top:1px; }
.sc-footer { padding:.8rem 1.2rem; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.item-badge { font-size:.72rem; font-weight:700; padding:3px 10px; border-radius:99px; }
.tel-link { font-size:.75rem; font-weight:700; color:var(--text-2); text-decoration:none; display:flex; align-items:center; gap:4px; transition:color .2s; }
.tel-link:hover { color:var(--gold); }

/*  MODAL  */
.modal-overlay { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.65); backdrop-filter:blur(6px); align-items:flex-start; justify-content:center; padding:2.5rem 1rem; overflow-y:auto; }
.modal-overlay.show { display:flex; }
.modal-sheet { background:var(--card-bg); border:1px solid var(--border); border-radius:18px; width:100%; max-width:540px; overflow:hidden; animation:slideUp .3s ease; transition:background .3s; }
@keyframes slideUp { from { transform:translateY(30px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.modal-head { padding:1.2rem 1.5rem; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.modal-head-title { font-size:1rem; font-weight:800; color:var(--text); }
.modal-close-btn { width:30px; height:30px; border-radius:8px; border:1px solid var(--border); background:transparent; display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--text-3); transition:all .2s; }
.modal-close-btn:hover { border-color:var(--gold); color:var(--gold); }
.modal-body-inner { padding:1.5rem; }
.modal-line-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:99px; font-size:.75rem; font-weight:700; margin-bottom:1rem; }
.m-grid { display:grid; grid-template-columns:1fr 1fr; gap:.9rem; margin-bottom:1.2rem; }
.m-field label { font-size:.67rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--text-3); display:block; margin-bottom:4px; }
.m-field .val { font-size:.83rem; font-weight:600; color:var(--text); }
.m-actions { display:flex; gap:.75rem; margin-top:1.25rem; flex-wrap:wrap; }
.btn-gold { display:inline-flex; align-items:center; gap:6px; padding:.65rem 1.2rem; background:var(--gold); color:var(--navy); border:none; border-radius:9px; font-size:.82rem; font-weight:700; text-decoration:none; cursor:pointer; font-family:inherit; transition:all .2s; }
.btn-gold:hover { background:var(--gold-lt); color:var(--navy); transform:translateY(-2px); box-shadow:0 5px 16px rgba(240,165,0,.3); }
.btn-outline { display:inline-flex; align-items:center; gap:6px; padding:.65rem 1.2rem; background:transparent; color:var(--text-2); border:1.5px solid var(--border); border-radius:9px; font-size:.82rem; font-weight:700; text-decoration:none; cursor:pointer; font-family:inherit; transition:all .2s; }
.btn-outline:hover { border-color:var(--gold); color:var(--gold); }

/* ANIMATIONS  */
.fade-up { opacity:0; transform:translateY(14px); animation:fadeUp .4s ease forwards; }
@keyframes fadeUp { to { opacity:1; transform:translateY(0); } }
.delay-1 { animation-delay:.05s; }
.delay-2 { animation-delay:.1s; }
.delay-3 { animation-delay:.15s; }

/* FOOTER  */
footer { padding:1.1rem 2rem; border-top:1px solid var(--border); font-size:.73rem; color:var(--text-3); text-align:center; }

/* SETTINGS PANEL */
.sp-overlay { position:fixed; inset:0; z-index:8888; background:rgba(0,0,0,0); pointer-events:none; transition:background .35s; }
.sp-overlay.open { background:rgba(0,0,0,.55); pointer-events:all; backdrop-filter:blur(4px); }
.settings-panel { position:fixed; top:0; right:0; bottom:0; z-index:8889; width:360px; max-width:92vw; background:#0E1E35; border-left:1px solid rgba(255,255,255,.09); display:flex; flex-direction:column; transform:translateX(100%); transition:transform .38s cubic-bezier(.22,1,.36,1); box-shadow:-24px 0 80px rgba(0,0,0,.55); }
.settings-panel.open { transform:translateX(0); }
[data-theme="light"] .settings-panel { background:#1A2E4A; }
.sp-header { padding:1.3rem 1.5rem 1.1rem; border-bottom:1px solid rgba(255,255,255,.08); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.sp-title { font-size:1rem; font-weight:800; color:#fff; display:flex; align-items:center; gap:9px; }
.sp-title i { color:#F59E0B; }
.sp-close { width:32px; height:32px; background:rgba(255,255,255,.07); border:none; border-radius:8px; color:rgba(255,255,255,.5); font-size:1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .2s,color .2s; }
.sp-close:hover { background:rgba(255,255,255,.13); color:#fff; }
.sp-body { flex:1; overflow-y:auto; padding:1.25rem 1.5rem; }
.sp-body::-webkit-scrollbar { width:4px; } .sp-body::-webkit-scrollbar-thumb { background:rgba(255,255,255,.12); border-radius:4px; }
.sp-section { margin-bottom:1.5rem; }
.sp-section-label { font-size:.65rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,.35); margin-bottom:.75rem; display:flex; align-items:center; gap:8px; }
.sp-section-label::after { content:''; flex:1; height:1px; background:rgba(255,255,255,.07); }
.theme-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.6rem; }
.theme-card { position:relative; padding:.8rem .6rem .65rem; border-radius:12px; border:2px solid rgba(255,255,255,.08); cursor:pointer; background:rgba(255,255,255,.04); text-align:center; transition:all .2s; }
.theme-card:hover { border-color:rgba(255,255,255,.2); background:rgba(255,255,255,.08); }
.theme-card.active { border-color:#F59E0B; background:rgba(245,158,11,.1); }
.theme-card-icon { font-size:1.6rem; margin-bottom:5px; display:block; }
.theme-card-name { font-size:.7rem; font-weight:700; color:rgba(255,255,255,.7); }
.theme-card.active .theme-card-name { color:#F59E0B; }
.theme-check { position:absolute; top:5px; right:5px; width:16px; height:16px; background:#F59E0B; border-radius:50%; display:none; align-items:center; justify-content:center; font-size:.5rem; color:#000; }
.theme-card.active .theme-check { display:flex; }
.accent-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:.6rem; }
.accent-dot { width:100%; aspect-ratio:1; border-radius:50%; cursor:pointer; border:3px solid transparent; transition:transform .18s,border-color .18s; }
.accent-dot:hover { transform:scale(1.15); }
.accent-dot.active { border-color:#fff; box-shadow:0 0 0 3px rgba(255,255,255,.25); }
.accent-label { text-align:center; font-size:.62rem; color:rgba(255,255,255,.45); margin-top:5px; font-weight:600; }
.fontsize-row { display:flex; gap:.5rem; }
.fs-btn { flex:1; padding:.55rem .5rem; border-radius:10px; border:2px solid rgba(255,255,255,.08); background:rgba(255,255,255,.04); color:rgba(255,255,255,.55); cursor:pointer; font-family:inherit; font-weight:700; transition:all .18s; text-align:center; }
.fs-btn:hover { border-color:rgba(255,255,255,.22); color:#fff; }
.fs-btn.active { border-color:#F59E0B; background:rgba(245,158,11,.1); color:#F59E0B; }
.fs-btn span { display:block; } .fs-btn .fs-sample { font-weight:400; color:rgba(255,255,255,.3); margin-top:1px; }
.fs-btn.active .fs-sample { color:rgba(245,158,11,.5); }
.sp-toggle-row { display:flex; align-items:center; justify-content:space-between; padding:.75rem 0; border-bottom:1px solid rgba(255,255,255,.06); }
.sp-toggle-row:last-child { border-bottom:none; }
.sp-toggle-info { display:flex; align-items:center; gap:10px; }
.sp-toggle-icon { width:32px; height:32px; border-radius:8px; background:rgba(255,255,255,.07); display:flex; align-items:center; justify-content:center; font-size:.9rem; color:rgba(255,255,255,.55); flex-shrink:0; }
.sp-toggle-label { font-size:.82rem; font-weight:700; color:rgba(255,255,255,.85); }
.sp-toggle-sub { font-size:.68rem; color:rgba(255,255,255,.35); margin-top:1px; }
.sp-switch { position:relative; width:40px; height:22px; flex-shrink:0; }
.sp-switch input { opacity:0; width:0; height:0; }
.sp-slider { position:absolute; inset:0; cursor:pointer; background:rgba(255,255,255,.12); border-radius:22px; transition:background .25s; }
.sp-slider::before { content:''; position:absolute; height:16px; width:16px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:transform .25s; box-shadow:0 1px 4px rgba(0,0,0,.3); }
input:checked+.sp-slider { background:#F59E0B; }
input:checked+.sp-slider::before { transform:translateX(18px); }
.sp-preview { margin:0 0 1rem; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07); border-radius:12px; padding:1rem; display:flex; align-items:center; gap:12px; }
.sp-preview-thumb { width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
.sp-preview-text .sp-preview-title { font-size:.82rem; font-weight:700; color:rgba(255,255,255,.8); }
.sp-preview-text .sp-preview-sub { font-size:.7rem; color:rgba(255,255,255,.4); margin-top:2px; }
.sp-footer { padding:1rem 1.5rem; border-top:1px solid rgba(255,255,255,.08); display:flex; gap:.6rem; flex-shrink:0; }
.sp-btn-reset { flex:1; padding:.65rem; border-radius:10px; border:1.5px solid rgba(255,255,255,.1); background:none; color:rgba(255,255,255,.5); font-family:inherit; font-size:.8rem; font-weight:700; cursor:pointer; transition:all .2s; }
.sp-btn-reset:hover { border-color:rgba(255,255,255,.25); color:#fff; background:rgba(255,255,255,.05); }
.sp-btn-apply { flex:2; padding:.65rem; border-radius:10px; border:none; background:#F59E0B; color:#0D1B2E; font-family:inherit; font-size:.8rem; font-weight:800; cursor:pointer; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:6px; }
.sp-btn-apply:hover { background:#FCD34D; transform:translateY(-1px); }
.sp-toast { position:fixed; bottom:1.5rem; left:50%; transform:translateX(-50%) translateY(20px); background:#1E3357; border:1px solid rgba(245,158,11,.35); color:#FCD34D; padding:.65rem 1.2rem; border-radius:99px; font-size:.8rem; font-weight:700; display:flex; align-items:center; gap:8px; z-index:9999; opacity:0; pointer-events:none; transition:all .35s; box-shadow:0 8px 32px rgba(0,0,0,.4); }
.sp-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }

/* RESPONSIVE */
@media(max-width:991px)  { .topbar-nav { display:none; } .stat-grid { grid-template-columns:repeat(2,1fr); } }
@media(max-width:575px)  { .page-content { padding:1rem; } .topbar { padding:.65rem 1rem; } .stations-grid { grid-template-columns:1fr; } .stat-grid { grid-template-columns:1fr 1fr; } }
</style>
</head>
<body>


<main class="page-content">

  <!-- BREADCRUMB -->
  <div class="breadcrumb-nav fade-up">
    <a href="index_pelapor.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <i class="bi bi-chevron-right" style="font-size:.55rem;"></i>
    <span>Daftar Stasiun</span>
  </div>

  <!-- BANNER -->
  <div class="page-banner fade-up">
    <div class="banner-label">Jaringan Stasiun</div>
    <div class="banner-title">Daftar Stasiun &amp; <span>Posko L&amp;F</span></div>
    <div class="banner-sub">Informasi kontak dan jam operasional posko Lost &amp; Found di setiap stasiun komuter.</div>
  </div>

  <!-- STAT CARDS -->
  <div class="stat-grid fade-up delay-1">
    <div class="stat-card">
      <div class="stat-icon ic-gold"><i class="bi bi-diagram-3"></i></div>
      <div class="stat-num">4</div>
      <div class="stat-label">Koridor Aktif</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon ic-blue"><i class="bi bi-train-front"></i></div>
      <div class="stat-num"><?= $totalStations ?></div>
      <div class="stat-label">Total Stasiun</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon ic-green"><i class="bi bi-box-seam"></i></div>
      <div class="stat-num"><?= $totalItem ?></div>
      <div class="stat-label">Item Tersimpan</div>
    </div>
  </div>

  <!-- FILTER BAR -->
  <div class="filter-bar fade-up delay-2">
    <div style="display:flex;align-items:center;gap:6px;font-size:.8rem;font-weight:600;color:var(--text-2);flex-shrink:0;">
      <i class="bi bi-funnel" style="color:var(--gold);"></i> Filter:
    </div>
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" id="searchInput" class="cl-input" placeholder="Cari nama stasiun..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <a href="stations.php" class="line-tab <?= !$lineFilter ? 'active' : '' ?>">Semua</a>
    <?php foreach($stationGroups as $g): ?>
    <a href="?line=<?= urlencode($g['line']) ?>" class="line-tab <?= $lineFilter===$g['line'] ? 'active' : '' ?>">
      <span class="line-dot" style="background:<?= $g['color'] ?>;"></span> <?= $g['line'] ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- STATION GROUPS -->
  <?php foreach($stationGroups as $group):
    if($lineFilter && $lineFilter !== $group['line']) continue;
    $groupTotal = array_sum(array_column($group['stations'],'item'));
  ?>
  <div class="line-section fade-up delay-3">
    <div class="line-header">
      <span class="line-badge" style="background:<?= $group['bg'] ?>;color:<?= $group['color'] ?>;">
        <?= $group['emoji'].' '.$group['line'] ?>
      </span>
      <span class="line-meta"><?= count($group['stations']) ?> stasiun &nbsp;&middot;&nbsp; <?= $groupTotal ?> item tersimpan</span>
    </div>
    <div class="stations-grid">
      <?php foreach($group['stations'] as $s):
        $data = json_encode(array_merge($s,['line'=>$group['line'],'color'=>$group['color'],'bg'=>$group['bg'],'emoji'=>$group['emoji']]));
      ?>
      <div class="station-card" onclick='openStation(<?= htmlspecialchars($data,ENT_QUOTES) ?>)'>
        <div class="sc-top">
          <div>
            <div class="sc-name"><?= htmlspecialchars($s['name']) ?></div>
            <div class="sc-kode-sub">Kode: <?= $s['kode'] ?></div>
          </div>
          <span class="sc-kode-tag"><?= $s['kode'] ?></span>
        </div>
        <div class="sc-body">
          <div class="sc-row"><i class="bi bi-geo-alt"></i><span><?= htmlspecialchars($s['alamat']) ?></span></div>
          <div class="sc-row"><i class="bi bi-clock"></i><span>Posko L&amp;F: <?= $s['jam'] ?> WIB</span></div>
          <div class="sc-row"><i class="bi bi-person-badge"></i><span>PIC: <?= htmlspecialchars($s['petugas']) ?></span></div>
        </div>
        <div class="sc-footer">
          <span class="item-badge" style="background:<?= $group['bg'] ?>;color:<?= $group['color'] ?>;"><?= $s['item'] ?> item tersimpan</span>
          <a href="tel:<?= $s['telp'] ?>" class="tel-link" onclick="event.stopPropagation()">
            <i class="bi bi-telephone"></i> <?= $s['telp'] ?>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

</main>

<footer>&copy; <?= date('Y') ?> CommuterLink Nusantara. All Rights Reserved.</footer>

<!-- MODAL -->
<div class="modal-overlay" id="stationModal" onclick="closeModal(event)">
  <div class="modal-sheet" onclick="event.stopPropagation()">
    <div class="modal-head">
      <div class="modal-head-title" id="modalName">Detail Stasiun</div>
      <button class="modal-close-btn" onclick="document.getElementById('stationModal').classList.remove('show')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body-inner" id="modalBody"></div>
  </div>
</div>

<!-- SETTINGS PANEL -->
<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>
<aside class="settings-panel" id="settingsPanel">
    <div class="sp-header"><div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan Tampilan</div><button class="sp-close" onclick="closeSettings()"><i class="bi bi-x-lg"></i></button></div>
    <div class="sp-body">
        <div class="sp-preview"><div class="sp-preview-thumb" id="spPreviewThumb">🚉</div><div class="sp-preview-text"><div class="sp-preview-title" id="spPreviewTitle">Dark · Amber</div><div class="sp-preview-sub" id="spPreviewSub">Tampilan saat ini</div></div></div>
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
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-layout-sidebar-inset-reverse"></i></div><div><div class="sp-toggle-label">Mode Kompak</div><div class="sp-toggle-sub">Kurangi jarak &amp; padding</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleCompact" onchange="setToggle('compact',this.checked)"><span class="sp-slider"></span></label></div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-magic"></i></div><div><div class="sp-toggle-label">Animasi</div><div class="sp-toggle-sub">Aktifkan transisi &amp; efek gerak</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleAnim" onchange="setToggle('anim',this.checked)"><span class="sp-slider"></span></label></div>
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
/* Station Modal */
function closeModal(e) {
    if (e.target === document.getElementById('stationModal'))
        document.getElementById('stationModal').classList.remove('show');
}
function openStation(s) {
    document.getElementById('modalName').textContent = s.name;
    document.getElementById('modalBody').innerHTML = `
        <span class="modal-line-badge" style="background:${s.bg};color:${s.color};">${s.emoji} ${s.line}</span>
        <div class="m-grid">
            <div class="m-field"><label>Kode Stasiun</label><div class="val" style="font-family:monospace;font-size:.9rem;">${s.kode}</div></div>
            <div class="m-field"><label>Jam Posko L&F</label><div class="val">${s.jam} WIB</div></div>
            <div class="m-field"><label>PIC Petugas</label><div class="val">${s.petugas}</div></div>
            <div class="m-field"><label>Item Tersimpan</label><div class="val">${s.item} item</div></div>
        </div>
        <div class="m-field" style="margin-bottom:.85rem;"><label>Alamat</label><div class="val" style="font-weight:400;line-height:1.5;">${s.alamat}</div></div>
        <div class="m-field" style="margin-bottom:1.2rem;"><label>Telepon Posko</label><div class="val">${s.telp}</div></div>
        <div class="m-actions">
            <a href="tel:${s.telp}" class="btn-gold"><i class="bi bi-telephone-fill"></i> Hubungi Posko</a>
            <a href="laporan.php?stasiun=${encodeURIComponent(s.name)}" class="btn-outline"><i class="bi bi-list-ul"></i> Lihat Laporan</a>
        </div>`;
    document.getElementById('stationModal').classList.add('show');
}

/* Search */
document.getElementById('searchInput').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.station-card').forEach(c => {
        c.style.display = c.querySelector('.sc-name').textContent.toLowerCase().includes(q) ? '' : 'none';
    });
    document.querySelectorAll('.line-section').forEach(s => {
        s.style.display = [...s.querySelectorAll('.station-card')].some(c => c.style.display !== 'none') ? '' : 'none';
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.getElementById('stationModal').classList.remove('show');
});

/*  Settings Panel */
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
    window.saveSettings=()=>{try{localStorage.setItem('cl_settings',JSON.stringify(S));}catch(e){}closeSettings();toast('✅ Pengaturan tersimpan!');};
    window.resetSettings=()=>{S=Object.assign({},DEFAULTS);apply();sync();try{localStorage.removeItem('cl_settings');}catch(e){}toast('🔄 Pengaturan direset');};
    function toast(m){const t=document.getElementById('spToast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2800);}
    document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key===','){e.preventDefault();openSettings();}if(e.key==='Escape')closeSettings();});
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>{if(S.theme==='system')apply();});
    load();apply();
})();
</script>
</body>
</html>