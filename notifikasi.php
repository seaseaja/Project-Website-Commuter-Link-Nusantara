<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user      = getCurrentUser();
$role      = $user['role'] ?? 'pelapor';
$firstName = explode(' ', $user['nama'] ?? 'User')[0];
$isPelapor = $role === 'pelapor';

/* Handle mark-as-read */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        $feedback = 'all_read';
    } elseif ($action === 'mark_read' && isset($_POST['notif_id'])) {
        $feedback = 'one_read';
    } elseif ($action === 'delete_all') {
        $feedback = 'deleted';
    }
}

$filterType = $_GET['type'] ?? 'semua';
$page       = max(1, (int)($_GET['p'] ?? 1));
$perPage    = 10;

/*  Mock notifications */
$allNotifs = [
  ['id'=>1,'type'=>'success','icon'=>'bi-check-circle-fill','color'=>'#10B981','bg'=>'rgba(16,185,129,0.1)',
   'title'=>'Laporan Kamu Selesai Diproses!',
   'body'=>'Laporan kehilangan <strong>Tas Ransel Hitam Eiger</strong> (LPR-20260301-A1B2C3) telah berhasil diselesaikan. Barang sudah dikonfirmasi diterima oleh pemilik.',
   'time'=>'2026-03-04 14:32','read'=>false,'cat'=>'laporan'],

  ['id'=>2,'type'=>'info','icon'=>'bi-search','color'=>'#3B82F6','bg'=>'rgba(59,130,246,0.1)',
   'title'=>'Barang Cocok Ditemukan!',
   'body'=>'Tim petugas Stasiun Manggarai menemukan barang yang kemungkinan cocok dengan laporan kamu <strong>LPR-20260228-X9Y8Z7</strong>. Silakan datang ke posko untuk verifikasi.',
   'time'=>'2026-03-03 09:15','read'=>false,'cat'=>'laporan'],

  ['id'=>3,'type'=>'warning','icon'=>'bi-exclamation-triangle-fill','color'=>'#F59E0B','bg'=>'rgba(245,158,11,0.1)',
   'title'=>'Laporan Mendekati Batas Waktu',
   'body'=>'Laporan <strong>LPR-20260210-M3N4O5</strong> akan kedaluwarsa dalam 3 hari. Segera hubungi posko L&F atau perbarui laporan kamu agar tidak tertutup otomatis.',
   'time'=>'2026-03-02 18:00','read'=>false,'cat'=>'laporan'],

  ['id'=>4,'type'=>'info','icon'=>'bi-megaphone-fill','color'=>'#6366F1','bg'=>'rgba(99,102,241,0.1)',
   'title'=>'Pengumuman: Posko Manggarai Renovasi',
   'body'=>'Posko Lost & Found Stasiun Manggarai ditutup sementara pada 5–7 Maret 2026 untuk renovasi. Penumpang dapat menghubungi posko Jatinegara.',
   'time'=>'2026-03-02 10:00','read'=>true,'cat'=>'pengumuman'],

  ['id'=>5,'type'=>'success','icon'=>'bi-person-check-fill','color'=>'#10B981','bg'=>'rgba(16,185,129,0.1)',
   'title'=>'Akun Kamu Berhasil Diverifikasi',
   'body'=>'Selamat! Akun CommuterLink kamu telah diverifikasi. Kini kamu bisa membuat laporan kehilangan tanpa batasan dan mendapatkan notifikasi prioritas.',
   'time'=>'2026-03-01 07:30','read'=>true,'cat'=>'akun'],

  ['id'=>6,'type'=>'info','icon'=>'bi-newspaper','color'=>'#3B82F6','bg'=>'rgba(59,130,246,0.1)',
   'title'=>'Artikel Baru: Tips Keamanan Barang di KRL',
   'body'=>'Redaksi CommuterLink menerbitkan artikel baru: "5 Cara Melindungi Barang Bawaanmu di Kereta Commuter". Baca selengkapnya di halaman Berita & Info.',
   'time'=>'2026-03-01 06:00','read'=>true,'cat'=>'info'],

  ['id'=>7,'type'=>'warning','icon'=>'bi-clock-history','color'=>'#F59E0B','bg'=>'rgba(245,158,11,0.1)',
   'title'=>'Status Laporan Diperbarui',
   'body'=>'Petugas memperbarui status laporan <strong>LPR-20260225-P6Q7R8</strong> menjadi <strong>Sedang Diproses</strong>. Tim sedang mencocokkan dengan barang temuan terbaru.',
   'time'=>'2026-02-28 15:20','read'=>true,'cat'=>'laporan'],

  ['id'=>8,'type'=>'info','icon'=>'bi-bell-fill','color'=>'#6366F1','bg'=>'rgba(99,102,241,0.1)',
   'title'=>'Jam Operasional L&F Diperpanjang',
   'body'=>'Mulai 1 Maret 2026, jam operasional posko Lost & Found di 6 stasiun utama diperpanjang hingga pukul 22.00 WIB untuk melayani lebih banyak penumpang.',
   'time'=>'2026-02-27 08:00','read'=>true,'cat'=>'pengumuman'],

  ['id'=>9,'type'=>'success','icon'=>'bi-box-seam','color'=>'#10B981','bg'=>'rgba(16,185,129,0.1)',
   'title'=>'Barang Temuan Baru Tersedia',
   'body'=>'Petugas Stasiun Depok baru menambahkan barang temuan baru: dompet warna hitam dengan inisial "AR". Cek halaman Barang Temuan untuk detail lengkap.',
   'time'=>'2026-02-26 11:45','read'=>true,'cat'=>'info'],

  ['id'=>10,'type'=>'info','icon'=>'bi-shield-check','color'=>'#3B82F6','bg'=>'rgba(59,130,246,0.1)',
   'title'=>'Login dari Perangkat Baru Terdeteksi',
   'body'=>'Aktivitas login baru terdeteksi dari perangkat Chrome / Windows pada 18 Feb 2026 pukul 07.30 WIB. Jika bukan kamu, segera ubah password.',
   'time'=>'2026-02-18 07:31','read'=>true,'cat'=>'akun'],
];

if (!$isPelapor) {
    array_unshift($allNotifs,
      ['id'=>11,'type'=>'warning','icon'=>'bi-file-earmark-text','color'=>'#F59E0B','bg'=>'rgba(245,158,11,0.1)',
       'title'=>'Laporan Baru Masuk — Perlu Ditangani',
       'body'=>'Ada <strong>3 laporan kehilangan baru</strong> masuk hari ini yang belum ditugaskan. Silakan cek halaman Laporan Kehilangan dan proses segera.',
       'time'=>'2026-03-04 08:00','read'=>false,'cat'=>'tugas'],
      ['id'=>12,'type'=>'danger','icon'=>'bi-exclamation-circle-fill','color'=>'#EF4444','bg'=>'rgba(239,68,68,0.1)',
       'title'=>'Barang Temuan Mendekati Kedaluwarsa',
       'body'=>'Sebanyak <strong>5 barang temuan</strong> di Stasiun Gambir akan melewati batas penyimpanan 30 hari dalam 3 hari ke depan. Segera proses atau catat ke sistem pusat.',
       'time'=>'2026-03-04 07:15','read'=>false,'cat'=>'tugas']
    );
}

$cats = ['semua','laporan','pengumuman','info','akun'];
if (!$isPelapor) $cats[] = 'tugas';

$filtered = $filterType === 'semua'
    ? $allNotifs
    : array_values(array_filter($allNotifs, fn($n) => $n['cat'] === $filterType));

$unreadCount  = count(array_filter($allNotifs, fn($n) => !$n['read']));
$totalFiltered = count($filtered);
$totalPages   = max(1, ceil($totalFiltered / $perPage));
$paged        = array_slice($filtered, ($page - 1) * $perPage, $perPage);

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Baru saja';
    if ($diff < 3600)   return floor($diff/60) . ' menit lalu';
    if ($diff < 86400)  return floor($diff/3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff/86400) . ' hari lalu';
    return date('d M Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifikasi — CommuterLink Nusantara</title>
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
[data-compact="true"] .notif-card   { padding:.75rem 1rem; }
[data-anim="off"] * { animation:none !important; transition:none !important; }

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; transition:background .3s, color .3s; }


/* PAGE LAYOUT */
.page-content { padding:2rem; max-width:1100px; margin:0 auto; }

/* BREADCRUMB */
.breadcrumb-nav { display:flex; align-items:center; gap:6px; font-size:.75rem; color:var(--text-3); margin-bottom:1.25rem; }
.breadcrumb-nav a { color:var(--text-3); text-decoration:none; transition:color .2s; }
.breadcrumb-nav a:hover { color:var(--gold); }

/* PAGE BANNER — identik reports.php */
.page-banner { border-radius:18px; padding:1.75rem 2.5rem; margin-bottom:1.75rem; position:relative; overflow:hidden; background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%); border:1px solid rgba(255,255,255,.07); transition:padding .3s; }
.page-banner::before { content:''; position:absolute; width:280px; height:280px; background:radial-gradient(circle,rgba(240,165,0,.2) 0%,transparent 70%); top:-80px; right:-60px; border-radius:50%; pointer-events:none; }
.page-banner::after { content:'🔔'; position:absolute; right:2.5rem; bottom:-8px; font-size:6.5rem; opacity:.08; pointer-events:none; }
.banner-label { font-size:.68rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--gold); margin-bottom:.4rem; }
.banner-title { font-size:1.55rem; font-weight:800; color:#fff; letter-spacing:-.5px; }
.banner-title span { color:var(--gold-lt); }
.banner-sub { font-size:.82rem; color:rgba(255,255,255,.45); margin-top:.4rem; }
.unread-badge { display:inline-flex; align-items:center; gap:6px; margin-top:.75rem; padding:.38rem .9rem; background:rgba(240,165,0,.15); border:1px solid rgba(240,165,0,.3); border-radius:99px; font-size:.73rem; font-weight:700; color:var(--gold-lt); }

/* LAYOUT GRID */
.layout-grid { display:grid; grid-template-columns:1fr 280px; gap:1.5rem; align-items:start; }

/* TOOLBAR */
.toolbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.filter-tabs { display:flex; gap:4px; background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:4px; overflow-x:auto; flex-shrink:0; transition:background .3s,border-color .3s; }
.filter-tab { padding:.45rem .9rem; border-radius:8px; border:none; background:transparent; font-family:inherit; font-size:.78rem; font-weight:700; color:var(--text-3); cursor:pointer; transition:all .2s; white-space:nowrap; display:flex; align-items:center; gap:5px; text-decoration:none; }
.filter-tab:hover { color:var(--text-2); }
.filter-tab.active { background:var(--gold); color:#fff; }
.tab-count { font-size:.65rem; padding:1px 6px; border-radius:99px; background:rgba(255,255,255,.25); }
.toolbar-actions { display:flex; gap:.5rem; }
.btn-action { display:inline-flex; align-items:center; gap:5px; padding:.5rem .9rem; border-radius:9px; font-family:inherit; font-size:.78rem; font-weight:700; cursor:pointer; border:1.5px solid var(--border); background:var(--card-bg); color:var(--text-2); transition:all .2s; }
.btn-action:hover { border-color:var(--gold); color:var(--gold); }
.btn-action-danger { border-color:rgba(239,68,68,.2); color:var(--danger); background:rgba(239,68,68,.06); }
.btn-action-danger:hover { border-color:var(--danger); background:rgba(239,68,68,.12); }

/* NOTIFICATION CARD */
.notif-list { display:flex; flex-direction:column; gap:.5rem; margin-bottom:1.5rem; }
.notif-card { background:var(--card-bg); border:1px solid var(--border); border-radius:14px; padding:1.1rem 1.3rem; display:flex; align-items:flex-start; gap:1rem; transition:transform .15s,box-shadow .15s,border-color .15s,background .3s; position:relative; overflow:hidden; }
.notif-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.1); border-color:rgba(240,165,0,.3); }
[data-theme="dark"] .notif-card:hover { box-shadow:0 8px 24px rgba(0,0,0,.3); }
.notif-card.unread { border-left:3px solid var(--gold); }
[data-theme="dark"] .notif-card.unread { background:#13263f; }
[data-theme="light"] .notif-card.unread { background:#FFFBF0; }
.notif-card.unread::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,var(--gold),transparent); }
.notif-icon-wrap { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.notif-body { flex:1; min-width:0; }
.notif-header { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; margin-bottom:.35rem; }
.notif-title { font-size:.87rem; font-weight:700; color:var(--text); line-height:1.35; }
.notif-card.unread .notif-title { color:var(--text); }
.notif-time { font-size:.7rem; color:var(--text-3); white-space:nowrap; flex-shrink:0; }
.notif-text { font-size:.8rem; color:var(--text-2); line-height:1.6; }
.notif-text strong { color:var(--text); font-weight:700; }
.notif-footer { display:flex; align-items:center; gap:.75rem; margin-top:.65rem; flex-wrap:wrap; }
.unread-pip { width:8px; height:8px; background:var(--gold); border-radius:50%; flex-shrink:0; margin-top:5px; }

/* CATEGORY PILLS */
.cat-pill { display:inline-flex; align-items:center; gap:4px; font-size:.67rem; font-weight:700; padding:3px 9px; border-radius:99px; }
.cp-laporan    { background:rgba(240,165,0,.12);    color:#D97706; }
.cp-pengumuman { background:rgba(99,102,241,.1);    color:#6366F1; }
.cp-info       { background:rgba(59,130,246,.1);    color:#2563EB; }
.cp-akun       { background:rgba(16,185,129,.1);    color:#059669; }
.cp-tugas      { background:rgba(239,68,68,.1);     color:#DC2626; }

.notif-action-btn { font-size:.72rem; font-weight:700; color:var(--text-3); background:none; border:none; cursor:pointer; display:flex; align-items:center; gap:4px; padding:0; font-family:inherit; transition:color .2s; text-decoration:none; }
.notif-action-btn:hover { color:var(--gold); }

/* EMPTY STATE */
.empty-state { text-align:center; padding:4rem 2rem; }
.empty-icon { width:80px; height:80px; border-radius:50%; background:var(--border); display:flex; align-items:center; justify-content:center; font-size:2.2rem; margin:0 auto 1.25rem; }
.empty-title { font-size:1rem; font-weight:700; color:var(--text-2); margin-bottom:.35rem; }
.empty-sub { font-size:.82rem; color:var(--text-3); line-height:1.6; }

/* PAGINATION — identik reports.php style */
.pagination-bar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; }
.page-info { font-size:.78rem; color:var(--text-3); }
.page-btns { display:flex; gap:4px; }
.page-btn { width:34px; height:34px; border:1.5px solid var(--border); background:var(--card-bg); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; color:var(--text-2); text-decoration:none; cursor:pointer; transition:all .2s; }
.page-btn:hover { border-color:var(--gold); color:var(--gold); }
.page-btn.active { background:var(--gold); color:var(--navy); border-color:var(--gold); }
.page-btn.disabled { opacity:.35; pointer-events:none; }

/* SUMMARY SIDEBAR — identik chart-card reports.php */
.summary-card { background:var(--card-bg); border:1px solid var(--border); border-radius:14px; overflow:hidden; position:sticky; top:80px; transition:background .3s,border-color .3s; margin-bottom:1rem; }
.sc-head { padding:.9rem 1.1rem; border-bottom:1px solid var(--border); font-size:.83rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:7px; }
.sc-head i { color:var(--gold); }
.sc-body { padding:.9rem 1.1rem; }
.summary-row { display:flex; align-items:center; justify-content:space-between; padding:.55rem 0; border-bottom:1px solid var(--border); }
.summary-row:last-child { border-bottom:none; }
.sum-label { font-size:.78rem; color:var(--text-2); display:flex; align-items:center; gap:7px; }
.sum-label i { font-size:.82rem; }
.sum-val { font-size:.82rem; font-weight:700; color:var(--text); }
.quick-action { display:flex; align-items:center; gap:10px; padding:.75rem .9rem; border-radius:10px; text-decoration:none; transition:background .15s; margin-bottom:2px; }
.quick-action:hover { background:var(--bg); }
.qa-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }
.qa-label { font-size:.8rem; font-weight:600; color:var(--text); }
.qa-desc { font-size:.69rem; color:var(--text-3); }

/* ANIMATIONS */
.fade-up { opacity:0; transform:translateY(14px); animation:fadeUp .4s ease forwards; }
@keyframes fadeUp { to { opacity:1; transform:translateY(0); } }
.delay-1 { animation-delay:.05s; }
.delay-2 { animation-delay:.1s; }
.delay-3 { animation-delay:.15s; }

/* TOAST  */
.toast-wrap { position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999; display:flex; flex-direction:column; gap:.5rem; }
.toast-item { display:flex; align-items:center; gap:10px; padding:.85rem 1.1rem; background:var(--navy-2); color:#fff; border-radius:12px; font-size:.82rem; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,.3); animation:slideUp .3s ease; min-width:260px; border:1px solid rgba(240,165,0,.2); }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.toast-icon { font-size:1rem; color:var(--gold); }

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

/* FOOTER */
footer { padding:1.1rem 2rem; border-top:1px solid var(--border); font-size:.73rem; color:var(--text-3); text-align:center; }

/* RESPONSIVE */
@media(max-width:1099.98px) { .layout-grid { grid-template-columns:1fr; } .summary-card { position:static; } }
@media(max-width:991px)     { .topbar-nav { display:none; } }
@media(max-width:575px)     { .page-content { padding:1rem; } .topbar { padding:.65rem 1rem; } .toolbar { flex-direction:column; align-items:stretch; } .filter-tabs { overflow-x:auto; } }
</style>
</head>
<body>


<main class="page-content">

  <!-- BREADCRUMB -->
  <div class="breadcrumb-nav fade-up">
    <a href="index_pelapor.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <i class="bi bi-chevron-right" style="font-size:.55rem;"></i>
    <span>Notifikasi</span>
  </div>

  <!-- BANNER -->
  <div class="page-banner fade-up">
    <div class="banner-label">Pusat Notifikasi</div>
    <div class="banner-title">Notifikasi &amp; <span>Pemberitahuan</span></div>
    <div class="banner-sub">Pantau semua update laporan, pengumuman, dan aktivitas akunmu di sini.</div>
    <?php if ($unreadCount > 0): ?>
    <div class="unread-badge">
      <i class="bi bi-circle-fill" style="font-size:6px;"></i>
      <?= $unreadCount ?> notifikasi belum dibaca
    </div>
    <?php endif; ?>
  </div>

  <div class="layout-grid">

    <!--  MAIN COLUMN -->
    <div>
      <!-- TOOLBAR -->
      <div class="toolbar fade-up delay-1">
        <?php
        $catLabels = [
          'semua'      => ['Semua',       'bi-bell'],
          'laporan'    => ['Laporan',     'bi-file-earmark-text'],
          'pengumuman' => ['Pengumuman',  'bi-megaphone'],
          'info'       => ['Info',        'bi-info-circle'],
          'akun'       => ['Akun',        'bi-person'],
        ];
        if (!$isPelapor) $catLabels['tugas'] = ['Tugas', 'bi-clipboard-check'];
        ?>
        <div class="filter-tabs">
          <?php foreach ($catLabels as $key => [$label, $icon]):
            $cnt = $key === 'semua' ? count($allNotifs) : count(array_filter($allNotifs, fn($n) => $n['cat'] === $key));
          ?>
          <a href="?type=<?= $key ?>" class="filter-tab <?= $filterType === $key ? 'active' : '' ?>">
            <i class="bi <?= $icon ?>"></i> <?= $label ?>
            <?php if ($cnt): ?><span class="tab-count"><?= $cnt ?></span><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
        <div class="toolbar-actions">
          <?php if ($unreadCount > 0): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn-action"><i class="bi bi-check2-all"></i> Tandai Dibaca</button>
          </form>
          <?php endif; ?>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus semua notifikasi?')">
            <input type="hidden" name="action" value="delete_all">
            <button type="submit" class="btn-action btn-action-danger"><i class="bi bi-trash3"></i> Hapus Semua</button>
          </form>
        </div>
      </div>

      <!-- NOTIF LIST -->
      <?php if (empty($paged)): ?>
      <div class="empty-state fade-up delay-2">
        <div class="empty-icon">🔕</div>
        <div class="empty-title">Tidak Ada Notifikasi</div>
        <div class="empty-sub">Belum ada notifikasi dalam kategori ini.<br>Kami akan memberitahumu saat ada update penting.</div>
      </div>
      <?php else: ?>
      <div class="notif-list fade-up delay-2">
        <?php foreach ($paged as $n): ?>
        <div class="notif-card <?= !$n['read'] ? 'unread' : '' ?>" id="notif-<?= $n['id'] ?>">
          <?php if (!$n['read']): ?><div class="unread-pip"></div><?php endif; ?>
          <div class="notif-icon-wrap" style="background:<?= $n['bg'] ?>;color:<?= $n['color'] ?>;">
            <i class="bi <?= $n['icon'] ?>"></i>
          </div>
          <div class="notif-body">
            <div class="notif-header">
              <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
              <div class="notif-time" title="<?= $n['time'] ?>"><?= timeAgo($n['time']) ?></div>
            </div>
            <div class="notif-text"><?= $n['body'] ?></div>
            <div class="notif-footer">
              <span class="cat-pill cp-<?= $n['cat'] ?>">
                <i class="bi <?= $catLabels[$n['cat']][1] ?? 'bi-bell' ?>"></i>
                <?= ucfirst($n['cat']) ?>
              </span>
              <?php if (!$n['read']): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                <button type="submit" class="notif-action-btn"><i class="bi bi-check2"></i> Tandai Dibaca</button>
              </form>
              <?php endif; ?>
              <?php if ($n['cat'] === 'laporan'): ?>
              <a href="laporan.php" class="notif-action-btn"><i class="bi bi-arrow-right"></i> Lihat Laporan</a>
              <?php elseif ($n['cat'] === 'info' || $n['cat'] === 'pengumuman'): ?>
              <a href="news.php" class="notif-action-btn"><i class="bi bi-arrow-right"></i> Baca Selengkapnya</a>
              <?php elseif ($n['cat'] === 'tugas'): ?>
              <a href="laporan.php" class="notif-action-btn"><i class="bi bi-arrow-right"></i> Proses Sekarang</a>
              <?php endif; ?>
              <button class="notif-action-btn" onclick="deleteNotif(<?= $n['id'] ?>)" style="margin-left:auto;color:var(--danger);">
                <i class="bi bi-trash3"></i>
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- PAGINATION -->
      <?php if ($totalPages > 1): ?>
      <div class="pagination-bar fade-up delay-3">
        <div class="page-info">Menampilkan <?= count($paged) ?> dari <?= $totalFiltered ?> notifikasi</div>
        <div class="page-btns">
          <a href="?type=<?= $filterType ?>&p=<?= max(1,$page-1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
            <i class="bi bi-chevron-left"></i>
          </a>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?type=<?= $filterType ?>&p=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <a href="?type=<?= $filterType ?>&p=<?= min($totalPages,$page+1) ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <i class="bi bi-chevron-right"></i>
          </a>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <!--  SIDEBAR SUMMARY  -->
    <div class="fade-up delay-2">
      <div class="summary-card">
        <div class="sc-head"><i class="bi bi-bar-chart-fill"></i> Ringkasan</div>
        <div class="sc-body">
          <?php
          $summaryRows = [
            ['icon'=>'bi-bell',              'ic'=>'var(--gold)',  'label'=>'Total',        'val'=>count($allNotifs)],
            ['icon'=>'bi-circle-fill',       'ic'=>'var(--gold)',  'label'=>'Belum Dibaca', 'val'=>$unreadCount],
            ['icon'=>'bi-file-earmark-text', 'ic'=>'#D97706',     'label'=>'Laporan',      'val'=>count(array_filter($allNotifs,fn($n)=>$n['cat']==='laporan'))],
            ['icon'=>'bi-megaphone',         'ic'=>'#6366F1',     'label'=>'Pengumuman',   'val'=>count(array_filter($allNotifs,fn($n)=>$n['cat']==='pengumuman'))],
            ['icon'=>'bi-info-circle',       'ic'=>'#2563EB',     'label'=>'Info',         'val'=>count(array_filter($allNotifs,fn($n)=>$n['cat']==='info'))],
            ['icon'=>'bi-person',            'ic'=>'#059669',     'label'=>'Akun',         'val'=>count(array_filter($allNotifs,fn($n)=>$n['cat']==='akun'))],
          ];
          if (!$isPelapor) $summaryRows[] = ['icon'=>'bi-clipboard-check','ic'=>'#DC2626','label'=>'Tugas','val'=>count(array_filter($allNotifs,fn($n)=>$n['cat']==='tugas'))];
          foreach ($summaryRows as $r):
          ?>
          <div class="summary-row">
            <div class="sum-label"><i class="bi <?= $r['icon'] ?>" style="color:<?= $r['ic'] ?>;"></i> <?= $r['label'] ?></div>
            <div class="sum-val"><?= $r['val'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="summary-card">
        <div class="sc-head"><i class="bi bi-lightning-charge-fill"></i> Aksi Cepat</div>
        <div class="sc-body" style="padding:.6rem .75rem;">
          <a href="laporan.php" class="quick-action">
            <div class="qa-icon" style="background:rgba(240,165,0,.12);color:#D97706;"><i class="bi bi-file-earmark-text"></i></div>
            <div><div class="qa-label">Laporan Saya</div><div class="qa-desc">Cek status laporan</div></div>
          </a>
          <a href="profile.php?tab=notif" class="quick-action">
            <div class="qa-icon" style="background:rgba(99,102,241,.1);color:#6366F1;"><i class="bi bi-gear"></i></div>
            <div><div class="qa-label">Preferensi Notif</div><div class="qa-desc">Atur notifikasi kamu</div></div>
          </a>
          <a href="track.php" class="quick-action">
            <div class="qa-icon" style="background:rgba(59,130,246,.1);color:#2563EB;"><i class="bi bi-geo-alt"></i></div>
            <div><div class="qa-label">Lacak Laporan</div><div class="qa-desc">Pantau perkembangan</div></div>
          </a>
          <?php if (!$isPelapor): ?>
          <a href="laporan.php?status=diproses" class="quick-action">
            <div class="qa-icon" style="background:rgba(239,68,68,.1);color:#DC2626;"><i class="bi bi-clipboard-check"></i></div>
            <div><div class="qa-label">Proses Laporan</div><div class="qa-desc">Tangani yang menunggu</div></div>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /layout-grid -->

</main>

<footer>&copy; <?= date('Y') ?> CommuterLink Nusantara. All Rights Reserved.</footer>

<!-- TOAST CONTAINER -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- SETTINGS PANEL -->
<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>
<aside class="settings-panel" id="settingsPanel">
    <div class="sp-header"><div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan Tampilan</div><button class="sp-close" onclick="closeSettings()"><i class="bi bi-x-lg"></i></button></div>
    <div class="sp-body">
        <div class="sp-preview"><div class="sp-preview-thumb" id="spPreviewThumb">🔔</div><div class="sp-preview-text"><div class="sp-preview-title" id="spPreviewTitle">Dark · Amber</div><div class="sp-preview-sub" id="spPreviewSub">Tampilan saat ini</div></div></div>
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
function showToast(msg, icon = 'bi-check-circle-fill') {
  const wrap = document.getElementById('toastWrap');
  const t = document.createElement('div');
  t.className = 'toast-item';
  t.innerHTML = `<i class="bi ${icon} toast-icon"></i> ${msg}`;
  wrap.appendChild(t);
  setTimeout(() => t.style.opacity = '0', 2500);
  setTimeout(() => t.remove(), 2900);
}

function deleteNotif(id) {
  const el = document.getElementById('notif-' + id);
  if (!el) return;
  el.style.transition = 'all .3s ease';
  el.style.opacity = '0';
  el.style.transform = 'translateX(30px)';
  setTimeout(() => el.remove(), 300);
  showToast('Notifikasi dihapus', 'bi-trash3');
}

<?php if (isset($feedback)): ?>
<?php if ($feedback === 'all_read'): ?>showToast('Semua notifikasi ditandai dibaca', 'bi-check2-all');
<?php elseif ($feedback === 'one_read'): ?>showToast('Notifikasi ditandai dibaca', 'bi-check2');
<?php elseif ($feedback === 'deleted'): ?>showToast('Semua notifikasi dihapus', 'bi-trash3');
<?php endif; ?>
<?php endif; ?>

document.addEventListener('keydown', e => {
  if (e.key === 'm' && !e.ctrlKey && !e.metaKey && document.activeElement.tagName !== 'INPUT') {
    document.querySelector('form [name="action"][value="mark_all_read"]')?.closest('form')?.submit();
  }
});

/* Settings Panel (identik reports.php) */
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