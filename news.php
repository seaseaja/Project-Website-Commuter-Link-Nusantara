<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user = getCurrentUser();
$role = $user['role'] ?? 'pelapor';
$firstName = explode(' ', $user['nama'] ?? 'User')[0];

$categories = ['Semua','Pengumuman','Tips & Trik','Kebijakan','Pemeliharaan'];
$activeCategory = $_GET['cat'] ?? 'Semua';
$search = trim($_GET['q'] ?? '');

$news = [
  ['id'=>1,'cat'=>'Pengumuman','title'=>'Area L&F Jakarta Kota Pindah ke Pintu Selatan','excerpt'=>'Mulai 3 Maret 2026, posko Lost & Found Stasiun Jakarta Kota resmi berpindah lokasi ke area pintu selatan, bersebelahan dengan loket tiket. Perubahan ini dilakukan untuk memudahkan akses penumpang.','date'=>'2026-03-03','author'=>'Admin Pusat','tag'=>'Penting','tagColor'=>'#EF4444','tagBg'=>'rgba(239,68,68,0.1)','thumb'=>'📍','views'=>412],
  ['id'=>2,'cat'=>'Tips & Trik','title'=>'5 Cara Melindungi Barang Bawaan di Kereta Commuter','excerpt'=>'Kehilangan barang di kereta bisa terjadi pada siapa saja. Berikut adalah lima tips praktis yang dapat kamu lakukan untuk menjaga keamanan barang bawaanmu selama perjalanan commuter.','date'=>'2026-03-01','author'=>'Tim Redaksi','tag'=>'Tips','tagColor'=>'#10B981','tagBg'=>'rgba(16,185,129,0.1)','thumb'=>'💡','views'=>887],
  ['id'=>3,'cat'=>'Kebijakan','title'=>'Perubahan SOP Pengambilan Barang Temuan Efektif April 2026','excerpt'=>'Untuk meningkatkan keamanan dan kepastian kepemilikan, PT KCI memperbarui prosedur pengambilan barang temuan. Pemohon kini wajib menyertakan dokumen identitas asli dan bukti kepemilikan.','date'=>'2026-02-28','author'=>'Divisi Hukum','tag'=>'Kebijakan','tagColor'=>'#3B82F6','tagBg'=>'rgba(59,130,246,0.1)','thumb'=>'📋','views'=>234],
  ['id'=>4,'cat'=>'Pemeliharaan','title'=>'Penutupan Sementara Posko L&F Stasiun Manggarai 5–7 Maret','excerpt'=>'Posko Lost & Found Stasiun Manggarai akan ditutup sementara pada tanggal 5 hingga 7 Maret 2026 untuk kegiatan renovasi. Penumpang dapat menghubungi posko terdekat di Stasiun Jatinegara.','date'=>'2026-02-26','author'=>'Operasional','tag'=>'Peringatan','tagColor'=>'#F59E0B','tagBg'=>'rgba(245,158,11,0.1)','thumb'=>'🔧','views'=>156],
  ['id'=>5,'cat'=>'Pengumuman','title'=>'Jam Operasional Posko L&F Diperpanjang Hingga Pukul 22.00','excerpt'=>'Merespons tingginya tingkat laporan kehilangan di jam-jam malam, PT KCI resmi memperpanjang jam operasional posko Lost & Found di 6 stasiun utama hingga pukul 22.00 WIB mulai Maret 2026.','date'=>'2026-02-24','author'=>'Admin Pusat','tag'=>'Penting','tagColor'=>'#EF4444','tagBg'=>'rgba(239,68,68,0.1)','thumb'=>'🕙','views'=>321],
  ['id'=>6,'cat'=>'Tips & Trik','title'=>'Cara Cepat Melapor Kehilangan Lewat CommuterLink','excerpt'=>'Tidak perlu repot datang ke posko dulu — kini kamu bisa membuat laporan kehilangan secara online melalui platform CommuterLink. Artikel ini memandu langkah-langkahnya dari awal hingga selesai.','date'=>'2026-02-20','author'=>'Tim Digital','tag'=>'Tips','tagColor'=>'#10B981','tagBg'=>'rgba(16,185,129,0.1)','thumb'=>'📱','views'=>1043],
  ['id'=>7,'cat'=>'Pengumuman','title'=>'Pelatihan Petugas L&F Seluruh Koridor Telah Selesai','excerpt'=>'Sebanyak 78 petugas posko Lost & Found dari empat koridor telah menyelesaikan pelatihan resmi. Pelatihan mencakup identifikasi barang, layanan pelanggan, dan penggunaan sistem CommuterLink.','date'=>'2026-02-15','author'=>'SDM & Pelatihan','tag'=>'Info','tagColor'=>'#6366F1','tagBg'=>'rgba(99,102,241,0.1)','thumb'=>'🎓','views'=>178],
  ['id'=>8,'cat'=>'Tips & Trik','title'=>'Tandai Barang Bawaanmu: Kunci Mudah Temukan Kembali','excerpt'=>'Sticker nama, label koper, atau gantungan kunci unik — benda-benda kecil ini ternyata sangat membantu petugas mengidentifikasi pemilik barang temuan. Yuk, mulai kebiasaan baik ini!','date'=>'2026-02-10','author'=>'Tim Redaksi','tag'=>'Tips','tagColor'=>'#10B981','tagBg'=>'rgba(16,185,129,0.1)','thumb'=>'🏷️','views'=>562],
];

$filtered = array_values(array_filter($news, function($n) use ($activeCategory, $search) {
    $catOk = $activeCategory === 'Semua' || $n['cat'] === $activeCategory;
    $qOk   = !$search || stripos($n['title'],$search)!==false || stripos($n['excerpt'],$search)!==false;
    return $catOk && $qOk;
}));
$featured = !empty($filtered) ? array_shift($filtered) : null;
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Berita &amp; Info — CommuterLink Nusantara</title>
<link rel="icon" href="uploads/favicon.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>

/* CSS VARIABLES */
:root{--navy:#0B1F3A;--navy-2:#152d52;--navy-3:#1e3d6e;--gold:#F0A500;--gold-lt:#F7C948;--bg:#F0F4F8;--card-bg:#FFFFFF;--text:#1E293B;--text-2:#475569;--text-3:#94A3B8;--border:#E2E8F0;--danger:#EF4444;--success:#10B981;--info:#3B82F6;}
[data-theme="dark"]{--bg:#0B1626;--card-bg:#112038;--text:#E8F0FE;--text-2:#94AEC8;--text-3:#506882;--border:rgba(255,255,255,0.07);}
[data-accent="blue"]  {--gold:#3B82F6;--gold-lt:#60A5FA;}
[data-accent="green"] {--gold:#10B981;--gold-lt:#34D399;}
[data-accent="purple"]{--gold:#8B5CF6;--gold-lt:#A78BFA;}
[data-accent="red"]   {--gold:#EF4444;--gold-lt:#FC8181;}
[data-accent="rose"]  {--gold:#EC4899;--gold-lt:#F472B6;}
[data-fontsize="sm"]{font-size:14px;}[data-fontsize="md"]{font-size:16px;}[data-fontsize="lg"]{font-size:18px;}
[data-compact="true"] .page-content{padding:1.25rem;}
[data-compact="true"] .page-banner{padding:1.25rem 1.5rem;}
[data-anim="off"] *{animation:none !important;transition:none !important;}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:"Plus Jakarta Sans",sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}


/* LAYOUT */
.page-content{padding:2rem;max-width:1280px;margin:0 auto;}
.breadcrumb-nav{display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-3);margin-bottom:1.25rem;}
.breadcrumb-nav a{color:var(--text-3);text-decoration:none;transition:color .2s;}
.breadcrumb-nav a:hover{color:var(--gold);}

/* BANNER */
.page-banner{border-radius:18px;padding:1.75rem 2.5rem;margin-bottom:1.75rem;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%);border:1px solid rgba(255,255,255,.07);transition:padding .3s;}
.page-banner::before{content:"";position:absolute;width:280px;height:280px;background:radial-gradient(circle,rgba(240,165,0,.2) 0%,transparent 70%);top:-80px;right:-60px;border-radius:50%;pointer-events:none;}
.page-banner::after{content:"📰";position:absolute;right:2.5rem;bottom:-8px;font-size:6.5rem;opacity:.08;pointer-events:none;}
.banner-label{font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:.4rem;}
.banner-title{font-size:1.55rem;font-weight:800;color:#fff;letter-spacing:-.5px;}
.banner-title span{color:var(--gold-lt);}
.banner-sub{font-size:.82rem;color:rgba(255,255,255,.45);margin-top:.4rem;}

/* FILTER */
.filter-bar{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:center;}
.search-wrap{flex:1;min-width:220px;position:relative;}
.search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-3);pointer-events:none;}
.cl-input{width:100%;padding:.65rem 1rem .65rem 2.4rem;border:1.5px solid var(--border);border-radius:10px;font-size:.83rem;background:var(--card-bg);color:var(--text);outline:none;transition:border-color .2s;font-family:inherit;}
.cl-input:focus{border-color:var(--gold);}
.cl-input::placeholder{color:var(--text-3);}
.cat-tab{display:inline-flex;padding:.45rem 1rem;border-radius:99px;border:1.5px solid var(--border);background:var(--card-bg);font-size:.78rem;font-weight:700;color:var(--text-2);cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;}
.cat-tab:hover,.cat-tab.active{background:var(--navy);color:#fff;border-color:var(--navy);}

/* CONTENT */
.content-layout{display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start;}

/* FEATURED */
.featured-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:1.5rem;transition:box-shadow .2s,background .3s,border-color .3s;}
.featured-card:hover{box-shadow:0 8px 28px rgba(0,0,0,.15);}
.featured-thumb{height:180px;background:linear-gradient(135deg,var(--navy) 0%,var(--navy-3) 100%);display:flex;align-items:center;justify-content:center;font-size:5rem;position:relative;}
.featured-badge{position:absolute;top:1rem;left:1rem;background:var(--gold);color:var(--navy);font-size:.7rem;font-weight:800;padding:4px 10px;border-radius:99px;}
.featured-body{padding:1.4rem;}

/* NEWS CARDS */
.news-grid{display:grid;grid-template-columns:1fr;gap:1rem;}
.news-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;padding:1.1rem 1.3rem;display:flex;gap:1rem;align-items:flex-start;transition:transform .2s,box-shadow .2s,border-color .2s,background .3s;cursor:pointer;}
.news-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.12);border-color:rgba(255,255,255,.15);}
[data-theme="light"] .news-card:hover{border-color:#cbd5e1;}
.news-thumb{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.75rem;flex-shrink:0;background:var(--bg);}
.news-body{flex:1;min-width:0;}
.news-tag{display:inline-flex;align-items:center;font-size:.67rem;font-weight:700;padding:2px 8px;border-radius:99px;margin-bottom:.4rem;}
.news-title{font-size:.85rem;font-weight:700;color:var(--text);line-height:1.4;margin-bottom:.4rem;}
.news-excerpt{font-size:.75rem;color:var(--text-3);line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.news-meta{display:flex;align-items:center;gap:.75rem;margin-top:.5rem;font-size:.7rem;color:var(--text-3);}

/* SIDEBAR */
.sidebar-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:1.25rem;transition:background .3s,border-color .3s;}
.sc-head{padding:.9rem 1.1rem;border-bottom:1px solid var(--border);font-size:.82rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:7px;}
.sc-head i{color:var(--gold);}
.sc-body{padding:.75rem 1.1rem;}
.trending-item{display:flex;gap:.75rem;padding:.65rem 0;border-bottom:1px solid var(--border);align-items:flex-start;}
.trending-item:last-child{border-bottom:none;}
.trend-num{width:22px;height:22px;border-radius:6px;background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;color:var(--text-3);flex-shrink:0;}
.trend-title{font-size:.78rem;font-weight:600;color:var(--text);line-height:1.4;}
.trend-views{font-size:.68rem;color:var(--text-3);margin-top:2px;}
.cat-list{display:flex;flex-direction:column;gap:4px;}
.cat-item{display:flex;align-items:center;justify-content:space-between;padding:.55rem .9rem;border-radius:9px;text-decoration:none;font-size:.8rem;font-weight:600;color:var(--text-2);transition:background .15s;}
.cat-item:hover{background:var(--bg);color:var(--text);}
.cat-count{font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:99px;background:var(--bg);color:var(--text-3);}

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
@media(max-width:991px){.topbar-nav{display:none;}.content-layout{grid-template-columns:1fr;}}
@media(max-width:575px){.page-content{padding:1rem;}.topbar{padding:.65rem 1rem;}}
</style>
</head>
<body>


<main class="page-content">
    <div class="breadcrumb-nav fade-up">
        <a href="index_pelapor.php"><i class="bi bi-house-door"></i> Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size:.55rem;"></i>
        <span>Berita &amp; Info</span>
    </div>
    <div class="page-banner fade-up">
        <div class="banner-label">Informasi Terkini</div>
        <div class="banner-title">Berita &amp; <span>Pengumuman</span></div>
        <div class="banner-sub">Ikuti perkembangan terbaru seputar layanan Lost &amp; Found dan tips perjalanan komuter.</div>
    </div>

    <div class="filter-bar fade-up delay-1">
        <div class="search-wrap">
            <i class="bi bi-search"></i>
            <form method="GET">
                <?php if($activeCategory !== 'Semua'): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($activeCategory) ?>"><?php endif; ?>
                <input type="text" name="q" class="cl-input" placeholder="Cari berita..." value="<?= htmlspecialchars($search) ?>">
            </form>
        </div>
        <?php foreach($categories as $c): ?>
        <a href="?cat=<?= urlencode($c) ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="cat-tab <?= $activeCategory===$c?'active':'' ?>"><?= htmlspecialchars($c) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="content-layout fade-up delay-2">
        <div>
            <?php if($featured): ?>
            <div class="featured-card">
                <div class="featured-thumb">
                    <span><?= $featured['thumb'] ?></span>
                    <span class="featured-badge">⭐ Artikel Utama</span>
                </div>
                <div class="featured-body">
                    <span class="news-tag" style="background:<?= $featured['tagBg'] ?>;color:<?= $featured['tagColor'] ?>;"><?= $featured['tag'] ?></span>
                    <div style="font-size:1.05rem;font-weight:800;color:var(--text);line-height:1.35;margin-bottom:.6rem;"><?= htmlspecialchars($featured['title']) ?></div>
                    <div style="font-size:.82rem;color:var(--text-2);line-height:1.6;margin-bottom:.9rem;"><?= htmlspecialchars($featured['excerpt']) ?></div>
                    <div style="display:flex;align-items:center;gap:1rem;font-size:.72rem;color:var(--text-3);">
                        <span><i class="bi bi-person"></i> <?= $featured['author'] ?></span>
                        <span><i class="bi bi-calendar3"></i> <?= date('d M Y',strtotime($featured['date'])) ?></span>
                        <span><i class="bi bi-eye"></i> <?= $featured['views'] ?> views</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="news-grid">
                <?php foreach($filtered as $n): ?>
                <div class="news-card">
                    <div class="news-thumb"><?= $n['thumb'] ?></div>
                    <div class="news-body">
                        <span class="news-tag" style="background:<?= $n['tagBg'] ?>;color:<?= $n['tagColor'] ?>;"><?= $n['tag'] ?></span>
                        <div class="news-title"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="news-excerpt"><?= htmlspecialchars($n['excerpt']) ?></div>
                        <div class="news-meta">
                            <span><i class="bi bi-person"></i> <?= $n['author'] ?></span>
                            <span><i class="bi bi-calendar3"></i> <?= date('d M Y',strtotime($n['date'])) ?></span>
                            <span><i class="bi bi-eye"></i> <?= $n['views'] ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($filtered) && !$featured): ?>
                <div style="text-align:center;padding:3rem;color:var(--text-3);">
                    <div style="font-size:3rem;margin-bottom:.75rem;opacity:.3;">📭</div>
                    <div style="font-weight:700;color:var(--text-2);">Tidak ada artikel ditemukan</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SIDEBAR -->
        <div>
            <div class="sidebar-card">
                <div class="sc-head"><i class="bi bi-fire"></i> Paling Banyak Dibaca</div>
                <div class="sc-body">
                    <?php $sorted=$news; usort($sorted,fn($a,$b)=>$b['views']-$a['views']); foreach(array_slice($sorted,0,5) as $i=>$n): ?>
                    <div class="trending-item">
                        <div class="trend-num"><?= $i+1 ?></div>
                        <div><div class="trend-title"><?= htmlspecialchars($n['title']) ?></div><div class="trend-views"><i class="bi bi-eye" style="font-size:.6rem;"></i> <?= $n['views'] ?> views</div></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="sidebar-card">
                <div class="sc-head"><i class="bi bi-grid-3x3-gap"></i> Kategori</div>
                <div class="sc-body" style="padding:.6rem .9rem;">
                    <div class="cat-list">
                        <?php foreach($categories as $c):
                            $cnt = $c==='Semua' ? count($news) : count(array_filter($news,fn($n)=>$n['cat']===$c));
                        ?>
                        <a href="?cat=<?= urlencode($c) ?>" class="cat-item" style="<?= $activeCategory===$c?'background:var(--bg);color:var(--text);':'' ?>">
                            <?= htmlspecialchars($c) ?><span class="cat-count"><?= $cnt ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="sidebar-card" style="background:linear-gradient(135deg,var(--navy),var(--navy-3));border:none;">
                <div style="padding:1.2rem;text-align:center;">
                    <div style="font-size:2rem;margin-bottom:.6rem;">📢</div>
                    <div style="font-size:.85rem;font-weight:700;color:#fff;margin-bottom:.35rem;">Punya Info Penting?</div>
                    <div style="font-size:.73rem;color:rgba(255,255,255,.5);margin-bottom:1rem;">Kirim pengumuman atau berita ke tim redaksi kami.</div>
                    <a href=""https://mail.google.com/mail/?view=cm&to=redaksi@commuterlink.id"" style="display:inline-flex;align-items:center;gap:6px;padding:.6rem 1rem;background:var(--gold);color:var(--navy);border-radius:9px;font-size:.8rem;font-weight:700;text-decoration:none;">
                        <i class="bi bi-envelope-fill"></i> Hubungi Redaksi
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<footer style="padding:1.1rem 2rem;border-top:1px solid var(--border);font-size:.73rem;color:var(--text-3);text-align:center;">&copy; <?= date('Y') ?> CommuterLink Nusantara.</footer>

<!-- SETTINGS PANEL -->
<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>
<aside class="settings-panel" id="settingsPanel">
    <div class="sp-header"><div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan Tampilan</div><button class="sp-close" onclick="closeSettings()"><i class="bi bi-x-lg"></i></button></div>
    <div class="sp-body">
        <div class="sp-preview"><div class="sp-preview-thumb" id="spPreviewThumb">📰</div><div class="sp-preview-text"><div class="sp-preview-title" id="spPreviewTitle">Dark · Amber</div><div class="sp-preview-sub" id="spPreviewSub">Tampilan saat ini</div></div></div>
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