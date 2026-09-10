<?php
/**
 * SIMKlinik — Pengaturan Konfigurasi Bridging API (PCare, Satu Sehat & BPJS EMR)
 */

$page_title    = 'Konfigurasi Bridging API';
$active_module = 'settings';
$sub_setting   = 'bridging';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ─── Simpan Pengaturan Integrasi ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_integrasi'])) {
    $settings = $_POST['settings'] ?? [];
    foreach ($settings as $module => $fields) {
        $mod_esc = $conn->real_escape_string($module);
        foreach ($fields as $field => $val) {
            $fld_esc = $conn->real_escape_string($field);
            $val_esc = $conn->real_escape_string(trim($val));

            $conn->query("
                INSERT INTO mlite_settings (module, field, value)
                VALUES ('$mod_esc', '$fld_esc', '$val_esc')
                ON DUPLICATE KEY UPDATE value = '$val_esc'
            ");
        }
    }
    set_flash('success', 'Konfigurasi integrasi API berhasil disimpan.');
    redirect(BASE_URL . 'modules/settings/bridging.php');
}

// ─── Load Existing Settings ──────────────────────────────────
$cfg_res = $conn->query("SELECT module, field, value FROM mlite_settings");
$cfg = [];
if ($cfg_res) {
    while ($row = $cfg_res->fetch_assoc()) {
        $cfg[$row['module']][$row['field']] = $row['value'];
    }
}

function get_cfg($mod, $fld, $default = '') {
    global $cfg;
    return $cfg[$mod][$fld] ?? $default;
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
  <div>
    <h1 class="page-title">Pengaturan Konfigurasi Bridging API</h1>
    <p class="page-subtitle">Konfigurasi kredensial API BPJS Kesehatan (PCare & E-RM) dan Kemenkes (Satu Sehat FHIR R4)</p>
  </div>
  <div class="page-actions" style="display:flex;align-items:center;gap:8px;">
    <button type="button" class="btn btn-primary" onclick="openBridgingMonitorModal()">
      <i class="fas fa-tower-broadcast"></i> Buka Monitor Realtime
    </button>
    <a href="<?= BASE_URL ?>modules/settings/identitas.php" class="btn btn-secondary">
      <i class="fas fa-hospital"></i> Profil & Identitas
    </a>
  </div>
</div>

<!-- ─── Sub-Nav Tab Pengaturan ───────────────────────────── -->
<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:6px 12px;margin-bottom:20px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
  <a href="<?= BASE_URL ?>modules/settings/identitas.php" class="btn btn-sm btn-secondary" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;padding:6px 14px;">
    <i class="fas fa-hospital"></i> 1. Profil & Identitas Klinik
  </a>
  <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="btn btn-sm btn-primary" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;padding:6px 14px;">
    <i class="fas fa-network-wired"></i> 2. Konfigurasi Bridging API
  </a>
  <a href="<?= BASE_URL ?>modules/settings/bridging_monitor.php" class="btn btn-sm btn-secondary" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;padding:6px 14px;">
    <i class="fas fa-tower-broadcast" style="color:#10b981;"></i> 3. Live Monitor Network
  </a>
</div>


<form method="POST" action="">
  <input type="hidden" name="simpan_integrasi" value="1">

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

    <!-- ─── Kolom Kiri: PCare BPJS & BPJS E-RM ─────────────── -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- PCare BPJS Card -->
      <div class="card" style="border-top:4px solid var(--primary-600);">
        <div class="card-header">
          <div class="card-title">
            <i class="fas fa-hospital text-primary"></i> 1. Bridging PCare BPJS Kesehatan
          </div>
          <span class="integration-badge <?= get_cfg('icare','consid') ? 'connected':'disconnected' ?>">
            <span class="dot"></span><?= get_cfg('icare','consid') ? 'Terkonfigurasi':'Belum Lengkap' ?>
          </span>
        </div>
        <div class="card-body" style="padding:18px 20px;">
          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">Consumer ID (ConsID)</label>
              <input type="text" name="settings[icare][consid]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('icare','consid')) ?>" placeholder="Cons ID PCare">
            </div>
            <div class="form-group">
              <label class="form-label">Secret Key</label>
              <input type="password" name="settings[icare][secretkey]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('icare','secretkey')) ?>" placeholder="Secret Key">
            </div>
          </div>

          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">User Key</label>
              <input type="text" name="settings[icare][userkey]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('icare','userkey')) ?>" placeholder="User Key PCare">
            </div>
            <div class="form-group">
              <label class="form-label">Username PCare / ICare</label>
              <input type="text" name="settings[icare][usernameICare]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('icare','usernameICare')) ?>" placeholder="Username bridging">
            </div>
          </div>

          <div class="form-row col-3 mb-14">
            <div class="form-group">
              <label class="form-label">Password PCare</label>
              <input type="password" name="settings[icare][passwordICare]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('icare','passwordICare')) ?>" placeholder="Password PCare">
            </div>
            <div class="form-group">
              <label class="form-label">Kode Aplikasi (KdApp)</label>
              <input type="text" name="settings[icare][kd_aplikasi]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('icare','kd_aplikasi', '095')) ?>" placeholder="Default: 095">
            </div>
            <div class="form-group">
              <label class="form-label">Kode Faskes / PPK</label>
              <input type="text" name="settings[icare][kode_faskes]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('icare','kode_faskes')) ?>" placeholder="Contoh: 0115B001">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">URL Endpoint PCare</label>
            <input type="text" name="settings[icare][urlPCare]" class="form-control"
                   value="<?= htmlspecialchars(get_cfg('icare','urlPCare', 'https://apijkn.bpjs-kesehatan.go.id/pcare-rest')) ?>">
          </div>
        </div>
      </div>

      <!-- BPJS E-RM Card -->
      <div class="card" style="border-top:4px solid #9333ea;">
        <div class="card-header">
          <div class="card-title">
            <i class="fas fa-laptop-medical" style="color:#9333ea;"></i> 3. E-RM BPJS Kesehatan
          </div>
          <span class="integration-badge <?= get_cfg('bpjs_emr','consid') ? 'connected':'disconnected' ?>">
            <span class="dot"></span><?= get_cfg('bpjs_emr','consid') ? 'Terkonfigurasi':'Belum Lengkap' ?>
          </span>
        </div>
        <div class="card-body" style="padding:18px 20px;">
          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">ConsID E-RM</label>
              <input type="text" name="settings[bpjs_emr][consid]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('bpjs_emr','consid')) ?>" placeholder="Cons ID">
            </div>
            <div class="form-group">
              <label class="form-label">Secret Key E-RM</label>
              <input type="password" name="settings[bpjs_emr][secretkey]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('bpjs_emr','secretkey')) ?>" placeholder="Secret Key">
            </div>
          </div>

          <div class="form-group mb-14">
            <label class="form-label">User Key E-RM</label>
            <input type="text" name="settings[bpjs_emr][userkey]" class="form-control"
                   value="<?= htmlspecialchars(get_cfg('bpjs_emr','userkey')) ?>" placeholder="User Key BPJS E-RM">
          </div>

          <div class="form-group">
            <label class="form-label">Base URL E-RM</label>
            <input type="text" name="settings[bpjs_emr][baseurl]" class="form-control"
                   value="<?= htmlspecialchars(get_cfg('bpjs_emr','baseurl', 'https://apijkn-dev.bpjs-kesehatan.go.id/erekammedis_dev/')) ?>">
          </div>
        </div>
      </div>

    </div>

    <!-- ─── Kolom Kanan: Satu Sehat Kemenkes & Action Button ─ -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Satu Sehat Kemenkes Card -->
      <div class="card" style="border-top:4px solid #0891b2;">
        <div class="card-header">
          <div class="card-title">
            <i class="fas fa-shield-heart" style="color:#0891b2;"></i> 2. Integrasi Satu Sehat (Kemenkes)
          </div>
          <span class="integration-badge <?= get_cfg('satu_sehat','organizationid') ? 'connected':'disconnected' ?>">
            <span class="dot"></span><?= get_cfg('satu_sehat','organizationid') ? 'Terkonfigurasi':'Belum Lengkap' ?>
          </span>
        </div>
        <div class="card-body" style="padding:18px 20px;">
          <div class="form-group mb-14">
            <label class="form-label">Organization ID (Kemenkes)</label>
            <input type="text" name="settings[satu_sehat][organizationid]" class="form-control"
                   value="<?= htmlspecialchars(get_cfg('satu_sehat','organizationid')) ?>" placeholder="cth: 10000004">
          </div>

          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">Client ID</label>
              <input type="text" name="settings[satu_sehat][clientid]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('satu_sehat','clientid')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Client Secret</label>
              <input type="password" name="settings[satu_sehat][secretkey]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('satu_sehat','secretkey')) ?>">
            </div>
          </div>

          <div class="form-group mb-14">
            <label class="form-label">OAuth Auth URL</label>
            <input type="text" name="settings[satu_sehat][authurl]" class="form-control"
                   value="<?= htmlspecialchars(get_cfg('satu_sehat','authurl', 'https://api-satusehat-dev.dto.kemkes.go.id/oauth2/v1')) ?>">
          </div>

          <div class="form-group">
            <label class="form-label">FHIR R4 Base URL (Kemenkes)</label>
            <input type="text" name="settings[satu_sehat][fhirurl]" class="form-control"
                   value="<?= htmlspecialchars(get_cfg('satu_sehat','fhirurl', 'https://api-satusehat-dev.dto.kemkes.go.id/fhir-r4/v1')) ?>">
          </div>
        </div>
      </div>

      <!-- Antrean Online BPJS (Mobile JKN) Card -->
      <div class="card" style="border-top:4px solid #10b981;">
        <div class="card-header">
          <div class="card-title">
            <i class="fas fa-mobile-alt" style="color:#10b981;"></i> 4. Antrean Online BPJS (Mobile JKN)
          </div>
          <span class="integration-badge <?= get_cfg('antrean_bpjs','consid') ? 'connected':'disconnected' ?>">
            <span class="dot"></span><?= get_cfg('antrean_bpjs','consid') ? 'Terkonfigurasi':'Belum Lengkap' ?>
          </span>
        </div>
        <div class="card-body" style="padding:18px 20px;">
          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">ConsID Antrean</label>
              <input type="text" name="settings[antrean_bpjs][consid]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('antrean_bpjs','consid', get_cfg('icare','consid'))) ?>" placeholder="Cons ID BPJS">
            </div>
            <div class="form-group">
              <label class="form-label">Secret Key Antrean</label>
              <input type="password" name="settings[antrean_bpjs][secretkey]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('antrean_bpjs','secretkey', get_cfg('icare','secretkey'))) ?>" placeholder="Secret Key">
            </div>
          </div>

          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">User Key Antrean</label>
              <input type="text" name="settings[antrean_bpjs][userkey]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('antrean_bpjs','userkey', get_cfg('icare','userkey'))) ?>" placeholder="User Key Antrean">
            </div>
            <div class="form-group">
              <label class="form-label">Kode PPK / Faskes</label>
              <input type="text" name="settings[antrean_bpjs][kode_ppk]" class="form-control"
                     value="<?= htmlspecialchars(get_cfg('antrean_bpjs','kode_ppk', get_cfg('icare','kode_faskes', '0115B001'))) ?>" placeholder="cth: 0115B001">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Base URL Antrean BPJS</label>
            <input type="text" name="settings[antrean_bpjs][api_url]" class="form-control"
                   value="<?= htmlspecialchars(get_cfg('antrean_bpjs','api_url', 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanrs_dev')) ?>">
          </div>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;">
        <button type="submit" class="btn btn-primary" style="padding:10px 24px;font-size:13px;font-weight:700;">
          <i class="fas fa-save"></i> Simpan Konfigurasi Bridging API
        </button>
      </div>

    </div>

  </div>
</form>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
