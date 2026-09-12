<?php
/**
 * SIMKlinik — Sidebar Navigation (Navigasi Kiri)
 */

if (!isset($active_module)) $active_module = 'dashboard';
$user = current_user();

$antrian_count = function_exists('stat_antrian_menunggu') ? stat_antrian_menunggu() : 0;
$stok_kritis_count = function_exists('stat_stok_kritis_count') ? stat_stok_kritis_count() : 0;
$lab_pending_count = function_exists('stat_permintaan_lab_menunggu') ? stat_permintaan_lab_menunggu() : 0;

$nav_sections = [
  [
    'title' => 'Menu Utama',
    'items' => [
      [
        'module' => 'dashboard',
        'label'  => 'Dashboard',
        'icon'   => 'fa-gauge',
        'color'  => '#2563eb',
        'url'    => 'dashboard/index.php'
      ],
      [
        'module' => 'pasien',
        'label'  => 'Data Pasien',
        'icon'   => 'fa-users',
        'color'  => '#059669',
        'url'    => 'pasien/index.php'
      ],
    ]
  ],
  [
    'title' => 'Pelayanan Medis',
    'items' => [
      [
        'module' => 'pendaftaran',
        'label'  => 'Pendaftaran Antrean',
        'icon'   => 'fa-clipboard-list',
        'color'  => '#d97706',
        'url'    => 'pendaftaran/index.php',
        'badge'  => $antrian_count > 0 ? $antrian_count : null,
        'badge_class' => 'badge-warning'
      ],
      [
        'module' => 'rekam_medis',
        'label'  => 'Rawat Jalan (E-RM)',
        'icon'   => 'fa-stethoscope',
        'color'  => '#4f46e5',
        'url'    => 'rekam_medis/index.php'
      ],
      [
        'module' => 'laboratorium',
        'label'  => 'Laboratorium',
        'icon'   => 'fa-flask-vial',
        'color'  => '#0284c7',
        'url'    => 'laboratorium/index.php',
        'badge'  => $lab_pending_count > 0 ? $lab_pending_count : null,
        'badge_class' => 'badge-info'
      ],
      [
        'module' => 'farmasi',
        'label'  => 'Farmasi & Apotek',
        'icon'   => 'fa-pills',
        'color'  => '#e11d48',
        'url'    => 'farmasi/index.php'
      ],
      [
        'module' => 'kasir',
        'label'  => 'Kasir & Pembayaran',
        'icon'   => 'fa-cash-register',
        'color'  => '#0d9488',
        'url'    => 'kasir/index.php'
      ],
    ]
  ],
  [
    'title' => 'Master & Logistik',
    'items' => [
      [
        'module' => 'kepegawaian',
        'label'  => 'Kepegawaian & Dokter',
        'icon'   => 'fa-id-badge',
        'color'  => '#7c3aed',
        'url'    => 'kepegawaian/index.php'
      ],
      [
        'module' => 'tarif_ralan',
        'label'  => 'Tarif Tindakan Ralan',
        'icon'   => 'fa-hand-holding-medical',
        'color'  => '#db2777',
        'url'    => 'tarif_ralan/index.php'
      ],
      [
        'module' => 'master_lab',
        'label'  => 'Master Tarif & Lab',
        'icon'   => 'fa-flask-vial',
        'color'  => '#0284c7',
        'url'    => 'laboratorium/master_lab.php'
      ],
      [
        'module' => 'gudang_obat',
        'label'  => 'Gudang Obat & Stok',
        'icon'   => 'fa-boxes-stacked',
        'color'  => '#d97706',
        'url'    => 'gudang_obat/index.php',
        'badge'  => $stok_kritis_count > 0 ? $stok_kritis_count : null,
        'badge_class' => 'badge-danger'
      ],
      [
        'module' => 'laporan',
        'label'  => 'Laporan & Rekap',
        'icon'   => 'fa-chart-bar',
        'color'  => '#0284c7',
        'url'    => 'laporan/index.php'
      ],
    ]
  ],
  [
    'title' => 'Integrasi & Bridging',
    'items' => [
      [
        'module' => 'bridging_monitor',
        'label'  => 'Monitoring PCare Live',
        'icon'   => 'fa-tower-broadcast',
        'color'  => '#0284c7',
        'url'    => 'settings/bridging_monitor.php',
        'badge'  => 'LIVE',
        'badge_class' => 'badge-live-pulse'
      ],

      [
        'module' => 'mapping',
        'label'  => 'Mapping Bridging',
        'icon'   => 'fa-shuffle',
        'color'  => '#0d9488',
        'url'    => 'mapping/index.php'
      ],
      [
        'module' => 'pcare',
        'label'  => 'PCare BPJS',
        'icon'   => 'fa-hospital',
        'color'  => '#0891b2',
        'url'    => 'pcare/index.php'
      ],
      [
        'module' => 'satu_sehat',
        'label'  => 'Satu Sehat Kemenkes',
        'icon'   => 'fa-shield-heart',
        'color'  => '#be123c',
        'url'    => 'satu_sehat/index.php'
      ],
      [
        'module' => 'antrean_bpjs',
        'label'  => 'Antrean Mobile JKN',
        'icon'   => 'fa-mobile-alt',
        'color'  => '#059669',
        'url'    => 'antrean_bpjs/index.php'
      ],
      [
        'module' => 'bpjs_emr',
        'label'  => 'E-RM BPJS',
        'icon'   => 'fa-laptop-medical',
        'color'  => '#9333ea',
        'url'    => 'bpjs_emr/index.php'
      ],
    ]
  ],
  [
    'title' => 'Pengaturan',
    'items' => [
      [
        'module' => 'manajemen_user',
        'label'  => 'Manajemen User',
        'icon'   => 'fa-users-cog',
        'color'  => '#dc2626',
        'url'    => 'manajemen_user/index.php'
      ],
      [
        'module' => 'settings',
        'label'  => 'Pengaturan Sistem',
        'icon'   => 'fa-sliders',
        'color'  => '#475569',
        'url'    => 'settings/index.php'
      ],
    ]
  ],
];
?>

<aside class="sidebar" id="sidebar" aria-label="Navigasi Menu Utama">

  <!-- Sidebar Brand Header -->
  <div class="sidebar-header">
    <a href="<?= BASE_URL ?>modules/dashboard/index.php" class="sidebar-brand" title="<?= htmlspecialchars(INSTANSI_NAMA) ?>">
      <div class="sidebar-logo-icon">
        <?php if (!empty(INSTANSI_LOGO) && file_exists(BASE_PATH . INSTANSI_LOGO)): ?>
          <img src="<?= BASE_URL . INSTANSI_LOGO ?>" alt="<?= htmlspecialchars(INSTANSI_NAMA) ?>">
        <?php else: ?>
          <i class="fas fa-hospital-alt"></i>
        <?php endif; ?>
      </div>
      <div class="sidebar-brand-text">
        <div class="sidebar-brand-name"><?= htmlspecialchars(INSTANSI_NAMA) ?></div>
        <div class="sidebar-brand-sub">
          <span><?= !empty(INSTANSI_KOTA) ? htmlspecialchars(INSTANSI_KOTA) : 'Sistem Klinik' ?></span>
          <span class="badge-online"><i class="fas fa-circle"></i> Online</span>
        </div>
      </div>
    </a>
  </div>

  <!-- Navigation Links -->
  <nav class="sidebar-nav">
    <?php foreach ($nav_sections as $sec):
      // Filter items: hanya tampilkan yang bisa diakses user
      $visible_items = array_filter($sec['items'], fn($item) => can_access($item['module']));
      if (empty($visible_items)) continue; // Skip section jika tidak ada item yang boleh diakses
    ?>
      <div class="nav-section"><?= htmlspecialchars($sec['title']) ?></div>
      <?php foreach ($visible_items as $item): ?>
        <?php 
          $is_active  = ($active_module === $item['module']); 
          $item_color = $item['color'] ?? '#00bfa5';
        ?>
        <div class="nav-item">
          <a class="nav-link <?= $is_active ? 'active' : '' ?>"
             href="<?= BASE_URL ?>modules/<?= $item['url'] ?>"
             title="<?= htmlspecialchars($item['label']) ?>">
            <span class="nav-icon" style="<?= $is_active ? '' : 'color:'.$item_color.'; background-color:'.$item_color.'18; border-color:'.$item_color.'35;' ?>">
              <i class="fas <?= $item['icon'] ?>"></i>
            </span>
            <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
            <?php if (!empty($item['badge'])): ?>
              <span class="nav-badge <?= $item['badge_class'] ?? '' ?>"><?= $item['badge'] ?></span>
            <?php endif; ?>
          </a>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>


  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <div class="sidebar-footer-inner">
      <span class="sidebar-version"><i class="fas fa-heartbeat" style="color:#3b82f6;margin-right:4px;"></i> <?= htmlspecialchars(APP_NAME) ?> v<?= htmlspecialchars(APP_VERSION) ?></span>
      <a href="<?= BASE_URL ?>modules/auth/logout.php" class="sidebar-power-btn" title="Keluar dari Sistem" data-confirm="Yakin ingin keluar dari sistem?">
        <i class="fas fa-power-off"></i>
      </a>
    </div>
  </div>

</aside>
