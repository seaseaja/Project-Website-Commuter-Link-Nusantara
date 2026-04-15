<?php
require_once __DIR__ . '/auth.php';

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

$db = getDBmysqli();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama        = trim($_POST['nama'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $no_telepon  = trim($_POST['no_telepon'] ?? '');
    $password    = $_POST['password'] ?? '';
    $password2   = $_POST['password2'] ?? '';
    $role        = $_POST['role'] ?? '';

    if ($nama === '' || $email === '' || $password === '' || $password2 === '' || $role === '') {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (!in_array($role, ['petugas', 'pelapor'])) {
        $error = 'Role tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $password2) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Email sudah terdaftar.';
            $stmt->close();
        } else {
            $stmt->close();
            $hash  = password_hash($password, PASSWORD_DEFAULT);
            $stmt2 = $db->prepare("INSERT INTO users (nama, email, no_telepon, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt2->bind_param('sssss', $nama, $email, $no_telepon, $hash, $role);
            if ($stmt2->execute()) {
                $success = 'Akun berhasil dibuat! Silakan login.';
                $_POST   = [];
            } else {
                $error = 'Gagal menyimpan data. Coba lagi.';
            }
            $stmt2->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Akun – CommuterLink Nusantara</title>
    <link rel="icon" href="uploads/favicon.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --navy:#0F2744; --navy2:#1E3A5F; --orange:#F7941D; --orange2:#e07c0a; }
        *, *::before, *::after { box-sizing:border-box; }
        body {
            font-family:'Plus Jakarta Sans',sans-serif;
            margin:0; min-height:100vh;
            background:#F0F4F9;
            display:flex; align-items:center; justify-content:center;
            padding:2rem 1rem;
        }
        .reg-wrap { width:100%; max-width:560px; }

        .reg-header {
            background:linear-gradient(135deg,var(--navy2),var(--navy));
            border-radius:18px 18px 0 0;
            padding:2rem 2.5rem; color:#fff;
            display:flex; align-items:center; gap:1rem;
            position:relative; overflow:hidden;
        }
        .reg-header::after {
            content:''; position:absolute; right:-40px; top:-40px;
            width:160px; height:160px;
            background:radial-gradient(circle,rgba(247,148,29,.25) 0%,transparent 70%);
            border-radius:50%;
        }
        .reg-header::before {
            content:''; position:absolute; bottom:0; left:0; right:0; height:4px;
            background:repeating-linear-gradient(90deg,
                var(--orange) 0,var(--orange) 30px,transparent 30px,transparent 50px);
            opacity:.5;
        }
        .reg-icon {
            width:52px; height:52px; background:var(--orange);
            border-radius:14px; display:flex; align-items:center;
            justify-content:center; font-size:1.4rem;
            flex-shrink:0; position:relative; z-index:1;
        }
        .reg-header-text { position:relative; z-index:1; }
        .reg-header-text h1 { font-size:1.2rem; font-weight:800; margin:0 0 .1rem; }
        .reg-header-text p  { font-size:.8rem; opacity:.65; margin:0; }

        .reg-body {
            background:#fff; border-radius:0 0 18px 18px;
            padding:2rem 2.5rem 2.5rem;
            box-shadow:0 16px 48px rgba(0,0,0,.12);
        }

        .form-label {
            font-size:.78rem; font-weight:700; color:var(--navy2);
            letter-spacing:.04em; text-transform:uppercase; margin-bottom:.4rem;
        }
        .form-control, .form-select {
            border:1.5px solid #dee2e6; border-radius:10px;
            padding:.65rem .9rem; font-size:.9rem; color:var(--navy);
            transition:border-color .2s,box-shadow .2s; font-family:inherit;
        }
        .form-control:focus, .form-select:focus {
            border-color:var(--orange);
            box-shadow:0 0 0 3px rgba(247,148,29,.15); outline:none;
        }
        .input-group .form-control { border-right:none; border-radius:10px 0 0 10px; }
        .input-group .btn-eye {
            border:1.5px solid #dee2e6; border-left:none;
            border-radius:0 10px 10px 0;
            background:#fff; color:#adb5bd;
            padding:0 .9rem; transition:color .2s;
        }
        .input-group .btn-eye:hover { color:var(--orange); }

        .role-options { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
        .role-card input[type="radio"] { display:none; }
        .role-card label {
            display:flex; flex-direction:column; align-items:center; gap:.5rem;
            padding:1rem; border:1.5px solid #dee2e6; border-radius:12px;
            cursor:pointer; font-size:.85rem; font-weight:600;
            color:#6c757d; transition:all .2s; text-align:center;
        }
        .role-card label i { font-size:1.4rem; color:#adb5bd; transition:color .2s; }
        .role-card input:checked + label {
            border-color:var(--orange); background:rgba(247,148,29,.06); color:var(--navy);
        }
        .role-card input:checked + label i { color:var(--orange); }
        .role-card label:hover { border-color:var(--orange); color:var(--navy); }

        .strength-bar { height:4px; border-radius:2px; background:#e9ecef; margin-top:.4rem; overflow:hidden; }
        .strength-fill { height:100%; border-radius:2px; width:0%; transition:width .3s,background .3s; }

        .btn-register {
            background:var(--orange); color:#fff; border:none;
            border-radius:10px; padding:.75rem; font-weight:700;
            font-size:.95rem; width:100%; letter-spacing:.02em;
            transition:background .2s,transform .1s; font-family:inherit; cursor:pointer;
        }
        .btn-register:hover  { background:var(--orange2); }
        .btn-register:active { transform:scale(.98); }

        .link-orange { color:var(--orange); font-weight:700; text-decoration:none; }
        .link-orange:hover { text-decoration:underline; }
        .alert { border-radius:10px; font-size:.875rem; padding:.75rem 1rem; margin-bottom:1.25rem; }

        .reg-body > * { animation:fadeUp .35s ease both; }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }
    </style>
</head>
<body>

<div class="reg-wrap">
    <div class="reg-header">
        <div class="reg-icon"><i class="bi bi-train-front-fill"></i></div>
        <div class="reg-header-text">
            <h1>CommuterLink Nusantara</h1>
            <p>Sistem Lost &amp; Found Digital KRL</p>
        </div>
    </div>

    <div class="reg-body">
        <h5 class="fw-bold mb-0" style="color:var(--navy)">Buat Akun Baru</h5>
        <p class="text-muted small mb-3">Isi data di bawah untuk mendaftar ke sistem.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill"></i>
                <?= htmlspecialchars($success) ?>
                <a href="login.php" class="ms-auto link-orange fw-bold">Login →</a>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="mb-3">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" class="form-control"
                       placeholder="Masukkan nama lengkap"
                       value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Alamat Email</label>
                <input type="email" name="email" class="form-control"
                       placeholder="nama@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">No. Telepon (opsional)</label>
                <input type="text" name="no_telepon" class="form-control"
                       placeholder="0812xxxxxxx"
                       value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Daftar Sebagai</label>
                <div class="role-options">
                    <div class="role-card">
                        <input type="radio" id="role_petugas" name="role" value="petugas"
                               <?= ($_POST['role'] ?? '') === 'petugas' ? 'checked' : '' ?>>
                        <label for="role_petugas">
                            <i class="bi bi-person-badge"></i>
                            Petugas
                            <small class="text-muted fw-normal">Kelola temuan &amp; laporan</small>
                        </label>
                    </div>
                    <div class="role-card">
                        <input type="radio" id="role_pelapor" name="role" value="pelapor"
                               <?= ($_POST['role'] ?? '') === 'pelapor' ? 'checked' : '' ?>>
                        <label for="role_pelapor">
                            <i class="bi bi-person-raised-hand"></i>
                            Pelapor
                            <small class="text-muted fw-normal">Laporkan kehilangan</small>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mb-1">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="pwd"
                           class="form-control" placeholder="Min. 6 karakter" required>
                    <button class="btn-eye" type="button" id="togglePwd">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
                <div class="strength-bar mt-2"><div class="strength-fill" id="strengthFill"></div></div>
                <small id="strengthText" style="font-size:.75rem"></small>
            </div>

            <div class="mb-4 mt-3">
                <label class="form-label">Konfirmasi Password</label>
                <div class="input-group">
                    <input type="password" name="password2" id="pwd2"
                           class="form-control" placeholder="Ulangi password" required>
                    <button class="btn-eye" type="button" id="togglePwd2">
                        <i class="bi bi-eye" id="eyeIcon2"></i>
                    </button>
                </div>
                <small id="matchText" style="font-size:.75rem"></small>
            </div>

            <button type="submit" class="btn-register">
                <i class="bi bi-person-plus me-2"></i>Daftar Sekarang
            </button>
        </form>

        <hr class="my-3">
        <p class="text-center small mb-0" style="color:#6c757d">
            Sudah punya akun? <a href="login.php" class="link-orange">Login di sini</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
}
document.getElementById('togglePwd').onclick  = () => togglePass('pwd',  'eyeIcon');
document.getElementById('togglePwd2').onclick = () => togglePass('pwd2', 'eyeIcon2');

const pwdInput = document.getElementById('pwd');
const fill     = document.getElementById('strengthFill');
const txt      = document.getElementById('strengthText');

pwdInput.addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 6)               score++;
    if (v.length >= 10)              score++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
    if (/[0-9]/.test(v))             score++;
    if (/[^A-Za-z0-9]/.test(v))      score++;
    const levels = [
        { w: '0%',   c: '#dc3545', t: '' },
        { w: '25%',  c: '#dc3545', t: 'Lemah' },
        { w: '50%',  c: '#ffc107', t: 'Cukup' },
        { w: '75%',  c: '#20c997', t: 'Kuat' },
        { w: '100%', c: '#198754', t: 'Sangat Kuat' },
    ];
    const l = levels[Math.min(score, 4)];
    fill.style.width      = l.w;
    fill.style.background = l.c;
    txt.textContent       = l.t;
    txt.style.color       = l.c;
});

const pwd2Input = document.getElementById('pwd2');
const matchTxt  = document.getElementById('matchText');
function checkMatch() {
    if (!pwd2Input.value) { matchTxt.textContent = ''; return; }
    if (pwdInput.value === pwd2Input.value) {
        matchTxt.textContent = '✓ Password cocok';
        matchTxt.style.color = '#198754';
    } else {
        matchTxt.textContent = '✗ Password tidak cocok';
        matchTxt.style.color = '#dc3545';
    }
}
pwd2Input.addEventListener('input', checkMatch);
pwdInput.addEventListener('input',  checkMatch);
</script>
</body>
</html>