<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$pdo  = getDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$user = getCurrentUser();
$role = $user['role'] ?? 'pelapor';

$now       = new DateTime();
$monthSel  = $_GET['bulan']   ?? $now->format('Y-m');
$lineSel   = $_GET['koridor'] ?? '';
$yearSel   = substr($monthSel, 0, 4);
$monthNum  = substr($monthSel, 5, 2);
$dateFrom  = $monthSel . '-01';
$dateTo    = date('Y-m-t', strtotime($dateFrom));

$stmtLaporan = $pdo->prepare("SELECT COUNT(*) FROM laporan_kehilangan WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ?");
$stmtLaporan->execute([$dateFrom, $dateTo]); $totalLaporan = (int)$stmtLaporan->fetchColumn();

$stmtSelesai = $pdo->prepare("SELECT COUNT(*) FROM laporan_kehilangan WHERE status='selesai' AND deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ?");
$stmtSelesai->execute([$dateFrom, $dateTo]); $totalSelesai = (int)$stmtSelesai->fetchColumn();

$stmtBarang = $pdo->prepare("SELECT COUNT(*) FROM barang_temuan WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ?");
$stmtBarang->execute([$dateFrom, $dateTo]); $totalBarang = (int)$stmtBarang->fetchColumn();

$stmtDiproses = $pdo->prepare("SELECT COUNT(*) FROM laporan_kehilangan WHERE status='diproses' AND deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ?");
$stmtDiproses->execute([$dateFrom, $dateTo]); $totalDiproses = (int)$stmtDiproses->fetchColumn();

$stmtCocok = $pdo->prepare("SELECT COUNT(*) FROM pencocokan WHERE DATE(created_at) BETWEEN ? AND ?");
$stmtCocok->execute([$dateFrom, $dateTo]); $totalCocok = (int)$stmtCocok->fetchColumn();

$resolveRate = $totalLaporan > 0 ? round($totalSelesai / $totalLaporan * 100) : 0;

$dailyData = [];
$stmtDaily = $pdo->prepare("SELECT DATE(created_at) as tgl, COUNT(*) as jml FROM laporan_kehilangan WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY tgl ASC");
$stmtDaily->execute([$dateFrom, $dateTo]);
$rawDaily = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);
$daysInMonth = (int)date('t', strtotime($dateFrom));
for ($d = 1; $d <= $daysInMonth; $d++) {
    $key = $yearSel.'-'.$monthNum.'-'.str_pad($d,2,'0',STR_PAD_LEFT);
    $dailyData[$key] = 0;
}
foreach ($rawDaily as $r) $dailyData[$r['tgl']] = (int)$r['jml'];

$stmtKat = $pdo->prepare("SELECT COALESCE(NULLIF(kategori,''),'Lainnya') as kat, COUNT(*) as jml FROM laporan_kehilangan WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ? GROUP BY kat ORDER BY jml DESC LIMIT 6");
$stmtKat->execute([$dateFrom, $dateTo]);
$kategoriData = $stmtKat->fetchAll(PDO::FETCH_ASSOC);
if (empty($kategoriData)) $kategoriData = [['kat'=>'Dompet','jml'=>0],['kat'=>'HP','jml'=>0],['kat'=>'Tas','jml'=>0],['kat'=>'Kunci','jml'=>0]];

$stmtStatus = $pdo->prepare("SELECT status, COUNT(*) as jml FROM laporan_kehilangan WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ? GROUP BY status");
$stmtStatus->execute([$dateFrom, $dateTo]);
$statusRows = $stmtStatus->fetchAll(PDO::FETCH_KEY_PAIR);
$statusMap  = ['diproses'=>['label'=>'Diproses','color'=>'#F59E0B'],'ditemukan'=>['label'=>'Ditemukan','color'=>'#3B82F6'],'selesai'=>['label'=>'Selesai','color'=>'#10B981'],'ditutup'=>['label'=>'Ditutup','color'=>'#6B7280']];

$stmtRecent = $pdo->prepare("SELECT lk.no_laporan,lk.nama_barang,lk.kategori,lk.lokasi_hilang,lk.status,lk.created_at,u.nama as nama_user FROM laporan_kehilangan lk LEFT JOIN users u ON lk.user_id=u.id WHERE lk.deleted_at IS NULL AND DATE(lk.created_at) BETWEEN ? AND ? ORDER BY lk.created_at DESC LIMIT 10");
$stmtRecent->execute([$dateFrom, $dateTo]);
$recentLaporan = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

$stmtLokasi = $pdo->prepare("SELECT COALESCE(NULLIF(lokasi_hilang,''),'Tidak diketahui') as lokasi, COUNT(*) as jml FROM laporan_kehilangan WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN ? AND ? GROUP BY lokasi ORDER BY jml DESC LIMIT 5");
$stmtLokasi->execute([$dateFrom, $dateTo]);
$topLokasi = $stmtLokasi->fetchAll(PDO::FETCH_ASSOC);

$stmtMonths = $pdo->query("SELECT DISTINCT DATE_FORMAT(created_at,'%Y-%m') as ym FROM laporan_kehilangan WHERE deleted_at IS NULL ORDER BY ym DESC LIMIT 12");
$availMonths = $stmtMonths->fetchAll(PDO::FETCH_COLUMN);
if (empty($availMonths)) $availMonths = [$now->format('Y-m')];

$chartDates  = json_encode(array_keys($dailyData));
$chartValues = json_encode(array_values($dailyData));
$katLabels   = json_encode(array_column($kategoriData,'kat'));
$katValues   = json_encode(array_column($kategoriData,'jml'));
$firstName   = explode(' ', $user['nama'] ?? 'User')[0];
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Laporan &amp; Statistik — CommuterLink</title>
<link rel="icon" href="uploads/favicon.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* CSS VARIABLES  */
:root {
    --navy:#0B1F3A; --navy-2:#152d52; --navy-3:#1e3d6e;
    --gold:#F0A500; --gold-lt:#F7C948;
    --bg:#F0F4F8; --card-bg:#FFFFFF;
    --text:#1E293B; --text-2:#475569; --text-3:#94A3B8; --border:#E2E8F0;
    --success:#10B981; --danger:#EF4444; --info:#3B82F6;
}
[data-theme="dark"] { --bg:#0B1626; --card-bg:#112038; --text:#E8F0FE; --text-2:#94AEC8; --text-3:#506882; --border:rgba(255,255,255,0.07); }
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
[data-compact="true"] .stat-card    { padding:.9rem 1rem; }
[data-anim="off"] * { animation:none !important; transition:none !important; }

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}



/*  LAYOUT  */
.page-content{padding:2rem;max-width:1280px;margin:0 auto;}

/*  BANNER  */
.page-banner{border-radius:18px;padding:1.75rem 2.5rem;margin-bottom:1.75rem;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%);border:1px solid rgba(255,255,255,.07);transition:padding .3s;}
.page-banner::before{content:'';position:absolute;width:280px;height:280px;background:radial-gradient(circle,rgba(240,165,0,.2) 0%,transparent 70%);top:-80px;right:-60px;border-radius:50%;pointer-events:none;}
.page-banner::after{content:'📊';position:absolute;right:2.5rem;bottom:-8px;font-size:6.5rem;opacity:.08;pointer-events:none;}
.banner-label{font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:.4rem;}
.banner-title{font-size:1.55rem;font-weight:800;color:#fff;letter-spacing:-.5px;}
.banner-title span{color:var(--gold-lt);}
.banner-sub{font-size:.82rem;color:rgba(255,255,255,.45);margin-top:.4rem;}

/*  BREADCRUMB  */
.breadcrumb-nav{display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-3);margin-bottom:1.25rem;}
.breadcrumb-nav a{color:var(--text-3);text-decoration:none;transition:color .2s;}
.breadcrumb-nav a:hover{color:var(--gold);}

/*  FILTER  */
.filter-bar{display:flex;align-items:center;flex-wrap:wrap;gap:.75rem;margin-bottom:1.75rem;padding:1rem 1.25rem;background:var(--card-bg);border:1px solid var(--border);border-radius:14px;transition:background .3s,border-color .3s;}
.cl-select{padding:.55rem .9rem;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;color:var(--text);font-family:inherit;font-size:.82rem;outline:none;cursor:pointer;transition:border-color .2s;}
.cl-select:focus{border-color:var(--gold);}
.cl-select option{background:var(--card-bg);}
.btn-export{display:inline-flex;align-items:center;gap:7px;padding:.58rem 1.1rem;background:var(--gold);color:var(--navy);border:none;border-radius:10px;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;text-decoration:none;transition:all .2s;margin-left:auto;}
.btn-export:hover{background:var(--gold-lt);color:var(--navy);transform:translateY(-1px);box-shadow:0 5px 16px rgba(240,165,0,.3);}

/*  STAT CARDS  */
.stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:1.75rem;}
.stat-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;padding:1.15rem 1.2rem;transition:transform .2s,box-shadow .2s,background .3s,border-color .3s;position:relative;overflow:hidden;}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(0,0,0,.1);}
.stat-icon{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:.9rem;}
.ic-gold  {background:rgba(240,165,0,.12); color:var(--gold);}
.ic-green {background:rgba(16,185,129,.12);color:#10B981;}
.ic-blue  {background:rgba(59,130,246,.12);color:#3B82F6;}
.ic-red   {background:rgba(239,68,68,.12); color:#EF4444;}
.ic-purple{background:rgba(139,92,246,.12);color:#A78BFA;}
.stat-num  {font-size:1.8rem;font-weight:800;color:var(--text);line-height:1;}
.stat-label{font-size:.72rem;font-weight:600;color:var(--text-3);margin-top:4px;}
.stat-badge{position:absolute;top:1rem;right:1rem;font-size:.67rem;font-weight:700;padding:2px 8px;border-radius:99px;background:rgba(16,185,129,.1);color:#10B981;}

/*  CHART GRID  */
.chart-grid{display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:1.25rem;}
.chart-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:background .3s,border-color .3s;}
.chart-head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.3rem;border-bottom:1px solid var(--border);}
.chart-title{font-size:.88rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:7px;}
.chart-title i{color:var(--gold);}
.chart-sub{font-size:.72rem;color:var(--text-3);margin-top:2px;}
.chart-body{padding:1.25rem;}
.chart-wrap{position:relative;height:220px;}
.chart-link{font-size:.75rem;font-weight:700;color:var(--gold);text-decoration:none;}
.chart-link:hover{color:var(--gold-lt);}

/*  STATUS BARS  */
.status-list{padding:1rem 1.3rem;display:flex;flex-direction:column;gap:.75rem;}
.status-row{display:flex;align-items:center;justify-content:space-between;gap:.75rem;}
.status-left{display:flex;align-items:center;gap:9px;}
.status-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.status-name{font-size:.8rem;font-weight:600;color:var(--text-2);}
.status-bar-wrap{flex:1;height:7px;background:var(--border);border-radius:4px;overflow:hidden;}
.status-bar-fill{height:100%;border-radius:4px;transition:width .6s ease;}
.status-count{font-size:.82rem;font-weight:700;color:var(--text);min-width:28px;text-align:right;}

/*  BOTTOM GRID  */
.bottom-grid{display:grid;grid-template-columns:1fr 320px;gap:1.25rem;margin-bottom:1.25rem;}

/* TABLE  */
.cl-table{width:100%;border-collapse:collapse;}
.cl-table thead th{font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-3);padding:.55rem 1rem;background:var(--bg);border-bottom:1px solid var(--border);text-align:left;}
.cl-table tbody td{padding:.72rem 1rem;font-size:.8rem;color:var(--text-2);border-bottom:1px solid var(--border);vertical-align:middle;}
.cl-table tbody tr:last-child td{border-bottom:none;}
.cl-table tbody tr:hover td{background:var(--bg);}
.no-lap{font-size:.7rem;font-family:monospace;color:var(--gold);font-weight:700;}
.item-name{font-weight:700;color:var(--text);font-size:.82rem;}
.item-kat{font-size:.69rem;color:var(--text-3);}
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:.68rem;font-weight:700;}
.sp-diproses {background:rgba(245,158,11,.12);color:#F59E0B;}
.sp-ditemukan{background:rgba(59,130,246,.12); color:#3B82F6;}
.sp-selesai  {background:rgba(16,185,129,.12); color:#10B981;}
.sp-ditutup  {background:rgba(107,114,128,.12);color:#9CA3AF;}

/*  LOKASI  */
.lokasi-list{padding:1rem 1.3rem;display:flex;flex-direction:column;gap:.7rem;}
.lokasi-item{display:flex;align-items:flex-start;gap:10px;}
.lokasi-rank{width:22px;height:22px;border-radius:6px;background:var(--border);color:var(--text-3);font-size:.68rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.lokasi-rank.gold{background:rgba(240,165,0,.15);color:var(--gold);}
.lokasi-name{font-size:.8rem;font-weight:600;color:var(--text);line-height:1.3;}
.lokasi-bar{height:5px;border-radius:3px;background:var(--border);margin-top:4px;}
.lokasi-bar-fill{height:100%;border-radius:3px;background:var(--gold);}
.lokasi-num{font-size:.78rem;font-weight:700;color:var(--text);margin-left:auto;flex-shrink:0;}

/* RESOLVE  */
.resolve-inner{padding:1.25rem;display:flex;flex-direction:column;align-items:center;gap:1rem;}
.resolve-ring{position:relative;width:150px;height:150px;}
.resolve-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.resolve-pct{font-size:2rem;font-weight:800;color:var(--text);}
.resolve-lbl{font-size:.7rem;color:var(--text-3);font-weight:600;}
.resolve-stats{width:100%;display:flex;flex-direction:column;gap:.5rem;}
.resolve-row{display:flex;justify-content:space-between;font-size:.78rem;color:var(--text-2);}
.resolve-row span:last-child{font-weight:700;color:var(--text);}

/* EMPTY / FOOTER */
.empty-state{padding:3rem;text-align:center;color:var(--text-3);}
.empty-icon{font-size:2.5rem;opacity:.3;margin-bottom:.75rem;}
footer{padding:1.1rem 2rem;border-top:1px solid var(--border);font-size:.73rem;color:var(--text-3);text-align:center;}

/* SETTINGS PANEL */
.sp-overlay{position:fixed;inset:0;z-index:8888;background:rgba(0,0,0,0);pointer-events:none;transition:background .35s;}
.sp-overlay.open{background:rgba(0,0,0,.55);pointer-events:all;backdrop-filter:blur(4px);}
.settings-panel{position:fixed;top:0;right:0;bottom:0;z-index:8889;width:360px;max-width:92vw;background:#0E1E35;border-left:1px solid rgba(255,255,255,.09);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .38s cubic-bezier(.22,1,.36,1);box-shadow:-24px 0 80px rgba(0,0,0,.55);}
.settings-panel.open{transform:translateX(0);}
[data-theme="light"] .settings-panel{background:#1A2E4A;}
.sp-header{padding:1.3rem 1.5rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.sp-title{font-size:1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:9px;}
.sp-title i{color:#F59E0B;}
.sp-close{width:32px;height:32px;background:rgba(255,255,255,.07);border:none;border-radius:8px;color:rgba(255,255,255,.5);font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s,color .2s;}
.sp-close:hover{background:rgba(255,255,255,.13);color:#fff;}
.sp-body{flex:1;overflow-y:auto;padding:1.25rem 1.5rem;}
.sp-body::-webkit-scrollbar{width:4px;}.sp-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:4px;}
.sp-section{margin-bottom:1.5rem;}
.sp-section-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:.75rem;display:flex;align-items:center;gap:8px;}
.sp-section-label::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.07);}
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
.sp-slider::before{content:'';position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:transform .25s;box-shadow:0 1px 4px rgba(0,0,0,.3);}
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
</style>
</head>
<body>


<!-- PAGE -->
<main class="page-content">
    <div class="breadcrumb-nav">
        <a href="index_petugas.php"><i class="bi bi-house-door"></i> Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size:.55rem;"></i>
        <span>Laporan &amp; Statistik</span>
    </div>

    <div class="page-banner">
        <div class="banner-label">Analitik &nbsp;·&nbsp; <?= DateTime::createFromFormat('Y-m',$monthSel)->format('F Y') ?></div>
        <div class="banner-title">Laporan &amp; <span>Statistik</span></div>
        <div class="banner-sub">Analisis performa layanan Lost &amp; Found dan tren kehilangan barang penumpang.</div>
    </div>

    <form method="GET" class="filter-bar">
        <div style="display:flex;align-items:center;gap:6px;font-size:.8rem;font-weight:600;color:var(--text-2);">
            <i class="bi bi-funnel" style="color:var(--gold);"></i> Filter:
        </div>
        <select name="bulan" class="cl-select" onchange="this.form.submit()">
            <?php foreach($availMonths as $m): ?>
            <option value="<?= $m ?>" <?= $m===$monthSel?'selected':'' ?>><?= DateTime::createFromFormat('Y-m',$m)->format('F Y') ?></option>
            <?php endforeach; ?>
        </select>
        <select name="koridor" class="cl-select" onchange="this.form.submit()">
            <option value="">Semua Koridor</option>
            <option value="bogor"     <?= $lineSel==='bogor'    ?'selected':'' ?>>Bogor Line</option>
            <option value="bekasi"    <?= $lineSel==='bekasi'   ?'selected':'' ?>>Bekasi Line</option>
            <option value="tangerang" <?= $lineSel==='tangerang'?'selected':'' ?>>Tangerang Line</option>
            <option value="cikarang"  <?= $lineSel==='cikarang' ?'selected':'' ?>>Cikarang Line</option>
        </select>
        <a href="export.php?bulan=<?= urlencode($monthSel) ?>&koridor=<?= urlencode($lineSel) ?>" class="btn-export" target="_blank">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </form>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-icon ic-gold"><i class="bi bi-file-earmark-text"></i></div><div class="stat-num"><?= $totalLaporan ?></div><div class="stat-label">Total Laporan</div></div>
        <div class="stat-card"><span class="stat-badge"><?= $resolveRate ?>%</span><div class="stat-icon ic-green"><i class="bi bi-check2-circle"></i></div><div class="stat-num"><?= $totalSelesai ?></div><div class="stat-label">Kasus Selesai</div></div>
        <div class="stat-card"><div class="stat-icon ic-blue"><i class="bi bi-box-seam"></i></div><div class="stat-num"><?= $totalBarang ?></div><div class="stat-label">Barang Temuan</div></div>
        <div class="stat-card"><div class="stat-icon ic-red"><i class="bi bi-hourglass-split"></i></div><div class="stat-num"><?= $totalDiproses ?></div><div class="stat-label">Masih Diproses</div></div>
        <div class="stat-card"><div class="stat-icon ic-purple"><i class="bi bi-puzzle"></i></div><div class="stat-num"><?= $totalCocok ?></div><div class="stat-label">Pencocokan</div></div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-head"><div><div class="chart-title"><i class="bi bi-bar-chart-line-fill"></i> Laporan Per Hari</div><div class="chart-sub"><?= DateTime::createFromFormat('Y-m',$monthSel)->format('F Y') ?></div></div></div>
            <div class="chart-body"><div class="chart-wrap"><canvas id="dailyChart"></canvas></div></div>
        </div>
        <div class="chart-card">
            <div class="chart-head"><div class="chart-title"><i class="bi bi-pie-chart-fill"></i> Status Laporan</div></div>
            <?php $statusTotal = array_sum($statusRows) ?: 1; ?>
            <div class="status-list">
                <?php foreach($statusMap as $key=>$info): $jml=$statusRows[$key]??0; $pct=round($jml/$statusTotal*100); ?>
                <div class="status-row">
                    <div class="status-left"><div class="status-dot" style="background:<?= $info['color'] ?>;"></div><span class="status-name"><?= $info['label'] ?></span></div>
                    <div class="status-bar-wrap"><div class="status-bar-fill" style="width:<?= $pct ?>%;background:<?= $info['color'] ?>;"></div></div>
                    <span class="status-count"><?= $jml ?></span>
                </div>
                <?php endforeach; ?>
                <?php if(!array_sum($statusRows)): ?><div style="text-align:center;padding:1rem;font-size:.8rem;color:var(--text-3);">Tidak ada data bulan ini</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-head"><div><div class="chart-title"><i class="bi bi-tag-fill"></i> Kategori Barang Hilang</div><div class="chart-sub">Distribusi kategori bulan ini</div></div></div>
            <div class="chart-body"><div class="chart-wrap"><canvas id="katChart"></canvas></div></div>
        </div>
        <div class="chart-card">
            <div class="chart-head"><div class="chart-title"><i class="bi bi-speedometer2"></i> Tingkat Resolusi</div></div>
            <div class="resolve-inner">
                <div class="resolve-ring"><canvas id="rateChart"></canvas><div class="resolve-center"><div class="resolve-pct"><?= $resolveRate ?>%</div><div class="resolve-lbl">Selesai</div></div></div>
                <div class="resolve-stats">
                    <div class="resolve-row"><span>Laporan masuk</span><span><?= $totalLaporan ?></span></div>
                    <div class="resolve-row"><span>Selesai</span><span style="color:#10B981;"><?= $totalSelesai ?></span></div>
                    <div class="resolve-row"><span>Masih diproses</span><span style="color:#F59E0B;"><?= $totalDiproses ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-grid">
        <div class="chart-card">
            <div class="chart-head"><div class="chart-title"><i class="bi bi-list-ul"></i> Laporan Terbaru</div><a href="laporan_kehilangan.php" class="chart-link">Lihat semua →</a></div>
            <div style="overflow-x:auto;">
                <table class="cl-table">
                    <thead><tr><th>No. Laporan</th><th>Barang</th><th>Lokasi</th><th>Pelapor</th><th>Status</th><th>Tanggal</th></tr></thead>
                    <tbody>
                        <?php if(empty($recentLaporan)): ?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📋</div><div>Tidak ada laporan periode ini.</div></div></td></tr>
                        <?php else: foreach($recentLaporan as $r): ?>
                        <tr>
                            <td><span class="no-lap"><?= htmlspecialchars($r['no_laporan']??'—') ?></span></td>
                            <td><div class="item-name"><?= htmlspecialchars($r['nama_barang']) ?></div><div class="item-kat"><?= htmlspecialchars($r['kategori']??'—') ?></div></td>
                            <td style="max-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.78rem;"><?= htmlspecialchars($r['lokasi_hilang']??'—') ?></td>
                            <td style="font-size:.78rem;"><?= htmlspecialchars($r['nama_user']??'—') ?></td>
                            <td><span class="status-pill sp-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                            <td style="white-space:nowrap;font-size:.75rem;"><?= $r['created_at']?date('d/m/Y',strtotime($r['created_at'])):'—' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-head"><div class="chart-title"><i class="bi bi-geo-alt-fill"></i> Top Lokasi Kejadian</div></div>
            <?php if(empty($topLokasi)): ?><div class="empty-state"><div class="empty-icon">📍</div><div>Belum ada data.</div></div>
            <?php else: $maxL=max(array_column($topLokasi,'jml'))?:1; ?>
            <div class="lokasi-list">
                <?php foreach($topLokasi as $i=>$l): $pct=round($l['jml']/$maxL*100); ?>
                <div class="lokasi-item">
                    <div class="lokasi-rank <?= $i===0?'gold':'' ?>"><?= $i+1 ?></div>
                    <div style="flex:1;min-width:0;"><div class="lokasi-name"><?= htmlspecialchars($l['lokasi']) ?></div><div class="lokasi-bar"><div class="lokasi-bar-fill" style="width:<?= $pct ?>%;"></div></div></div>
                    <div class="lokasi-num"><?= $l['jml'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<footer>&copy; <?= date('Y') ?> CommuterLink Nusantara &nbsp;·&nbsp; Laporan &amp; Statistik</footer>

<!-- SETTINGS PANEL -->
<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>
<aside class="settings-panel" id="settingsPanel">
    <div class="sp-header"><div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan Tampilan</div><button class="sp-close" onclick="closeSettings()"><i class="bi bi-x-lg"></i></button></div>
    <div class="sp-body">
        <div class="sp-preview"><div class="sp-preview-thumb" id="spPreviewThumb">📊</div><div class="sp-preview-text"><div class="sp-preview-title" id="spPreviewTitle">Dark · Amber</div><div class="sp-preview-sub" id="spPreviewSub">Tampilan saat ini</div></div></div>
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
document.addEventListener('DOMContentLoaded', function(){
    function cv(v){return getComputedStyle(document.documentElement).getPropertyValue(v).trim();}
    const gold=cv('--gold')||'#F0A500', goldLt=cv('--gold-lt')||'#F7C948',
          text3=cv('--text-3')||'#94A3B8', border=cv('--border')||'#E2E8F0',
          cardBg=cv('--card-bg')||'#fff', textClr=cv('--text')||'#1E293B';
    Chart.defaults.font.family="'Plus Jakarta Sans',sans-serif";
    Chart.defaults.color=text3;

    new Chart(document.getElementById('dailyChart'),{type:'bar',data:{labels:<?= $chartDates ?>.map(d=>new Date(d).getDate()),datasets:[{label:'Laporan',data:<?= $chartValues ?>,backgroundColor:gold+'99',borderColor:gold,borderWidth:1.5,borderRadius:6,hoverBackgroundColor:goldLt+'cc'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:cardBg,titleColor:textClr,bodyColor:text3,borderColor:border,borderWidth:1}},scales:{x:{grid:{color:border},ticks:{color:text3,font:{size:11}}},y:{grid:{color:border},ticks:{color:text3,precision:0,font:{size:11}},beginAtZero:true}}}});

    const kc=['#F0A500','#3B82F6','#10B981','#A78BFA','#F43F5E','#14B8A6'];
    new Chart(document.getElementById('katChart'),{type:'bar',data:{labels:<?= $katLabels ?>,datasets:[{data:<?= $katValues ?>,backgroundColor:kc.map(c=>c+'88'),borderColor:kc,borderWidth:1.5,borderRadius:6}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:cardBg,titleColor:textClr,bodyColor:text3,borderColor:border,borderWidth:1}},scales:{x:{grid:{color:border},ticks:{color:text3,precision:0,font:{size:11}},beginAtZero:true},y:{grid:{display:false},ticks:{color:text3,font:{size:11}}}}}});

    new Chart(document.getElementById('rateChart'),{type:'doughnut',data:{datasets:[{data:[<?= $totalSelesai ?>,<?= max($totalLaporan-$totalSelesai,0) ?>||1],backgroundColor:['#10B981',border],borderWidth:0,hoverOffset:4}]},options:{cutout:'78%',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{enabled:false}}}});
});

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