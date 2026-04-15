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

$firstName = explode(' ', $user['nama'] ?? 'User')[0];

$hour = (int)date('H');
if ($hour < 11)      $greeting = 'Selamat pagi';
elseif ($hour < 15)  $greeting = 'Selamat siang';
elseif ($hour < 18)  $greeting = 'Selamat sore';
else                 $greeting = 'Selamat malam';

$recentActivities = [];
$petugasStats     = ['total_barang' => 0, 'diklaim' => 0, 'diproses' => 0, 'hari_ini' => 0];

try {
    $pdo = getDB();

    $row = $pdo->query("SELECT COUNT(*) FROM barang_temuan WHERE deleted_at IS NULL")->fetchColumn();
    $petugasStats['total_barang'] = (int)$row;

    $row = $pdo->query("SELECT COUNT(*) FROM barang_temuan WHERE status IN ('diklaim','diserahkan') AND deleted_at IS NULL")->fetchColumn();
    $petugasStats['diklaim'] = (int)$row;

    $row = $pdo->query("SELECT COUNT(*) FROM laporan_kehilangan WHERE status = 'diproses' AND deleted_at IS NULL")->fetchColumn();
    $petugasStats['diproses'] = (int)$row;

    $row = $pdo->query("SELECT COUNT(*) FROM laporan_kehilangan WHERE DATE(created_at) = CURDATE() AND deleted_at IS NULL")->fetchColumn();
    $petugasStats['hari_ini'] = (int)$row;

    $stmt = $pdo->query("
        SELECT
            'laporan'           AS tipe,
            lk.no_laporan       AS kode,
            lk.nama_barang,
            lk.lokasi_hilang    AS lokasi,
            lk.created_at       AS tanggal,
            lk.status,
            u.nama              AS aktor_nama
        FROM laporan_kehilangan lk
        JOIN users u ON u.id = lk.user_id
        WHERE lk.deleted_at IS NULL

        UNION ALL

        SELECT
            'barang'                AS tipe,
            bt.kode_barang          AS kode,
            bt.nama_barang,
            bt.lokasi_ditemukan     AS lokasi,
            bt.created_at           AS tanggal,
            bt.status,
            u.nama                  AS aktor_nama
        FROM barang_temuan bt
        JOIN users u ON u.id = bt.petugas_id
        WHERE bt.deleted_at IS NULL

        ORDER BY tanggal DESC
        LIMIT 10
    ");
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Petugas — CommuterLink Nusantara</title>
    <link rel="icon" href="uploads/favicon.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy: #0B1F3A; --navy-2: #152d52; --navy-3: #1e3d6e;
            --gold: #F0A500; --gold-lt: #F7C948; --white: #FFFFFF;
            --bg: #F0F4F8; --card-bg: #FFFFFF; --text: #1E293B;
            --text-2: #475569; --text-3: #94A3B8; --border: #E2E8F0;
            --success: #10B981; --warning: #F59E0B; --danger: #EF4444; --info: #3B82F6;
            --sidebar-w: 260px;
        }

        /* ── DARK MODE ── */
        [data-theme="dark"] {
            --bg: #0B1626; --card-bg: #112038; --text: #E8F0FE;
            --text-2: #94AEC8; --text-3: #506882; --border: rgba(255,255,255,0.07);
        }
        [data-theme="dark"] .topbar { background: rgba(11,22,38,0.92); border-color: rgba(255,255,255,0.07); }
        [data-theme="dark"] .topbar-btn { background: var(--card-bg); border-color: rgba(255,255,255,0.07); color: var(--text-2); }
        [data-theme="dark"] .topbar-btn:hover { border-color: var(--gold); color: var(--gold); }
        [data-theme="dark"] .stat-card { background: var(--card-bg); border-color: rgba(255,255,255,0.07); }
        [data-theme="dark"] .cl-card { background: var(--card-bg); border-color: rgba(255,255,255,0.07); }
        [data-theme="dark"] .cl-card-header { border-color: rgba(255,255,255,0.07); }
        [data-theme="dark"] .activity-table th, [data-theme="dark"] .activity-table td { border-color: rgba(255,255,255,0.07); }
        [data-theme="dark"] .activity-table tr:hover td { background: rgba(255,255,255,0.03); }
        [data-theme="dark"] .item-code { background: rgba(255,255,255,0.05); color: var(--text-2); }
        [data-theme="dark"] .qa-btn { background: var(--card-bg); border-color: rgba(255,255,255,0.07); }
        [data-theme="dark"] .qa-btn:hover { border-color: var(--gold); }
        [data-theme="dark"] .station-item { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.07); }
        [data-theme="dark"] .ann-item { border-color: rgba(255,255,255,0.07); }

        /* ── ACCENT ── */
        [data-accent="blue"]   { --gold: #3B82F6; --gold-lt: #60A5FA; }
        [data-accent="green"]  { --gold: #10B981; --gold-lt: #34D399; }
        [data-accent="purple"] { --gold: #8B5CF6; --gold-lt: #A78BFA; }
        [data-accent="red"]    { --gold: #EF4444; --gold-lt: #FC8181; }
        [data-accent="rose"]   { --gold: #EC4899; --gold-lt: #F472B6; }

        /* ── FONT SIZES ── */
        [data-fontsize="sm"] { font-size: 14px; }
        [data-fontsize="md"] { font-size: 16px; }
        [data-fontsize="lg"] { font-size: 18px; }

        /* ── COMPACT ── */
        [data-compact="true"] .page-content { padding: 1.25rem; }
        [data-compact="true"] .greeting-banner { padding: 1.25rem 1.5rem; }
        [data-compact="true"] .stat-card { padding: 0.9rem 1rem; }
        [data-compact="true"] .cl-card-body { padding: 0.85rem 1rem; }

        /* ── ANIM OFF ── */
        [data-anim="off"] * { animation: none !important; transition: none !important; }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; transition: background 0.3s, color 0.3s; }

        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: var(--sidebar-w); background: var(--navy); display: flex; flex-direction: column; z-index: 100; transition: transform 0.3s ease; }
        .sidebar-brand { padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.07); }
        .brand-pill { display: inline-flex; align-items: center; gap: 10px; }
        .brand-icon-box { width: 38px; height: 38px; background: var(--gold); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(240,165,0,0.35); flex-shrink: 0; }
        .brand-text-main { font-size: 1rem; font-weight: 800; color: var(--white); line-height: 1; }
        .brand-text-main em { font-style: normal; color: var(--gold); }
        .brand-text-sub { font-size: 0.65rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.1em; margin-top: 2px; }
        .sidebar-user { padding: 1rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.07); }
        .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--gold), var(--gold-lt)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; color: var(--navy); flex-shrink: 0; }
        .user-name { font-size: 0.82rem; font-weight: 700; color: var(--white); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 0.68rem; color: rgba(255,255,255,0.4); text-transform: capitalize; }
        .sidebar-nav { flex: 1; padding: 1rem 0.75rem; overflow-y: auto; }
        .nav-section-label { font-size: 0.62rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.25); padding: 0.75rem 0.75rem 0.35rem; }
        .nav-item { margin-bottom: 2px; }
        .nav-link { display: flex; align-items: center; gap: 10px; padding: 0.6rem 0.85rem; border-radius: 9px; color: rgba(255,255,255,0.55); font-size: 0.83rem; font-weight: 600; text-decoration: none; transition: background 0.15s, color 0.15s; }
        .nav-link i { font-size: 1rem; flex-shrink: 0; }
        .nav-link:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.9); }
        .nav-link.active { background: rgba(240,165,0,0.15); color: var(--gold); }
        .nav-link.active i { color: var(--gold); }
        .nav-badge { margin-left: auto; background: var(--gold); color: var(--navy); font-size: 0.6rem; font-weight: 800; padding: 1px 6px; border-radius: 99px; }
        .sidebar-footer { padding: 1rem 0.75rem; border-top: 1px solid rgba(255,255,255,0.07); }
        .logout-btn { display: flex; align-items: center; gap: 10px; padding: 0.6rem 0.85rem; border-radius: 9px; color: rgba(255,255,255,0.45); font-size: 0.83rem; font-weight: 600; text-decoration: none; transition: background 0.15s, color 0.15s; width: 100%; border: none; background: none; cursor: pointer; }
        .logout-btn:hover { background: rgba(239,68,68,0.12); color: #FC8181; }

        .main-wrap { margin-left: var(--sidebar-w); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { position: sticky; top: 0; background: rgba(240,244,248,0.92); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); padding: 0.75rem 2rem; display: flex; align-items: center; justify-content: space-between; z-index: 50; transition: background 0.3s; }
        .topbar-left { display: flex; align-items: center; gap: 10px; }
        .sidebar-toggle { display: none; background: none; border: none; font-size: 1.3rem; color: var(--text); cursor: pointer; padding: 4px; }
        .topbar-title { font-size: 0.92rem; font-weight: 700; color: var(--text); }
        .topbar-right { display: flex; align-items: center; gap: 0.75rem; }
        .topbar-btn { width: 36px; height: 36px; background: var(--white); border: 1px solid var(--border); border-radius: 9px; display: flex; align-items: center; justify-content: center; color: var(--text-2); font-size: 1rem; cursor: pointer; text-decoration: none; transition: border-color 0.2s, color 0.2s; position: relative; }
        .topbar-btn:hover { border-color: var(--navy); color: var(--navy); }
        .notif-dot { position: absolute; top: 6px; right: 6px; width: 7px; height: 7px; background: var(--danger); border-radius: 50%; border: 1.5px solid var(--bg); }

        .page-content { padding: 2rem; flex: 1; transition: padding 0.3s; }
        .greeting-banner { background: linear-gradient(135deg, var(--navy) 0%, var(--navy-3) 100%); border-radius: 18px; padding: 2rem 2.5rem; position: relative; overflow: hidden; margin-bottom: 1.75rem; transition: padding 0.3s; }
        .greeting-banner::before { content: ''; position: absolute; width: 280px; height: 280px; background: radial-gradient(circle, rgba(240,165,0,0.18) 0%, transparent 70%); top: -80px; right: -60px; border-radius: 50%; }
        .greeting-banner::after { content: '🚆'; position: absolute; right: 2rem; bottom: -10px; font-size: 7rem; opacity: 0.07; pointer-events: none; }
        .greeting-time { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--gold); margin-bottom: 0.4rem; }
        .greeting-title { font-size: 1.6rem; font-weight: 800; color: var(--white); line-height: 1.2; letter-spacing: -0.5px; }
        .greeting-title span { color: var(--gold-lt); }
        .greeting-sub { font-size: 0.82rem; color: rgba(255,255,255,0.5); margin-top: 0.4rem; }
        .banner-actions { margin-top: 1.4rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .btn-gold { display: inline-flex; align-items: center; gap: 6px; padding: 0.6rem 1.2rem; background: var(--gold); color: var(--navy); border: none; border-radius: 9px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.82rem; font-weight: 700; text-decoration: none; cursor: pointer; transition: transform 0.15s, box-shadow 0.15s; }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(240,165,0,0.35); color: var(--navy); }
        .btn-ghost-white { display: inline-flex; align-items: center; gap: 6px; padding: 0.6rem 1.2rem; background: rgba(255,255,255,0.1); color: var(--white); border: 1px solid rgba(255,255,255,0.2); border-radius: 9px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.82rem; font-weight: 600; text-decoration: none; transition: background 0.15s; }
        .btn-ghost-white:hover { background: rgba(255,255,255,0.18); color: var(--white); }

        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
        .stat-card { background: var(--card-bg); border-radius: 14px; padding: 1.25rem 1.4rem; border: 1px solid var(--border); position: relative; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s, background 0.3s, border-color 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.07); }
        .stat-card-icon { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 1rem; }
        .icon-blue  { background: rgba(59,130,246,0.12);  color: var(--info); }
        .icon-green { background: rgba(16,185,129,0.12);  color: var(--success); }
        .icon-gold  { background: rgba(240,165,0,0.12);   color: var(--gold); }
        .icon-red   { background: rgba(239,68,68,0.12);   color: var(--danger); }
        .stat-num   { font-size: 1.75rem; font-weight: 800; color: var(--text); line-height: 1; }
        .stat-label { font-size: 0.75rem; color: var(--text-3); margin-top: 4px; font-weight: 600; }

        .content-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.25rem; }
        .cl-card { background: var(--card-bg); border-radius: 14px; border: 1px solid var(--border); overflow: hidden; transition: background 0.3s, border-color 0.3s; }
        .cl-card-header { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--border); }
        .cl-card-title { font-size: 0.88rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .cl-card-title i { color: var(--navy); }
        .cl-card-link { font-size: 0.75rem; font-weight: 700; color: var(--navy); text-decoration: none; display: flex; align-items: center; gap: 4px; }
        .cl-card-link:hover { color: var(--gold); }
        .cl-card-body { padding: 1.2rem 1.4rem; transition: padding 0.3s; }

        .quick-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
        .qa-btn { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; padding: 1.1rem 1.2rem; border-radius: 12px; border: 1.5px solid var(--border); background: var(--white); text-decoration: none; transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s; cursor: pointer; }
        .qa-btn:hover { border-color: var(--navy); transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.07); }
        .qa-icon  { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; }
        .qa-title { font-size: 0.82rem; font-weight: 700; color: var(--text); }
        .qa-desc  { font-size: 0.71rem; color: var(--text-3); margin-top: 2px; }

        .activity-table { width: 100%; border-collapse: collapse; }
        .activity-table th { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--text-3); padding: 0.5rem 0.6rem; text-align: left; border-bottom: 1px solid var(--border); }
        .activity-table td { padding: 0.75rem 0.6rem; font-size: 0.8rem; color: var(--text-2); border-bottom: 1px solid var(--border); vertical-align: middle; }
        .activity-table tr:last-child td { border-bottom: none; }
        .activity-table tr:hover td { background: var(--bg); }
        .item-code { font-family: monospace; font-size: 0.72rem; background: var(--bg); padding: 2px 7px; border-radius: 5px; color: var(--text-2); }
        .item-name { font-weight: 600; color: var(--text); font-size: 0.82rem; }
        .item-loc  { font-size: 0.72rem; color: var(--text-3); margin-top: 1px; }
        .badge-status { display: inline-flex; align-items: center; gap: 4px; font-size: 0.68rem; font-weight: 700; padding: 3px 9px; border-radius: 99px; }
        .badge-pending    { background: rgba(245,158,11,0.1);  color: #D97706; }
        .badge-claimed    { background: rgba(59,130,246,0.1);  color: #2563EB; }
        .badge-selesai    { background: rgba(16,185,129,0.1);  color: #059669; }
        .badge-diserahkan { background: rgba(16,185,129,0.1);  color: #059669; }
        .badge-dicocokkan { background: rgba(59,130,246,0.1);  color: #2563EB; }
        .badge-tersimpan  { background: rgba(245,158,11,0.1);  color: #D97706; }

        .ann-item { display: flex; gap: 12px; padding: 0.9rem 0; border-bottom: 1px solid var(--border); }
        .ann-item:last-child { border-bottom: none; padding-bottom: 0; }
        .ann-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); margin-top: 5px; flex-shrink: 0; }
        .ann-dot.info  { background: var(--info); }
        .ann-dot.alert { background: var(--danger); }
        .ann-title { font-size: 0.8rem; font-weight: 700; color: var(--text); line-height: 1.35; }
        .ann-meta  { font-size: 0.7rem; color: var(--text-3); margin-top: 3px; }

        .station-line { display: flex; flex-direction: column; gap: 8px; }
        .station-item { display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.9rem; background: var(--bg); border-radius: 10px; border: 1px solid var(--border); transition: background 0.3s, border-color 0.3s; }
        .station-name  { font-size: 0.78rem; font-weight: 700; color: var(--text); }
        .station-desc  { font-size: 0.68rem; color: var(--text-3); }
        .station-count { font-size: 0.72rem; font-weight: 800; color: var(--navy); background: rgba(11,31,58,0.07); padding: 2px 8px; border-radius: 99px; }

        .empty-activity { text-align: center; padding: 2.5rem 1rem; color: var(--text-3); }
        .empty-activity i { font-size: 2rem; display: block; margin-bottom: 0.5rem; opacity: 0.4; }

        @media (max-width: 1199.98px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 991.98px)  { .content-grid { grid-template-columns: 1fr; } .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); } .main-wrap { margin-left: 0; } .sidebar-toggle { display: flex; } .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 99; } .sidebar-overlay.show { display: block; } }
        @media (max-width: 575.98px)  { .page-content { padding: 1rem; } .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; } .greeting-banner { padding: 1.4rem 1.5rem; } .greeting-title { font-size: 1.3rem; } .topbar { padding: 0.65rem 1rem; } }
    </style>

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
        .sp-preview-thumb { width: 48px; height: 48px; border-radius: 10px; background: linear-gradient(135deg, #F59E0B, rgba(255,255,255,0.2)); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
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
        <div class="sp-preview">
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
            <div class="accent-grid">
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

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-pill">
            <div class="brand-icon-box">🚆</div>
            <div>
                <div class="brand-text-main">Commuter<em>Link</em></div>
                <div class="brand-text-sub">Lost &amp; Found</div>
            </div>
        </div>
    </div>
    <div class="sidebar-user">
        <a href="profile.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;padding:2px 4px;border-radius:10px;transition:background .15s;" title="Lihat Profil" onmouseover="this.style.background='rgba(255,255,255,0.07)'" onmouseout="this.style.background='transparent'">
            <div class="user-avatar" style="overflow:hidden;position:relative;">
                <?php if (!empty($user['avatar']) && file_exists(__DIR__.'/'.$user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= filemtime(__DIR__.'/'.$user['avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;position:absolute;inset:0;">
                    <span style="opacity:0;"><?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?></span>
                <?php else: ?>
                    <?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div style="overflow:hidden;flex:1;min-width:0;">
                <div class="user-name"><?= htmlspecialchars($user['nama'] ?? 'User') ?></div>
                <div class="user-role" style="display:flex;align-items:center;gap:4px;">
                    <?= htmlspecialchars($user['role'] ?? 'petugas') ?>
                    <i class="bi bi-pencil-square" style="font-size:.55rem;opacity:.4;"></i>
                </div>
            </div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu Utama</div>
        <div class="nav-item"><a href="index_petugas.php" class="nav-link active"><i class="bi bi-grid"></i> Dashboard</a></div>
        <div class="nav-item"><a href="track.php" class="nav-link"><i class="bi bi-geo-alt"></i> Lacak Laporan</a></div>
        <div class="nav-section-label" style="margin-top:0.5rem;">Administrasi</div>
        <div class="nav-item"><a href="admin.php" class="nav-link"><i class="bi bi-shield-check"></i> Panel Admin</a></div>
        <div class="nav-item"><a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Laporan</a></div>
    </nav>
    <div class="sidebar-footer">
        <a href="profile.php" class="nav-link" style="margin-bottom:2px;"><i class="bi bi-person-circle"></i> Profil Saya</a>
        <a href="logout.php" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
</aside>

<div class="main-wrap">
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <span class="topbar-title">Dashboard</span>
        </div>
        <div class="topbar-right">
            <a href="notifikasi_petugas.php" class="topbar-btn" title="Notifikasi" id="notifBtn"><i class="bi bi-bell"></i><span class="notif-dot" id="notifPip"></span></a>
            <button onclick="openSettings()" class="topbar-btn" title="Pengaturan Tampilan" style="border:none;cursor:pointer;"><i class="bi bi-sliders"></i></button>
            <a href="profile.php" class="topbar-btn" title="Lihat & Edit Profil — <?= htmlspecialchars($user['nama'] ?? '') ?>" style="overflow:hidden;padding:0;">
                <?php if (!empty($user['avatar']) && file_exists(__DIR__.'/'.$user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= filemtime(__DIR__.'/'.$user['avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
                <?php else: ?>
                    <span style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--gold),var(--gold-lt));color:var(--navy);font-weight:800;font-size:.8rem;border-radius:8px;"><?= strtoupper(substr($user['nama']??'U',0,1)) ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <main class="page-content">
        <div class="greeting-banner">
            <div class="greeting-time"><?= date('l, d F Y') ?></div>
            <div class="greeting-title"><?= $greeting ?>, <span><?= htmlspecialchars($firstName) ?></span>! 👋</div>
            <div class="greeting-sub">Apa yang ingin kamu lakukan hari ini di CommuterLink?</div>
            <div class="banner-actions">
                <a href="laporan_kehilangan.php" class="btn-gold"><i class="bi bi-search-heart"></i> Lapor Kehilangan</a>
                <a href="barang_temuan.php" class="btn-ghost-white"><i class="bi bi-box-seam"></i> Cek Barang Temuan</a>
            </div>
        </div>

        <div class="stat-grid" id="statsRow">
            <div class="stat-card">
                <div class="stat-card-icon icon-blue"><i class="bi bi-box-seam"></i></div>
                <div class="stat-num"><?= $petugasStats['total_barang'] ?></div>
                <div class="stat-label">Total Barang Terdaftar</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon icon-green"><i class="bi bi-check-circle"></i></div>
                <div class="stat-num"><?= $petugasStats['diklaim'] ?></div>
                <div class="stat-label">Berhasil Diklaim</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon icon-gold"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-num"><?= $petugasStats['diproses'] ?></div>
                <div class="stat-label">Sedang Diproses</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon icon-red"><i class="bi bi-exclamation-circle"></i></div>
                <div class="stat-num"><?= $petugasStats['hari_ini'] ?></div>
                <div class="stat-label">Laporan Hari Ini</div>
            </div>
        </div>

        <div class="content-grid">
            <div style="display:flex;flex-direction:column;gap:1.25rem;">

                <!-- Aksi Cepat -->
                <div class="cl-card">
                    <div class="cl-card-header"><div class="cl-card-title"><i class="bi bi-lightning-charge-fill"></i> Aksi Cepat</div></div>
                    <div class="cl-card-body">
                        <div class="quick-actions">
                            <a href="laporan_kehilangan.php" class="qa-btn"><div class="qa-icon icon-red"><i class="bi bi-search-heart"></i></div><div><div class="qa-title">Lapor Kehilangan</div><div class="qa-desc">Buat laporan barang hilang</div></div></a>
                            <a href="barang_temuan.php" class="qa-btn"><div class="qa-icon icon-green"><i class="bi bi-box-seam"></i></div><div><div class="qa-title">Lihat Temuan</div><div class="qa-desc">Cek daftar barang temuan</div></div></a>
                            <a href="track.php" class="qa-btn"><div class="qa-icon icon-blue"><i class="bi bi-geo-alt"></i></div><div><div class="qa-title">Lacak Laporan</div><div class="qa-desc">Status laporan saya</div></div></a>
                            <a href="stations.php" class="qa-btn"><div class="qa-icon icon-gold"><i class="bi bi-train-front"></i></div><div><div class="qa-title">Info Stasiun</div><div class="qa-desc">Lokasi & kontak stasiun</div></div></a>
                        </div>
                    </div>
                </div>

                <!-- Aktivitas Terbaru -->
                <div class="cl-card">
                    <div class="cl-card-header">
                        <div class="cl-card-title"><i class="bi bi-clock-history"></i> Aktivitas Terbaru</div>
                        <a href="barang_temuan.php" class="cl-card-link">Lihat semua <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div style="overflow-x:auto;">
                        <?php if (empty($recentActivities)): ?>
                        <div class="empty-activity">
                            <i class="bi bi-inbox"></i>
                            Belum ada aktivitas. Data akan muncul setelah ada laporan masuk.
                        </div>
                        <?php else: ?>
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Barang</th>
                                    <th>Lokasi</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivities as $item):
                                    if ($item['tipe'] === 'laporan') {
                                        [$badgeClass, $badgeLabel] = match($item['status']) {
                                            'diproses'  => ['badge-pending',  'Diproses'],
                                            'ditemukan' => ['badge-claimed',  'Ditemukan'],
                                            'selesai'   => ['badge-selesai',  'Selesai'],
                                            default     => ['badge-pending',  ucfirst($item['status'])],
                                        };
                                        $tipeIcon   = '📋';
                                        $aktorLabel = 'Laporan dari';
                                    } else {
                                        [$badgeClass, $badgeLabel] = match($item['status']) {
                                            'tersimpan'  => ['badge-tersimpan',  'Tersimpan'],
                                            'dicocokkan' => ['badge-dicocokkan', 'Dicocokkan'],
                                            'diklaim'    => ['badge-claimed',    'Diklaim'],
                                            'diserahkan' => ['badge-diserahkan', 'Diserahkan'],
                                            default      => ['badge-pending',    ucfirst($item['status'])],
                                        };
                                        $tipeIcon   = '📦';
                                        $aktorLabel = 'Ditemukan oleh';
                                    }
                                    $namaAktor = htmlspecialchars(explode(' ', $item['aktor_nama'])[0]);
                                    $lokasiStr = htmlspecialchars(mb_strimwidth($item['lokasi'], 0, 18, '…'));
                                    $kodeStr   = htmlspecialchars(mb_strimwidth($item['kode'], 0, 14, '…'));
                                ?>
                                <tr>
                                    <td><span class="item-code"><?= $kodeStr ?></span></td>
                                    <td>
                                        <div class="item-name"><?= htmlspecialchars($item['nama_barang']) ?></div>
                                        <div class="item-loc"><?= $tipeIcon ?> <?= $aktorLabel ?> <?= $namaAktor ?></div>
                                    </td>
                                    <td style="font-size:0.78rem;"><?= $lokasiStr ?></td>
                                    <td style="font-size:0.78rem;white-space:nowrap;"><?= date('d M Y', strtotime($item['tanggal'])) ?></td>
                                    <td>
                                        <span class="badge-status <?= $badgeClass ?>">
                                            <i class="bi bi-circle-fill" style="font-size:6px;"></i>
                                            <?= $badgeLabel ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Sidebar Kanan -->
            <div style="display:flex;flex-direction:column;gap:1.25rem;">
                <div class="cl-card">
                    <div class="cl-card-header"><div class="cl-card-title"><i class="bi bi-megaphone"></i> Pengumuman</div><a href="news.php" class="cl-card-link">Semua <i class="bi bi-arrow-right"></i></a></div>
                    <div class="cl-card-body" style="padding-top:0.5rem;padding-bottom:0.5rem;">
                        <div class="ann-item"><div class="ann-dot alert"></div><div><div class="ann-title">Area L&F Jakarta Kota pindah ke pintu Selatan</div><div class="ann-meta"><i class="bi bi-clock" style="font-size:0.65rem"></i> 2 hari lalu</div></div></div>
                        <div class="ann-item"><div class="ann-dot info"></div><div><div class="ann-title">Tips: Tandai barang bawaan dengan nama & nomor HP</div><div class="ann-meta"><i class="bi bi-clock" style="font-size:0.65rem"></i> 5 hari lalu</div></div></div>
                        <div class="ann-item"><div class="ann-dot"></div><div><div class="ann-title">Jam operasional Lost & Found diperpanjang s/d 22.00</div><div class="ann-meta"><i class="bi bi-clock" style="font-size:0.65rem"></i> 1 minggu lalu</div></div></div>
                        <div class="ann-item"><div class="ann-dot info"></div><div><div class="ann-title">Pelatihan petugas Lost & Found seluruh koridor selesai</div><div class="ann-meta"><i class="bi bi-clock" style="font-size:0.65rem"></i> 2 minggu lalu</div></div></div>
                    </div>
                </div>
                <div class="cl-card">
                    <div class="cl-card-header"><div class="cl-card-title"><i class="bi bi-train-front"></i> Stasiun Aktif</div><a href="stations.php" class="cl-card-link">Semua <i class="bi bi-arrow-right"></i></a></div>
                    <div class="cl-card-body">
                        <div class="station-line">
                            <div class="station-item"><div><div class="station-name">🔴 Bogor Line</div><div class="station-desc">Bogor → Jakarta Kota</div></div><span class="station-count">12 item</span></div>
                            <div class="station-item"><div><div class="station-name">🔵 Bekasi Line</div><div class="station-desc">Bekasi → Angke</div></div><span class="station-count">8 item</span></div>
                            <div class="station-item"><div><div class="station-name">🟡 Tangerang Line</div><div class="station-desc">Duri → Tangerang</div></div><span class="station-count">5 item</span></div>
                            <div class="station-item"><div><div class="station-name">🟢 Cikarang Line</div><div class="station-desc">Cikarang → Jakarta Kota</div></div><span class="station-count">3 item</span></div>
                        </div>
                    </div>
                </div>
                <div class="cl-card">
                    <div class="cl-card-header"><div class="cl-card-title"><i class="bi bi-headset"></i> Butuh Bantuan?</div></div>
                    <div class="cl-card-body" style="text-align:center;">
                        <div style="font-size:2rem;margin-bottom:0.5rem;">🎧</div>
                        <div style="font-size:0.82rem;font-weight:700;color:var(--text);margin-bottom:0.25rem;">Tim Customer Service</div>
                        <div style="font-size:0.75rem;color:var(--text-3);margin-bottom:1rem;">Senin–Jumat, 07.00–21.00 WIB</div>
                        <a href="tel:021-121" class="btn-gold" style="justify-content:center;width:100%;"><i class="bi bi-telephone"></i> 021-121</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer style="padding:1.25rem 2rem;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;font-size:0.73rem;color:var(--text-3);flex-wrap:wrap;gap:0.5rem;">
        <span>&copy; <?= date('Y') ?> CommuterLink Nusantara. All Rights Reserved.</span>
        <div style="display:flex;gap:1.25rem;">
            <a href="#" style="color:var(--text-3);text-decoration:none;">Kebijakan Privasi</a>
            <a href="#" style="color:var(--text-3);text-decoration:none;">Syarat &amp; Ketentuan</a>
            <a href="#" style="color:var(--text-3);text-decoration:none;">Hubungi Kami</a>
        </div>
    </footer>
</div>

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('sidebarOverlay').classList.toggle('show'); }
function closeSidebar()  { document.getElementById('sidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('show'); }
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
</body>
</html>