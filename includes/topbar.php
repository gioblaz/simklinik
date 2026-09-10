<?php
/**
 * SIMKlinik — Topbar Header (For Left Sidebar Layout)
 */

if (!isset($page_title)) $page_title = 'Dashboard';
$user = current_user();
$antrian_cnt = function_exists('stat_antrian_menunggu') ? stat_antrian_menunggu() : 0;
$today_indo = function_exists('tgl_indo') ? tgl_indo(date('Y-m-d'), true) : date('d/m/Y');
?>
<header class="topbar" id="topbar">

  <!-- Left: Sidebar Toggle & Breadcrumb -->
  <div class="topbar-left">
    <button type="button" class="topbar-toggle-btn" id="sidebarToggle" title="Buka/Tutup Navigasi Menu" aria-label="Toggle Sidebar">
      <i class="fas fa-bars"></i>
    </button>
    <div class="topbar-breadcrumb">
      <span class="crumb-root"><i class="fas fa-hospital-alt"></i> <?= htmlspecialchars(APP_NAME) ?></span>
      <i class="fas fa-chevron-right crumb-sep"></i>
      <span class="crumb-current"><?= htmlspecialchars($page_title) ?></span>
    </div>
  </div>

  <!-- Right: Live Date/Clock, Notifications & User Dropdown -->
  <div class="topbar-right">

    <!-- Live Date & Clock -->
    <div class="topbar-datetime">
      <i class="fas fa-calendar-day" style="color:var(--primary-600);"></i>
      <span><?= $today_indo ?></span>
      <span style="color:#cbd5e1;margin:0 4px;">|</span>
      <span id="currentTime" style="font-weight:600;font-family:monospace;color:#334155;"></span>
    </div>

    <?php if (isset($active_module) && $active_module === 'bridging_monitor'): ?>
    <!-- Live PCare Latency Radar Widget (Hanya aktif di Menu Monitoring PCare) -->
    <button type="button" class="topbar-bridging-pill" id="topbarBridgingPill" data-open-bridging-monitor title="Klik untuk membuka Realtime PCare Latency Monitor">
      <span class="topbar-radar-wrapper">
        <span class="topbar-radar-ping"></span>
        <span class="topbar-radar-dot dot-online"></span>
      </span>
      <span class="topbar-bridging-text">PCare Live</span>
      <span class="topbar-bridging-lat">--ms</span>
    </button>
    <?php endif; ?>


    <!-- Notifikasi Antrian Dropdown -->
    <div class="dropdown">
      <button class="topbar-btn" data-dropdown="notifMenuTop" title="Notifikasi Antrian">
        <i class="fas fa-bell"></i>
        <?php if ($antrian_cnt > 0): ?>
          <span class="notif-dot"></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu" id="notifMenuTop" style="min-width:280px;right:0;left:auto;">
        <div style="padding:12px 16px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
          <strong style="font-size:13px;color:#0f172a;">Notifikasi Klinik</strong>
          <?php if ($antrian_cnt > 0): ?>
            <span class="badge badge-warning"><?= $antrian_cnt ?> Menunggu</span>
          <?php endif; ?>
        </div>
        <?php if ($antrian_cnt > 0): ?>
          <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="dropdown-item" style="padding:12px 16px;">
            <div style="width:34px;height:34px;background:#fffbeb;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#d97706;flex-shrink:0;">
              <i class="fas fa-user-clock"></i>
            </div>
            <div>
              <div style="font-size:12.5px;font-weight:600;color:#0f172a;"><?= $antrian_cnt ?> Pasien di Antrian</div>
              <div style="font-size:11px;color:#64748b;">Kunjungan poliklinik hari ini</div>
            </div>
          </a>
        <?php else: ?>
          <div style="padding:20px;text-align:center;font-size:12px;color:#94a3b8;">
            <i class="fas fa-check-circle" style="font-size:24px;color:#10b981;display:block;margin-bottom:8px;"></i>
            Semua antrian telah terlayani
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- User Profile Dropdown -->
    <div class="dropdown">
      <button class="topbar-btn" data-dropdown="userMenuTop" title="Akun Pengguna" style="width:auto;padding:4px 10px;gap:8px;border-radius:99px;background:#f8fafc;border:1px solid #e2e8f0;">
        <div class="user-avatar" style="width:28px;height:28px;font-size:11px;border-radius:50%;background:linear-gradient(135deg, #3b82f6, #1d4ed8);">
          <?php if (!empty($user['avatar']) && file_exists(BASE_PATH . 'uploads/avatars/' . $user['avatar'])): ?>
            <img src="<?= BASE_URL ?>uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="" style="border-radius:50%;width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <?= strtoupper(substr($user['fullname'] ?? 'A', 0, 1)) ?>
          <?php endif; ?>
        </div>
        <div style="text-align:left;line-height:1.2;">
          <div style="font-size:12px;font-weight:700;color:#0f172a;"><?= htmlspecialchars(explode(' ', $user['fullname'] ?? 'User')[0]) ?></div>
          <div style="font-size:10px;color:#64748b;text-transform:capitalize;"><?= htmlspecialchars($user['role'] ?? 'admin') ?></div>
        </div>
        <i class="fas fa-chevron-down" style="font-size:9px;color:#94a3b8;margin-left:2px;"></i>
      </button>
      <div class="dropdown-menu" id="userMenuTop" style="min-width:210px;right:0;left:auto;">
        <div style="padding:12px 16px;border-bottom:1px solid #f1f5f9;">
          <div style="font-size:13px;font-weight:700;color:#0f172a;"><?= htmlspecialchars($user['fullname'] ?? 'Administrator') ?></div>
          <div style="font-size:11px;color:#64748b;text-transform:capitalize;">Role: <?= htmlspecialchars($user['role'] ?? 'admin') ?></div>
        </div>
        <a href="<?= BASE_URL ?>modules/settings/identitas.php" class="dropdown-item">
          <i class="fas fa-hospital" style="color:#3b82f6;"></i> Identitas Klinik
        </a>
        <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="dropdown-item">
          <i class="fas fa-network-wired" style="color:#0891b2;"></i> Konfigurasi Bridging
        </a>
        <div class="dropdown-divider"></div>
        <a href="<?= BASE_URL ?>modules/auth/logout.php" class="dropdown-item danger" data-confirm="Yakin ingin keluar dari sistem?">
          <i class="fas fa-sign-out-alt"></i> Keluar
        </a>
      </div>
    </div>

  </div>
</header>
