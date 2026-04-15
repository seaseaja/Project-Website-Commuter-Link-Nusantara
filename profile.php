<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$successMsg = '';
$errorMsg   = '';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    if ($flash['ok']) $successMsg = $flash['msg'];
    else              $errorMsg   = $flash['msg'];
}

$sessionUser = getCurrentUser();
$userId      = $sessionUser['id'] ?? 0;
$user        = $sessionUser;

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dbUser) { unset($dbUser['password']); $user = $dbUser; }
} catch (Exception $e) {}

$role      = $user['role'] ?? 'pelapor';
$firstName = explode(' ', $user['nama'] ?? 'User')[0];
$isPelapor = $role === 'pelapor';

$joinDate     = $user['created_at'] ?? '2025-11-01';
$totalLaporan = 7;
$selesai      = 4;
$diproses     = 3;
$activeTab    = $_GET['tab'] ?? 'profile';

$indexHref = $isPelapor ? 'index_pelapor.php' : 'index_petugas.php';

$krlLines = ['Bogor Line','Tangerang Line','Bekasi Line','Cikarang Line','Rangkasbitung Line'];

function lineClass(string $l): string {
    return ['Bogor Line'=>'line-bogor','Tangerang Line'=>'line-tangerang','Bekasi Line'=>'line-bekasi',
            'Cikarang Line'=>'line-cikarang','Rangkasbitung Line'=>'line-rangkas'][$l] ?? 'line-bogor';
}
function lineEmoji(string $l): string {
    return ['Bogor Line'=>'🔴','Tangerang Line'=>'🟡','Bekasi Line'=>'🔵',
            'Cikarang Line'=>'🟣','Rangkasbitung Line'=>'🟢'][$l] ?? '🚆';
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Profil Saya — CommuterLink Nusantara</title>
<link rel="icon" href="uploads/favicon.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<?php if($isPelapor): ?>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,700;0,900;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php else: ?>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php endif; ?>
<style>
:root{
  --navy:#0B1F3A;--navy-2:#152d52;--navy-3:#1e3d6e;
  --gold:#F0A500;--gold-lt:#F7C948;
  --bg:<?= $isPelapor?'#0A1628':'#0B1F3A' ?>;
  --card:<?= $isPelapor?'#132035':'#132035' ?>;
  --card-2:<?= $isPelapor?'#192A45':'#192A45' ?>;
  --text:<?= $isPelapor?'#F0F6FF':'#F0F6FF' ?>;
  --text-2:<?= $isPelapor?'#A8BDD6':'#A8BDD6' ?>;
  --text-3:<?= $isPelapor?'#5A7A9E':'#5A7A9E' ?>;
  --border:<?= $isPelapor?'rgba(255,255,255,0.07)':'rgba(255,255,255,0.07)' ?>;
  --border-2:<?= $isPelapor?'rgba(255,255,255,0.12)':'rgba(255,255,255,0.12)' ?>;
  --amber:#F59E0B;--amber-lt:#FCD34D;
  --success:#10B981;--danger:#EF4444;--info:#3B82F6;
  --sidebar-w:<?= $isPelapor?'0':'260px' ?>;
}
[data-theme="light"]{--bg:#F0F6FF;--card:#FFFFFF;--card-2:#F5F9FF;--text:#0D1B2E;--text-2:#2A4263;--text-3:#6B89A8;--border:rgba(13,27,46,.08);--border-2:rgba(13,27,46,.14);}
[data-theme="dark"] {--bg:#0A1628;--card:#132035;--card-2:#192A45;--text:#F0F6FF;--text-2:#A8BDD6;--text-3:#5A7A9E;--border:rgba(255,255,255,.07);--border-2:rgba(255,255,255,.12);}
[data-accent="blue"]  {--amber:#3B82F6;--amber-lt:#60A5FA;}
[data-accent="green"] {--amber:#10B981;--amber-lt:#34D399;}
[data-accent="purple"]{--amber:#8B5CF6;--amber-lt:#A78BFA;}
[data-accent="red"]   {--amber:#EF4444;--amber-lt:#FC8181;}
[data-accent="rose"]  {--amber:#EC4899;--amber-lt:#F472B6;}
[data-fontsize="sm"]{font-size:14px;}[data-fontsize="md"]{font-size:16px;}[data-fontsize="lg"]{font-size:18px;}
[data-compact="true"] .page-content{padding:1rem 1.25rem;}
[data-compact="true"] .profile-hero{padding:1.4rem 1.75rem;}
[data-compact="true"] .card-body{padding:1rem;}
[data-anim="off"] *{animation:none!important;transition:none!important;}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:<?= $isPelapor?'"DM Sans"':'"Plus Jakarta Sans"' ?>,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}

<?php if(!$isPelapor): ?>
.sidebar{position:fixed;top:0;left:0;bottom:0;width:260px;background:var(--navy);display:flex;flex-direction:column;z-index:100;transition:transform .3s;}
.sidebar-brand{padding:1.5rem 1.5rem 1rem;border-bottom:1px solid rgba(255,255,255,.07);}
.brand-icon-box{width:38px;height:38px;background:var(--gold);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
.brand-text-main{font-size:1rem;font-weight:800;color:#fff;line-height:1;}
.brand-text-main em{font-style:normal;color:var(--gold);}
.brand-text-sub{font-size:.65rem;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.1em;margin-top:2px;}
.sidebar-nav{flex:1;padding:1rem .75rem;overflow-y:auto;}
.nav-section-label{font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.25);padding:.75rem .75rem .35rem;}
.nav-item{margin-bottom:2px;}
.nav-link{display:flex;align-items:center;gap:10px;padding:.6rem .85rem;border-radius:9px;color:rgba(255,255,255,.55);font-size:.83rem;font-weight:600;text-decoration:none;transition:background .15s,color .15s;}
.nav-link i{font-size:1rem;}
.nav-link:hover{background:rgba(255,255,255,.07);color:rgba(255,255,255,.9);}
.nav-link.active{background:rgba(240,165,0,.15);color:var(--gold);}
.nav-badge{margin-left:auto;background:var(--gold);color:var(--navy);font-size:.6rem;font-weight:800;padding:1px 6px;border-radius:99px;}
.sidebar-footer{padding:1rem .75rem;border-top:1px solid rgba(255,255,255,.07);}
.logout-btn{display:flex;align-items:center;gap:10px;padding:.6rem .85rem;border-radius:9px;color:rgba(255,255,255,.45);font-size:.83rem;font-weight:600;width:100%;border:none;background:none;cursor:pointer;}
.logout-btn:hover{background:rgba(239,68,68,.12);color:#FC8181;}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99;}
.sidebar-overlay.show{display:block;}
<?php endif; ?>

.main-wrap{margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column;}

.top-nav{position:sticky;top:0;z-index:200;background:<?= $isPelapor?'rgba(10,22,40,.92)':'rgba(240,244,248,.95)' ?>;backdrop-filter:blur(20px);border-bottom:1px solid var(--border);padding:0 2rem;height:62px;display:flex;align-items:center;justify-content:space-between;gap:1rem;transition:background .3s;}
.nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.brand-gem{width:34px;height:34px;background:linear-gradient(135deg,var(--amber),var(--amber-lt));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;box-shadow:0 4px 14px rgba(245,158,11,.4);}
.brand-name{font-family:'Fraunces',serif;font-size:1.05rem;font-weight:700;color:#fff;line-height:1;}
.brand-name em{font-style:italic;color:var(--amber);}
.brand-sub{font-size:.6rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.1em;}
.nav-actions{display:flex;align-items:center;gap:.5rem;}
.nav-icon-btn{width:36px;height:36px;border:1px solid var(--border);background:var(--card);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--text-2);font-size:.95rem;text-decoration:none;transition:all .2s;position:relative;cursor:pointer;}
.nav-icon-btn:hover{border-color:var(--amber);color:var(--amber);background:rgba(245,158,11,.1);}
.notif-pip{position:absolute;top:7px;right:7px;width:6px;height:6px;background:var(--danger);border-radius:50%;border:1.5px solid var(--bg);}
.user-chip{display:flex;align-items:center;gap:8px;padding:4px 12px 4px 4px;border:1px solid var(--border);border-radius:99px;background:var(--card);text-decoration:none;transition:all .2s;}
.chip-avatar{width:28px;height:28px;background:linear-gradient(135deg,var(--amber),var(--amber-lt));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:var(--navy);}
.chip-name{font-size:.78rem;font-weight:600;color:var(--text);}
.topbar{position:sticky;top:0;background:rgba(11,31,58,.97);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,.07);padding:.75rem 2rem;display:flex;align-items:center;justify-content:space-between;z-index:50;transition:background .3s;}
[data-theme="light"] .topbar{background:rgba(240,244,248,.95);border-bottom:1px solid var(--border);}
.sidebar-toggle{display:none;background:none;border:none;font-size:1.3rem;color:var(--text);cursor:pointer;}
.topbar-title{font-size:.92rem;font-weight:700;color:#fff;}
[data-theme="light"] .topbar-title{color:var(--text);}
.topbar-left{display:flex;align-items:center;gap:.75rem;}
.topbar-right{display:flex;align-items:center;gap:.5rem;}
.topbar-btn{width:36px;height:36px;background:var(--card);border:1px solid var(--border);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--text-2);text-decoration:none;position:relative;transition:border-color .2s,color .2s,background .3s;cursor:pointer;}
.topbar-btn:hover{border-color:var(--amber);color:var(--amber);}
.notif-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;background:var(--danger);border-radius:50%;border:1.5px solid var(--bg);}

.page-content{padding:2rem;flex:1;max-width:1000px;margin:0 auto;width:100%;transition:padding .3s;}
<?php if($isPelapor): ?>
@media(max-width:767px){.page-content{padding:1.25rem 1rem 7rem;}}
<?php endif; ?>

.breadcrumb-nav{display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-3);margin-bottom:1.25rem;}
.breadcrumb-nav a{color:var(--text-3);text-decoration:none;}
.breadcrumb-nav a:hover{color:var(--amber);}

.profile-hero{background:linear-gradient(135deg,var(--navy-2) 0%,var(--navy-3) 100%);border-radius:20px;padding:2rem 2.5rem;margin-bottom:1.75rem;position:relative;overflow:hidden;border:1px solid var(--border-2);transition:padding .3s;}
.profile-hero::before{content:"";position:absolute;width:300px;height:300px;background:radial-gradient(circle,rgba(245,158,11,.15) 0%,transparent 70%);top:-100px;right:-60px;border-radius:50%;}

.hero-avatar-ring{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--amber),var(--amber-lt));display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:900;color:var(--navy);box-shadow:0 8px 28px rgba(245,158,11,.35);flex-shrink:0;position:relative;cursor:pointer;overflow:hidden;transition:box-shadow .25s;}
.hero-avatar-ring:hover{box-shadow:0 8px 28px rgba(245,158,11,.55);}
.avatar-img{width:100%;height:100%;object-fit:cover;border-radius:50%;position:absolute;inset:0;}
.avatar-initial{position:relative;z-index:1;}
.avatar-overlay{position:absolute;inset:0;background:rgba(0,0,0,0);display:flex;align-items:center;justify-content:center;border-radius:50%;transition:background .2s;z-index:2;}
.hero-avatar-ring:hover .avatar-overlay{background:rgba(0,0,0,.5);}
.avatar-overlay i{color:#fff;font-size:1.3rem;opacity:0;transform:scale(.7);transition:opacity .2s,transform .2s;}
.hero-avatar-ring:hover .avatar-overlay i{opacity:1;transform:scale(1);}
.avatar-status{position:absolute;bottom:4px;right:4px;width:16px;height:16px;background:var(--success);border-radius:50%;border:2.5px solid var(--navy-2);z-index:3;}
.avatar-cam-badge{position:absolute;bottom:2px;right:2px;width:22px;height:22px;background:var(--amber);border-radius:50%;border:2px solid var(--navy-2);display:flex;align-items:center;justify-content:center;z-index:4;pointer-events:none;}
.avatar-cam-badge i{font-size:.65rem;color:var(--navy);}
.upload-ring{position:absolute;inset:-3px;border-radius:50%;border:3px solid transparent;border-top-color:var(--amber);animation:spin .8s linear infinite;display:none;z-index:5;}
@keyframes spin{to{transform:rotate(360deg);}}

.hero-name{font-family:'Fraunces',serif;font-size:1.5rem;font-weight:900;color:#fff;letter-spacing:-.5px;}
.hero-role-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:.72rem;font-weight:700;background:rgba(245,158,11,.15);color:var(--amber-lt);border:1px solid rgba(245,158,11,.25);margin-top:.3rem;}
.hero-meta{font-size:.78rem;color:rgba(255,255,255,.5);margin-top:.3rem;}

.hero-stats{display:flex;gap:2rem;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,.08);flex-wrap:wrap;}
.hs-item{display:flex;flex-direction:column;}
.hs-num{font-family:'Fraunces',serif;font-size:1.5rem;font-weight:700;color:#fff;}
.hs-label{font-size:.68rem;color:rgba(255,255,255,.45);margin-top:2px;}

.btn-edit-profile{display:inline-flex;align-items:center;gap:6px;padding:.6rem 1.1rem;background:rgba(245,158,11,.15);color:var(--amber-lt);border:1px solid rgba(245,158,11,.25);border-radius:10px;font-size:.78rem;font-weight:700;cursor:pointer;transition:background .2s;}
.btn-edit-profile:hover{background:rgba(245,158,11,.25);color:#fff;}

.tab-bar{display:flex;gap:4px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:4px;margin-bottom:1.5rem;overflow-x:auto;transition:background .3s,border-color .3s;}
.tab-btn{flex:1;min-width:fit-content;padding:.55rem 1.1rem;border-radius:9px;border:none;background:transparent;font-family:inherit;font-size:.82rem;font-weight:600;color:var(--text-3);cursor:pointer;transition:all .2s;white-space:nowrap;display:flex;align-items:center;justify-content:center;gap:6px;}
.tab-btn:hover{color:var(--text-2);}
.tab-btn.active{background:<?= $isPelapor?'var(--amber)':'var(--navy)' ?>;color:<?= $isPelapor?'var(--navy)':'#fff' ?>;}

.cl-card{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;transition:background .3s,border-color .3s;}
.card-head{padding:1.1rem 1.4rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.card-title{font-size:.9rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.card-title i{color:var(--amber);}
.card-body{padding:1.4rem;transition:padding .3s;}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:.9rem;}
.form-group{margin-bottom:.9rem;}
.form-label{font-size:.73rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-3);display:block;margin-bottom:.4rem;}
.form-control{width:100%;padding:.7rem 1rem;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.84rem;color:var(--text);background:var(--card-2);transition:border-color .2s,box-shadow .2s,background .3s;outline:none;}
.form-control:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(245,158,11,.13);}
.form-control::placeholder{color:var(--text-3);}
.form-control[readonly]{opacity:.6;cursor:not-allowed;}
textarea.form-control{resize:vertical;min-height:90px;}
.input-with-icon{position:relative;}
.input-with-icon>i:first-child{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-3);font-size:.9rem;pointer-events:none;z-index:1;}
.input-with-icon .form-control{padding-left:2.3rem;}
.input-icon-right{position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--text-3);font-size:.8rem;}
.password-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-3);cursor:pointer;font-size:.9rem;border:none;background:none;}
.password-toggle:hover{color:var(--amber);}
select.form-control{appearance:none;cursor:pointer;padding-right:2.5rem;}

.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:.72rem 1.4rem;background:var(--amber);color:var(--navy);border:none;border-radius:10px;font-family:inherit;font-size:.84rem;font-weight:700;cursor:pointer;transition:all .2s;}
.btn-primary:hover{background:var(--amber-lt);transform:translateY(-1px);}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.btn-outline{display:inline-flex;align-items:center;gap:7px;padding:.72rem 1.2rem;background:transparent;color:var(--text-2);border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.84rem;font-weight:700;cursor:pointer;transition:all .2s;}
.btn-outline:hover{border-color:var(--amber);color:var(--amber);}
.btn-danger{display:inline-flex;align-items:center;gap:7px;padding:.72rem 1.2rem;background:rgba(239,68,68,.1);color:var(--danger);border:1.5px solid rgba(239,68,68,.2);border-radius:10px;font-family:inherit;font-size:.84rem;font-weight:700;cursor:pointer;transition:all .2s;}
.btn-danger:hover{background:rgba(239,68,68,.15);}

.alert{display:flex;align-items:center;gap:9px;padding:.8rem 1rem;border-radius:10px;font-size:.82rem;font-weight:600;margin-bottom:1.25rem;}
.alert-success{background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.2);}
.alert-error{background:rgba(239,68,68,.1);color:#DC2626;border:1px solid rgba(239,68,68,.15);}

.avatar-toast{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(60px);background:var(--navy);color:#fff;padding:.7rem 1.3rem;border-radius:99px;font-size:.8rem;font-weight:600;display:flex;align-items:center;gap:8px;box-shadow:0 8px 28px rgba(0,0,0,.35);z-index:9999;transition:transform .3s cubic-bezier(.34,1.56,.64,1),opacity .3s;opacity:0;}
.avatar-toast.show{transform:translateX(-50%) translateY(0);opacity:1;}
.avatar-toast.success i{color:var(--success);}
.avatar-toast.error i{color:var(--danger);}

.strength-bar{display:flex;gap:4px;margin-top:.5rem;}
.strength-seg{flex:1;height:4px;border-radius:99px;background:var(--border-2);transition:background .3s;}
.strength-label{font-size:.68rem;margin-top:.3rem;color:var(--text-3);}

.pref-row{display:flex;align-items:center;justify-content:space-between;padding:.85rem 0;border-bottom:1px solid var(--border);}
.pref-row:last-child{border-bottom:none;}
.pref-title{font-size:.83rem;font-weight:600;color:var(--text);}
.pref-desc{font-size:.72rem;color:var(--text-3);margin-top:2px;}
.toggle-switch{position:relative;width:42px;height:24px;flex-shrink:0;}
.toggle-switch input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;inset:0;background:var(--border-2);border-radius:99px;cursor:pointer;transition:.3s;}
.toggle-slider:before{content:"";position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 4px rgba(0,0,0,.2);}
input:checked+.toggle-slider{background:var(--amber);}
input:checked+.toggle-slider:before{transform:translateX(18px);}

.danger-zone{background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.15);border-radius:14px;padding:1.3rem;margin-top:1.25rem;}
.danger-title{font-size:.85rem;font-weight:700;color:#DC2626;margin-bottom:.4rem;display:flex;align-items:center;gap:7px;}
.danger-desc{font-size:.78rem;color:var(--text-2);line-height:1.6;margin-bottom:1rem;}

.activity-item{display:flex;gap:12px;padding:.8rem 0;border-bottom:1px solid var(--border);align-items:flex-start;}
.activity-item:last-child{border-bottom:none;}
.act-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
.act-text{font-size:.8rem;font-weight:600;color:var(--text);line-height:1.4;}
.act-meta{font-size:.69rem;color:var(--text-3);margin-top:2px;}
.status-badge{display:inline-flex;font-size:.67rem;font-weight:700;padding:3px 9px;border-radius:99px;}
.b-selesai{background:rgba(16,185,129,.12);color:#059669;}
.b-diproses{background:rgba(245,158,11,.12);color:#D97706;}
.b-ditemukan{background:rgba(59,130,246,.12);color:#2563EB;}

.line-pill{display:inline-flex;align-items:center;gap:5px;font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:99px;margin-top:.35rem;}
.line-bogor    {background:rgba(220,38,38,.13);color:#DC2626;}
.line-tangerang{background:rgba(245,158,11,.13);color:#D97706;}
.line-bekasi   {background:rgba(59,130,246,.13);color:#2563EB;}
.line-cikarang {background:rgba(99,102,241,.13);color:#6366F1;}
.line-rangkas  {background:rgba(16,185,129,.13);color:#059669;}

.fade-up{opacity:0;transform:translateY(14px);animation:fadeUp .4s ease forwards;}
@keyframes fadeUp{to{opacity:1;transform:translateY(0);}}
.delay-1{animation-delay:.05s;}.delay-2{animation-delay:.1s;}.delay-3{animation-delay:.15s;}

.form-saving .btn-primary{opacity:.7;pointer-events:none;}
@keyframes spinBtn{to{transform:rotate(360deg);}}
.spin-icon{display:inline-block;animation:spinBtn .7s linear infinite;}

<?php if($isPelapor): ?>
.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:300;background:var(--card);border-top:1px solid var(--border);backdrop-filter:blur(20px);display:flex;align-items:stretch;padding-bottom:env(safe-area-inset-bottom);box-shadow:0 -8px 32px rgba(0,0,0,.25);}
[data-theme="light"] .bottom-nav{background:rgba(255,255,255,.95);box-shadow:0 -4px 24px rgba(13,27,46,.08);}
.bn-item{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:.6rem .15rem .55rem;text-decoration:none;color:var(--text-3);font-size:.58rem;font-weight:600;transition:color .2s;position:relative;border:none;background:none;cursor:pointer;}
.bn-item i{font-size:1.25rem;transition:transform .2s;line-height:1;}
.bn-item:hover{color:var(--amber);}
.bn-item:hover i{transform:translateY(-2px);}
.bn-item.active{color:var(--amber);}
.bn-item.active::after{content:'';position:absolute;top:0;left:20%;right:20%;height:2.5px;background:var(--amber);border-radius:0 0 3px 3px;}
<?php endif; ?>

@media(max-width:991.98px){
  <?php if(!$isPelapor): ?>.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.main-wrap{margin-left:0!important}.sidebar-toggle{display:flex}<?php endif; ?>
  .form-row{grid-template-columns:1fr}
}
@media(max-width:575.98px){.page-content{padding:1.25rem 1rem <?= $isPelapor?'7rem':'1.25rem' ?>}.top-nav,.topbar{padding:0 1rem}.profile-hero{padding:1.5rem}}
</style>

<style>
.sp-overlay{position:fixed;inset:0;z-index:8888;background:rgba(0,0,0,0);pointer-events:none;transition:background .35s;}
.sp-overlay.open{background:rgba(0,0,0,.55);pointer-events:all;backdrop-filter:blur(4px);}
.settings-panel{position:fixed;top:0;right:0;bottom:0;z-index:8889;width:360px;max-width:92vw;background:#0E1E35;border-left:1px solid rgba(255,255,255,.09);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .38s cubic-bezier(.22,1,.36,1);box-shadow:-24px 0 80px rgba(0,0,0,.55);}
.settings-panel.open{transform:translateX(0);}
.sp-header{padding:1.3rem 1.5rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.sp-title{font-size:1rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:9px;}
.sp-title i{color:#F59E0B;}
.sp-close{width:32px;height:32px;background:rgba(255,255,255,.07);border:none;border-radius:8px;color:rgba(255,255,255,.5);font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s,color .2s;}
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
.sp-toast{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(20px);background:#1E3357;border:1px solid rgba(245,158,11,.35);color:#FCD34D;padding:.65rem 1.2rem;border-radius:99px;font-size:.8rem;font-weight:700;display:flex;align-items:center;gap:8px;z-index:9999;opacity:0;pointer-events:none;transition:all .35s;}
.sp-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
</style>
</head>
<body>

<!-- ══ SETTINGS PANEL (shared) ══ -->
<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>
<aside class="settings-panel" id="settingsPanel" role="dialog" aria-label="Pengaturan Tampilan">
    <div class="sp-header">
        <div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan Tampilan</div>
        <button class="sp-close" onclick="closeSettings()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sp-body">
        <div class="sp-preview"><div class="sp-preview-thumb" id="spPreviewThumb">🚆</div><div class="sp-preview-text"><div class="sp-preview-title" id="spPreviewTitle">Dark · Amber</div><div class="sp-preview-sub" id="spPreviewSub">Tampilan saat ini</div></div></div>
        <div class="sp-section"><div class="sp-section-label">Mode Tema</div><div class="theme-grid"><div class="theme-card" data-theme="dark" onclick="setTheme('dark')"><span class="theme-card-icon">🌙</span><div class="theme-card-name">Gelap</div><div class="theme-check"><i class="bi bi-check"></i></div></div><div class="theme-card" data-theme="light" onclick="setTheme('light')"><span class="theme-card-icon">☀️</span><div class="theme-card-name">Terang</div><div class="theme-check"><i class="bi bi-check"></i></div></div><div class="theme-card" data-theme="system" onclick="setTheme('system')"><span class="theme-card-icon">💻</span><div class="theme-card-name">Sistem</div><div class="theme-check"><i class="bi bi-check"></i></div></div></div></div>
        <div class="sp-section"><div class="sp-section-label">Warna Aksen</div><div class="accent-grid"><div><div class="accent-dot" data-accent="amber" style="background:#F59E0B;" onclick="setAccent('amber')"></div><div class="accent-label">Amber</div></div><div><div class="accent-dot" data-accent="blue" style="background:#3B82F6;" onclick="setAccent('blue')"></div><div class="accent-label">Biru</div></div><div><div class="accent-dot" data-accent="green" style="background:#10B981;" onclick="setAccent('green')"></div><div class="accent-label">Hijau</div></div><div><div class="accent-dot" data-accent="purple" style="background:#8B5CF6;" onclick="setAccent('purple')"></div><div class="accent-label">Ungu</div></div><div><div class="accent-dot" data-accent="rose" style="background:#EC4899;" onclick="setAccent('rose')"></div><div class="accent-label">Rose</div></div></div></div>
        <div class="sp-section"><div class="sp-section-label">Ukuran Teks</div><div class="fontsize-row"><button class="fs-btn" data-size="sm" onclick="setFontSize('sm')"><span style="font-size:.8rem;">Aa</span></button><button class="fs-btn" data-size="md" onclick="setFontSize('md')"><span style="font-size:1rem;">Aa</span></button><button class="fs-btn" data-size="lg" onclick="setFontSize('lg')"><span style="font-size:1.2rem;">Aa</span></button></div></div>
        <div class="sp-section"><div class="sp-section-label">Preferensi Lainnya</div><div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-layout-sidebar-inset-reverse"></i></div><div><div class="sp-toggle-label">Mode Kompak</div><div class="sp-toggle-sub">Kurangi jarak &amp; padding</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleCompact" onchange="setToggle('compact',this.checked)"><span class="sp-slider"></span></label></div><div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-magic"></i></div><div><div class="sp-toggle-label">Animasi</div><div class="sp-toggle-sub">Aktifkan transisi &amp; efek gerak</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleAnim" onchange="setToggle('anim',this.checked)"><span class="sp-slider"></span></label></div><div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-bell"></i></div><div><div class="sp-toggle-label">Notifikasi Badge</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleNotif" onchange="setToggle('notif',this.checked)"><span class="sp-slider"></span></label></div></div>
    </div>
    <div class="sp-footer">
        <button class="sp-btn-reset" onclick="resetSettings()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
        <button class="sp-btn-apply" id="spApplyBtn" onclick="saveSettings()"><i class="bi bi-check-lg"></i> Simpan</button>
    </div>
</aside>
<div class="sp-toast" id="spToast"></div>

<!-- ══ SHARED: Avatar form + toast (always rendered) ══ -->
<form method="POST" action="update_profile.php" enctype="multipart/form-data" id="avatarForm" style="display:none;">
    <input type="hidden" name="action" value="upload_avatar">
    <input type="hidden" name="redirect" value="profile.php">
    <input type="file" name="avatar" id="avatarFileInput" accept="image/jpeg,image/png,image/webp,image/gif">
</form>
<div class="avatar-toast" id="mainToast"><i class="bi bi-check-circle-fill"></i><span id="mainToastMsg"></span></div>

<?php if($isPelapor): ?>
<!-- ══ PELAPOR: Top nav ══ -->
<nav class="top-nav">
    <a href="<?= $indexHref ?>" class="nav-brand">
        <div class="brand-gem">🚆</div>
        <div><div class="brand-name">Commuter<em>Link</em></div><div class="brand-sub">Lost &amp; Found</div></div>
    </a>
    <div class="nav-actions">
</nav>
<div class="main-wrap">

<?php else: ?>
<!-- ══ PETUGAS: Sidebar + topbar ══ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div style="display:flex;align-items:center;gap:10px;">
            <div class="brand-icon-box">🚆</div>
            <div><div class="brand-text-main">Commuter<em>Link</em></div><div class="brand-text-sub">Lost &amp; Found</div></div>
        </div>
    </div>
    <div style="padding:1rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.07);">
        <a href="profile.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
            <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0;position:relative;background:linear-gradient(135deg,var(--gold),var(--gold-lt));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;color:var(--navy);" id="sidebarAvatar">
                <?php if(!empty($user['avatar']) && file_exists(__DIR__.'/'.$user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= filemtime(__DIR__.'/'.$user['avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;border-radius:50%;">
                    <span style="opacity:0;"><?= strtoupper(substr($user['nama']??'U',0,1)) ?></span>
                <?php else: ?>
                    <?= strtoupper(substr($user['nama']??'U',0,1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:.82rem;font-weight:700;color:#fff;" id="sidebarName"><?= htmlspecialchars($user['nama']??'User') ?></div>
                <div style="font-size:.68rem;color:rgba(255,255,255,.4);text-transform:capitalize;"><?= htmlspecialchars($role) ?></div>
            </div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu Utama</div>
        <div class="nav-item"><a href="index_petugas.php" class="nav-link"><i class="bi bi-grid"></i> Dashboard</a></div>
        <div class="nav-item"><a href="track.php" class="nav-link"><i class="bi bi-geo-alt"></i> Lacak Laporan</a></div>
        <div class="nav-section-label" style="margin-top:0.5rem;">Administrasi</div>
        <div class="nav-item"><a href="admin.php" class="nav-link"><i class="bi bi-shield-check"></i> Panel Admin</a></div>
        <div class="nav-item"><a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Laporan</a></div>
    </nav>
    <div class="sidebar-footer">
        <a href="profile.php" class="nav-link active" style="margin-bottom:2px;"><i class="bi bi-person-circle"></i> Profil Saya</a>
        <a href="logout.php" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
</aside>
<div class="main-wrap">
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <span class="topbar-title">Profil Saya</span>
        </div>
        <div class="topbar-right">
            <a href="notifikasi_petugas.php" class="topbar-btn" title="Notifikasi" id="notifBtn"><i class="bi bi-bell"></i><span class="notif-dot" id="notifPip"></span></a>
            <button onclick="openSettings()" class="topbar-btn" title="Pengaturan Tampilan" style="border:none;cursor:pointer;"><i class="bi bi-sliders"></i></button>
            <a href="profile.php" class="topbar-btn" title="Lihat &amp; Edit Profil — <?= htmlspecialchars($user['nama'] ?? '') ?>" style="overflow:hidden;padding:0;">
                <?php if (!empty($user['avatar']) && file_exists(__DIR__.'/'.$user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= filemtime(__DIR__.'/'.$user['avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
                <?php else: ?>
                    <span style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--gold),var(--gold-lt));color:var(--navy);font-weight:800;font-size:.8rem;border-radius:8px;"><?= strtoupper(substr($user['nama']??'U',0,1)) ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

<?php endif; ?>

<!-- ══ SHARED PAGE CONTENT ══ -->
<main class="page-content">

    <div class="breadcrumb-nav fade-up">
        <a href="<?= $indexHref ?>"><i class="bi bi-house-door"></i> Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size:.55rem;"></i>
        <span>Profil Saya</span>
    </div>

    <?php if($successMsg): ?>
    <div class="alert alert-success fade-up"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if($errorMsg): ?>
    <div class="alert alert-error fade-up"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <!-- ══ PROFILE HERO ══ -->
    <div class="profile-hero fade-up">
        <div style="display:flex;align-items:flex-start;gap:1.5rem;flex-wrap:wrap;">
            <div class="hero-avatar-ring" id="heroAvatar" onclick="triggerAvatarUpload()" title="Klik untuk ganti foto profil">
                <?php if(!empty($user['avatar']) && file_exists(__DIR__.'/'.$user['avatar'])): ?>
                    <img class="avatar-img" id="avatarPreview" src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= time() ?>" alt="Foto Profil">
                    <span class="avatar-initial" id="avatarInitial" style="display:none;"><?= strtoupper(substr($user['nama']??'U',0,1)) ?></span>
                <?php else: ?>
                    <img class="avatar-img" id="avatarPreview" src="" style="display:none;" alt="">
                    <span class="avatar-initial" id="avatarInitial"><?= strtoupper(substr($user['nama']??'U',0,1)) ?></span>
                <?php endif; ?>
                <div class="avatar-overlay"><i class="bi bi-camera-fill"></i></div>
                <div class="upload-ring" id="uploadRing"></div>
                <span class="avatar-status"></span>
                <div class="avatar-cam-badge"><i class="bi bi-camera-fill"></i></div>
            </div>
            <div style="flex:1;min-width:0;">
                <div class="hero-name" id="heroName"><?= htmlspecialchars($user['nama']??'Pengguna') ?></div>
                <div class="hero-role-badge">
                    <?php $roleIcon=['pelapor'=>'bi-person','petugas'=>'bi-person-badge','admin'=>'bi-shield-check'][$role]??'bi-person'; ?>
                    <i class="bi <?= $roleIcon ?>"></i> <?= ucfirst($role) ?>
                </div>
                <?php if(!empty($user['stasiun']) && !$isPelapor): ?>
                <div><span class="line-pill <?= lineClass($user['stasiun']) ?>"><?= lineEmoji($user['stasiun']).' '.htmlspecialchars($user['stasiun']) ?></span></div>
                <?php endif; ?>
                <div class="hero-meta" style="margin-top:.4rem;">
                    <i class="bi bi-envelope" style="font-size:.72rem;"></i> <span id="heroEmail"><?= htmlspecialchars($user['email']??'—') ?></span>
                    &nbsp;·&nbsp;
                    <i class="bi bi-calendar3" style="font-size:.72rem;"></i> Bergabung <?= date('M Y', strtotime($joinDate)) ?>
                </div>
            </div>
            <button type="button" class="btn-edit-profile" onclick="switchTab('profile')">
                <i class="bi bi-pencil"></i> Edit Profil
            </button>
        </div>
        <div class="hero-stats">
        
        </div>
    </div>

    <!-- ══ TAB BAR ══ -->
    <div class="tab-bar fade-up delay-1">
        <button class="tab-btn <?= $activeTab==='profile' ?'active':'' ?>" onclick="switchTab('profile')"><i class="bi bi-person"></i> Informasi</button>
        <button class="tab-btn <?= $activeTab==='password'?'active':'' ?>" onclick="switchTab('password')"><i class="bi bi-lock"></i> Keamanan</button>
        <button class="tab-btn <?= $activeTab==='notif'   ?'active':'' ?>" onclick="switchTab('notif')"><i class="bi bi-bell"></i> Notifikasi</button>
        <button class="tab-btn <?= $activeTab==='activity'?'active':'' ?>" onclick="switchTab('activity')"><i class="bi bi-clock-history"></i> Aktivitas</button>
        <button class="tab-btn" onclick="openSettings()"><i class="bi bi-sliders"></i> Tampilan</button>
    </div>

    <!-- ══ TAB: PROFILE ══ -->
    <div id="tab-profile" class="tab-content fade-up delay-2" style="display:<?= $activeTab==='profile'?'block':'none' ?>">
        <div class="cl-card">
            <div class="card-head"><div class="card-title"><i class="bi bi-person-fill"></i> Informasi Akun</div></div>
            <div class="card-body">
                <form id="profileForm" method="POST" action="update_profile.php" novalidate>
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="redirect" value="profile.php?tab=profile">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap *</label>
                            <div class="input-with-icon">
                                <i class="bi bi-person"></i>
                                <input type="text" name="nama" id="fieldNama" class="form-control" value="<?= htmlspecialchars($user['nama']??'') ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <div class="input-with-icon">
                                <i class="bi bi-envelope"></i>
                                <input type="email" name="email" id="fieldEmail" class="form-control" value="<?= htmlspecialchars($user['email']??'') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nomor Telepon</label>
                            <div class="input-with-icon">
                                <i class="bi bi-telephone"></i>
                                <input type="tel" name="telp" class="form-control" placeholder="08xx-xxxx-xxxx" value="<?= htmlspecialchars($user['telp'] ?? $user['no_telepon'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <div class="input-with-icon">
                                <i class="bi bi-person-badge"></i>
                                <input type="text" class="form-control" value="<?= ucfirst($role) ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <?php if(!$isPelapor): ?>
                    <div class="form-group">
                        <label class="form-label">Line Tugas</label>
                        <div class="input-with-icon">
                            <i class="bi bi-train-front"></i>
                            <select name="stasiun" class="form-control" id="lineSelect" onchange="updateLinePill(this.value)">
                                <option value="">— Pilih Line —</option>
                                <?php foreach($krlLines as $line):
                                    $sel = ($user['stasiun']??'')===$line?'selected':''; ?>
                                <option value="<?= htmlspecialchars($line) ?>" <?= $sel ?>><?= lineEmoji($line).' '.htmlspecialchars($line) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="bi bi-chevron-down input-icon-right"></i>
                        </div>
                        <div id="linePillPreview" style="margin-top:.5rem;min-height:24px;">
                            <?php if(!empty($user['stasiun'])): ?>
                            <span class="line-pill <?= lineClass($user['stasiun']) ?>"><?= lineEmoji($user['stasiun']).' '.htmlspecialchars($user['stasiun']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label class="form-label">Bio / Keterangan</label>
                        <textarea name="bio" class="form-control" placeholder="Tulis sedikit tentang dirimu..."><?= htmlspecialchars($user['bio']??'') ?></textarea>
                    </div>
                    <div id="profileFormMsg" style="display:none;margin-bottom:.75rem;"></div>
                    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem;">
                        <button type="submit" class="btn-primary" id="profileSubmitBtn"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
                        <button type="reset" class="btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ══ TAB: PASSWORD ══ -->
    <div id="tab-password" class="tab-content fade-up delay-2" style="display:<?= $activeTab==='password'?'block':'none' ?>">
        <div class="cl-card" style="margin-bottom:1.25rem;">
            <div class="card-head"><div class="card-title"><i class="bi bi-lock-fill"></i> Ubah Password</div></div>
            <div class="card-body">
                <form id="passwordForm" method="POST" action="update_profile.php" novalidate>
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="redirect" value="profile.php?tab=password">
                    <div class="form-group">
                        <label class="form-label">Password Lama</label>
                        <div class="input-with-icon" style="position:relative;">
                            <i class="bi bi-lock"></i>
                            <input type="password" name="old_password" id="oldPw" class="form-control" placeholder="Masukkan password saat ini" required>
                            <button type="button" class="password-toggle" onclick="togglePw('oldPw',this)"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Password Baru</label>
                            <div class="input-with-icon" style="position:relative;">
                                <i class="bi bi-key"></i>
                                <input type="password" name="new_password" id="newPw" class="form-control" placeholder="Min. 8 karakter" required oninput="checkStrength(this.value)">
                                <button type="button" class="password-toggle" onclick="togglePw('newPw',this)"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="strength-bar"><div class="strength-seg" id="s1"></div><div class="strength-seg" id="s2"></div><div class="strength-seg" id="s3"></div><div class="strength-seg" id="s4"></div></div>
                            <div class="strength-label" id="strengthLabel">Masukkan password baru</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <div class="input-with-icon" style="position:relative;">
                                <i class="bi bi-key-fill"></i>
                                <input type="password" name="confirm_password" id="confPw" class="form-control" placeholder="Ulangi password baru" required>
                                <button type="button" class="password-toggle" onclick="togglePw('confPw',this)"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <div style="background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:.9rem 1rem;margin-bottom:1.25rem;font-size:.78rem;color:var(--text-2);line-height:1.6;">
                        <strong style="color:var(--amber);">Tips keamanan:</strong> Gunakan kombinasi huruf besar, huruf kecil, angka, dan simbol.
                    </div>
                    <div id="passwordFormMsg" style="display:none;margin-bottom:.75rem;"></div>
                    <button type="submit" class="btn-primary"><i class="bi bi-shield-lock"></i> Ubah Password</button>
                </form>
            </div>
        </div>
        <div class="danger-zone">
            <div class="danger-title"><i class="bi bi-exclamation-triangle-fill"></i> Zona Bahaya</div>
            <div class="danger-desc">Menghapus akun bersifat permanen. Semua data laporan akan ikut terhapus.</div>
            <button class="btn-danger" onclick="confirmDelete()"><i class="bi bi-trash3"></i> Hapus Akun Saya</button>
        </div>
    </div>

    <!-- ══ TAB: NOTIFIKASI ══ -->
    <div id="tab-notif" class="tab-content fade-up delay-2" style="display:<?= $activeTab==='notif'?'block':'none' ?>">
        <div class="cl-card">
            <div class="card-head"><div class="card-title"><i class="bi bi-bell-fill"></i> Preferensi Notifikasi</div></div>
            <div class="card-body">
                <?php
                $prefs=[
                    ['title'=>'Update Status Laporan','desc'=>'Notifikasi saat status laporanmu berubah','checked'=>true],
                    ['title'=>'Barang Ditemukan','desc'=>'Alert langsung ketika ada kecocokan barang','checked'=>true],
                    ['title'=>'Pengumuman Sistem','desc'=>'Info perubahan layanan dan pemeliharaan','checked'=>true],
                    ['title'=>'Berita & Artikel Baru','desc'=>'Kirim notifikasi saat ada konten baru','checked'=>false],
                    ['title'=>'Email Mingguan','desc'=>'Ringkasan aktivitas akun setiap minggu','checked'=>false],
                    ['title'=>'Notifikasi SMS','desc'=>'Terima notifikasi penting via SMS','checked'=>true],
                ];
                foreach($prefs as $p): ?>
                <div class="pref-row">
                    <div><div class="pref-title"><?= htmlspecialchars($p['title']) ?></div><div class="pref-desc"><?= htmlspecialchars($p['desc']) ?></div></div>
                    <label class="toggle-switch"><input type="checkbox" <?= $p['checked']?'checked':'' ?>><span class="toggle-slider"></span></label>
                </div>
                <?php endforeach; ?>
                <div style="margin-top:1.25rem;">
                    <button class="btn-primary" onclick="showMainToast('Preferensi notifikasi tersimpan!','success')"><i class="bi bi-check-lg"></i> Simpan Preferensi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ TAB: AKTIVITAS ══ -->
    <div id="tab-activity" class="tab-content fade-up delay-2" style="display:<?= $activeTab==='activity'?'block':'none' ?>">
        <div class="cl-card">
            <div class="card-head"><div class="card-title"><i class="bi bi-clock-history"></i> Riwayat Aktivitas</div></div>
            <div class="card-body">
                <?php
                $activities=[
                    ['icon'=>'bi-file-earmark-plus','bg'=>'rgba(245,158,11,.12)','ic'=>'#D97706','text'=>'Membuat laporan kehilangan: <strong>Tas Ransel Hitam</strong>','meta'=>'04 Mar 2026 · 14.32 WIB','badge'=>null],
                    ['icon'=>'bi-check-circle','bg'=>'rgba(16,185,129,.12)','ic'=>'#059669','text'=>'Laporan <strong>LPR-20260228-A1B2C3</strong> selesai diproses','meta'=>'01 Mar 2026 · 09.15 WIB','badge'=>'selesai'],
                    ['icon'=>'bi-search','bg'=>'rgba(59,130,246,.12)','ic'=>'#2563EB','text'=>'Status laporan berubah menjadi <strong>Ditemukan</strong>','meta'=>'28 Feb 2026 · 16.45 WIB','badge'=>'ditemukan'],
                    ['icon'=>'bi-file-earmark-plus','bg'=>'rgba(245,158,11,.12)','ic'=>'#D97706','text'=>'Membuat laporan kehilangan: <strong>Dompet Kulit Coklat</strong>','meta'=>'25 Feb 2026 · 08.20 WIB','badge'=>null],
                    ['icon'=>'bi-person-check','bg'=>'rgba(99,102,241,.12)','ic'=>'#6366F1','text'=>'Profil berhasil diperbarui','meta'=>'20 Feb 2026 · 11.05 WIB','badge'=>null],
                    ['icon'=>'bi-box-arrow-in-right','bg'=>'rgba(16,185,129,.12)','ic'=>'#059669','text'=>'Login dari perangkat baru (Chrome / Windows)','meta'=>'18 Feb 2026 · 07.30 WIB','badge'=>null],
                ];
                foreach($activities as $a): ?>
                <div class="activity-item">
                    <div class="act-icon" style="background:<?= $a['bg'] ?>;color:<?= $a['ic'] ?>;"><i class="bi <?= $a['icon'] ?>"></i></div>
                    <div>
                        <div class="act-text"><?= $a['text'] ?></div>
                        <div class="act-meta"><i class="bi bi-clock" style="font-size:.6rem;"></i> <?= $a['meta'] ?><?php if($a['badge']): ?><span class="status-badge b-<?= $a['badge'] ?>" style="margin-left:6px;"><?= ucfirst($a['badge']) ?></span><?php endif; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</main>

<footer style="padding:1.1rem 2rem;border-top:1px solid var(--border);font-size:.73rem;color:var(--text-3);text-align:center;">
    &copy; <?= date('Y') ?> CommuterLink Nusantara. All Rights Reserved.
</footer>

<?php if($isPelapor): ?>
<!-- ══ PELAPOR: Bottom nav ══ -->
<nav class="bottom-nav" role="navigation" aria-label="Navigasi bawah">
    <a href="index_pelapor.php" class="bn-item" title="Beranda"><i class="bi bi-house"></i><span>Beranda</span></a>
    <a href="stations.php"      class="bn-item" title="Stasiun"><i class="bi bi-train-front"></i><span>Stasiun</span></a>
    <a href="news.php"          class="bn-item" title="Berita"><i class="bi bi-newspaper"></i><span>Berita</span></a>
    <a href="faq.php"           class="bn-item" title="FAQ"><i class="bi bi-question-circle"></i><span>FAQ</span></a>
    <a href="about.php"         class="bn-item" title="Tentang"><i class="bi bi-info-circle"></i><span>Tentang</span></a>
</nav>
<?php endif; ?>

</div><!-- /.main-wrap -->

<script>
<?php if(!$isPelapor): ?>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('show');}
<?php endif; ?>

function switchTab(t) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    const panel = document.getElementById('tab-' + t);
    if (panel) panel.style.display = 'block';
    document.querySelectorAll('.tab-btn').forEach(btn => {
        const oc = btn.getAttribute('onclick') || '';
        if (oc.includes("'" + t + "'")) btn.classList.add('active');
    });
    history.replaceState(null, '', '?tab=' + t);
}

document.getElementById('profileForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn   = document.getElementById('profileSubmitBtn');
    const msgEl = document.getElementById('profileFormMsg');
    msgEl.style.display = 'none';
    setBtn(btn, 'loading');
    try {
        const fd  = new FormData(this);
        const res = await fetch('update_profile.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const data = await res.json();
        if (data.ok) {
            setBtn(btn, 'success');
            if (data.nama) {
                document.getElementById('heroName').textContent = data.nama;
                document.querySelectorAll('.chip-name').forEach(el => el.textContent = data.nama.split(' ')[0]);
                document.querySelectorAll('.avatar-initial').forEach(el => { if (!el.querySelector('img')) el.textContent = data.nama.charAt(0).toUpperCase(); });
            }
            if (data.email) document.getElementById('heroEmail').textContent = data.email;
            if (data.stasiun) { updateLinePill(data.stasiun); }
            showMsgInline(msgEl, data.msg, 'success');
            showMainToast(' ' + data.msg, 'success');
            setTimeout(() => setBtn(btn, 'idle'), 2500);
        } else {
            setBtn(btn, 'idle');
            showMsgInline(msgEl, data.msg, 'error');
            showMainToast('❌ ' + data.msg, 'error');
        }
    } catch(err) {
        setBtn(btn, 'idle');
        showMsgInline(msgEl, 'Koneksi gagal. Coba lagi.', 'error');
    }
});

function setBtn(btn, state) {
    btn.disabled = state !== 'idle';
    const states = {
        idle:    { html: '<i class="bi bi-check-lg"></i> Simpan Perubahan', style: '' },
        loading: { html: '<i class="bi bi-arrow-repeat spin-icon"></i> Menyimpan...', style: 'opacity:.75;cursor:wait;' },
        success: { html: '<i class="bi bi-check-circle-fill"></i> Tersimpan!', style: 'background:#10B981;' },
    };
    const s = states[state] || states.idle;
    btn.innerHTML   = s.html;
    btn.style.cssText = s.style;
}

document.getElementById('passwordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn    = this.querySelector('.btn-primary');
    const msgEl  = document.getElementById('passwordFormMsg');
    const newPw  = document.getElementById('newPw').value;
    const confPw = document.getElementById('confPw').value;
    if (newPw !== confPw) { showMsgInline(msgEl, 'Password baru dan konfirmasi tidak cocok.', 'error'); return; }
    if (newPw.length < 8) { showMsgInline(msgEl, 'Password minimal 8 karakter.', 'error'); return; }
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon"></i> Memproses...';
    msgEl.style.display = 'none';
    try {
        const fd = new FormData(this);
        const res = await fetch('update_profile.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const data = await res.json();
        showMsgInline(msgEl, data.msg, data.ok ? 'success' : 'error');
        showMainToast((data.ok?' ':'❌ ') + data.msg, data.ok?'success':'error');
        if (data.ok) this.reset();
    } catch(err) { showMsgInline(msgEl, 'Koneksi gagal. Coba lagi.', 'error'); }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-shield-lock"></i> Ubah Password';
});

function showMsgInline(el, msg, type) {
    el.style.display = 'flex';
    el.className = 'alert alert-' + (type === 'success' ? 'success' : 'error');
    el.innerHTML = `<i class="bi bi-${type==='success'?'check-circle-fill':'exclamation-triangle-fill'}"></i> ${msg}`;
}

const avatarFileInput = document.getElementById('avatarFileInput');
const avatarPreview   = document.getElementById('avatarPreview');
const avatarInitial   = document.getElementById('avatarInitial');
const uploadRing      = document.getElementById('uploadRing');

function triggerAvatarUpload() { avatarFileInput.click(); }

avatarFileInput.addEventListener('change', async function() {
    const file = this.files[0];
    if (!file) return;
    const allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    if (!allowed.includes(file.type)) { showMainToast('Format tidak didukung. Gunakan JPG/PNG/WEBP.', 'error'); return; }
    if (file.size > 2 * 1024 * 1024) { showMainToast('Ukuran file maksimal 2 MB!', 'error'); return; }
    const reader = new FileReader();
    reader.onload = e => { avatarPreview.src = e.target.result; avatarPreview.style.display = 'block'; if(avatarInitial) avatarInitial.style.display = 'none'; };
    reader.readAsDataURL(file);
    uploadRing.style.display = 'block';
    try {
        const fd = new FormData(document.getElementById('avatarForm'));
        const res = await fetch('update_profile.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const data = await res.json();
        uploadRing.style.display = 'none';
        if (data.ok) {
            if (data.path) {
                avatarPreview.src = data.path; avatarPreview.style.display = 'block';
                if (avatarInitial) avatarInitial.style.display = 'none';
                const sb = document.getElementById('sidebarAvatar');
                if (sb) sb.innerHTML = `<img src="${data.path}" alt="" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;border-radius:50%;">`;
            }
            showMainToast(' ' + data.msg, 'success');
        } else { showMainToast('❌ ' + data.msg, 'error'); }
    } catch(err) {
        uploadRing.style.display = 'none';
        document.getElementById('avatarForm').submit();
    }
});

function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
}
function checkStrength(v) {
    let score = 0;
    if(v.length>=8) score++; if(/[A-Z]/.test(v)) score++; if(/[0-9]/.test(v)) score++; if(/[^A-Za-z0-9]/.test(v)) score++;
    const colors=['#EF4444','#F59E0B','#3B82F6','#10B981'];
    const labels=['Lemah','Cukup','Kuat','Sangat Kuat'];
    for(let i=1;i<=4;i++) document.getElementById('s'+i).style.background = i<=score ? colors[score-1] : 'var(--border-2)';
    const lbl = document.getElementById('strengthLabel');
    lbl.textContent = v.length===0 ? 'Masukkan password baru' : (labels[score-1]||'Lemah');
    lbl.style.color  = score>0 ? colors[score-1] : 'var(--text-3)';
}

const lineColors={'Bogor Line':'line-bogor','Tangerang Line':'line-tangerang','Bekasi Line':'line-bekasi','Cikarang Line':'line-cikarang','Rangkasbitung Line':'line-rangkas'};
const lineEmojis={'Bogor Line':'🔴','Tangerang Line':'🟡','Bekasi Line':'🔵','Cikarang Line':'🟣','Rangkasbitung Line':'🟢'};
function updateLinePill(val){
    const p=document.getElementById('linePillPreview');
    if(!p) return;
    if(!val){p.innerHTML='';return;}
    p.innerHTML=`<span class="line-pill ${lineColors[val]||'line-bogor'}">${lineEmojis[val]||'🚆'} ${val}</span>`;
}

function showMainToast(msg, type='success') {
    const t = document.getElementById('mainToast');
    t.className = 'avatar-toast ' + type;
    document.getElementById('mainToastMsg').textContent = msg;
    t.querySelector('i').className = type==='success' ? 'bi bi-check-circle-fill' : 'bi bi-x-circle-fill';
    t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'), 3200);
}

function confirmDelete(){
    if(confirm('Yakin ingin menghapus akun?\n\nSemua data PERMANEN terhapus dan tidak dapat dipulihkan.'))
        window.location.href='delete_account.php';
}

<?php if($successMsg): ?>showMainToast(' <?= addslashes($successMsg) ?>', 'success');<?php endif; ?>
<?php if($errorMsg):   ?>showMainToast(' <?= addslashes($errorMsg) ?>', 'error');<?php endif; ?>
</script>

<script>
(function(){
    const DEFAULTS={theme:'dark',accent:'amber',fontSize:'md',compact:false,anim:true,notif:true};
    let S=Object.assign({},DEFAULTS);
    const ACCENT_COLORS={amber:'#F59E0B',blue:'#3B82F6',green:'#10B981',purple:'#8B5CF6',rose:'#EC4899'};
    const ACCENT_NAMES ={amber:'Amber',blue:'Biru',green:'Hijau',purple:'Ungu',rose:'Rose'};
    const THEME_NAMES  ={dark:'Gelap',light:'Terang',system:'Sistem'};
    const THEME_ICONS  ={dark:'🌙',light:'☀️',system:'💻'};
    function loadSettings(){try{const s=localStorage.getItem('cl_settings');if(s)S=Object.assign({},DEFAULTS,JSON.parse(s));}catch(e){}}
    function applySettings(){
        const html=document.documentElement;
        let rt=S.theme==='system'?(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):S.theme;
        html.setAttribute('data-theme',rt);html.setAttribute('data-accent',S.accent);
        html.setAttribute('data-fontsize',S.fontSize);html.setAttribute('data-compact',S.compact?'true':'false');
        html.setAttribute('data-anim',S.anim?'on':'off');
        document.querySelectorAll('#notifPip').forEach(p=>p.style.display=S.notif?'':'none');
    }
    function syncPanel(){
        document.querySelectorAll('.theme-card').forEach(c=>c.classList.toggle('active',c.dataset.theme===S.theme));
        document.querySelectorAll('.accent-dot').forEach(d=>d.classList.toggle('active',d.dataset.accent===S.accent));
        document.querySelectorAll('.fs-btn').forEach(b=>b.classList.toggle('active',b.dataset.size===S.fontSize));
        const tc=document.getElementById('toggleCompact'),ta=document.getElementById('toggleAnim'),tn=document.getElementById('toggleNotif');
        if(tc)tc.checked=S.compact;if(ta)ta.checked=S.anim;if(tn)tn.checked=S.notif;
        const ac=ACCENT_COLORS[S.accent]||'#F59E0B';
        document.getElementById('spPreviewThumb').style.background=`linear-gradient(135deg,${ac},rgba(255,255,255,.25))`;
        document.getElementById('spPreviewTitle').textContent=`${THEME_ICONS[S.theme]} ${THEME_NAMES[S.theme]} · ${ACCENT_NAMES[S.accent]}`;
        document.getElementById('spPreviewSub').textContent=`Font: ${S.fontSize==='sm'?'Kecil':S.fontSize==='lg'?'Besar':'Sedang'} · Kompak: ${S.compact?'Ya':'Tidak'}`;
        const ab=document.getElementById('spApplyBtn');if(ab)ab.style.background=ac;
    }
    window.setTheme    = t=>{S.theme=t;   applySettings();syncPanel();};
    window.setAccent   = a=>{S.accent=a;  applySettings();syncPanel();};
    window.setFontSize = f=>{S.fontSize=f;applySettings();syncPanel();};
    window.setToggle   = (k,v)=>{if(k==='compact')S.compact=v;else if(k==='anim')S.anim=v;else if(k==='notif')S.notif=v;applySettings();syncPanel();};
    window.openSettings=()=>{syncPanel();document.getElementById('settingsPanel').classList.add('open');document.getElementById('spOverlay').classList.add('open');document.body.style.overflow='hidden';};
    window.closeSettings=()=>{document.getElementById('settingsPanel').classList.remove('open');document.getElementById('spOverlay').classList.remove('open');document.body.style.overflow='';};
    window.saveSettings=()=>{try{localStorage.setItem('cl_settings',JSON.stringify(S));}catch(e){}closeSettings();showSpToast(' Pengaturan tersimpan!');};
    window.resetSettings=()=>{S=Object.assign({},DEFAULTS);applySettings();syncPanel();try{localStorage.removeItem('cl_settings');}catch(e){}showSpToast('🔄 Pengaturan direset');};
    function showSpToast(msg){const t=document.getElementById('spToast');t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2800);}
    document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key===','){e.preventDefault();openSettings();}if(e.key==='Escape')closeSettings();});
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>{if(S.theme==='system')applySettings();});
    loadSettings();applySettings();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>