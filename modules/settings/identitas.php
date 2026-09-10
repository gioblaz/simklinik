<?php
/**
 * SIMKlinik — Pengaturan Profil & Identitas Klinik
 */

$page_title    = 'Profil & Identitas Klinik';
$active_module = 'settings';
$sub_setting   = 'identitas';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ─── Proses Simpan Identitas Klinik ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_identitas'])) {
    $fields = [
        'nama_instansi' => sanitize($_POST['nama_instansi'] ?? ''),
        'alamat'        => sanitize($_POST['alamat'] ?? ''),
        'kota'          => sanitize($_POST['kota'] ?? ''),
        'propinsi'      => sanitize($_POST['propinsi'] ?? ''),
        'nomor_telepon' => sanitize($_POST['nomor_telepon'] ?? ''),
        'email'         => sanitize($_POST['email'] ?? ''),
        'website'       => sanitize($_POST['website'] ?? ''),
        'footer'        => sanitize($_POST['footer'] ?? ''),
    ];

    foreach ($fields as $key => $val) {
        $val_esc = $conn->real_escape_string($val);
        $conn->query("
            INSERT INTO mlite_settings (module, field, value)
            VALUES ('settings', '$key', '$val_esc')
            ON DUPLICATE KEY UPDATE value = '$val_esc'
        ");
    }

    // Handle Upload Logo jika ada
    if (!empty($_FILES['logo_file']['name'])) {
        $file     = $_FILES['logo_file'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

        if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
            $upload_dir = BASE_PATH . 'uploads/settings/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $new_name   = 'logo_' . time() . '.' . $ext;
            $dest_path  = $upload_dir . $new_name;
            $rel_path   = 'uploads/settings/' . $new_name;

            if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                $conn->query("
                    INSERT INTO mlite_settings (module, field, value)
                    VALUES ('settings', 'logo', '$rel_path')
                    ON DUPLICATE KEY UPDATE value = '$rel_path'
                ");
            }
        }
    }

    set_flash('success', 'Profil dan identitas klinik berhasil diperbarui.');
    redirect(BASE_URL . 'modules/settings/identitas.php');
}

// ─── Load Existing Settings ──────────────────────────────────
$cfg_res = $conn->query("SELECT field, value FROM mlite_settings WHERE module = 'settings'");
$setting = [];
if ($cfg_res) {
    while ($row = $cfg_res->fetch_assoc()) {
        $setting[$row['field']] = $row['value'];
    }
}

function get_s($field, $default = '') {
    global $setting;
    return $setting[$field] ?? $default;
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
  <div>
    <h1 class="page-title">Pengaturan Profil & Identitas Klinik</h1>
    <p class="page-subtitle">Kelola nama klinik, alamat, kontak, logo instansi, dan informasi legalitas</p>
  </div>
  <div class="page-actions" style="display:flex;align-items:center;gap:8px;">
    <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="btn btn-secondary">
      <i class="fas fa-network-wired"></i> Konfigurasi Bridging API
    </a>
  </div>
</div>

<!-- ─── Sub-Nav Tab Pengaturan ───────────────────────────── -->
<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:6px 12px;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
  <a href="<?= BASE_URL ?>modules/settings/identitas.php" class="btn btn-sm btn-primary" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;padding:6px 14px;">
    <i class="fas fa-hospital"></i> 1. Profil & Identitas Klinik
  </a>
  <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="btn btn-sm btn-secondary" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;padding:6px 14px;">
    <i class="fas fa-network-wired"></i> 2. Konfigurasi Bridging API
  </a>
</div>

<form method="POST" action="" enctype="multipart/form-data">
  <input type="hidden" name="simpan_identitas" value="1">

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start;">

    <!-- ─── Kolom Kiri: Form Identitas & Kontak ────────────── -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      
      <div class="card" style="border-top:4px solid var(--primary-600);">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-building text-primary"></i> Data Utama Fasilitas Kesehatan</div>
        </div>
        <div class="card-body" style="padding:18px 20px;">
          
          <div class="form-group mb-14">
            <label class="form-label">Nama Fasilitas Kesehatan / Klinik <span style="color:#ef4444;">*</span></label>
            <input type="text" name="nama_instansi" class="form-control" required
                   value="<?= htmlspecialchars(get_s('nama_instansi', INSTANSI_NAMA)) ?>"
                   placeholder="Contoh: Klinik Pratama Sehat Mandiri">
          </div>

          <div class="form-group mb-14">
            <label class="form-label">Alamat Lengkap <span style="color:#ef4444;">*</span></label>
            <textarea name="alamat" class="form-control" rows="2" required placeholder="Jl. Raya Utama No. 123"><?= htmlspecialchars(get_s('alamat', INSTANSI_ALAMAT)) ?></textarea>
          </div>

          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">Kota / Kabupaten</label>
              <input type="text" name="kota" class="form-control"
                     value="<?= htmlspecialchars(get_s('kota', INSTANSI_KOTA)) ?>" placeholder="Contoh: Barabai">
            </div>
            <div class="form-group">
              <label class="form-label">Provinsi</label>
              <input type="text" name="propinsi" class="form-control"
                     value="<?= htmlspecialchars(get_s('propinsi', INSTANSI_PROVINSI)) ?>" placeholder="Contoh: Kalimantan Selatan">
            </div>
          </div>

          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">Nomor Telepon / WhatsApp</label>
              <input type="text" name="nomor_telepon" class="form-control"
                     value="<?= htmlspecialchars(get_s('nomor_telepon', INSTANSI_TELP)) ?>" placeholder="08123456789">
            </div>
            <div class="form-group">
              <label class="form-label">Email Resmi</label>
              <input type="email" name="email" class="form-control"
                     value="<?= htmlspecialchars(get_s('email', 'info@klinik.id')) ?>" placeholder="kontak@klinik.id">
            </div>
          </div>

          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">Website Resmi</label>
              <input type="text" name="website" class="form-control"
                     value="<?= htmlspecialchars(get_s('website', 'https://klinik.id')) ?>" placeholder="https://klinik.id">
            </div>
            <div class="form-group">
              <label class="form-label">Teks Footer / Hak Cipta</label>
              <input type="text" name="footer" class="form-control"
                     value="<?= htmlspecialchars(get_s('footer', 'SIMKlinik — Sistem Informasi Manajemen Klinik')) ?>">
            </div>
          </div>

        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;">
        <button type="submit" class="btn btn-primary" style="padding:10px 24px;font-size:13px;font-weight:700;">
          <i class="fas fa-save"></i> Simpan Perubahan Identitas
        </button>
      </div>

    </div>

    <!-- ─── Kolom Kanan: Logo & Preview ────────────────────── -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      
      <!-- Upload Logo Card -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-image text-primary"></i> Logo Klinik</div>
        </div>
        <div class="card-body" style="padding:18px 20px;text-align:center;">
          
          <?php
            $current_logo = get_s('logo', '');
            $logo_url = (!empty($current_logo) && file_exists(BASE_PATH . $current_logo))
              ? BASE_URL . $current_logo
              : BASE_URL . 'assets/img/logo-default.png';
          ?>

          <div style="width:140px;height:140px;border-radius:14px;border:2px dashed #cbd5e1;background:#f8fafc;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:10px;">
            <img src="<?= $logo_url ?>" alt="Logo Instansi" id="logoPreview" style="max-width:100%;max-height:100%;object-fit:contain;"
                 onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'60\' height=\'60\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2394a3b8\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\' ry=\'2\'/><circle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'/><polyline points=\'21 15 16 10 5 21\'/></svg>'">
          </div>

          <label class="form-label" style="font-size:12px;color:#64748b;margin-bottom:8px;display:block;">
            Format: PNG, JPG, WEBP, atau SVG (Maksimal 2 MB)
          </label>

          <input type="file" name="logo_file" id="logoInput" class="form-control" accept="image/*" onchange="previewFileLogo(event)" style="font-size:12px;">
        </div>
      </div>

      <!-- Live Info Box -->
      <div class="card" style="background:#f8fafc;">
        <div class="card-body" style="padding:16px 18px;font-size:12px;color:#475569;">
          <strong style="color:#0f172a;display:block;margin-bottom:6px;"><i class="fas fa-circle-info text-primary"></i> Penggunaan Data:</strong>
          Nama instansi, alamat, dan logo ini otomatis digunakan pada:
          <ul style="margin:6px 0 0 16px;padding:0;">
            <li>Header Resume Rekam Medis & EMR</li>
            <li>Kwitansi & Nota Pembayaran Kasir</li>
            <li>Surat Pesanan (SP) Gudang Obat</li>
            <li>Etiket Obat Farmasi & Surat Rujukan</li>
          </ul>
        </div>
      </div>

    </div>

  </div>
</form>

<script>
function previewFileLogo(e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(event) {
      document.getElementById('logoPreview').src = event.target.result;
    }
    reader.readAsDataURL(file);
  }
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
