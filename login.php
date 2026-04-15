<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php';

// Sudah login → redirect sesuai role
if (isLoggedIn()) {
    $userCheck = getCurrentUser();
    if ($userCheck) {
        if (($userCheck['role'] ?? '') === 'petugas') {
            header('Location: index_petugas.php');
        } else {
            header('Location: index_pelapor.php');
        }
        exit;
    }
    logoutUser();
}

$csrf_token = generateCSRF();
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        if (empty($email) || empty($password)) {
            $error = 'Email dan password wajib diisi.';
        } else {
            try {
                $result = loginUser($email, $password);
                if ($result['success']) {
                    // Redirect berdasarkan role setelah login
                    $user = getCurrentUser();
                    if (($user['role'] ?? '') === 'petugas') {
                        header('Location: index_petugas.php');
                    } else {
                        header('Location: index_pelapor.php');
                    }
                    exit;
                } else {
                    $error = $result['error'];
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = 'Terjadi kesalahan. Silakan coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — CommuterLink Nusantara</title>

    <link rel="icon" href="uploads/favicon.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy:    #0B1F3A;
            --navy-2:  #152d52;
            --gold:    #F0A500;
            --gold-lt: #F7C948;
            --white:   #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100:#F1F5F9;
            --gray-400:#94A3B8;
            --gray-600:#475569;
            --gray-800:#1E293B;
            --danger:  #EF4444;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--navy);
            min-height: 100vh;
            margin: 0;
        }

        .hero-side {
            background: var(--navy);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.5rem 3rem;
            position: relative;
            overflow: hidden;
        }
        .hero-side::before {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(240,165,0,0.12) 0%, transparent 70%);
            top: -150px; right: -150px;
            border-radius: 50%;
            pointer-events: none;
        }
        .hero-side::after {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(21,45,82,0.8) 0%, transparent 70%);
            bottom: -80px; left: -80px;
            border-radius: 50%;
            pointer-events: none;
        }

        .rail-lines { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
        .rail { position: absolute; top: 0; bottom: 0; width: 1px; background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.06), transparent); }
        .rail:nth-child(1) { left: 20%; }
        .rail:nth-child(2) { left: 42%; }
        .rail:nth-child(3) { left: 65%; }
        .rail:nth-child(4) { left: 85%; }
        .rail-train { position: absolute; height: 3px; background: linear-gradient(90deg, transparent, var(--gold), transparent); border-radius: 99px; animation: trainRun 5s linear infinite; opacity: 0; }
        .rail-train:nth-child(5) { left: 10%; width: 120px; animation-delay: 0s;   top: 30%; }
        .rail-train:nth-child(6) { left: 10%; width: 80px;  animation-delay: 2s;   top: 60%; }
        .rail-train:nth-child(7) { left: 10%; width: 150px; animation-delay: 3.5s; top: 80%; }
        @keyframes trainRun {
            0%   { transform: translateX(-20px); opacity: 0; }
            5%   { opacity: 0.6; }
            90%  { opacity: 0.6; }
            100% { transform: translateX(calc(100vw)); opacity: 0; }
        }

        .brand-logo { position: relative; z-index: 2; animation: fadeSlideUp 0.6s ease both; }
        .brand-logo .icon-wrap { width: 48px; height: 48px; background: var(--gold); border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 0.75rem; box-shadow: 0 4px 20px rgba(240,165,0,0.3); }
        .brand-name { font-size: 1.35rem; font-weight: 800; color: var(--white); line-height: 1; letter-spacing: -0.3px; }
        .brand-name em { font-style: normal; color: var(--gold); }
        .brand-tagline { font-size: 0.7rem; font-weight: 600; letter-spacing: 0.15em; text-transform: uppercase; color: var(--gray-400); margin-top: 0.3rem; }

        .hero-copy { position: relative; z-index: 2; animation: fadeSlideUp 0.7s 0.1s ease both; }
        .hero-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(240,165,0,0.15); border: 1px solid rgba(240,165,0,0.3); color: var(--gold-lt); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 5px 12px; border-radius: 99px; margin-bottom: 1.25rem; }
        .hero-title { font-size: clamp(2.2rem, 4vw, 3.2rem); font-weight: 800; color: var(--white); line-height: 1.1; letter-spacing: -1.5px; margin-bottom: 1rem; }
        .hero-title .accent { color: var(--gold); }
        .hero-desc { font-size: 0.9rem; color: var(--gray-400); line-height: 1.75; max-width: 380px; }

        .stats-bar { position: relative; z-index: 2; display: flex; gap: 1rem; animation: fadeSlideUp 0.7s 0.2s ease both; }
        .stat-card { flex: 1; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 1rem 1.1rem; backdrop-filter: blur(8px); transition: border-color 0.3s; }
        .stat-card:hover { border-color: rgba(240,165,0,0.3); }
        .stat-num { font-size: 1.5rem; font-weight: 800; color: var(--gold); line-height: 1; }
        .stat-label { font-size: 0.7rem; color: var(--gray-400); margin-top: 4px; font-weight: 500; }

        .form-side { background: var(--white); min-height: 100vh; display: flex; flex-direction: column; justify-content: center; padding: 3rem; position: relative; animation: slideFromRight 0.65s cubic-bezier(0.22, 1, 0.36, 1) both; }
        .form-side::before { content: ''; position: absolute; top: 0; right: 0; width: 120px; height: 120px; background: var(--gold); clip-path: polygon(100% 0, 0 0, 100% 100%); opacity: 0.12; }

        .form-eyebrow { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--gold); margin-bottom: 0.5rem; }
        .form-heading { font-size: 2rem; font-weight: 800; color: var(--gray-800); letter-spacing: -0.8px; margin-bottom: 0.4rem; line-height: 1.15; }
        .form-subtext { font-size: 0.84rem; color: var(--gray-400); margin-bottom: 2rem; }
        .form-subtext a { color: var(--navy); font-weight: 700; text-decoration: none; border-bottom: 2px solid var(--gold); padding-bottom: 1px; transition: color 0.2s; }
        .form-subtext a:hover { color: var(--gold); }

        .cl-alert { display: flex; align-items: flex-start; gap: 10px; background: #FEF2F2; border: 1px solid #FECACA; border-left: 4px solid var(--danger); border-radius: 10px; padding: 12px 14px; font-size: 0.82rem; color: #B91C1C; margin-bottom: 1.5rem; animation: shake 0.4s ease; }
        .cl-alert i { flex-shrink: 0; font-size: 1rem; margin-top: 1px; }
        @keyframes shake { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }

        .cl-label { font-size: 0.78rem; font-weight: 700; color: var(--gray-800); margin-bottom: 0.4rem; display: block; }
        .cl-input-wrap { position: relative; }
        .cl-input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 1rem; pointer-events: none; z-index: 2; }
        .cl-input { width: 100%; padding: 0.78rem 0.9rem 0.78rem 2.6rem; border: 1.5px solid #E2E8F0; border-radius: 10px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.88rem; color: var(--gray-800); background: var(--gray-50); transition: border-color 0.2s, box-shadow 0.2s, background 0.2s; outline: none; }
        .cl-input:focus { border-color: var(--navy); background: var(--white); box-shadow: 0 0 0 3px rgba(11,31,58,0.07); }
        .cl-input::placeholder { color: #CBD5E1; }
        .cl-input.has-toggle { padding-right: 3rem; }
        .cl-pw-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray-400); font-size: 1.05rem; cursor: pointer; padding: 4px; line-height: 1; transition: color 0.2s; z-index: 2; }
        .cl-pw-toggle:hover { color: var(--navy); }

        .cl-btn-primary { width: 100%; padding: 0.85rem 1rem; background: var(--navy); color: var(--white); border: none; border-radius: 10px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.92rem; font-weight: 700; letter-spacing: 0.02em; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; position: relative; overflow: hidden; transition: transform 0.15s, box-shadow 0.2s; }
        .cl-btn-primary::after { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, transparent 40%, rgba(240,165,0,0.2)); }
        .cl-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(11,31,58,0.28); }
        .cl-btn-primary:active { transform: translateY(0); }

        .cl-divider { display: flex; align-items: center; gap: 1rem; margin: 1.5rem 0; color: var(--gray-400); font-size: 0.75rem; font-weight: 600; }
        .cl-divider::before, .cl-divider::after { content: ''; flex: 1; height: 1px; background: #E2E8F0; }

        .register-box { background: var(--gray-50); border: 1.5px solid #E2E8F0; border-radius: 14px; padding: 1.25rem 1.5rem; text-align: center; }
        .register-box p { font-size: 0.82rem; color: var(--gray-400); margin-bottom: 0.85rem; font-weight: 500; }
        .cl-btn-outline { display: inline-flex; align-items: center; gap: 6px; padding: 0.65rem 1.75rem; border: 2px solid var(--navy); border-radius: 9px; color: var(--navy); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.84rem; font-weight: 700; text-decoration: none; transition: background 0.2s, color 0.2s, transform 0.15s, box-shadow 0.2s; }
        .cl-btn-outline:hover { background: var(--navy); color: var(--white); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(11,31,58,0.2); }

        .form-footer-note { margin-top: 1.75rem; text-align: center; font-size: 0.72rem; color: #CBD5E1; }

        @keyframes fadeSlideUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideFromRight { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }

        @media (max-width: 991.98px) {
            body { background: var(--white); }
            .hero-side { min-height: auto; padding: 2rem 1.5rem 2.5rem; }
            .stats-bar { display: none; }
            .hero-title { font-size: 1.9rem; }
            .hero-desc { display: none; }
            .form-side { min-height: auto; padding: 2rem 1.5rem 3rem; animation: none; }
            .form-side::before { display: none; }
        }
        @media (max-width: 575.98px) {
            .hero-side { padding: 1.5rem 1.25rem 2rem; }
            .hero-title { font-size: 1.6rem; }
            .form-side { padding: 1.5rem 1.25rem 2.5rem; }
            .form-heading { font-size: 1.65rem; }
        }
    </style>
</head>
<body>

<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">

        <!-- LEFT: HERO -->
        <div class="col-lg-6 hero-side">
            <div class="rail-lines">
                <div class="rail"></div>
                <div class="rail"></div>
                <div class="rail"></div>
                <div class="rail"></div>
                <div class="rail-train"></div>
                <div class="rail-train"></div>
                <div class="rail-train"></div>
            </div>
            <div class="brand-logo">
                <div class="icon-wrap">🚆</div>
                <div class="brand-name">Commuter<em>Link</em></div>
                <div class="brand-tagline">Nusantara · Lost &amp; Found</div>
            </div>
            <div class="hero-copy">
                <div class="hero-badge"><i class="bi bi-lightning-fill"></i> KRL Jabodetabek</div>
                <h1 class="hero-title">Temukan<br>barang <span class="accent">hilangmu</span><br>kembali.</h1>
                <p class="hero-desc">Platform pelaporan dan pencarian barang hilang di jaringan KRL Jabodetabek. Cepat, mudah, dan terpercaya.</p>
            </div>
            <div class="stats-bar">
                <div class="stat-card"><div class="stat-num">2.4K+</div><div class="stat-label">Barang ditemukan</div></div>
                <div class="stat-card"><div class="stat-num">89%</div><div class="stat-label">Berhasil diklaim</div></div>
                <div class="stat-card"><div class="stat-num">48 Stasiun</div><div class="stat-label">Stasiun terdaftar</div></div>
            </div>
        </div>

        <!-- RIGHT: FORM -->
        <div class="col-lg-6 form-side">
            <div style="max-width: 420px; width: 100%; margin: 0 auto;">

                <div class="form-eyebrow">Selamat datang kembali</div>
                <h2 class="form-heading">Masuk ke akun</h2>
                <p class="form-subtext">Belum punya akun? <a href="register.php">Daftar sekarang →</a></p>

                <?php if ($error): ?>
                <div class="cl-alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="mb-3">
                        <label class="cl-label" for="email">Alamat Email</label>
                        <div class="cl-input-wrap">
                            <i class="bi bi-envelope cl-input-icon"></i>
                            <input type="email" id="email" name="email" class="cl-input"
                                placeholder="nama@email.com"
                                value="<?= htmlspecialchars($email) ?>"
                                autocomplete="email" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="cl-label" for="password">Password</label>
                        <div class="cl-input-wrap">
                            <i class="bi bi-lock cl-input-icon"></i>
                            <input type="password" id="password" name="password"
                                class="cl-input has-toggle"
                                placeholder="Masukkan password"
                                autocomplete="current-password" required>
                            <button type="button" class="cl-pw-toggle" onclick="togglePw()" title="Tampilkan password">
                                <i class="bi bi-eye" id="pwIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="cl-btn-primary">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Masuk Sekarang
                    </button>
                </form>

                <div class="cl-divider">atau</div>

                <div class="register-box">
                    <p>Belum terdaftar di CommuterLink Nusantara?</p>
                    <a href="register.php" class="cl-btn-outline">
                        <i class="bi bi-person-plus"></i>
                        Buat Akun Baru
                    </a>
                </div>

                <p class="form-footer-note">CommuterLink Nusantara &copy; <?= date('Y') ?></p>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('pwIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>