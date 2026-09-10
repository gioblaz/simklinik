<?php
/**
 * SIMKlinik — Header Layout
 * @param string $page_title Judul halaman
 * @param string $active_module Modul aktif untuk highlight sidebar
 */

if (!isset($page_title)) $page_title = 'Dashboard';
if (!isset($active_module)) $active_module = 'dashboard';

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_login();

$user  = current_user();
$flash = get_flash();
$today_indo = tgl_indo(date('Y-m-d'), true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars(INSTANSI_NAMA) ?> — <?= htmlspecialchars(INSTANSI_KOTA) ?>">
  <title><?= htmlspecialchars($page_title) ?> | <?= htmlspecialchars(INSTANSI_NAMA) ?></title>

  <!-- Favicon Dynamic -->
  <?php if (!empty(INSTANSI_LOGO) && file_exists(BASE_PATH . INSTANSI_LOGO)): ?>
    <link rel="icon" href="<?= BASE_URL . INSTANSI_LOGO ?>">
  <?php else: ?>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/favicon.svg">
  <?php endif; ?>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Main CSS with auto cache-busting -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= file_exists(BASE_PATH . 'assets/css/style.css') ? filemtime(BASE_PATH . 'assets/css/style.css') : time() ?>">

  <?php if (isset($extra_css)): ?>
    <?= $extra_css ?>
  <?php endif; ?>
</head>
<body>
<div class="app-wrapper">

  <!-- ─── Left Sidebar Navigation ────────────────────────────── -->
  <?php include __DIR__ . '/sidebar.php'; ?>

  <!-- Mobile Overlay Backdrop -->
  <div class="mobile-overlay" id="mobileOverlay"></div>

  <!-- Main Content Area -->
  <div class="main-content" id="mainContent">

    <!-- ─── Topbar Header ──────────────────────────────────────── -->
    <?php include __DIR__ . '/topbar.php'; ?>

    <!-- Page Content Start -->
    <main class="page-content">

      <?php if ($flash): ?>
        <?php
        $icon_map = ['success' => 'fa-check-circle', 'danger' => 'fa-times-circle', 'warning' => 'fa-exclamation-triangle', 'info' => 'fa-info-circle'];
        $icon = $icon_map[$flash['type']] ?? 'fa-info-circle';
        ?>
        <div class="alert alert-<?= $flash['type'] ?>" data-auto-dismiss="5000">
          <i class="fas <?= $icon ?>"></i>
          <span><?= htmlspecialchars($flash['message']) ?></span>
          <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
      <?php endif; ?>
