<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$user   = getCurrentUser();
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (session_status() === PHP_SESSION_NONE) session_start();

function jsonResp(bool $ok, string $msg, array $extra = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}


function redirectWithFlash(string $url, bool $ok, string $msg): void {
    $_SESSION['flash'] = ['ok' => $ok, 'msg' => $msg];
    header('Location: ' . $url);
    exit;
}

function refreshUserSession(PDO $pdo, int $userId): void {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $fresh = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fresh) {

            unset($fresh['password']);

            foreach (['user', 'current_user', 'auth_user', 'logged_user'] as $key) {
                if (isset($_SESSION[$key]) && is_array($_SESSION[$key])) {
                    $_SESSION[$key] = array_merge($_SESSION[$key], $fresh);
                }
            }
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                $_SESSION['user_nama']  = $fresh['nama']  ?? $_SESSION['user_nama']  ?? '';
                $_SESSION['user_email'] = $fresh['email'] ?? $_SESSION['user_email'] ?? '';
            }
        }
    } catch (Exception $e) { /* ignore */ }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) jsonResp(false, 'Method tidak diizinkan.');
    header('Location: profile.php');
    exit;
}

$action   = trim($_POST['action'] ?? '');
$redirect = $_POST['redirect'] ?? 'profile.php';
$pdo      = getDB();

if ($action === 'update_profile') {
    $nama  = trim($_POST['nama']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $telp  = trim($_POST['telp']  ?? '');
    $bio   = trim($_POST['bio']   ?? '');
    $stasiun = trim($_POST['stasiun'] ?? '');

    if (!$nama || !$email) {
        $msg = 'Nama dan email wajib diisi.';
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect, false, $msg);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Format email tidak valid.';
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect, false, $msg);
    }

    try {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = :e AND id != :id LIMIT 1");
        $chk->execute([':e' => $email, ':id' => $user['id']]);
        if ($chk->fetch()) {
            $msg = 'Email sudah digunakan akun lain.';
            if ($isAjax) jsonResp(false, $msg);
            redirectWithFlash($redirect, false, $msg);
        }
    } catch (Exception $e) { /* ignore */ }

    $existingCols = [];
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $existingCols = array_map('strtolower', $cols);
    } catch (Exception $e) {
       
    }


    try {
        $pdo->prepare("UPDATE users SET nama=:n, email=:e, updated_at=NOW() WHERE id=:id")
            ->execute([':n' => $nama, ':e' => $email, ':id' => $user['id']]);
    } catch (Exception $e) {
        $msg = 'Gagal memperbarui profil: ' . $e->getMessage();
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect, false, $msg);
    }

    $optionalFields = [
        'no_telepon' => $telp,
        'telepon'    => $telp,
        'phone'      => $telp, 
        'bio'        => $bio,
        'stasiun'    => $stasiun,
        'station'    => $stasiun, 
    ];

    foreach ($optionalFields as $col => $val) {
        if (!empty($existingCols) && !in_array(strtolower($col), $existingCols)) continue;
        try {
            $pdo->prepare("UPDATE users SET `$col`=:v, updated_at=NOW() WHERE id=:id")
                ->execute([':v' => $val ?: null, ':id' => $user['id']]);
        } catch (Exception $e) {
            /* Kolom tidak ada — lanjut saja */
        }
    }

    refreshUserSession($pdo, (int)$user['id']);

    $fresh = [];
    try {
        $s = $pdo->prepare("SELECT * FROM users WHERE id=:id LIMIT 1");
        $s->execute([':id' => $user['id']]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            unset($row['password']);
            $fresh = $row;
        }
    } catch (Exception $e) {}

    $msg = 'Profil berhasil diperbarui.';
    if ($isAjax) jsonResp(true, $msg, [
        'user'    => $fresh,
        'nama'    => $fresh['nama']        ?? $nama,
        'email'   => $fresh['email']       ?? $email,
        'telp'    => $fresh['no_telepon']  ?? $fresh['telepon'] ?? $fresh['phone'] ?? $telp,
        'bio'     => $fresh['bio']         ?? $bio,
        'stasiun' => $fresh['stasiun']     ?? $fresh['station'] ?? $stasiun,
        'avatar'  => $fresh['avatar']      ?? null,
    ]);
    redirectWithFlash($redirect, true, $msg);
}

if ($action === 'change_password') {
    $old  = $_POST['old_password']     ?? '';
    $new  = $_POST['new_password']     ?? '';
    $conf = $_POST['confirm_password'] ?? '';

    if (!$old || !$new || !$conf) {
        $msg = 'Semua field password wajib diisi.';
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect . '?tab=password', false, $msg);
    }
    if ($new !== $conf) {
        $msg = 'Password baru dan konfirmasi tidak cocok.';
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect . '?tab=password', false, $msg);
    }
    if (strlen($new) < 8) {
        $msg = 'Password baru minimal 8 karakter.';
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect . '?tab=password', false, $msg);
    }

    try {
        $row = $pdo->prepare("SELECT password FROM users WHERE id=:id");
        $row->execute([':id' => $user['id']]);
        $data = $row->fetch(PDO::FETCH_ASSOC);
        if (!$data || !password_verify($old, $data['password'] ?? '')) {
            $msg = 'Password lama tidak sesuai.';
            if ($isAjax) jsonResp(false, $msg);
            redirectWithFlash($redirect . '?tab=password', false, $msg);
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=:p, updated_at=NOW() WHERE id=:id")
            ->execute([':p' => $hash, ':id' => $user['id']]);
        $msg = 'Password berhasil diubah.';
        if ($isAjax) jsonResp(true, $msg);
        redirectWithFlash($redirect . '?tab=password', true, $msg);
    } catch (Exception $e) {
        $msg = 'Gagal mengubah password.';
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect . '?tab=password', false, $msg);
    }
}

if ($action === 'upload_avatar') {
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Tidak ada file yang diunggah.';
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect, false, $msg);
    }

    $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime     = mime_content_type($_FILES['avatar']['tmp_name']);
    $ext      = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    $extMap   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];

    if (!in_array($mime, $allowed)) {
        $msg = 'Format tidak didukung. Gunakan JPG/PNG/WEBP.';
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect, false, $msg);
    }
    if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        $msg = 'Ukuran file maksimal 2 MB.';
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect, false, $msg);
    }

    $uploadDir = __DIR__ . '/uploads/avatars/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    try {
        $old = $pdo->prepare("SELECT avatar FROM users WHERE id=:id");
        $old->execute([':id' => $user['id']]);
        $oldRow = $old->fetch(PDO::FETCH_ASSOC);
        if (!empty($oldRow['avatar']) && file_exists(__DIR__ . '/' . $oldRow['avatar'])) {
            @unlink(__DIR__ . '/' . $oldRow['avatar']);
        }
    } catch (Exception $e) { /* ignore */ }

    $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . ($extMap[$mime] ?? $ext);
    $dest     = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
        $msg = 'Gagal menyimpan file.';
        if ($isAjax) jsonResp(false, $msg);
        redirectWithFlash($redirect, false, $msg);
    }

    $avatarPath = 'uploads/avatars/' . $filename;
    try {
        $pdo->prepare("UPDATE users SET avatar=:a, updated_at=NOW() WHERE id=:id")
            ->execute([':a' => $avatarPath, ':id' => $user['id']]);
    } catch (Exception $e) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email");
            $pdo->prepare("UPDATE users SET avatar=:a WHERE id=:id")
                ->execute([':a' => $avatarPath, ':id' => $user['id']]);
        } catch (Exception $e2) { /* ignore */ }
    }

    $msg = 'Foto profil berhasil diperbarui.';
    if ($isAjax) jsonResp(true, $msg, ['path' => $avatarPath . '?v=' . time()]);
    redirectWithFlash($redirect, true, $msg);
}

if ($isAjax) jsonResp(false, 'Aksi tidak dikenal.');
header('Location: profile.php');
exit;