<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$db_host = 'localhost';
$db_name = 'commuter_link_nusantara';
$db_user = 'root';
$db_pass = '021936';

function getDB() {
    global $db_host, $db_name, $db_user, $db_pass;
    
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        return $pdo;
    } catch (\PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Koneksi database gagal. Silakan hubungi administrator.");
    }
}
function getDBmysqli() {
    static $mysqli = null;
    
    if ($mysqli !== null) {
        return $mysqli;
    }
    
    global $db_host, $db_user, $db_pass, $db_name;
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        error_log("Database connection failed: " . $mysqli->connect_error);
        die("Koneksi database gagal. Silakan hubungi administrator.");
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function userColumnExists(string $column) {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
        $stmt->execute([$column]);
        $cache[$column] = (bool) $stmt->fetch();
    } catch (Exception $e) {
        // if something goes wrong assume column missing
        $cache[$column] = false;
    }

    return $cache[$column];
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $pdo = getDB();
    $cols = ['id','nama','email','role'];
    if (userColumnExists('no_telepon')) {
        $cols[] = 'no_telepon';
    }
    $sql = 'SELECT ' . implode(',', $cols) . ' FROM users WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_unset();
        session_destroy();
        return null;
    }

    return $user;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
    
    $user = getCurrentUser();
    if (!$user) {
        header("Location: login.php");
        exit;
    }
}

function generateCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function clean($input) {
    if (is_array($input)) {
        return array_map('clean', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateItemCode($prefix = 'ITM') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
}

function logActivity($action, $entityType, $entityId, $description) {
    $pdo = getDB();
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $entityType, $entityId, $description, $ip]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function loginUser($email, $password) {
    $pdo = getDB();

    $cols = ['id','nama','email','password','role'];
    if (userColumnExists('no_telepon')) {
        array_splice($cols, 3, 0, 'no_telepon'); // insert before password
    }
    $sql = 'SELECT ' . implode(',', $cols) . ' FROM users WHERE email = ?';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Query gagal: ' . $e->getMessage()];
    }

    if (!$user) {
        return ['success' => false, 'error' => 'Email tidak ditemukan.'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Password salah.'];
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['nama'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    if (isset($user['no_telepon'])) {
        $_SESSION['user_phone'] = $user['no_telepon'];
    }

    return ['success' => true, 'user' => $user];
}

function logoutUser() {
    session_unset();
    session_destroy();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}
