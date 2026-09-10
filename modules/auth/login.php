<?php
/**
 * SIMKlinik — Halaman Login
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

// Jika sudah login, langsung ke dashboard
if (is_logged_in()) {
    redirect(BASE_URL . 'modules/dashboard/index.php');
}

$error   = '';
$success = '';

// Handle flash message
$flash = get_flash();
if ($flash) {
    ${$flash['type'] === 'success' ? 'success' : 'error'} = $flash['message'];
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan password tidak boleh kosong.';
    } else {
        $result = login_user($username, $password);
        if ($result['success']) {
            redirect(BASE_URL . 'modules/dashboard/index.php');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | <?= htmlspecialchars(INSTANSI_NAMA) ?></title>
  <?php if (!empty(INSTANSI_LOGO) && file_exists(BASE_PATH . INSTANSI_LOGO)): ?>
    <link rel="icon" href="<?= BASE_URL . INSTANSI_LOGO ?>">
  <?php else: ?>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/favicon.svg">
  <?php endif; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/login.css">
</head>
<body>

<div class="login-wrapper">

  <!-- ─── Left Visual Panel ─────────────────────────────── -->
  <div class="login-visual">
    <div class="login-visual-content">

      <!-- Medical Icon Grid -->
      <div class="visual-icon-grid">
        <div class="visual-icon-item">🏥</div>
        <div class="visual-icon-item">💊</div>
        <div class="visual-icon-item">🩺</div>
        <div class="visual-icon-item">🔬</div>
        <div class="visual-icon-item">❤️</div>
        <div class="visual-icon-item">💉</div>
        <div class="visual-icon-item">📋</div>
        <div class="visual-icon-item">🩻</div>
        <div class="visual-icon-item">🧬</div>
      </div>

      <h1 class="visual-title">
        Sistem Informasi<br>Manajemen Klinik
      </h1>
      <p class="visual-subtitle">
        Solusi digital terintegrasi untuk pelayanan kesehatan<br>
        yang lebih efisien, akurat, dan modern.
      </p>

      <!-- Integration Badges -->
      <div class="integration-list">
        <span class="int-badge"><span class="dot"></span>PCare BPJS</span>
        <span class="int-badge"><span class="dot"></span>Satu Sehat</span>
        <span class="int-badge"><span class="dot"></span>E-RM BPJS</span>
      </div>

    </div>
  </div>

  <!-- ─── Right Form Panel ──────────────────────────────── -->
  <div class="login-form-panel">

    <!-- Logo -->
    <div class="login-header">
      <div class="login-logo">
        <div class="login-logo-icon" style="overflow:hidden;background:#ffffff;border:1px solid #e2e8f0;padding:2px;">
          <?php if (!empty(INSTANSI_LOGO) && file_exists(BASE_PATH . INSTANSI_LOGO)): ?>
            <img src="<?= BASE_URL . INSTANSI_LOGO ?>" alt="<?= htmlspecialchars(INSTANSI_NAMA) ?>" style="width:100%;height:100%;object-fit:contain;border-radius:8px;">
          <?php else: ?>
            <i class="fas fa-hospital-alt"></i>
          <?php endif; ?>
        </div>
        <div class="login-logo-text">
          <div class="app-name"><?= htmlspecialchars(INSTANSI_NAMA) ?></div>
          <div class="app-tagline"><?= htmlspecialchars(INSTANSI_KOTA) ?> &bull; SIMKlinik</div>
        </div>
      </div>

      <h2 class="login-welcome">Selamat Datang 👋</h2>
      <p class="login-desc">Masukkan kredensial Anda untuk mengakses sistem.</p>
    </div>

    <!-- Alert -->
    <?php if ($error): ?>
      <div class="login-alert error" id="loginAlert">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="login-alert success">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($success) ?></span>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form class="login-form" method="POST" action="" id="loginForm" autocomplete="off">

      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <div class="input-wrapper">
          <i class="fas fa-user input-icon"></i>
          <input
            type="text"
            id="username"
            name="username"
            class="form-input <?= $error ? 'has-error' : '' ?>"
            placeholder="Masukkan username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            autocomplete="username"
            autofocus
            required
          >
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-wrapper">
          <i class="fas fa-lock input-icon"></i>
          <input
            type="password"
            id="password"
            name="password"
            class="form-input <?= $error ? 'has-error' : '' ?>"
            placeholder="Masukkan password"
            autocomplete="current-password"
            required
          >
          <button type="button" class="btn-toggle-pass" id="togglePass" title="Tampilkan password">
            <i class="fas fa-eye" id="togglePassIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login" id="btnLogin">
        <i class="fas fa-sign-in-alt"></i>
        <span>Masuk ke Sistem</span>
      </button>

    </form>

    <!-- Footer -->
    <div class="login-footer">
      <p>
        <i class="fas fa-shield-alt" style="color:#2563eb;margin-right:4px;"></i>
        Akses dilindungi. Hanya untuk pengguna yang berwenang.
      </p>
      <p class="login-version">
        <?= APP_NAME ?> v<?= APP_VERSION ?> &mdash; <?= date('Y') ?>
      </p>
    </div>

  </div><!-- /.login-form-panel -->
</div><!-- /.login-wrapper -->

<script>
// Toggle password visibility
const togglePass = document.getElementById('togglePass');
const passInput  = document.getElementById('password');
const toggleIcon = document.getElementById('togglePassIcon');

if (togglePass) {
  togglePass.addEventListener('click', () => {
    const isText = passInput.type === 'text';
    passInput.type = isText ? 'password' : 'text';
    toggleIcon.className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
  });
}

// Form submit loading state
const loginForm = document.getElementById('loginForm');
const btnLogin  = document.getElementById('btnLogin');

if (loginForm) {
  loginForm.addEventListener('submit', () => {
    btnLogin.disabled = true;
    btnLogin.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.4);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;"></span> <span>Memverifikasi...</span>';
  });
}

// Auto dismiss alert after 5 seconds
const alert = document.getElementById('loginAlert');
if (alert) {
  setTimeout(() => {
    alert.style.transition = 'opacity 0.4s';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 400);
  }, 5000);
}

// Spin animation
const s = document.createElement('style');
s.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(s);
</script>

</body>
</html>
