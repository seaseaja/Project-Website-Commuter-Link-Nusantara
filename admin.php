<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user = getCurrentUser();
$role = $user['role'] ?? 'pelapor';
if ($role !== 'petugas') { header('Location: index.php'); exit; }
// Anggap petugas yang bisa akses ini adalah "admin" level — sesuaikan dengan kebutuhan
$firstName = explode(' ', $user['nama'] ?? 'User')[0];

// Ambil koneksi DB via fungsi getDB() dari auth.php
$pdo = getDB();

// ── HANDLE TOGGLE AKTIF / NONAKTIF ─────────────────────────────────────────
$actionMsg  = '';
$actionType = 'success'; // 'success' | 'danger'

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['uid'])) {
    $uid = (int) $_POST['uid'];
    // Jangan bisa nonaktifkan diri sendiri
    if ($uid === (int)($user['id'] ?? 0)) {
        $actionMsg  = 'Tidak dapat mengubah status akun sendiri.';
        $actionType = 'danger';
    } else {
        if ($_POST['action'] === 'nonaktifkan') {
            $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
            $stmt->execute([$user['id'], $uid]);
            $actionMsg = 'Akun berhasil dinonaktifkan.';
        } elseif ($_POST['action'] === 'aktifkan') {
            $stmt = $pdo->prepare("UPDATE users SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
            $stmt->execute([$uid]);
            $actionMsg = 'Akun berhasil diaktifkan kembali.';
        }
        // Log aktivitas
        if ($actionMsg) {
            $logStmt = $pdo->prepare("INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) VALUES (?, ?, ?)");
            $logStmt->execute([$user['id'], $actionMsg . " (ID: $uid)", $_SERVER['REMOTE_ADDR'] ?? '']);
        }
    }
}

// ── STATS ───────────────────────────────────────────────────────────────────
$stats = [];

// Total pengguna (tidak termasuk yg di-soft-delete)
$stats['users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();

// Total petugas aktif
$stats['petugas'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='petugas' AND deleted_at IS NULL")->fetchColumn();

// Total laporan kehilangan
$stats['laporan'] = $pdo->query("SELECT COUNT(*) FROM laporan_kehilangan WHERE deleted_at IS NULL")->fetchColumn();

// Kasus selesai (status = 'selesai' atau 'ditutup')
$stats['resolved'] = $pdo->query("SELECT COUNT(*) FROM laporan_kehilangan WHERE status IN ('selesai','ditutup') AND deleted_at IS NULL")->fetchColumn();

// ── USERS LIST ──────────────────────────────────────────────────────────────
$allUsers = $pdo->query("
    SELECT id, nama, email, role, stasiun, deleted_at, created_at,
           CASE WHEN deleted_at IS NULL THEN 'aktif' ELSE 'nonaktif' END AS status
    FROM users
    WHERE role = 'petugas'
    ORDER BY created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── ROLE BREAKDOWN ──────────────────────────────────────────────────────────
$totalUsers    = max(1, (int)$stats['users']);
$petugasCount  = (int)$stats['petugas'];
$pelaporCount  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='pelapor' AND deleted_at IS NULL")->fetchColumn();
$roleData = [
    ['label'=>'Pelapor', 'count'=>$pelaporCount, 'color'=>'#10B981', 'pct'=> round($pelaporCount/$totalUsers*100)],
    ['label'=>'Petugas', 'count'=>$petugasCount, 'color'=>'#3B82F6', 'pct'=> round($petugasCount/$totalUsers*100)],
];

// ── ACTIVITY LOGS ────────────────────────────────────────────────────────────
$systemLogs = $pdo->query("
    SELECT l.aktivitas, l.ip_address, l.created_at, u.nama
    FROM log_aktivitas l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// ── BARANG TEMUAN STATS ──────────────────────────────────────────────────────
$barangStats = $pdo->query("
    SELECT status, COUNT(*) as total
    FROM barang_temuan
    WHERE deleted_at IS NULL
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── SISTEM INFO ──────────────────────────────────────────────────────────────
$lastBackupLog = $pdo->query("
    SELECT created_at FROM log_aktivitas
    WHERE aktivitas LIKE '%backup%'
    ORDER BY created_at DESC LIMIT 1
")->fetchColumn();
$lastBackupStr = $lastBackupLog ? date('d M Y · H.i', strtotime($lastBackupLog)) : 'Belum ada';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel Admin — CommuterLink Nusantara</title>
<link rel="icon" href="uploads/favicon.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══ CSS VARIABLES ═══ */
:root{--navy:#0B1F3A;--navy-2:#152d52;--navy-3:#1e3d6e;--gold:#F0A500;--gold-lt:#F7C948;--bg:#F0F4F8;--card-bg:#FFFFFF;--text:#1E293B;--text-2:#475569;--text-3:#94A3B8;--border:#E2E8F0;--success:#10B981;--warning:#F59E0B;--danger:#EF4444;--info:#3B82F6;}
[data-theme="dark"]{--bg:#0B1626;--card-bg:#112038;--text:#E8F0FE;--text-2:#94AEC8;--text-3:#506882;--border:rgba(255,255,255,0.07);}
[data-accent="blue"]  {--gold:#3B82F6;--gold-lt:#60A5FA;}
[data-accent="green"] {--gold:#10B981;--gold-lt:#34D399;}
[data-accent="purple"]{--gold:#8B5CF6;--gold-lt:#A78BFA;}
[data-accent="red"]   {--gold:#EF4444;--gold-lt:#FC8181;}
[data-accent="rose"]  {--gold:#EC4899;--gold-lt:#F472B6;}
[data-fontsize="sm"]{font-size:14px;}[data-fontsize="md"]{font-size:16px;}[data-fontsize="lg"]{font-size:18px;}
[data-compact="true"] .page-content{padding:1.25rem;}
[data-compact="true"] .page-banner{padding:1.25rem 1.5rem;}
[data-compact="true"] .stat-card{padding:.9rem 1rem;}
[data-anim="off"] *{animation:none !important;transition:none !important;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:"Plus Jakarta Sans",sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}

/* ═══ LAYOUT ═══ */
.page-content{padding:2rem;max-width:1280px;margin:0 auto;}
.breadcrumb-nav{display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-3);margin-bottom:1.25rem;}
.breadcrumb-nav a{color:var(--text-3);text-decoration:none;transition:color .2s;}
.breadcrumb-nav a:hover{color:var(--gold);}

/* ═══ BANNER ═══ */
.page-banner{border-radius:18px;padding:1.75rem 2.5rem;margin-bottom:1.75rem;position:relative;overflow:hidden;background:linear-gradient(135deg,#1a0a2e 0%,#16213e 50%,#0f3460 100%);border:1px solid rgba(255,255,255,.07);transition:padding .3s;}
.page-banner::before{content:"";position:absolute;width:280px;height:280px;background:radial-gradient(circle,rgba(240,165,0,.25) 0%,transparent 70%);top:-80px;right:-60px;border-radius:50%;pointer-events:none;}
.page-banner::after{content:"🛡️";position:absolute;right:2.5rem;bottom:-8px;font-size:6.5rem;opacity:.1;pointer-events:none;}
.banner-label{font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:.4rem;}
.banner-title{font-size:1.55rem;font-weight:800;color:#fff;letter-spacing:-.5px;}
.banner-title span{color:var(--gold-lt);}
.banner-sub{font-size:.82rem;color:rgba(255,255,255,.45);margin-top:.4rem;}

/* ═══ ALERT BAR ═══ */
.alert-bar{display:flex;align-items:center;gap:10px;padding:.8rem 1.1rem;border-radius:12px;font-size:.82rem;font-weight:600;margin-bottom:1.25rem;}
.alert-bar.success{background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.2);}
.alert-bar.danger{background:rgba(239,68,68,.1);color:#DC2626;border:1px solid rgba(239,68,68,.2);}

/* ═══ STAT GRID ═══ */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.75rem;}
.stat-card{background:var(--card-bg);border-radius:14px;padding:1.25rem 1.4rem;border:1px solid var(--border);transition:transform .2s,box-shadow .2s,background .3s,border-color .3s;}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 28px rgba(0,0,0,.15);}
.stat-icon{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;margin-bottom:1rem;}
.si-blue{background:rgba(59,130,246,.12);color:var(--info);}
.si-green{background:rgba(16,185,129,.12);color:var(--success);}
.si-gold{background:rgba(240,165,0,.12);color:var(--gold);}
.si-red{background:rgba(239,68,68,.12);color:var(--danger);}
.stat-num{font-size:1.75rem;font-weight:800;color:var(--text);line-height:1;}
.stat-label{font-size:.75rem;color:var(--text-3);margin-top:4px;font-weight:600;}

/* ═══ ADMIN GRID ═══ */
.admin-grid{display:grid;grid-template-columns:1fr 360px;gap:1.25rem;}

/* ═══ CARDS ═══ */
.cl-card{background:var(--card-bg);border-radius:14px;border:1px solid var(--border);overflow:hidden;transition:background .3s,border-color .3s;}
.cl-head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.3rem;border-bottom:1px solid var(--border);}
.cl-head-title{font-size:.88rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:7px;}
.cl-head-title i{color:var(--gold);}
.cl-head-link{font-size:.75rem;font-weight:700;color:var(--gold);text-decoration:none;transition:opacity .2s;}
.cl-head-link:hover{opacity:.75;}

/* ═══ USERS TABLE ═══ */
.users-table{width:100%;border-collapse:collapse;}
.users-table th{font-size:.67rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-3);padding:.55rem .9rem;border-bottom:1px solid var(--border);text-align:left;}
.users-table td{padding:.75rem .9rem;font-size:.8rem;border-bottom:1px solid var(--border);vertical-align:middle;}
.users-table tr:last-child td{border-bottom:none;}
.users-table tr:hover td{background:rgba(255,255,255,.03);}
[data-theme="light"] .users-table tr:hover td{background:var(--bg);}
.u-name{font-weight:700;color:var(--text);}
.u-email{font-size:.71rem;color:var(--text-3);margin-top:1px;}
.role-badge{display:inline-flex;font-size:.67rem;font-weight:700;padding:3px 9px;border-radius:99px;}
.r-petugas{background:rgba(59,130,246,.1);color:#2563EB;}
.r-pelapor{background:rgba(16,185,129,.1);color:#059669;}
.status-dot{display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:600;}
.dot-green{width:7px;height:7px;border-radius:50%;background:var(--success);}
.dot-red{width:7px;height:7px;border-radius:50%;background:var(--danger);}
.action-btn{padding:4px 10px;border-radius:7px;font-size:.7rem;font-weight:700;border:1px solid var(--border);background:var(--card-bg);cursor:pointer;color:var(--text-2);font-family:inherit;transition:all .15s;}
.action-btn:hover{border-color:var(--gold);color:var(--gold);}

/* ═══ CONFIRM MODAL ═══ */
.modal-overlay{position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{background:var(--card-bg);border:1px solid var(--border);border-radius:18px;padding:2rem;max-width:360px;width:90%;box-shadow:0 24px 80px rgba(0,0,0,.5);animation:fadeUp .25s ease;}
.modal-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;margin:0 auto 1rem;}
.modal-icon.nonaktif{background:rgba(239,68,68,.12);color:#EF4444;}
.modal-icon.aktif{background:rgba(16,185,129,.12);color:#10B981;}
.modal-title{font-size:1rem;font-weight:800;color:var(--text);text-align:center;margin-bottom:.4rem;}
.modal-sub{font-size:.8rem;color:var(--text-3);text-align:center;margin-bottom:1.5rem;line-height:1.5;}
.modal-actions{display:flex;gap:.6rem;}
.modal-cancel{flex:1;padding:.65rem;border-radius:10px;border:1.5px solid var(--border);background:none;color:var(--text-2);font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;}
.modal-cancel:hover{border-color:var(--text-3);color:var(--text);}
.modal-confirm{flex:2;padding:.65rem;border-radius:10px;border:none;font-family:inherit;font-size:.82rem;font-weight:800;cursor:pointer;transition:all .2s;}
.modal-confirm.nonaktif{background:#EF4444;color:#fff;}
.modal-confirm.nonaktif:hover{background:#DC2626;}
.modal-confirm.aktif{background:#10B981;color:#fff;}
.modal-confirm.aktif:hover{background:#059669;}

/* ═══ LOGS ═══ */
.log-empty{padding:2rem;text-align:center;color:var(--text-3);font-size:.8rem;}
.log-list{display:flex;flex-direction:column;}
.log-item{display:flex;gap:10px;padding:.8rem 1.2rem;border-bottom:1px solid var(--border);align-items:flex-start;}
.log-item:last-child{border-bottom:none;}
.log-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:5px;}
.log-action{font-size:.78rem;color:var(--text);font-weight:600;line-height:1.35;}
.log-meta{font-size:.68rem;color:var(--text-3);margin-top:3px;}

/* ═══ QUICK TOOLS ═══ */
.quick-tools{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;padding:1.1rem;}
.tool-btn{display:flex;flex-direction:column;align-items:flex-start;gap:8px;padding:.9rem 1rem;border-radius:12px;border:1.5px solid var(--border);background:var(--card-bg);text-decoration:none;transition:border-color .2s,transform .2s,background .3s;}
.tool-btn:hover{border-color:var(--gold);transform:translateY(-2px);}
.tool-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;}
.tool-label{font-size:.78rem;font-weight:700;color:var(--text);}
.tool-desc{font-size:.68rem;color:var(--text-3);}

/* ═══ SYSTEM STATUS ═══ */
.sys-row{display:flex;align-items:center;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);}
.sys-row:last-child{border-bottom:none;}
.sys-label{font-size:.8rem;color:var(--text-2);font-weight:600;}
.sys-val{font-size:.78rem;font-weight:700;color:var(--success);display:flex;align-items:center;gap:5px;}
.sys-dot{width:6px;height:6px;border-radius:50%;background:var(--success);}
.sys-val.neutral{color:var(--text-2);}
.sys-val.neutral .sys-dot{background:var(--info);}

/* ═══ BARANG CHIPS ═══ */
.barang-chips{display:flex;flex-wrap:wrap;gap:.5rem;padding:.9rem 1.2rem 1.2rem;}
.bchip{padding:4px 11px;border-radius:99px;font-size:.72rem;font-weight:700;}

/* ═══ ADD USER FORM ═══ */
.add-user-overlay{position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;}
.add-user-overlay.open{display:flex;}
.add-user-box{background:var(--card-bg);border:1px solid var(--border);border-radius:18px;padding:2rem;max-width:440px;width:92%;box-shadow:0 24px 80px rgba(0,0,0,.5);animation:fadeUp .25s ease;}
.add-user-box h3{font-size:1rem;font-weight:800;color:var(--text);margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;}
.add-user-box h3 i{color:var(--gold);}
.form-group{margin-bottom:1rem;}
.form-group label{display:block;font-size:.75rem;font-weight:700;color:var(--text-2);margin-bottom:.4rem;}
.form-group input,.form-group select{width:100%;padding:.6rem .85rem;border-radius:9px;border:1.5px solid var(--border);background:var(--bg);color:var(--text);font-family:inherit;font-size:.82rem;outline:none;transition:border-color .2s;}
.form-group input:focus,.form-group select:focus{border-color:var(--gold);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}
.btn-submit{width:100%;padding:.7rem;border-radius:10px;border:none;background:var(--gold);color:var(--navy);font-family:inherit;font-size:.85rem;font-weight:800;cursor:pointer;transition:opacity .2s;margin-top:.5rem;}
.btn-submit:hover{opacity:.85;}

/* ═══ BTN ═══ */
.btn-add{display:inline-flex;align-items:center;gap:5px;padding:.45rem .9rem;background:var(--gold);color:var(--navy);border:none;border-radius:8px;font-size:.75rem;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity .2s;}
.btn-add:hover{opacity:.85;}

/* ═══ SETTINGS PANEL ═══ */
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
@media(max-width:1199.98px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:991px){.admin-grid{grid-template-columns:1fr}}
@media(max-width:575px){.page-content{padding:1rem;}}
</style>
</head>
<body>

<main class="page-content">
    <div class="breadcrumb-nav fade-up">
        <a href="index_petugas.php"><i class="bi bi-house-door"></i> Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size:.55rem;"></i>
        <span>Panel Admin</span>
    </div>

    <?php if ($actionMsg): ?>
    <div class="alert-bar <?= $actionType ?> fade-up">
        <i class="bi bi-<?= $actionType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
        <?= htmlspecialchars($actionMsg) ?>
    </div>
    <?php endif; ?>

    <div class="page-banner fade-up">
        <div class="banner-label">Administrasi Sistem</div>
        <div class="banner-title">Panel <span>Admin</span></div>
        <div class="banner-sub">Selamat datang, <strong><?= htmlspecialchars($firstName) ?></strong>. Kelola pengguna, monitor sistem, dan konfigurasi layanan CommuterLink.</div>
    </div>

    <!-- ── STAT GRID ── -->
    <div class="stat-grid fade-up delay-1">
        <div class="stat-card">
            <div class="stat-icon si-blue"><i class="bi bi-people"></i></div>
            <div class="stat-num"><?= $stats['users'] ?></div>
            <div class="stat-label">Total Pengguna Aktif</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-gold"><i class="bi bi-person-badge"></i></div>
            <div class="stat-num"><?= $stats['petugas'] ?></div>
            <div class="stat-label">Petugas Aktif</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-red"><i class="bi bi-file-earmark-text"></i></div>
            <div class="stat-num"><?= $stats['laporan'] ?></div>
            <div class="stat-label">Total Laporan</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-green"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-num"><?= $stats['resolved'] ?></div>
            <div class="stat-label">Kasus Selesai / Ditutup</div>
        </div>
    </div>

    <div class="admin-grid fade-up delay-2">

        <!-- ── LEFT COLUMN ── -->
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <!-- USER MANAGEMENT TABLE -->
            <div class="cl-card">
                <div class="cl-head">
                    <div class="cl-head-title"><i class="bi bi-people-fill"></i> Manajemen Pengguna</div>
                    <button onclick="openAddUser()" class="btn-add"><i class="bi bi-plus-lg"></i> Tambah</button>
                </div>
                <div style="overflow-x:auto;">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pengguna</th>
                                <th>Role</th>
                                <th>Stasiun</th>
                                <th>Status</th>
                                <th>Bergabung</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($allUsers)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;color:var(--text-3);padding:2rem;font-size:.8rem;">
                                    Belum ada data pengguna.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allUsers as $i => $u):
                                $isAktif   = is_null($u['deleted_at']);
                                $isSelf    = (int)$u['id'] === (int)($user['id'] ?? 0);
                                $joinDate  = date('d M Y', strtotime($u['created_at']));
                                $stasiunTxt = $u['stasiun'] ?: '—';
                            ?>
                            <tr>
                                <td style="color:var(--text-3);font-size:.72rem;"><?= $i + 1 ?></td>
                                <td>
                                    <div class="u-name"><?= htmlspecialchars($u['nama']) ?></div>
                                    <div class="u-email"><?= htmlspecialchars($u['email']) ?></div>
                                </td>
                                <td><span class="role-badge r-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                                <td style="font-size:.78rem;color:var(--text-2);"><?= htmlspecialchars($stasiunTxt) ?></td>
                                <td>
                                    <span class="status-dot">
                                        <span class="<?= $isAktif ? 'dot-green' : 'dot-red' ?>"></span>
                                        <?= $isAktif ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </td>
                                <td style="font-size:.72rem;color:var(--text-3);"><?= $joinDate ?></td>
                                <td>
                                    <?php if ($isSelf): ?>
                                        <span style="font-size:.7rem;color:var(--text-3);font-style:italic;">Anda</span>
                                    <?php else: ?>
                                    <div style="display:flex;gap:4px;">
                                        <?php if ($isAktif): ?>
                                        <button class="action-btn"
                                            style="color:#EF4444;border-color:rgba(239,68,68,.3);"
                                            onclick="openConfirm(<?= $u['id'] ?>,'nonaktifkan','<?= htmlspecialchars(addslashes($u['nama'])) ?>')">
                                            <i class="bi bi-slash-circle"></i> Nonaktifkan
                                        </button>
                                        <?php else: ?>
                                        <button class="action-btn"
                                            style="color:#10B981;border-color:rgba(16,185,129,.3);"
                                            onclick="openConfirm(<?= $u['id'] ?>,'aktifkan','<?= htmlspecialchars(addslashes($u['nama'])) ?>')">
                                            <i class="bi bi-check-circle"></i> Aktifkan
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ACTIVITY LOG -->
            <div class="cl-card">
                <div class="cl-head">
                    <div class="cl-head-title"><i class="bi bi-clock-history"></i> Log Aktivitas Sistem</div>
                </div>
                <?php if (empty($systemLogs)): ?>
                    <div class="log-empty"><i class="bi bi-inbox" style="font-size:1.5rem;display:block;margin-bottom:.5rem;"></i>Belum ada aktivitas tercatat.</div>
                <?php else: ?>
                <div class="log-list">
                    <?php foreach ($systemLogs as $log):
                        $logTime = date('d M Y · H:i', strtotime($log['created_at']));
                        $logNama = $log['nama'] ?? 'System';
                    ?>
                    <div class="log-item">
                        <div class="log-dot" style="background:var(--gold);"></div>
                        <div>
                            <div class="log-action"><?= htmlspecialchars($log['aktivitas']) ?></div>
                            <div class="log-meta">
                                <i class="bi bi-person" style="font-size:.6rem;"></i> <?= htmlspecialchars($logNama) ?>
                                &nbsp;&middot;&nbsp;
                                <i class="bi bi-clock" style="font-size:.6rem;"></i> <?= $logTime ?>
                                <?php if ($log['ip_address']): ?>
                                &nbsp;&middot;&nbsp; IP: <?= htmlspecialchars($log['ip_address']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── RIGHT COLUMN ── -->
        <div style="display:flex;flex-direction:column;gap:1.25rem;">

            <!-- QUICK TOOLS -->
            <div class="cl-card">
                <div class="cl-head"><div class="cl-head-title"><i class="bi bi-lightning-charge-fill"></i> Alat Admin Cepat</div></div>
                <div class="quick-tools">
                    <a href="reports.php" class="tool-btn">
                        <div class="tool-icon" style="background:rgba(59,130,246,.1);color:#2563EB;"><i class="bi bi-bar-chart-line"></i></div>
                        <div><div class="tool-label">Laporan Bulanan</div><div class="tool-desc">Export data CSV</div></div>
                    </a>
                    <a href="#" class="tool-btn" onclick="confirmBackup();return false;">
                        <div class="tool-icon" style="background:rgba(16,185,129,.1);color:#059669;"><i class="bi bi-cloud-arrow-up"></i></div>
                        <div><div class="tool-label">Backup Database</div><div class="tool-desc">Simpan ke server</div></div>
                    </a>
                    <a href="laporan.php" class="tool-btn">
                        <div class="tool-icon" style="background:rgba(245,158,11,.1);color:#D97706;"><i class="bi bi-search-heart"></i></div>
                        <div><div class="tool-label">Semua Laporan</div><div class="tool-desc">Kelola laporan masuk</div></div>
                    </a>
                    <a href="stations.php" class="tool-btn">
                        <div class="tool-icon" style="background:rgba(239,68,68,.1);color:#DC2626;"><i class="bi bi-train-front"></i></div>
                        <div><div class="tool-label">Data Stasiun</div><div class="tool-desc">Kelola info stasiun</div></div>
                    </a>
                    <a href="verifikasi_ownership.php" class="tool-btn" style="grid-column:span 2;">
                        <div class="tool-icon" style="background:rgba(139,92,246,.1);color:#7C3AED;"><i class="bi bi-shield-check"></i></div>
                        <div><div class="tool-label">Verifikasi Kepemilikan</div><div class="tool-desc">Periksa & konfirmasi bukti pelapor</div></div>
                    </a>
                </div>
            </div>

            <!-- SYSTEM STATUS -->
            <div class="cl-card">
                <div class="cl-head"><div class="cl-head-title"><i class="bi bi-cpu"></i> Status Sistem</div></div>
                <div style="padding:1.1rem;">
                    <?php
                    // Hitung storage sederhana berdasarkan jumlah record
                    $totalRecords = (int)$stats['laporan'] + (int)$pdo->query("SELECT COUNT(*) FROM barang_temuan")->fetchColumn();
                    $sysItems = [
                        ['label' => 'Database',         'val' => 'Online',           'ok' => true],
                        ['label' => 'Total Pengguna',    'val' => count($allUsers) . ' akun', 'ok' => true],
                        ['label' => 'Total Records',     'val' => $totalRecords . ' entri',    'ok' => true],
                        ['label' => 'Backup Terakhir',   'val' => $lastBackupStr,     'ok' => false],
                        ['label' => 'Versi Sistem',      'val' => 'v2.4.1',           'ok' => false],
                    ];
                    foreach ($sysItems as $s): ?>
                    <div class="sys-row">
                        <span class="sys-label"><?= $s['label'] ?></span>
                        <span class="sys-val <?= $s['ok'] ? '' : 'neutral' ?>">
                            <span class="sys-dot"></span>
                            <?= $s['val'] ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ROLE BREAKDOWN -->
            <div class="cl-card">
                <div class="cl-head"><div class="cl-head-title"><i class="bi bi-pie-chart"></i> Komposisi Pengguna</div></div>
                <div style="padding:1.1rem;display:flex;flex-direction:column;gap:.6rem;">
                    <?php foreach ($roleData as $r): ?>
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:.75rem;margin-bottom:4px;">
                            <span style="font-weight:600;color:var(--text-2);"><?= $r['label'] ?></span>
                            <span style="font-weight:700;color:var(--text);"><?= $r['count'] ?> <span style="color:var(--text-3);font-weight:400;">(<?= $r['pct'] ?>%)</span></span>
                        </div>
                        <div style="height:6px;background:var(--bg);border-radius:99px;overflow:hidden;">
                            <div style="height:100%;width:<?= $r['pct'] ?>%;background:<?= $r['color'] ?>;border-radius:99px;transition:width .6s ease;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- BARANG TEMUAN STATUS -->
            <div class="cl-card">
                <div class="cl-head"><div class="cl-head-title"><i class="bi bi-bag-check"></i> Status Barang Temuan</div></div>
                <div class="barang-chips">
                    <?php
                    $bColors = [
                        'tersimpan'   => ['bg'=>'rgba(59,130,246,.1)', 'color'=>'#2563EB'],
                        'dicocokkan'  => ['bg'=>'rgba(245,158,11,.1)', 'color'=>'#D97706'],
                        'diklaim'     => ['bg'=>'rgba(139,92,246,.1)', 'color'=>'#7C3AED'],
                        'diserahkan'  => ['bg'=>'rgba(16,185,129,.1)', 'color'=>'#059669'],
                    ];
                    if (empty($barangStats)): ?>
                        <span style="font-size:.78rem;color:var(--text-3);">Belum ada barang temuan.</span>
                    <?php else: foreach ($barangStats as $status => $total):
                        $bc = $bColors[$status] ?? ['bg'=>'rgba(148,163,184,.1)','color'=>'#64748B'];
                    ?>
                    <span class="bchip" style="background:<?= $bc['bg'] ?>;color:<?= $bc['color'] ?>;">
                        <?= ucfirst($status) ?>: <?= $total ?>
                    </span>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        </div>
    </div>
</main>

<footer style="padding:1.1rem 2rem;border-top:1px solid var(--border);font-size:.73rem;color:var(--text-3);text-align:center;">&copy; <?= date('Y') ?> CommuterLink Nusantara.</footer>

<!-- ── CONFIRM MODAL (Aktifkan / Nonaktifkan) ── -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <div class="modal-icon" id="confirmIcon">⚠️</div>
        <div class="modal-title" id="confirmTitle">Konfirmasi Aksi</div>
        <div class="modal-sub" id="confirmSub">Apakah Anda yakin?</div>
        <div class="modal-actions">
            <button class="modal-cancel" onclick="closeConfirm()">Batal</button>
            <form method="POST" style="flex:2;display:flex;">
                <input type="hidden" name="action" id="confirmAction">
                <input type="hidden" name="uid"    id="confirmUid">
                <button type="submit" class="modal-confirm" id="confirmBtn" style="width:100%;">Ya, Lanjutkan</button>
            </form>
        </div>
    </div>
</div>

<!-- ── ADD USER MODAL ── -->
<div class="add-user-overlay" id="addUserModal">
    <div class="add-user-box">
        <h3><i class="bi bi-person-plus-fill"></i> Tambah Pengguna Baru</h3>
        <form method="POST" action="admin_add_user.php">
            <div class="form-row">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" placeholder="Nama lengkap" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="email@example.com" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Min. 8 karakter" required>
                </div>
                <div class="form-group">
                    <label>No. Telepon</label>
                    <input type="text" name="no_telepon" placeholder="08xx">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="pelapor">Pelapor</option>
                        <option value="petugas">Petugas</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Stasiun (opsional)</label>
                    <input type="text" name="stasiun" placeholder="Nama stasiun">
                </div>
            </div>
            <div style="display:flex;gap:.6rem;margin-top:.5rem;">
                <button type="button" class="modal-cancel" onclick="closeAddUser()" style="flex:1;padding:.68rem;border-radius:10px;border:1.5px solid var(--border);background:none;color:var(--text-2);font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;">Batal</button>
                <button type="submit" class="btn-submit" style="flex:2;margin-top:0;">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

<!-- ── SETTINGS PANEL ── -->
<div class="sp-overlay" id="spOverlay" onclick="closeSettings()"></div>
<aside class="settings-panel" id="settingsPanel">
    <div class="sp-header">
        <div class="sp-title"><i class="bi bi-sliders"></i> Pengaturan Tampilan</div>
        <button class="sp-close" onclick="closeSettings()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sp-body">
        <div class="sp-preview">
            <div class="sp-preview-thumb" id="spPreviewThumb">🛡️</div>
            <div class="sp-preview-text">
                <div class="sp-preview-title" id="spPreviewTitle">Dark · Amber</div>
                <div class="sp-preview-sub" id="spPreviewSub">Tampilan saat ini</div>
            </div>
        </div>
        <div class="sp-section">
            <div class="sp-section-label">Mode Tema</div>
            <div class="theme-grid">
                <div class="theme-card" data-theme="dark"   onclick="setTheme('dark')"  ><span class="theme-card-icon">🌙</span><div class="theme-card-name">Gelap</div>  <div class="theme-check"><i class="bi bi-check"></i></div></div>
                <div class="theme-card" data-theme="light"  onclick="setTheme('light')" ><span class="theme-card-icon">☀️</span><div class="theme-card-name">Terang</div> <div class="theme-check"><i class="bi bi-check"></i></div></div>
                <div class="theme-card" data-theme="system" onclick="setTheme('system')"><span class="theme-card-icon">💻</span><div class="theme-card-name">Sistem</div> <div class="theme-check"><i class="bi bi-check"></i></div></div>
            </div>
        </div>
        <div class="sp-section">
            <div class="sp-section-label">Warna Aksen</div>
            <div class="accent-grid">
                <div><div class="accent-dot" data-accent="amber"  style="background:#F59E0B;" onclick="setAccent('amber')" ></div><div class="accent-label">Amber</div></div>
                <div><div class="accent-dot" data-accent="blue"   style="background:#3B82F6;" onclick="setAccent('blue')"  ></div><div class="accent-label">Biru</div></div>
                <div><div class="accent-dot" data-accent="green"  style="background:#10B981;" onclick="setAccent('green')" ></div><div class="accent-label">Hijau</div></div>
                <div><div class="accent-dot" data-accent="purple" style="background:#8B5CF6;" onclick="setAccent('purple')"></div><div class="accent-label">Ungu</div></div>
                <div><div class="accent-dot" data-accent="rose"   style="background:#EC4899;" onclick="setAccent('rose')"  ></div><div class="accent-label">Rose</div></div>
            </div>
        </div>
        <div class="sp-section">
            <div class="sp-section-label">Ukuran Teks</div>
            <div class="fontsize-row">
                <button class="fs-btn" data-size="sm" onclick="setFontSize('sm')"><span style="font-size:.8rem;">Aa</span><span class="fs-sample" style="font-size:.65rem;">Kecil</span></button>
                <button class="fs-btn" data-size="md" onclick="setFontSize('md')"><span style="font-size:1rem;">Aa</span><span class="fs-sample" style="font-size:.7rem;">Sedang</span></button>
                <button class="fs-btn" data-size="lg" onclick="setFontSize('lg')"><span style="font-size:1.2rem;">Aa</span><span class="fs-sample" style="font-size:.75rem;">Besar</span></button>
            </div>
        </div>
        <div class="sp-section">
            <div class="sp-section-label">Preferensi Lainnya</div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-layout-sidebar-inset-reverse"></i></div><div><div class="sp-toggle-label">Mode Kompak</div><div class="sp-toggle-sub">Kurangi jarak & padding</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleCompact" onchange="setToggle('compact',this.checked)"><span class="sp-slider"></span></label></div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-magic"></i></div><div><div class="sp-toggle-label">Animasi</div><div class="sp-toggle-sub">Aktifkan transisi & efek gerak</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleAnim" onchange="setToggle('anim',this.checked)"><span class="sp-slider"></span></label></div>
            <div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-bell"></i></div><div><div class="sp-toggle-label">Notifikasi</div><div class="sp-toggle-sub">Tampilkan badge notifikasi</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleNotif" onchange="setToggle('notif',this.checked)"><span class="sp-slider"></span></label></div>
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
/* ── CONFIRM MODAL ── */
function openConfirm(uid, action, nama) {
    const modal    = document.getElementById('confirmModal');
    const icon     = document.getElementById('confirmIcon');
    const title    = document.getElementById('confirmTitle');
    const sub      = document.getElementById('confirmSub');
    const btn      = document.getElementById('confirmBtn');
    const actInput = document.getElementById('confirmAction');
    const uidInput = document.getElementById('confirmUid');

    actInput.value = action;
    uidInput.value = uid;

    if (action === 'nonaktifkan') {
        icon.className  = 'modal-icon nonaktif';
        icon.textContent = '🚫';
        title.textContent = 'Nonaktifkan Akun?';
        sub.textContent  = `Akun "${nama}" akan dinonaktifkan dan tidak bisa login hingga diaktifkan kembali.`;
        btn.className   = 'modal-confirm nonaktif';
        btn.textContent = 'Ya, Nonaktifkan';
    } else {
        icon.className  = 'modal-icon aktif';
        icon.textContent = '✅';
        title.textContent = 'Aktifkan Kembali?';
        sub.textContent  = `Akun "${nama}" akan diaktifkan dan dapat login ke sistem.`;
        btn.className   = 'modal-confirm aktif';
        btn.textContent = 'Ya, Aktifkan';
    }

    modal.classList.add('open');
}
function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
}

/* ── ADD USER MODAL ── */
function openAddUser()  { document.getElementById('addUserModal').classList.add('open'); }
function closeAddUser() { document.getElementById('addUserModal').classList.remove('open'); }

/* ── BACKUP ── */
function confirmBackup() {
    if (confirm('Jalankan backup database sekarang?')) alert('Backup berhasil dijadwalkan.');
}

/* ── SETTINGS ── */
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
    document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key===','){e.preventDefault();openSettings();}if(e.key==='Escape'){closeSettings();closeConfirm();closeAddUser();}});
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>{if(S.theme==='system')apply();});
    load();apply();
})();
</script>
</body>
</html>