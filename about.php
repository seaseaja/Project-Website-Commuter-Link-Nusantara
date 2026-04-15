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

$firstName = explode(' ', $user['nama'] ?? 'User')[0];
$role      = $user['role'] ?? 'pelapor';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark" data-accent="amber" data-fontsize="md" data-compact="false" data-anim="on">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami — CommuterLink Nusantara</title>
    
    <link rel="icon" href="uploads/favicon.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy:   #0D1B2E; --navy-2: #152640; --navy-3: #1E3357; --navy-4: #253F6A;
            --blue:   #2563EB; --blue-lt: #3B82F6;
            --amber:  #F59E0B; --amber-lt: #FCD34D; --amber-pale: #FFFBEB;
            --bg:     #0A1628; --bg-2: #0F1F38; --card: #132035; --card-2: #192A45;
            --text:   #F0F6FF; --text-2: #A8BDD6; --text-3: #5A7A9E; --white: #FFFFFF;
            --border: rgba(255,255,255,0.07); --border-2: rgba(255,255,255,0.12);
            --success:#10B981; --danger: #F87171; --card-r: 16px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; transition: background 0.3s, color 0.3s; }

        [data-theme="light"] {
            --bg: #F0F6FF; --bg-2: #E4EEF9; --card: #FFFFFF; --card-2: #F5F9FF;
            --text: #0D1B2E; --text-2: #2A4263; --text-3: #6B89A8;
            --border: rgba(13,27,46,0.08); --border-2: rgba(13,27,46,0.14);
        }
        [data-theme="light"] .top-nav { background: rgba(240,246,255,0.92); }
        [data-theme="light"] .hero-about { background: linear-gradient(135deg,#1E3357 0%,#253F6A 50%,#2B4D80 100%); }

        [data-accent="blue"]   { --amber: #3B82F6; --amber-lt: #60A5FA; }
        [data-accent="green"]  { --amber: #10B981; --amber-lt: #34D399; }
        [data-accent="purple"] { --amber: #8B5CF6; --amber-lt: #A78BFA; }
        [data-accent="red"]    { --amber: #EF4444; --amber-lt: #FC8181; }
        [data-accent="rose"]   { --amber: #EC4899; --amber-lt: #F472B6; }

        [data-fontsize="sm"] { font-size: 14px; }
        [data-fontsize="md"] { font-size: 16px; }
        [data-fontsize="lg"] { font-size: 18px; }
        [data-compact="true"] .page-wrap { padding: 1rem 1.25rem 6rem; }
        [data-anim="off"] * { animation: none !important; transition: none !important; }

        /* TOP NAV */
        .top-nav {
            position: sticky; top: 0; z-index: 200;
            background: rgba(10,22,40,0.92); backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border); padding: 0 2rem; height: 62px;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .brand-gem { width: 34px; height: 34px; background: linear-gradient(135deg, var(--amber), var(--amber-lt)); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; box-shadow: 0 4px 14px rgba(245,158,11,0.4); }
        .brand-name { font-family: 'Fraunces', serif; font-size: 1.05rem; font-weight: 700; color: var(--white); line-height: 1; }
        .brand-name em { font-style: italic; color: var(--amber); }
        .brand-sub { font-size: 0.6rem; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.1em; }
        .nav-actions { display: flex; align-items: center; gap: 0.5rem; }
        .nav-icon-btn { width: 36px; height: 36px; border: 1px solid var(--border); background: var(--card); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--text-2); font-size: 0.95rem; text-decoration: none; transition: all 0.2s; cursor: pointer; }
        .nav-icon-btn:hover { border-color: var(--amber); color: var(--amber); background: rgba(245,158,11,0.1); }
        .user-chip { display: flex; align-items: center; gap: 8px; padding: 4px 12px 4px 4px; border: 1px solid var(--border); border-radius: 99px; background: var(--card); text-decoration: none; transition: all 0.2s; }
        .user-chip:hover { border-color: var(--amber); background: rgba(245,158,11,0.08); }
        .chip-avatar { width: 28px; height: 28px; background: linear-gradient(135deg, var(--amber), var(--amber-lt)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 700; color: var(--navy); }
        .chip-name { font-size: 0.78rem; font-weight: 600; color: var(--text); }

        /* PAGE */
        .page-wrap { max-width: 900px; margin: 0 auto; padding: 2.5rem 1.5rem 7rem; }

        /* HERO */
        .hero-about {
            position: relative; background: linear-gradient(135deg, var(--navy-2) 0%, var(--navy-3) 50%, var(--navy-4) 100%);
            border-radius: 24px; padding: 3rem 3rem 3.5rem; margin-bottom: 2.5rem;
            overflow: hidden; border: 1px solid var(--border-2); text-align: center;
        }
        .hero-about::before { content: ''; position: absolute; inset: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
        .hero-glow-1 { position: absolute; right: -80px; top: -80px; width: 320px; height: 320px; background: radial-gradient(circle, rgba(245,158,11,0.18) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
        .hero-glow-2 { position: absolute; left: -60px; bottom: -60px; width: 240px; height: 240px; background: radial-gradient(circle, rgba(37,99,235,0.15) 0%, transparent 70%); border-radius: 50%; pointer-events: none; }
        .hero-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); color: var(--amber-lt); font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; padding: 5px 12px; border-radius: 99px; margin-bottom: 1.25rem; position: relative; z-index: 1; }
        .hero-title { font-family: 'Fraunces', serif; font-size: 2.8rem; font-weight: 900; color: var(--white); letter-spacing: -1.5px; line-height: 1.1; margin-bottom: 1rem; position: relative; z-index: 1; }
        .hero-title em { font-style: italic; color: var(--amber); }
        .hero-desc { font-size: 0.92rem; color: var(--text-2); line-height: 1.75; max-width: 560px; margin: 0 auto; position: relative; z-index: 1; }

        /* LOGO BIG */
        .logo-big { width: 80px; height: 80px; background: linear-gradient(135deg, var(--amber), var(--amber-lt)); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2.4rem; margin: 0 auto 1.5rem; box-shadow: 0 12px 36px rgba(245,158,11,0.35); position: relative; z-index: 1; }

        /* STATS BAND */
        .stats-band { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2.5rem; }
        .stat-box { background: var(--card); border: 1px solid var(--border); border-radius: var(--card-r); padding: 1.4rem 1rem; text-align: center; transition: transform 0.2s, border-color 0.2s; }
        .stat-box:hover { transform: translateY(-3px); border-color: rgba(245,158,11,0.3); }
        .stat-box-num { font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 900; color: var(--amber); line-height: 1; }
        .stat-box-label { font-size: 0.73rem; color: var(--text-3); margin-top: 5px; font-weight: 500; }

        /* SECTION */
        .section-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--amber); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 8px; }
        .section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }
        .section-title { font-family: 'Fraunces', serif; font-size: 1.5rem; font-weight: 900; color: var(--text); letter-spacing: -0.5px; margin-bottom: 0.75rem; }
        .section-body  { font-size: 0.88rem; color: var(--text-2); line-height: 1.8; }

        /* CARDS */
        .cl-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--card-r); overflow: hidden; }
        .cl-card-body { padding: 1.75rem; }

        /* MISI GRID */
        .misi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .misi-item { background: var(--card-2); border: 1px solid var(--border); border-radius: 14px; padding: 1.5rem 1.25rem; text-align: center; transition: transform 0.2s, border-color 0.2s; }
        .misi-item:hover { transform: translateY(-3px); border-color: rgba(245,158,11,0.25); }
        .misi-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin: 0 auto 1rem; }
        .mi-amber  { background: rgba(245,158,11,0.12); }
        .mi-blue   { background: rgba(37,99,235,0.12); }
        .mi-teal   { background: rgba(16,185,129,0.12); }
        .mi-purple { background: rgba(139,92,246,0.12); }
        .mi-rose   { background: rgba(236,72,153,0.12); }
        .mi-sky    { background: rgba(14,165,233,0.12); }
        .misi-title { font-size: 0.9rem; font-weight: 700; color: var(--text); margin-bottom: 0.4rem; }
        .misi-desc  { font-size: 0.77rem; color: var(--text-3); line-height: 1.6; }

        /* TIMELINE */
        .timeline { display: flex; flex-direction: column; gap: 0; position: relative; }
        .timeline::before { content: ''; position: absolute; left: 20px; top: 0; bottom: 0; width: 2px; background: var(--border); }
        .tl-item { display: flex; gap: 1.25rem; padding-bottom: 1.75rem; position: relative; }
        .tl-item:last-child { padding-bottom: 0; }
        .tl-dot { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; flex-shrink: 0; position: relative; z-index: 1; border: 2px solid var(--bg); }
        .tl-dot-amber  { background: var(--amber); color: var(--navy); }
        .tl-dot-blue   { background: var(--blue-lt); color: var(--white); }
        .tl-dot-teal   { background: #10B981; color: var(--white); }
        .tl-dot-purple { background: #8B5CF6; color: var(--white); }
        .tl-year  { font-size: 0.7rem; font-weight: 700; color: var(--amber); margin-bottom: 3px; letter-spacing: 0.05em; }
        .tl-title { font-size: 0.88rem; font-weight: 700; color: var(--text); }
        .tl-desc  { font-size: 0.78rem; color: var(--text-3); margin-top: 3px; line-height: 1.55; }

        /* TEAM — 2x2 grid */
        .team-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; }
        .team-card { background: var(--card-2); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem 1.25rem; text-align: center; transition: transform 0.2s, border-color 0.2s; }
        .team-card:hover { transform: translateY(-4px); border-color: rgba(245,158,11,0.25); }
        .team-avatar { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 800; margin: 0 auto 0.9rem; color: var(--navy); }
        .team-name { font-size: 0.88rem; font-weight: 700; color: var(--text); }
        .team-role { font-size: 0.72rem; color: var(--amber); font-weight: 600; margin-top: 3px; }
        .team-desc { font-size: 0.73rem; color: var(--text-3); margin-top: 6px; line-height: 1.55; }

        /* VALUES */
        .values-list { display: flex; flex-direction: column; gap: 1rem; }
        .value-item { display: flex; align-items: flex-start; gap: 1rem; padding: 1.1rem 1.25rem; background: var(--card-2); border: 1px solid var(--border); border-radius: 12px; transition: border-color 0.2s; }
        .value-item:hover { border-color: rgba(245,158,11,0.2); }
        .value-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(245,158,11,0.1); color: var(--amber); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .value-title { font-size: 0.85rem; font-weight: 700; color: var(--text); }
        .value-desc  { font-size: 0.77rem; color: var(--text-3); margin-top: 2px; line-height: 1.55; }

        /* CTA BAND */
        .cta-band { background: linear-gradient(135deg, var(--navy-2), var(--navy-3)); border: 1px solid var(--border-2); border-radius: 20px; padding: 2.5rem 2rem; text-align: center; position: relative; overflow: hidden; }
        .cta-band::before { content: ''; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(245,158,11,0.15) 0%, transparent 70%); border-radius: 50%; }
        .cta-band-title { font-family: 'Fraunces', serif; font-size: 1.6rem; font-weight: 900; color: var(--white); letter-spacing: -0.5px; margin-bottom: 0.5rem; position: relative; z-index: 1; }
        .cta-band-sub { font-size: 0.85rem; color: var(--text-2); margin-bottom: 1.5rem; position: relative; z-index: 1; }
        .cta-btn { display: inline-flex; align-items: center; gap: 7px; background: var(--amber); color: var(--navy); padding: 0.75rem 1.75rem; border-radius: 10px; font-size: 0.88rem; font-weight: 700; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 16px rgba(245,158,11,0.3); position: relative; z-index: 1; }
        .cta-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(245,158,11,0.45); color: var(--navy); }

        /* CONTACT ROW */
        .contact-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .contact-item { display: flex; align-items: center; gap: 0.9rem; background: var(--card-2); border: 1px solid var(--border); border-radius: 12px; padding: 1rem 1.1rem; transition: border-color 0.2s; text-decoration: none; }
        .contact-item:hover { border-color: rgba(245,158,11,0.3); }
        .ci-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(245,158,11,0.1); color: var(--amber); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .ci-label { font-size: 0.72rem; color: var(--text-3); }
        .ci-value { font-size: 0.82rem; font-weight: 700; color: var(--text); }

        /* BOTTOM NAV */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 300;
            background: var(--card); border-top: 1px solid var(--border);
            backdrop-filter: blur(20px);
            display: flex; align-items: stretch;
            padding-bottom: env(safe-area-inset-bottom);
            box-shadow: 0 -8px 32px rgba(0,0,0,0.25);
        }
        [data-theme="light"] .bottom-nav { background: rgba(255,255,255,0.95); box-shadow: 0 -4px 24px rgba(13,27,46,0.08); }
        .bn-item { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 0.6rem 0.25rem 0.55rem; text-decoration: none; color: var(--text-3); font-size: 0.62rem; font-weight: 600; transition: color 0.2s; position: relative; border: none; background: none; cursor: pointer; }
        .bn-item i { font-size: 1.25rem; transition: transform 0.2s; line-height: 1; }
        .bn-item:hover { color: var(--amber); }
        .bn-item:hover i { transform: translateY(-2px); }
        .bn-item.active { color: var(--amber); }
        .bn-item.active::after { content: ''; position: absolute; top: 0; left: 20%; right: 20%; height: 2.5px; background: var(--amber); border-radius: 0 0 3px 3px; }

        /* ANIM */
        .fade-up { opacity: 0; transform: translateY(18px); animation: fadeUp 0.45s ease forwards; }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
        .delay-1 { animation-delay: 0.05s; } .delay-2 { animation-delay: 0.1s; }
        .delay-3 { animation-delay: 0.15s; } .delay-4 { animation-delay: 0.2s; }
        .delay-5 { animation-delay: 0.25s; } .delay-6 { animation-delay: 0.3s; }

        @media (max-width: 767px) {
            .stats-band { grid-template-columns: repeat(2, 1fr); }
            .misi-grid  { grid-template-columns: 1fr 1fr; }
            .contact-row { grid-template-columns: 1fr; }
            .hero-title { font-size: 2rem; }
            .hero-about { padding: 2rem 1.5rem 2.5rem; }
            .page-wrap { padding: 1.5rem 1rem 7rem; }
            .top-nav { padding: 0 1rem; }
        }
        @media (max-width: 480px) {
            .misi-grid { grid-template-columns: 1fr; }
            .team-grid { grid-template-columns: 1fr; }
            .hero-title { font-size: 1.7rem; }
        }

        /* Settings panel */
        .sp-overlay { position: fixed; inset: 0; z-index: 8888; background: rgba(0,0,0,0); pointer-events: none; transition: background 0.35s; }
        .sp-overlay.open { background: rgba(0,0,0,0.55); pointer-events: all; backdrop-filter: blur(4px); }
        .settings-panel { position: fixed; top: 0; right: 0; bottom: 0; z-index: 8889; width: 360px; max-width: 92vw; background: #0E1E35; border-left: 1px solid rgba(255,255,255,0.09); display: flex; flex-direction: column; transform: translateX(100%); transition: transform 0.38s cubic-bezier(0.22, 1, 0.36, 1); box-shadow: -24px 0 80px rgba(0,0,0,0.55); }
        .settings-panel.open { transform: translateX(0); }
        .sp-header { padding: 1.3rem 1.5rem 1.1rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .sp-title { font-size: 1rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 9px; }
        .sp-title i { color: #F59E0B; }
        .sp-close { width: 32px; height: 32px; background: rgba(255,255,255,0.07); border: none; border-radius: 8px; color: rgba(255,255,255,0.5); font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .sp-close:hover { background: rgba(255,255,255,0.13); color: #fff; }
        .sp-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; }
        .sp-section { margin-bottom: 1.5rem; }
        .sp-section-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.35); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 8px; }
        .sp-section-label::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.07); }
        .theme-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.6rem; }
        .theme-card { position: relative; padding: 0.8rem 0.6rem 0.65rem; border-radius: 12px; border: 2px solid rgba(255,255,255,0.08); cursor: pointer; background: rgba(255,255,255,0.04); text-align: center; transition: all 0.2s; }
        .theme-card.active { border-color: #F59E0B; background: rgba(245,158,11,0.1); }
        .theme-card-icon { font-size: 1.6rem; margin-bottom: 5px; display: block; }
        .theme-card-name { font-size: 0.7rem; font-weight: 700; color: rgba(255,255,255,0.7); }
        .theme-card.active .theme-card-name { color: #F59E0B; }
        .theme-check { position: absolute; top: 5px; right: 5px; width: 16px; height: 16px; background: #F59E0B; border-radius: 50%; display: none; align-items: center; justify-content: center; font-size: 0.5rem; color: #000; }
        .theme-card.active .theme-check { display: flex; }
        .accent-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.6rem; }
        .accent-dot { width: 100%; aspect-ratio: 1; border-radius: 50%; cursor: pointer; border: 3px solid transparent; transition: transform 0.18s; }
        .accent-dot:hover { transform: scale(1.15); }
        .accent-dot.active { border-color: #fff; box-shadow: 0 0 0 3px rgba(255,255,255,0.25); }
        .accent-label { text-align: center; font-size: 0.62rem; color: rgba(255,255,255,0.45); margin-top: 5px; font-weight: 600; }
        .fontsize-row { display: flex; gap: 0.5rem; }
        .fs-btn { flex: 1; padding: 0.55rem 0.5rem; border-radius: 10px; border: 2px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.55); cursor: pointer; font-family: inherit; font-weight: 700; transition: all 0.18s; text-align: center; }
        .fs-btn.active { border-color: #F59E0B; background: rgba(245,158,11,0.1); color: #F59E0B; }
        .fs-btn span { display: block; }
        .sp-toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .sp-toggle-row:last-child { border-bottom: none; }
        .sp-toggle-info { display: flex; align-items: center; gap: 10px; }
        .sp-toggle-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(255,255,255,0.07); display: flex; align-items: center; justify-content: center; font-size: 0.9rem; color: rgba(255,255,255,0.55); flex-shrink: 0; }
        .sp-toggle-label { font-size: 0.82rem; font-weight: 700; color: rgba(255,255,255,0.85); }
        .sp-toggle-sub { font-size: 0.68rem; color: rgba(255,255,255,0.35); margin-top: 1px; }
        .sp-switch { position: relative; width: 40px; height: 22px; flex-shrink: 0; }
        .sp-switch input { opacity: 0; width: 0; height: 0; }
        .sp-slider { position: absolute; inset: 0; cursor: pointer; background: rgba(255,255,255,0.12); border-radius: 22px; transition: background 0.25s; }
        .sp-slider::before { content: ''; position: absolute; height: 16px; width: 16px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: transform 0.25s; }
        input:checked + .sp-slider { background: #F59E0B; }
        input:checked + .sp-slider::before { transform: translateX(18px); }
        .sp-preview { margin: 0 0 1rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; padding: 1rem; display: flex; align-items: center; gap: 12px; }
        .sp-preview-thumb { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .sp-preview-text .sp-preview-title { font-size: 0.82rem; font-weight: 700; color: rgba(255,255,255,0.8); }
        .sp-preview-text .sp-preview-sub { font-size: 0.7rem; color: rgba(255,255,255,0.4); margin-top: 2px; }
        .sp-footer { padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); display: flex; gap: 0.6rem; flex-shrink: 0; }
        .sp-btn-reset { flex: 1; padding: 0.65rem; border-radius: 10px; border: 1.5px solid rgba(255,255,255,0.1); background: none; color: rgba(255,255,255,0.5); font-family: inherit; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .sp-btn-reset:hover { border-color: rgba(255,255,255,0.25); color: #fff; }
        .sp-btn-apply { flex: 2; padding: 0.65rem; border-radius: 10px; border: none; background: #F59E0B; color: #0D1B2E; font-family: inherit; font-size: 0.8rem; font-weight: 800; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .sp-btn-apply:hover { background: #FCD34D; }
        .sp-toast { position: fixed; bottom: 5rem; left: 50%; transform: translateX(-50%) translateY(20px); background: #1E3357; border: 1px solid rgba(245,158,11,0.35); color: #FCD34D; padding: 0.65rem 1.2rem; border-radius: 99px; font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; gap: 8px; z-index: 9999; opacity: 0; pointer-events: none; transition: all 0.35s; }
        .sp-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .notif-pip { position: absolute; top: 7px; right: 7px; width: 6px; height: 6px; background: var(--danger); border-radius: 50%; border: 1.5px solid var(--bg); }
    </style>
</head>
<body>

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
        <div class="sp-section"><div class="sp-section-label">Ukuran Teks</div><div class="fontsize-row"><button class="fs-btn" data-size="sm" onclick="setFontSize('sm')"><span style="font-size:0.8rem;">Aa</span></button><button class="fs-btn" data-size="md" onclick="setFontSize('md')"><span style="font-size:1rem;">Aa</span></button><button class="fs-btn" data-size="lg" onclick="setFontSize('lg')"><span style="font-size:1.2rem;">Aa</span></button></div></div>
        <div class="sp-section"><div class="sp-section-label">Preferensi Lainnya</div><div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-magic"></i></div><div><div class="sp-toggle-label">Animasi</div><div class="sp-toggle-sub">Aktifkan transisi & efek gerak</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleAnim" onchange="setToggle('anim', this.checked)"><span class="sp-slider"></span></label></div><div class="sp-toggle-row"><div class="sp-toggle-info"><div class="sp-toggle-icon"><i class="bi bi-bell"></i></div><div><div class="sp-toggle-label">Notifikasi</div></div></div><label class="sp-switch"><input type="checkbox" id="toggleNotif" onchange="setToggle('notif', this.checked)"><span class="sp-slider"></span></label></div></div>
    </div>
    <div class="sp-footer">
        <button class="sp-btn-reset" onclick="resetSettings()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
        <button class="sp-btn-apply" onclick="saveSettings()"><i class="bi bi-check-lg"></i> Simpan</button>
    </div>
</aside>
<div class="sp-toast" id="spToast"></div>

<!-- TOP NAV -->
<nav class="top-nav">
    <a href="index_pelapor.php" class="nav-brand">
        <div class="brand-gem">🚆</div>
        <div>
            <div class="brand-name">Commuter<em>Link</em></div>
            <div class="brand-sub">Lost & Found</div>
        </div>
    </a>
    <div class="nav-actions">
        <a href="#" class="nav-icon-btn" id="notifBtn"><i class="bi bi-bell"></i><span class="notif-pip" id="notifPip"></span></a>
        <button onclick="openSettings()" class="nav-icon-btn" style="border:none;"><i class="bi bi-sliders"></i></button>
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
    <div class="hero-about fade-up">
        <div class="hero-glow-1"></div>
        <div class="hero-glow-2"></div>
        <div class="logo-big">🚆</div>
        <div class="hero-badge"><i class="bi bi-info-circle-fill"></i> Tentang Kami</div>
        <h1 class="hero-title">Commuter<em>Link</em> Nusantara</h1>
        <p class="hero-desc">Platform digital Lost &amp; Found untuk jaringan KRL Jabodetabek. Kami hadir untuk menghubungkan penumpang yang kehilangan barang dengan petugas di stasiun — cepat, transparan, dan terpercaya.</p>
    </div>

    <!-- STATS -->
    <div class="stats-band fade-up delay-1">
        <div class="stat-box"><div class="stat-box-num">2.4K+</div><div class="stat-box-label">Barang ditemukan</div></div>
        <div class="stat-box"><div class="stat-box-num">89%</div><div class="stat-box-label">Tingkat keberhasilan</div></div>
        <div class="stat-box"><div class="stat-box-num">48</div><div class="stat-box-label">Stasiun terdaftar</div></div>
        <div class="stat-box"><div class="stat-box-num">12K+</div><div class="stat-box-label">Pengguna aktif</div></div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- TENTANG -->
        <div class="cl-card fade-up delay-1">
            <div class="cl-card-body">
                <div class="section-label"><i class="bi bi-building"></i> Siapa Kami</div>
                <h2 class="section-title">Misi kami sederhana</h2>
                <p class="section-body">CommuterLink Nusantara adalah inisiatif digital yang lahir dari keprihatinan atas tingginya kasus kehilangan barang di transportasi publik. Dengan lebih dari 1,2 juta penumpang KRL setiap hari, ribuan barang tertinggal setiap minggunya — namun hanya sebagian kecil yang berhasil dikembalikan ke pemiliknya.</p>
                <p class="section-body" style="margin-top: 0.75rem;">Kami membangun jembatan digital antara penumpang dan petugas L&amp;F di seluruh stasiun, memastikan setiap laporan kehilangan ditangani secara cepat dan sistematis.</p>
            </div>
        </div>
        <!-- VISI -->
        <div class="cl-card fade-up delay-2">
            <div class="cl-card-body">
                <div class="section-label"><i class="bi bi-eye"></i> Visi</div>
                <h2 class="section-title">Transportasi publik yang lebih aman</h2>
                <p class="section-body">Mewujudkan ekosistem transportasi publik yang jujur, aman, dan nyaman — di mana setiap penumpang dapat bepergian tanpa khawatir kehilangan barang bawaan mereka untuk selamanya.</p>
                <div style="margin-top: 1.25rem; display: flex; flex-direction: column; gap: 0.6rem;">
                    <?php
                    $visions = [
                        ['bi-check-circle-fill','rgba(16,185,129,0.12)','#34D399', 'Sistem pelaporan real-time 24/7'],
                        ['bi-check-circle-fill','rgba(16,185,129,0.12)','#34D399', 'Integrasi ke seluruh koridor KRL'],
                        ['bi-check-circle-fill','rgba(16,185,129,0.12)','#34D399', 'Notifikasi otomatis ke pemilik barang'],
                    ];
                    foreach ($visions as $v): ?>
                    <div style="display:flex;align-items:center;gap:8px;font-size:0.82rem;color:var(--text-2);">
                        <i class="bi <?= $v[0] ?>" style="color:<?= $v[2] ?>;flex-shrink:0;"></i>
                        <?= $v[3] ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- NILAI-NILAI -->
    <div class="cl-card fade-up delay-2" style="margin-bottom: 1.5rem;">
        <div class="cl-card-body">
            <div class="section-label"><i class="bi bi-stars"></i> Nilai Kami</div>
            <h2 class="section-title">Apa yang kami pegang teguh</h2>
            <div class="values-list" style="margin-top: 1.25rem;">
                <div class="value-item"><div class="value-icon"><i class="bi bi-shield-check"></i></div><div><div class="value-title">Integritas</div><div class="value-desc">Setiap barang temuan ditangani dengan jujur dan transparan. Tidak ada informasi yang disembunyikan dari pemilik barang.</div></div></div>
                <div class="value-item"><div class="value-icon"><i class="bi bi-lightning-charge"></i></div><div><div class="value-title">Kecepatan</div><div class="value-desc">Respon cepat adalah prioritas. Kami menargetkan konfirmasi laporan dalam 2 jam dan pencocokan barang dalam 24 jam.</div></div></div>
                <div class="value-item"><div class="value-icon"><i class="bi bi-people"></i></div><div><div class="value-title">Komunitas</div><div class="value-desc">Membangun budaya saling peduli di antara pengguna transportasi publik — karena kejujuran dimulai dari individu.</div></div></div>
                <div class="value-item"><div class="value-icon"><i class="bi bi-phone"></i></div><div><div class="value-title">Aksesibilitas</div><div class="value-desc">Mudah digunakan oleh siapa saja, dari semua kalangan, tanpa perlu keahlian teknis apapun.</div></div></div>
            </div>
        </div>
    </div>

    <!-- MISI GRID -->
    <div class="cl-card fade-up delay-3" style="margin-bottom: 1.5rem;">
        <div class="cl-card-body">
            <div class="section-label"><i class="bi bi-bullseye"></i> Yang Kami Lakukan</div>
            <h2 class="section-title">Fitur unggulan platform</h2>
            <div class="misi-grid" style="margin-top: 1.25rem;">
                <div class="misi-item"><div class="misi-icon mi-amber">📋</div><div class="misi-title">Pelaporan Digital</div><div class="misi-desc">Buat laporan kehilangan kapan saja dan di mana saja, lengkap dengan foto dan detail barang.</div></div>
                <div class="misi-item"><div class="misi-icon mi-blue">🔍</div><div class="misi-title">Pencocokan Cerdas</div><div class="misi-desc">Sistem kami mencocokkan laporan dengan barang temuan secara otomatis berdasarkan deskripsi.</div></div>
                <div class="misi-item"><div class="misi-icon mi-teal">🔔</div><div class="misi-title">Notifikasi Real-time</div><div class="misi-desc">Dapatkan update langsung saat barangmu berhasil ditemukan atau ada perkembangan laporan.</div></div>
                <div class="misi-item"><div class="misi-icon mi-purple">🗺️</div><div class="misi-title">Peta Stasiun</div><div class="misi-desc">Informasi lengkap lokasi posko Lost &amp; Found di setiap stasiun KRL Jabodetabek.</div></div>
                <div class="misi-item"><div class="misi-icon mi-rose">📊</div><div class="misi-title">Dashboard Petugas</div><div class="misi-desc">Antarmuka khusus petugas untuk mengelola barang temuan dan menangani laporan masuk.</div></div>
                <div class="misi-item"><div class="misi-icon mi-sky">🔒</div><div class="misi-title">Data Aman</div><div class="misi-desc">Informasi pengguna dilindungi dan hanya dibagikan kepada pihak yang berwenang.</div></div>
            </div>
        </div>
    </div>

    <!-- TIMELINE -->
    <div class="cl-card fade-up delay-3" style="margin-bottom: 1.5rem;">
        <div class="cl-card-body">
            <div class="section-label"><i class="bi bi-clock-history"></i> Perjalanan Kami</div>
            <h2 class="section-title">Dari ide menjadi kenyataan</h2>
            <div class="timeline" style="margin-top: 1.5rem;">
                <div class="tl-item"><div class="tl-dot tl-dot-amber">2022</div><div><div class="tl-year">Awal Mula</div><div class="tl-title">Gagasan pertama lahir</div><div class="tl-desc">Berawal dari pengalaman pribadi kehilangan dompet di KRL Bogor Line, ide platform digital L&amp;F mulai digagas.</div></div></div>
                <div class="tl-item"><div class="tl-dot tl-dot-blue">2023</div><div><div class="tl-year">Pengembangan</div><div class="tl-title">Prototipe & uji coba</div><div class="tl-desc">Prototipe pertama dibangun dan diuji di 5 stasiun pilot: Bogor, Citayam, Depok, Manggarai, dan Jakarta Kota.</div></div></div>
                <div class="tl-item"><div class="tl-dot tl-dot-teal">2024</div><div><div class="tl-year">Peluncuran</div><div class="tl-title">Diluncurkan ke publik</div><div class="tl-desc">CommuterLink Nusantara resmi diluncurkan dan mencakup 48 stasiun di seluruh jaringan KRL Jabodetabek.</div></div></div>
                <div class="tl-item"><div class="tl-dot tl-dot-purple">2025</div><div><div class="tl-year">Saat Ini</div><div class="tl-title">Terus berkembang</div><div class="tl-desc">Lebih dari 12.000 pengguna aktif, 2.400+ barang berhasil dikembalikan, dan tingkat keberhasilan 89%.</div></div></div>
            </div>
        </div>
    </div>

    <!-- TEAM — 2x2 -->
    <div class="cl-card fade-up delay-4" style="margin-bottom: 1.5rem;">
        <div class="cl-card-body">
            <div class="section-label"><i class="bi bi-people-fill"></i> Tim Kami</div>
            <h2 class="section-title">Orang-orang di balik platform ini</h2>
            <div class="team-grid" style="margin-top: 1.25rem;">
                <div class="team-card">
                    <div class="team-avatar" style="background: linear-gradient(135deg,#F59E0B,#FCD34D);">S</div>
                    <div class="team-name">Steven</div>
                    <div class="team-role">Founder & CEO</div>
                    <div class="team-desc">Visioner di balik CommuterLink. Berpengalaman di bidang transportasi publik selama 8 tahun.</div>
                </div>
                <div class="team-card">
                    <div class="team-avatar" style="background: linear-gradient(135deg,#3B82F6,#60A5FA);">RA</div>
                    <div class="team-name">Rian Antony</div>
                    <div class="team-role">Head of Operations</div>
                    <div class="team-desc">Memastikan koordinasi petugas di seluruh stasiun berjalan lancar dan efisien setiap harinya.</div>
                </div>
                <div class="team-card">
                    <div class="team-avatar" style="background: linear-gradient(135deg,#10B981,#34D399);">CA</div>
                    <div class="team-name">Chelsea Arlyn</div>
                    <div class="team-role">Lead Developer</div>
                    <div class="team-desc">Arsitek teknologi platform. Membangun sistem yang cepat, andal, dan mudah digunakan.</div>
                </div>
                <div class="team-card">
                    <div class="team-avatar" style="background: linear-gradient(135deg,#8B5CF6,#A78BFA);">VEN</div>
                    <div class="team-name">Vincenzo Excellentza Ngawing</div>
                    <div class="team-role">Community Customer Support</div>
                    <div class="team-desc">Memimpin tim dukungan pelanggan untuk memastikan setiap laporan ditangani dengan baik.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTACT -->
    <div class="cl-card fade-up delay-5" style="margin-bottom: 1.5rem;">
        <div class="cl-card-body">
            <div class="section-label"><i class="bi bi-envelope-fill"></i> Hubungi Kami</div>
            <h2 class="section-title">Ada pertanyaan? Kami siap membantu</h2>
            <div class="contact-row" style="margin-top: 1.25rem;">
                <a href="tel:021-121" class="contact-item">
                    <div class="ci-icon"><i class="bi bi-telephone-fill"></i></div>
                    <div><div class="ci-label">Telepon</div><div class="ci-value">021-121</div></div>
                </a>
                <a href="mailto:info@commuterlink.id" class="contact-item">
                    <div class="ci-icon"><i class="bi bi-envelope-fill"></i></div>
                    <div><div class="ci-label">Email</div><div class="ci-value">info@commuterlink.id</div></div>
                </a>
                <a href="#" class="contact-item">
                    <div class="ci-icon"><i class="bi bi-geo-alt-fill"></i></div>
                    <div><div class="ci-label">Kantor Pusat</div><div class="ci-value">Jakarta Pusat</div></div>
                </a>
            </div>
            <div style="margin-top: 0.75rem; font-size: 0.78rem; color: var(--text-3); text-align: center;">
                <i class="bi bi-clock" style="font-size: 0.7rem;"></i> Jam operasional: Senin–Jumat, 07.00–21.00 WIB
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="cta-band fade-up delay-6">
        <div class="cta-band-title">Mulai gunakan CommuterLink sekarang</div>
        <div class="cta-band-sub">Lapor kehilangan, lacak perkembangan, dan temukan barangmu kembali.</div>
        <a href="index_pelapor.php#form-laporan" class="cta-btn"><i class="bi bi-plus-lg"></i> Buat Laporan Sekarang</a>
    </div>

</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav" role="navigation" aria-label="Navigasi bawah">
    <a href="index_pelapor.php" class="bn-item" title="Beranda"><i class="bi bi-house"></i><span>Beranda</span></a>
    <a href="stations.php"      class="bn-item" title="Stasiun"><i class="bi bi-train-front"></i><span>Stasiun</span></a>
    <a href="news.php"          class="bn-item" title="Berita"><i class="bi bi-newspaper"></i><span>Berita</span></a>
    <a href="faq.php"           class="bn-item" title="FAQ"><i class="bi bi-question-circle"></i><span>FAQ</span></a>
    <a href="about.php"         class="bn-item active" title="Tentang"><i class="bi bi-info-circle-fill"></i><span>Tentang</span></a>
</nav>

<script>
(function () {
    const DEFAULTS = { theme: 'dark', accent: 'amber', fontSize: 'md', compact: false, anim: true, notif: true };
    let S = Object.assign({}, DEFAULTS);
    const ACCENT_COLORS = { amber:'#F59E0B', blue:'#3B82F6', green:'#10B981', purple:'#8B5CF6', rose:'#EC4899' };
    const ACCENT_NAMES  = { amber:'Amber', blue:'Biru', green:'Hijau', purple:'Ungu', rose:'Rose' };
    const THEME_NAMES   = { dark:'Gelap', light:'Terang', system:'Sistem' };
    const THEME_ICONS   = { dark:'🌙', light:'☀️', system:'💻' };

    function loadSettings() { try { const s = localStorage.getItem('cl_settings'); if (s) S = Object.assign({}, DEFAULTS, JSON.parse(s)); } catch(e) {} }
    function applySettings() {
        const html = document.documentElement;
        let t = S.theme; if (t === 'system') t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        html.setAttribute('data-theme', t);
        html.setAttribute('data-accent', S.accent);
        html.setAttribute('data-fontsize', S.fontSize);
        html.setAttribute('data-compact', S.compact ? 'true' : 'false');
        html.setAttribute('data-anim', S.anim ? 'on' : 'off');
        const pip = document.getElementById('notifPip'); if (pip) pip.style.display = S.notif ? '' : 'none';
    }
    function syncPanel() {
        document.querySelectorAll('.theme-card').forEach(c => c.classList.toggle('active', c.dataset.theme === S.theme));
        document.querySelectorAll('.accent-dot').forEach(d => d.classList.toggle('active', d.dataset.accent === S.accent));
        document.querySelectorAll('.fs-btn').forEach(b => b.classList.toggle('active', b.dataset.size === S.fontSize));
        const ta = document.getElementById('toggleAnim'); if (ta) ta.checked = S.anim;
        const tn = document.getElementById('toggleNotif'); if (tn) tn.checked = S.notif;
        const ac = ACCENT_COLORS[S.accent] || '#F59E0B';
        document.getElementById('spPreviewThumb').style.background = `linear-gradient(135deg, ${ac}, rgba(255,255,255,0.25))`;
        document.getElementById('spPreviewTitle').textContent = `${THEME_ICONS[S.theme]} ${THEME_NAMES[S.theme]} · ${ACCENT_NAMES[S.accent]}`;
        document.getElementById('spPreviewSub').textContent = `Font: ${S.fontSize === 'sm' ? 'Kecil' : S.fontSize === 'lg' ? 'Besar' : 'Sedang'}`;
        const ab = document.querySelector('.sp-btn-apply'); if (ab) ab.style.background = ac;
    }
    window.setTheme    = t => { S.theme    = t; applySettings(); syncPanel(); };
    window.setAccent   = a => { S.accent   = a; applySettings(); syncPanel(); };
    window.setFontSize = f => { S.fontSize = f; applySettings(); syncPanel(); };
    window.setToggle   = (k, v) => { if (k==='anim') S.anim = v; else if (k==='notif') S.notif = v; applySettings(); syncPanel(); };
    window.openSettings  = () => { syncPanel(); document.getElementById('settingsPanel').classList.add('open'); document.getElementById('spOverlay').classList.add('open'); document.body.style.overflow='hidden'; };
    window.closeSettings = () => { document.getElementById('settingsPanel').classList.remove('open'); document.getElementById('spOverlay').classList.remove('open'); document.body.style.overflow=''; };
    window.saveSettings  = () => { try { localStorage.setItem('cl_settings', JSON.stringify(S)); } catch(e) {} closeSettings(); showToast('✅ Pengaturan tersimpan!'); };
    window.resetSettings = () => { S = Object.assign({}, DEFAULTS); applySettings(); syncPanel(); try { localStorage.removeItem('cl_settings'); } catch(e) {} showToast('🔄 Reset ke default'); };
    function showToast(msg) { const t = document.getElementById('spToast'); t.textContent = msg; t.classList.add('show'); setTimeout(() => t.classList.remove('show'), 2800); }
    document.addEventListener('keydown', e => { if ((e.ctrlKey||e.metaKey) && e.key===',') { e.preventDefault(); openSettings(); } if (e.key==='Escape') closeSettings(); });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (S.theme==='system') applySettings(); });
    loadSettings(); applySettings();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>