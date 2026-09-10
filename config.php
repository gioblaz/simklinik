<?php
/**
 * SIMKlinik — Konfigurasi Utama
 * Koneksi database, konstanta global, dan inisialisasi session
 */

// ─── Konfigurasi Database ────────────────────────────────────────────────────
define('DB_HOST', '7.7.7.105');
define('DB_PORT', 3306);
define('DB_USER', 'klinikbmy');
define('DB_PASS', 'xkBjcGaPPhm5nRKB');
define('DB_NAME', 'klinikbmy');
define('DB_CHARSET', 'utf8mb4');

// ─── Konfigurasi Aplikasi ────────────────────────────────────────────────────
define('APP_NAME', 'SIMKlinik');
define('APP_SUBTITLE', 'Sistem Informasi Manajemen Klinik');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/simklinik/');
define('BASE_PATH', dirname(__FILE__) . '/');

// ─── Session ─────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ─── Timezone (WIB - Waktu Indonesia Barat) ──────────────────────────────────
date_default_timezone_set('Asia/Jakarta');

// ─── Error Reporting (production: set ke 0) ──────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ─── Koneksi Database (MySQLi) ───────────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        'status'  => 'error',
        'message' => 'Koneksi database gagal: ' . $conn->connect_error
    ]));
}

$conn->set_charset(DB_CHARSET);
$conn->query("SET sql_mode = ''");
$conn->query("SET time_zone = '+07:00'");

// ─── Konfigurasi Instansi (Dynamic from DB) ──────────────────────────────────
$db_settings = [];
$res_s = @$conn->query("SELECT field, value FROM mlite_settings WHERE module = 'settings'");
if ($res_s) {
    while ($r_s = $res_s->fetch_assoc()) {
        $db_settings[$r_s['field']] = $r_s['value'];
    }
}

define('INSTANSI_NAMA', !empty($db_settings['nama_instansi']) ? $db_settings['nama_instansi'] : 'Klinik Pratama');
define('INSTANSI_ALAMAT', !empty($db_settings['alamat']) ? $db_settings['alamat'] : 'Jl. Perintis Kemerdekaan No. 45');
define('INSTANSI_KOTA', !empty($db_settings['kota']) ? $db_settings['kota'] : 'Barabai');
define('INSTANSI_PROVINSI', !empty($db_settings['propinsi']) ? $db_settings['propinsi'] : 'Kalimantan Selatan');
define('INSTANSI_TELP', !empty($db_settings['nomor_telepon']) ? $db_settings['nomor_telepon'] : '0812345678');
define('INSTANSI_LOGO', !empty($db_settings['logo']) ? $db_settings['logo'] : 'uploads/settings/logo.png');

// ─── Helper: Escape String ───────────────────────────────────────────────────
function e(string $str): string {
    global $conn;
    return htmlspecialchars($conn->real_escape_string($str), ENT_QUOTES, 'UTF-8');
}

// ─── Helper: Redirect ────────────────────────────────────────────────────────
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

// ─── Helper: Flash Message ───────────────────────────────────────────────────
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─── Helper: Auth Check ──────────────────────────────────────────────────────
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan login terlebih dahulu.');
        redirect(BASE_URL . 'modules/auth/login.php');
    }
}

// ─── Helper: Role Check ──────────────────────────────────────────────────────
function has_role(string $role): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'fullname' => $_SESSION['fullname'] ?? '',
        'role'     => $_SESSION['user_role'] ?? 'user',
        'avatar'   => $_SESSION['avatar'] ?? '',
    ];
}

// ─── Helper: Format Tanggal Indonesia ────────────────────────────────────────
function tgl_indo(string $date, bool $with_day = false): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    $days  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $months = ['','Januari','Februari','Maret','April','Mei','Juni',
               'Juli','Agustus','September','Oktober','November','Desember'];
    $ts  = strtotime($date);
    $day = $with_day ? $days[date('w', $ts)] . ', ' : '';
    return $day . date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

// ─── Helper: Format Rupiah ───────────────────────────────────────────────────
function rupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// ─── Helper: Hitung Umur ─────────────────────────────────────────────────────
function hitung_umur(string $tgl_lahir): string {
    if (empty($tgl_lahir) || $tgl_lahir === '0000-00-00') return '-';
    $dob  = new DateTime($tgl_lahir);
    $now  = new DateTime();
    $diff = $now->diff($dob);
    if ($diff->y > 0) return $diff->y . ' Tahun';
    if ($diff->m > 0) return $diff->m . ' Bulan';
    return $diff->d . ' Hari';
}

// ─── Helper: Generate No Rawat ───────────────────────────────────────────────
function generate_no_rawat(): string {
    global $conn;
    $today  = date('Y/m/d');
    $prefix = $today . '/';
    $query  = "SELECT no_rawat FROM reg_periksa WHERE no_rawat LIKE '$prefix%' ORDER BY no_rawat DESC LIMIT 1";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $last   = $result->fetch_assoc()['no_rawat'];
        $lastNo = (int)substr($last, -4);
        $newNo  = str_pad($lastNo + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNo = '0001';
    }
    return $prefix . $newNo;
}

// ─── Helper: Generate No RM ──────────────────────────────────────────────────
function generate_no_rm(): string {
    global $conn;
    $result = $conn->query("SELECT no_rkm_medis FROM pasien ORDER BY no_rkm_medis DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $last = $result->fetch_assoc()['no_rkm_medis'];
        $num  = (int)preg_replace('/[^0-9]/', '', $last) + 1;
    } else {
        $num = 1;
    }
    return str_pad($num, 6, '0', STR_PAD_LEFT);
}

// ─── Helper: CSRF Token ──────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
