<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user = getCurrentUser();
$role = $user['role'] ?? 'pelapor';
$firstName = explode(' ', $user['nama'] ?? 'User')[0];

$faqs = [
  ['cat'=>'Laporan Kehilangan','icon'=>'bi-search-heart','color'=>'#EF4444','bg'=>'rgba(239,68,68,0.08)','items'=>[
    ['q'=>'Bagaimana cara melaporkan barang yang hilang di kereta?','a'=>'Kamu bisa membuat laporan melalui halaman Dashboard, klik "Buat Laporan Baru". Isi detail barang (nama, deskripsi, warna, merek), pilih lokasi stasiun atau "Di dalam Kereta", serta waktu kejadian. Sertakan foto jika ada untuk mempermudah identifikasi.'],
    ['q'=>'Apakah laporan bisa dibuat setelah beberapa hari kejadian?','a'=>'Ya, laporan tetap bisa dibuat. Namun semakin cepat laporan dibuat, semakin besar kemungkinan barang berhasil ditemukan. Kami menyarankan untuk melapor paling lambat 3×24 jam setelah kejadian.'],
    ['q'=>'Berapa lama laporan saya akan diproses?','a'=>'Tim petugas kami akan mulai mencocokkan laporan dengan daftar barang temuan dalam 1×24 jam setelah laporan masuk. Kamu akan mendapat notifikasi jika ada kecocokan ditemukan.'],
    ['q'=>'Apakah saya bisa membatalkan laporan yang sudah dibuat?','a'=>'Ya, kamu bisa menghubungi posko L&F stasiun atau Customer Service 021-121 untuk membatalkan laporan. Pembatalan dilakukan secara manual oleh petugas.'],
  ]],
  ['cat'=>'Barang Temuan','icon'=>'bi-box-seam','color'=>'#3B82F6','bg'=>'rgba(59,130,246,0.08)','items'=>[
    ['q'=>'Di mana saya bisa melihat daftar barang temuan?','a'=>'Kamu bisa mengakses halaman "Barang Temuan" dari menu utama atau dashboard. Daftar ini diperbarui setiap hari oleh petugas posko di masing-masing stasiun.'],
    ['q'=>'Bagaimana proses pengambilan barang temuan?','a'=>'Setelah mendapat notifikasi bahwa barangmu telah ditemukan, datang ke posko L&F stasiun yang tercantum dengan membawa: (1) KTP/identitas asli, (2) bukti kepemilikan barang, dan (3) nomor laporan yang kamu terima.'],
    ['q'=>'Berapa lama barang temuan disimpan di posko?','a'=>'Barang temuan disimpan maksimal 30 hari kalender sejak tanggal ditemukan. Jika tidak diambil dalam periode tersebut, barang akan diserahkan ke instansi terkait sesuai regulasi PT KCI.'],
    ['q'=>'Apakah ada biaya untuk mengambil barang temuan?','a'=>'Tidak ada biaya apapun untuk pengambilan barang temuan yang sah. Harap waspada terhadap pihak yang meminta pembayaran dengan dalih pengurusan barang hilang.'],
  ]],
  ['cat'=>'Akun & Sistem','icon'=>'bi-person-circle','color'=>'#10B981','bg'=>'rgba(16,185,129,0.08)','items'=>[
    ['q'=>'Bagaimana cara mendaftar akun CommuterLink?','a'=>'Kunjungi halaman register.php dan isi formulir pendaftaran dengan nama lengkap, email aktif, dan password. Setelah verifikasi email, akun kamu langsung bisa digunakan untuk membuat laporan.'],
    ['q'=>'Saya lupa password, bagaimana cara reset-nya?','a'=>'Klik tautan "Lupa Password" di halaman login. Masukkan email yang terdaftar, lalu ikuti instruksi yang dikirim ke emailmu untuk membuat password baru.'],
    ['q'=>'Apakah data laporan saya aman?','a'=>'Ya, semua data laporan dienkripsi dan hanya bisa diakses oleh kamu dan petugas yang berwenang. Kami tidak membagikan data pribadi pengguna kepada pihak ketiga tanpa persetujuan.'],
  ]],
  ['cat'=>'Kontak & Bantuan','icon'=>'bi-headset','color'=>'#F59E0B','bg'=>'rgba(245,158,11,0.08)','items'=>[
    ['q'=>'Bagaimana cara menghubungi tim Customer Service?','a'=>'Kamu bisa menghubungi CS kami melalui: (1) Telepon: 021-121 (Senin–Jumat 07.00–21.00 WIB), (2) Email: cs@commuterlink.id, atau (3) datang langsung ke posko L&F di stasiun terdekat.'],
    ['q'=>'Apa yang harus dilakukan jika sistem tidak merespons laporan saya?','a'=>'Jika lebih dari 3 hari laporan belum diproses, hubungi CS kami di 021-121 dengan menyebutkan nomor laporan. Kami akan menanganinya segera.'],
    ['q'=>'Apakah ada layanan darurat untuk kehilangan dokumen penting?','a'=>'Untuk kehilangan dokumen penting (KTP, passport, STNK, dll), kami menyarankan untuk langsung menghubungi posko L&F terdekat secara langsung atau menelepon 021-121 agar penanganannya diprioritaskan.'],
  ]],
];
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>FAQ — CommuterLink Nusantara</title>
<link rel="icon" href="uploads/favicon.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0B1F3A;--navy-2:#152d52;--navy-3:#1e3d6e;--gold:#F0A500;--gold-lt:#F7C948;--bg:#F0F4F8;--card-bg:#FFFFFF;--text:#1E293B;--text-2:#475569;--text-3:#94A3B8;--border:#E2E8F0;--danger:#EF4444;}
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



.page-content{padding:2rem;max-width:900px;margin:0 auto;}
.breadcrumb-nav{display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-3);margin-bottom:1.25rem;}
.breadcrumb-nav a{color:var(--text-3);text-decoration:none;transition:color .2s;}
.breadcrumb-nav a:hover{color:var(--gold);}

.page-banner{border-radius:18px;padding:1.75rem 2.5rem;margin-bottom:1.75rem;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%);border:1px solid rgba(255,255,255,.07);transition:padding .3s;}
.page-banner::before{content:"";position:absolute;width:280px;height:280px;background:radial-gradient(circle,rgba(240,165,0,.2) 0%,transparent 70%);top:-80px;right:-60px;border-radius:50%;pointer-events:none;}
.page-banner::after{content:"❓";position:absolute;right:2.5rem;bottom:-8px;font-size:6.5rem;opacity:.08;pointer-events:none;}
.banner-label{font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:.4rem;}
.banner-title{font-size:1.55rem;font-weight:800;color:#fff;letter-spacing:-.5px;}
.banner-title span{color:var(--gold-lt);}
.banner-sub{font-size:.82rem;color:rgba(255,255,255,.45);margin-top:.4rem;}

.search-bar{position:relative;margin-bottom:2rem;}
.search-bar i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-3);font-size:1rem;pointer-events:none;}
.search-bar input{width:100%;padding:.8rem 1rem .8rem 2.75rem;border:1.5px solid var(--border);border-radius:12px;font-size:.88rem;background:var(--card-bg);color:var(--text);outline:none;transition:border-color .2s;font-family:inherit;}
.search-bar input:focus{border-color:var(--gold);}
.search-bar input::placeholder{color:var(--text-3);}

.faq-section{margin-bottom:1.75rem;}
.section-head{display:flex;align-items:center;gap:12px;margin-bottom:.9rem;padding:.75rem 1rem;border-radius:12px;}
.section-head-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.section-head-title{font-size:.92rem;font-weight:800;color:var(--text);}
.section-head-count{margin-left:auto;font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:99px;background:rgba(0,0,0,.06);color:var(--text-2);}
[data-theme="dark"] .section-head-count{background:rgba(255,255,255,.07);color:var(--text-3);}
.faq-list{display:flex;flex-direction:column;gap:.6rem;}
.faq-item{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:border-color .2s,background .3s;}
.faq-item.open{border-color:rgba(255,255,255,.18);}
[data-theme="light"] .faq-item.open{border-color:#cbd5e1;}
.faq-q{display:flex;align-items:center;gap:.75rem;padding:.95rem 1.2rem;cursor:pointer;user-select:none;}
.faq-q:hover{background:rgba(255,255,255,.03);}
[data-theme="light"] .faq-q:hover{background:var(--bg);}
.faq-q-text{flex:1;font-size:.85rem;font-weight:700;color:var(--text);line-height:1.4;}
.faq-arrow{color:var(--text-3);font-size:.9rem;transition:transform .25s;flex-shrink:0;}
.faq-item.open .faq-arrow{transform:rotate(180deg);color:var(--gold);}
.faq-a{display:none;padding:.1rem 1.2rem 1.1rem;font-size:.82rem;color:var(--text-2);line-height:1.7;border-top:1px solid var(--border);}
.faq-item.open .faq-a{display:block;}

.contact-cta{background:linear-gradient(135deg,var(--navy),var(--navy-3));border-radius:16px;padding:2rem;text-align:center;margin-top:2.5rem;border:1px solid rgba(255,255,255,.07);}
.cta-title{font-size:1.1rem;font-weight:800;color:#fff;margin-bottom:.4rem;}
.cta-sub{font-size:.82rem;color:rgba(255,255,255,.5);margin-bottom:1.25rem;}
.cta-btns{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;}
.btn-gold{display:inline-flex;align-items:center;gap:6px;padding:.65rem 1.3rem;background:var(--gold);color:var(--navy);border:none;border-radius:9px;font-size:.83rem;font-weight:700;text-decoration:none;cursor:pointer;font-family:inherit;transition:opacity .2s;}
.btn-gold:hover{opacity:.9;color:var(--navy);}
.btn-ghost{display:inline-flex;align-items:center;gap:6px;padding:.65rem 1.3rem;background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:9px;font-size:.83rem;font-weight:600;text-decoration:none;transition:background .15s;}
.btn-ghost:hover{background:rgba(255,255,255,.18);color:#fff;}

/* SETTINGS PANEL */
.sp-overlay{position:fixed;inset:0;z-index:8888;background:rgba(0,0,0,0);pointer-events:none;transition:background .35s;}
.sp-overlay.open{background:rgba(0,0,0,.55);pointer-events:all;backdrop-filter:blur(4px);}
.settings-panel{position:fixed;top:0;right:0;bottom:0;z-index:8889;width:360px;max-width:92vw;background:#0E1E35;border-left:1px solid rgba(255,255,255,.09);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .38s cubic-bezier(.22,1,.36,1);box-shadow:-24px 0 80px rgba(0,0,0,.55);}
.settings-panel.open{transform:translateX(0);}
[data-theme="light"] .settings-panel{background:#1A2E4A;}
.sp-header{padding:1.3rem 1.5rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.sp-title{font-size:1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:9px;}.sp-title i{color:#F59E0B;}
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
@media(max-width:991px){.topbar-nav{display:none;}}
@media(max-width:575px){.page-content{padding:1rem;}.topbar{padding:.65rem 1rem;}}
</style>
</head>
<body>


<main class="page-content">
    <div class="breadcrumb-nav fade-up">
        <a href="index_pelapor.php"><i class="bi bi-house-door"></i> Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size:.55rem;"></i>
        <span>FAQ</span>
    </div>
    <div class="page-banner fade-up">
        <div class="banner-label">Bantuan</div>
        <div class="banner-title">Pertanyaan yang <span>Sering Diajukan</span></div>
        <div class="banner-sub">Temukan jawaban atas pertanyaan umum seputar layanan Lost &amp; Found CommuterLink.</div>
    </div>

    <div class="search-bar fade-up delay-1">
        <i class="bi bi-search"></i>
        <input type="text" id="faqSearch" placeholder="Cari pertanyaan...">
    </div>

    <?php foreach($faqs as $section): ?>
    <div class="faq-section fade-up delay-2">
        <div class="section-head" style="background:<?= $section['bg'] ?>;">
            <div class="section-head-icon" style="background:<?= $section['bg'] ?>;color:<?= $section['color'] ?>;border:1px solid <?= $section['color'] ?>22;"><i class="bi <?= $section['icon'] ?>"></i></div>
            <div class="section-head-title"><?= htmlspecialchars($section['cat']) ?></div>
            <span class="section-head-count"><?= count($section['items']) ?> pertanyaan</span>
        </div>
        <div class="faq-list">
            <?php foreach($section['items'] as $f): ?>
            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-q">
                    <div class="faq-q-text"><?= htmlspecialchars($f['q']) ?></div>
                    <i class="bi bi-chevron-down faq-arrow"></i>
                </div>
                <div class="faq-a"><?= htmlspecialchars($f['a']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="contact-cta fade-up delay-3">
        <div class="cta-title">Masih ada pertanyaan?</div>
        <div class="cta-sub">Tim kami siap membantu kamu setiap hari kerja.</div>
        <div class="cta-btns">
            <a href="tel:021-121" class="btn-gold"><i class="bi bi-telephone-fill"></i> Hubungi 021-121</a>
            <a href="https://mail.google.com/mail/?view=cm&to=redaksi@commuterlink.id" class="btn-ghost"><i class="bi bi-envelope"></i> Email CS</a>
        </div>
    </div>
</main>

<footer style="padding:1.1rem 2rem;border-top:1px solid var(--border);font-size:.73rem;color:var(--text-3);text-align:center;margin-top:1rem;">&copy; <?= date('Y') ?> CommuterLink Nusantara.</footer>

<!-- SETTINGS PANEL -->
<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>
<aside class="settings-panel" id="settingsPanel">
    <div class="sp-header"><div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan Tampilan</div><button class="sp-close" onclick="closeSettings()"><i class="bi bi-x-lg"></i></button></div>
    <div class="sp-body">
        <div class="sp-preview"><div class="sp-preview-thumb" id="spPreviewThumb">❓</div><div class="sp-preview-text"><div class="sp-preview-title" id="spPreviewTitle">Dark · Amber</div><div class="sp-preview-sub" id="spPreviewSub">Tampilan saat ini</div></div></div>
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
function toggleFaq(el){const was=el.classList.contains('open');document.querySelectorAll('.faq-item.open').forEach(i=>i.classList.remove('open'));if(!was)el.classList.add('open');}
document.getElementById('faqSearch').addEventListener('input',function(){
    const q=this.value.toLowerCase();
    document.querySelectorAll('.faq-item').forEach(item=>{
        const txt=item.querySelector('.faq-q-text').textContent.toLowerCase()+item.querySelector('.faq-a').textContent.toLowerCase();
        item.style.display=txt.includes(q)?'':'none';
    });
    document.querySelectorAll('.faq-section').forEach(s=>{
        s.style.display=[...s.querySelectorAll('.faq-item')].some(i=>i.style.display!=='none')?'':'none';
    });
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