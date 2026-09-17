<?php
/**
 * SIMKlinik — Auth Functions
 */

require_once dirname(__DIR__) . '/config.php';

/**
 * Proses login user
 */
function login_user(string $username, string $password): array {
    global $conn;

    $username = $conn->real_escape_string(trim($username));
    $result   = $conn->query("SELECT * FROM mlite_users WHERE username = '$username' LIMIT 1");

    if (!$result || $result->num_rows === 0) {
        return ['success' => false, 'message' => 'Username atau password salah.'];
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Username atau password salah.'];
    }

    // Set session
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['fullname']  = $user['fullname'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['avatar']    = $user['avatar'] ?? '';
    $_SESSION['access']    = $user['access'] ?? '';
    $_SESSION['login_at']  = time();

    return ['success' => true, 'message' => 'Login berhasil.'];
}

/**
 * Logout user
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Cek akses modul
 */
function can_access(string $module): bool {
    if (!is_logged_in()) return false;
    // Dashboard selalu bisa diakses semua user yang login
    if ($module === 'dashboard') return true;
    $access = $_SESSION['access'] ?? '';
    if ($access === 'all' || ($_SESSION['user_role'] ?? '') === 'admin') return true;
    $modules = array_map('trim', explode(',', $access));
    if (in_array($module, $modules, true)) return true;

    // Sub-module / alias access
    if ($module === 'general_consent' && (in_array('rekam_medis', $modules, true) || in_array('pendaftaran', $modules, true))) return true;
    if ($module === 'master_lab' && in_array('laboratorium', $modules, true)) return true;
    if ($module === 'bridging_monitor' && (in_array('settings', $modules, true) || in_array('pcare', $modules, true))) return true;
    if ($module === 'mapping' && (in_array('settings', $modules, true) || in_array('pcare', $modules, true) || in_array('satu_sehat', $modules, true))) return true;

    return false;
}

/**
 * Block akses langsung ke modul — redirect jika tidak punya izin
 */
function require_module_access(string $module): void {
    require_login();
    if (!can_access($module)) {
        set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk modul ini.');
        redirect(BASE_URL . 'modules/dashboard/index.php');
    }
}
