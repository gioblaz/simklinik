<?php
/**
 * SIMKlinik — Top Navigation Bar (Tab Mode)
 * Clean, Friendly, Calm Coloring Tab Interface with Bridging Dropdown
 */

if (!isset($active_module)) $active_module = 'dashboard';
$user = current_user();

$is_bridging_active = in_array($active_module, ['mapping', 'pcare', 'satu_sehat', 'bpjs_emr', 'antrean_bpjs']);
$is_master_active   = in_array($active_module, ['kepegawaian', 'tarif_ralan', 'gudang_obat', 'laporan']);
$stok_kritis_badge  = function_exists('stat_stok_kritis_count') ? stat_stok_kritis_count() : 0;

$nav_groups = [
  [
    'group' => 'Utama',
    'tabs'  => [
      ['module' => 'dashboard',   'label' => 'Dashboard',   'icon' => 'fa-gauge',          'color' => 'color-blue',   'url' => 'dashboard/index.php'],
      ['module' => 'pasien',      'label' => 'Pasien',      'icon' => 'fa-users',          'color' => 'color-green',  'url' => 'pasien/index.php'],
    ]
  ],
  [
    'group' => 'Pelayanan',
    'tabs'  => [
      ['module' => 'pendaftaran', 'label' => 'Pendaftaran', 'icon' => 'fa-clipboard-list', 'color' => 'color-amber',  'url' => 'pendaftaran/index.php', 'badge' => stat_antrian_menunggu() ?: null],
      ['module' => 'rekam_medis', 'label' => 'Rawat Jalan', 'icon' => 'fa-stethoscope',    'color' => 'color-indigo', 'url' => 'rekam_medis/index.php'],
      ['module' => 'laboratorium', 'label' => 'Laboratorium', 'icon' => 'fa-flask-vial',   'color' => 'color-cyan',   'url' => 'laboratorium/index.php', 'badge' => (function_exists('stat_permintaan_lab_menunggu') ? stat_permintaan_lab_menunggu() : null) ?: null],
      ['module' => 'farmasi',     'label' => 'Farmasi',      'icon' => 'fa-pills',          'color' => 'color-rose',   'url' => 'farmasi/index.php'],
      ['module' => 'kasir',       'label' => 'Kasir',        'icon' => 'fa-cash-register',  'color' => 'color-teal',   'url' => 'kasir/index.php'],
    ]
  ]
];
?>

<header class="top-navbar-container">

  <!-- ─── Row 1: Brand, Quick Search, Utilities & Profile ─── -->
  <div class="top-header-row">
    
    <!-- Brand Logo -->
    <a href="<?= BASE_URL ?>modules/dashboard/index.php" class="top-brand">
      <div class="top-brand-icon" style="overflow:hidden;background:#ffffff;border:1px solid #e2e8f0;padding:2px;">
        <?php if (!empty(INSTANSI_LOGO) && file_exists(BASE_PATH . INSTANSI_LOGO)): ?>
          <img src="<?= BASE_URL . INSTANSI_LOGO ?>" alt="<?= htmlspecialchars(INSTANSI_NAMA) ?>" style="width:100%;height:100%;object-fit:contain;border-radius:8px;">
        <?php else: ?>
          <div style="width:100%;height:100%;background:linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;">
            <i class="fas fa-hospital-alt"></i>
          </div>
        <?php endif; ?>
      </div>
      <div class="top-brand-text">
        <div class="top-brand-name"><?= htmlspecialchars(INSTANSI_NAMA) ?></div>
        <div class="top-brand-sub">
          <span><?= htmlspecialchars(INSTANSI_KOTA) ?></span>
          <span class="badge-online"><i class="fas fa-circle"></i> Online</span>
        </div>
      </div>
    </a>

    <!-- Utilities (Clock, Notif, User Dropdown) -->
    <div class="top-utilities">
      
      <!-- Live Date & Clock -->
      <div class="top-datetime">
        <i class="fas fa-calendar-day" style="color:var(--primary-600);"></i>
        <span><?= tgl_indo(date('Y-m-d'), true) ?></span>
        <span style="color:#cbd5e1;">|</span>
        <span id="currentTime" style="font-weight:600;font-family:monospace;color:#334155;"></span>
      </div>

      <!-- Notifikasi Antrian -->
      <?php $antrian_cnt = stat_antrian_menunggu(); ?>
      <div class="dropdown">
        <button class="topbar-btn" data-dropdown="notifMenuTop" title="Notifikasi Antrian">
          <i class="fas fa-bell"></i>
          <?php if ($antrian_cnt > 0): ?>
            <span class="notif-dot"></span>
          <?php endif; ?>
        </button>
        <div class="dropdown-menu" id="notifMenuTop" style="min-width:290px;">
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
              <img src="<?= BASE_URL ?>uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="" style="border-radius:50%;">
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
        <div class="dropdown-menu" id="userMenuTop" style="min-width:210px;">
          <div style="padding:12px 16px;border-bottom:1px solid #f1f5f9;">
            <div style="font-size:13px;font-weight:700;color:#0f172a;"><?= htmlspecialchars($user['fullname'] ?? 'Administrator') ?></div>
            <div style="font-size:11px;color:#64748b;text-transform:capitalize;">Role: <?= htmlspecialchars($user['role'] ?? 'admin') ?></div>
          </div>
          <a href="<?= BASE_URL ?>modules/settings/profil.php" class="dropdown-item">
            <i class="fas fa-user-circle" style="color:#3b82f6;"></i> Profil Saya
          </a>
          <a href="<?= BASE_URL ?>modules/settings/index.php" class="dropdown-item">
            <i class="fas fa-sliders" style="color:#64748b;"></i> Pengaturan Sistem
          </a>
          <div class="dropdown-divider"></div>
          <a href="<?= BASE_URL ?>modules/auth/logout.php" class="dropdown-item danger" data-confirm="Yakin ingin keluar dari sistem?">
            <i class="fas fa-sign-out-alt"></i> Keluar
          </a>
        </div>
      </div>

    </div>
  </div>

  <!-- ─── Row 2: Tab Navigation Mode (Clean, Friendly & Calm) ─ -->
  <nav class="top-nav-tabs-wrapper" aria-label="Menu Navigasi Tab">
    <div class="top-nav-tabs">
      
      <!-- Modul Utama & Pelayanan -->
      <?php $group_count = 0; ?>
      <?php foreach ($nav_groups as $g): ?>
        <?php if ($group_count > 0): ?>
          <div class="nav-tab-divider" aria-hidden="true"></div>
        <?php endif; ?>
        <?php foreach ($g['tabs'] as $t): ?>
          <?php $is_act = ($active_module === $t['module']); ?>
          <a class="nav-tab <?= $t['color'] ?> <?= $is_act ? 'active' : '' ?>"
             href="<?= BASE_URL ?>modules/<?= $t['url'] ?>"
             title="<?= $t['label'] ?>">
            <span class="tab-icon">
              <i class="fas <?= $t['icon'] ?>"></i>
            </span>
            <span class="tab-label"><?= $t['label'] ?></span>
            <?php if (!empty($t['badge']) && $t['badge'] > 0): ?>
              <span class="tab-badge"><?= $t['badge'] ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php $group_count++; ?>
      <?php endforeach; ?>

      <!-- Divider Sebelum Master Data -->
      <div class="nav-tab-divider" aria-hidden="true"></div>

      <!-- ─── Menu Master Data Dropdown (Dokter + Gudang Obat) ─── -->
      <div class="nav-tab-dropdown dropdown">
        <button type="button" class="nav-tab color-violet <?= $is_master_active ? 'active' : '' ?>"
                data-dropdown="masterDropdownMenu"
                style="background:none;cursor:pointer;outline:none;"
                title="Master Data Klinik & Gudang">
          <span class="tab-icon" style="background:#f5f3ff;color:#7c3aed;">
            <i class="fas fa-database"></i>
          </span>
          <span class="tab-label">Master Data</span>
          <?php if ($stok_kritis_badge > 0): ?>
            <span class="tab-badge"><?= $stok_kritis_badge ?></span>
          <?php endif; ?>
          <i class="fas fa-chevron-down" style="font-size:9px;color:#64748b;margin-left:2px;"></i>
        </button>

        <div class="dropdown-menu" id="masterDropdownMenu">
          <div style="padding:8px 12px 6px;border-bottom:1px solid #f1f5f9;margin-bottom:4px;">
            <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Master Data Klinik</span>
          </div>

          <!-- 1. Master Dokter & Pegawai -->
          <a href="<?= BASE_URL ?>modules/kepegawaian/index.php" class="bridging-dropdown-item <?= ($active_module==='kepegawaian')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#f5f3ff;color:#7c3aed;">
              <i class="fas fa-user-md"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">Master Dokter</span>
              <span class="bridging-item-desc">Data Tenaga Medis, Dokter & Jadwal Praktek</span>
            </div>
          </a>

          <!-- 2. Tarif Tindakan Rawat Jalan -->
          <a href="<?= BASE_URL ?>modules/tarif_ralan/index.php" class="bridging-dropdown-item <?= ($active_module==='tarif_ralan')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#fdf2f8;color:#db2777;">
              <i class="fas fa-hand-holding-medical"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">Tarif Tindakan Ralan</span>
              <span class="bridging-item-desc">Daftar Prosedur, Jasa Dokter, Perawat & BHP</span>
            </div>
          </a>

          <!-- 3. Master Tarif & Template Lab -->
          <a href="<?= BASE_URL ?>modules/laboratorium/master_lab.php" class="bridging-dropdown-item <?= ($active_module==='laboratorium' && basename($_SERVER['PHP_SELF'])==='master_lab.php')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#f0fdf4;color:#16a34a;">
              <i class="fas fa-flask-vial"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">Master Tarif & Template Lab</span>
              <span class="bridging-item-desc">Paket Tes, Parameter & Nilai Rujukan Lab</span>
            </div>
          </a>

          <!-- 4. Gudang Obat & Logistik -->
          <a href="<?= BASE_URL ?>modules/gudang_obat/index.php" class="bridging-dropdown-item <?= ($active_module==='gudang_obat')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#fffbeb;color:#d97706;">
              <i class="fas fa-boxes-stacked"></i>
            </div>
            <div class="bridging-item-text">
              <div style="display:flex;align-items:center;gap:6px;">
                <span class="bridging-item-title">Gudang Obat</span>
                <?php if ($stok_kritis_badge > 0): ?>
                  <span class="badge badge-danger" style="font-size:9.5px;padding:1px 5px;border-radius:99px;"><?= $stok_kritis_badge ?> Alert</span>
                <?php endif; ?>
              </div>
              <span class="bridging-item-desc">Master Obat, Stok Darurat, Opname, PO & Mutasi</span>
            </div>
          </a>

          <!-- 5. Laporan & Rekapitulasi -->
          <a href="<?= BASE_URL ?>modules/laporan/index.php" class="bridging-dropdown-item <?= ($active_module==='laporan')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#f0f9ff;color:#0284c7;">
              <i class="fas fa-chart-bar"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">Laporan & Statistik</span>
              <span class="bridging-item-desc">Rekap Kunjungan, 10 Besar Penyakit & Kasir</span>
            </div>
          </a>
        </div>
      </div>

      <!-- Divider Sebelum Bridging -->
      <div class="nav-tab-divider" aria-hidden="true"></div>

      <!-- ─── Menu Bridging Dropdown (PCare + Satu Sehat + E-RM BPJS) ─── -->
      <div class="nav-tab-dropdown dropdown">
        <button type="button" class="nav-tab color-cyan <?= $is_bridging_active ? 'active' : '' ?>"
                data-dropdown="bridgingDropdownMenu"
                style="background:none;cursor:pointer;outline:none;"
                title="Integrasi & Bridging Eksternal">
          <span class="tab-icon" style="background:#ecfeff;color:#0891b2;">
            <i class="fas fa-network-wired"></i>
          </span>
          <span class="tab-label">Bridging</span>
          <i class="fas fa-chevron-down" style="font-size:9px;color:#64748b;margin-left:2px;"></i>
        </button>

        <div class="dropdown-menu" id="bridgingDropdownMenu">
          <div style="padding:8px 12px 6px;border-bottom:1px solid #f1f5f9;margin-bottom:4px;">
            <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Integrasi Eksternal</span>
          </div>

          <!-- 0. Mapping Bridging (PCare & Satu Sehat) -->
          <a href="<?= BASE_URL ?>modules/mapping/index.php" class="bridging-dropdown-item <?= ($active_module==='mapping')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#f0fdfa;color:#0d9488;">
              <i class="fas fa-shuffle"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">Mapping Bridging</span>
              <span class="bridging-item-desc">Pemadanan Dokter, Poli, Obat &amp; LOINC (PCare + Satu Sehat)</span>
            </div>
          </a>

          <!-- 1. PCare BPJS -->
          <a href="<?= BASE_URL ?>modules/pcare/index.php" class="bridging-dropdown-item <?= ($active_module==='pcare')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#ecfeff;color:#0891b2;">
              <i class="fas fa-hospital"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">PCare BPJS</span>
              <span class="bridging-item-desc">Verifikasi Peserta & Bridging Kunjungan</span>
            </div>
          </a>

          <!-- 2. Satu Sehat -->
          <a href="<?= BASE_URL ?>modules/satu_sehat/index.php" class="bridging-dropdown-item <?= ($active_module==='satu_sehat')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#fff1f2;color:#be123c;">
              <i class="fas fa-shield-heart"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">Satu Sehat Kemenkes</span>
              <span class="bridging-item-desc">Standarisasi HL7 FHIR R4 Rekam Medis</span>
            </div>
          </a>

          <!-- 3. Antrean Online BPJS (Mobile JKN) -->
          <a href="<?= BASE_URL ?>modules/antrean_bpjs/index.php" class="bridging-dropdown-item <?= ($active_module==='antrean_bpjs')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#ecfdf5;color:#059669;">
              <i class="fas fa-mobile-alt"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">Antrean Mobile JKN</span>
              <span class="bridging-item-desc">Booking Online BPJS & 7 Task Pelayanan</span>
            </div>
          </a>

          <!-- 4. E-RM BPJS -->
          <a href="<?= BASE_URL ?>modules/bpjs_emr/index.php" class="bridging-dropdown-item <?= ($active_module==='bpjs_emr')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#faf5ff;color:#9333ea;">
              <i class="fas fa-laptop-medical"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">E-RM BPJS</span>
              <span class="bridging-item-desc">Kirim Resume Medis Elektronik ke BPJS</span>
            </div>
          </a>
        </div>
      </div>

      <!-- Divider Sebelum Pengaturan -->
      <div class="nav-tab-divider" aria-hidden="true"></div>

      <!-- ─── Menu Pengaturan Dropdown (Profil Identitas Klinik + Konfigurasi Bridging API) ─── -->
      <div class="nav-tab-dropdown dropdown">
        <button type="button" class="nav-tab color-slate <?= ($active_module === 'settings') ? 'active' : '' ?>"
                data-dropdown="settingsDropdownMenu"
                style="background:none;cursor:pointer;outline:none;"
                title="Pengaturan & Konfigurasi Sistem">
          <span class="tab-icon" style="background:#f1f5f9;color:#475569;">
            <i class="fas fa-sliders"></i>
          </span>
          <span class="tab-label">Pengaturan</span>
          <i class="fas fa-chevron-down" style="font-size:9px;color:#64748b;margin-left:2px;"></i>
        </button>

        <div class="dropdown-menu" id="settingsDropdownMenu">
          <div style="padding:8px 12px 6px;border-bottom:1px solid #f1f5f9;margin-bottom:4px;">
            <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Pengaturan Sistem</span>
          </div>

          <!-- 1. Profil & Identitas Klinik -->
          <a href="<?= BASE_URL ?>modules/settings/identitas.php" class="bridging-dropdown-item <?= ($active_module==='settings' && ($sub_setting ?? '')==='identitas')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#eff6ff;color:#2563eb;">
              <i class="fas fa-hospital"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">Identitas Klinik</span>
              <span class="bridging-item-desc">Nama Instansi, Alamat, Kontak & Logo</span>
            </div>
          </a>

          <!-- 2. Konfigurasi Bridging API -->
          <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="bridging-dropdown-item <?= ($active_module==='settings' && ($sub_setting ?? '')==='bridging')?'active':'' ?>">
            <div class="bridging-item-icon" style="background:#ecfeff;color:#0891b2;">
              <i class="fas fa-network-wired"></i>
            </div>
            <div class="bridging-item-text">
              <span class="bridging-item-title">Konfigurasi Bridging</span>
              <span class="bridging-item-desc">API PCare, Satu Sehat & E-RM BPJS</span>
            </div>
          </a>
        </div>
      </div>

    </div>
  </nav>

</header>
