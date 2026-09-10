<?php
/**
 * SIMKlinik — Form Edit Pendaftaran Kunjungan
 */

$page_title    = 'Edit Pendaftaran';
$active_module = 'pendaftaran';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$errors = [];
$no_rawat = sanitize($_GET['no_rawat'] ?? '');

if (empty($no_rawat)) {
    set_flash('danger', 'Nomor Rawat tidak valid.');
    redirect(BASE_URL . 'modules/pendaftaran/index.php');
}

$rawat_esc = $conn->real_escape_string($no_rawat);

// ─── Load Data Pendaftaran Saat Ini ───────────────────────────
$res = $conn->query("
    SELECT r.*, p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_tlp, p.alamat,
           d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
    WHERE r.no_rawat = '$rawat_esc'
    LIMIT 1
");

if (!$res || $res->num_rows === 0) {
    set_flash('danger', 'Data pendaftaran tidak ditemukan.');
    redirect(BASE_URL . 'modules/pendaftaran/index.php');
}

$reg = $res->fetch_assoc();

// ─── Master Data ──────────────────────────────────────────────
$poli_list = [];
$rp = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli");
if ($rp) while ($row = $rp->fetch_assoc()) $poli_list[] = $row;

$penjab_list = [];
$rj = $conn->query("SELECT kd_pj, png_jawab as nm_penjab FROM penjab WHERE status = '1' ORDER BY png_jawab");
if ($rj) while ($row = $rj->fetch_assoc()) $penjab_list[] = $row;

// Dokter aktif berdasarkan poli yang dipilih
$dokter_list = [];
$curr_poli = $reg['kd_poli'];
$rd = $conn->query("
    SELECT d.kd_dokter, d.nm_dokter, sp.nm_sps as nm_spesialis
    FROM jadwal j
    JOIN dokter d ON j.kd_dokter = d.kd_dokter
    LEFT JOIN spesialis sp ON d.kd_sps = sp.kd_sps
    WHERE j.kd_poli = '$curr_poli' AND d.status = '1'
    GROUP BY d.kd_dokter
    ORDER BY d.nm_dokter
");
if ($rd && $rd->num_rows > 0) {
    while ($row = $rd->fetch_assoc()) $dokter_list[] = $row;
} else {
    // Fallback: semua dokter aktif
    $rd2 = $conn->query("SELECT kd_dokter, nm_dokter, '' as nm_spesialis FROM dokter WHERE status = '1' ORDER BY nm_dokter");
    if ($rd2) while ($row = $rd2->fetch_assoc()) $dokter_list[] = $row;
}

// ─── Proses Simpan Perubahan ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kd_poli    = $conn->real_escape_string(trim($_POST['kd_poli'] ?? ''));
    $kd_dok     = $conn->real_escape_string(trim($_POST['kd_dokter'] ?? ''));
    $kd_pj      = $conn->real_escape_string(trim($_POST['kd_pj'] ?? ''));
    $no_pes     = $conn->real_escape_string(trim($_POST['no_peserta'] ?? ''));
    $no_reg     = $conn->real_escape_string(trim($_POST['no_reg'] ?? ''));
    $jam_reg    = $conn->real_escape_string(trim($_POST['jam_reg'] ?? ''));
    $stts       = $conn->real_escape_string(trim($_POST['stts'] ?? 'Belum'));
    $p_jawab    = $conn->real_escape_string(trim($_POST['p_jawab'] ?? ''));
    $hubunganpj = $conn->real_escape_string(trim($_POST['hubunganpj'] ?? ''));
    $almt_pj    = $conn->real_escape_string(trim($_POST['almt_pj'] ?? ''));

    if (empty($kd_poli)) $errors['kd_poli']   = 'Pilih poliklinik.';
    if (empty($kd_dok))  $errors['kd_dokter']  = 'Pilih dokter.';
    if (empty($no_reg))  $errors['no_reg']     = 'Nomor antrian tidak boleh kosong.';

    if (empty($errors)) {
        // Update reg_periksa
        $sql = "UPDATE reg_periksa SET
                    kd_poli     = '$kd_poli',
                    kd_dokter   = '$kd_dok',
                    kd_pj       = '$kd_pj',
                    no_reg      = '$no_reg',
                    jam_reg     = '$jam_reg',
                    stts        = '$stts',
                    p_jawab     = '$p_jawab',
                    hubunganpj  = '$hubunganpj',
                    almt_pj     = '$almt_pj'
                WHERE no_rawat = '$rawat_esc'";

        if ($conn->query($sql)) {
            // Update no_peserta & kd_pj di master pasien
            $no_rm = $reg['no_rkm_medis'];
            $conn->query("UPDATE pasien SET no_peserta = '$no_pes', kd_pj = '$kd_pj' WHERE no_rkm_medis = '$no_rm'");

            set_flash('success', "Pendaftaran pasien <strong>{$reg['nm_pasien']}</strong> (No. Rawat: $no_rawat) berhasil diperbarui.");
            redirect(BASE_URL . 'modules/pendaftaran/index.php');
        } else {
            $errors['db'] = 'Gagal menyimpan perubahan: ' . $conn->error;
        }
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Breadcrumb ───────────────────────────────────────── -->
<div class="breadcrumb" style="margin-bottom:12px;">
  <a href="<?= BASE_URL ?>modules/pendaftaran/index.php">Pendaftaran</a>
  <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
  <span class="breadcrumb-current">Edit Pendaftaran</span>
</div>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
  <div>
    <h1 class="page-title" style="font-size:18px;margin-bottom:2px;">Edit Data Pendaftaran Kunjungan</h1>
    <p class="page-subtitle" style="font-size:12px;margin:0;">Ubah tujuan poliklinik, dokter pemeriksa, penjamin, nomor antrian, atau status kunjungan</p>
  </div>
  <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="btn btn-secondary" style="padding:6px 14px;font-size:12.5px;">
    <i class="fas fa-arrow-left"></i> Kembali ke Antrian
  </a>
</div>

<?php if (!empty($errors['db'])): ?>
  <div class="alert alert-danger" style="margin-bottom:14px;">
    <i class="fas fa-exclamation-circle"></i>
    <span><?= $errors['db'] ?></span>
  </div>
<?php endif; ?>

<form method="POST" action="">
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;align-items:start;">

    <!-- ─── Kolom Kiri: Form Perubahan Data Kunjungan ──────── -->
    <div style="display:flex;flex-direction:column;gap:14px;">

      <div class="card" style="border-top:4px solid var(--primary-600);margin-bottom:0;">
        <div class="card-header" style="padding:12px 18px;">
          <div class="card-title" style="font-size:13.5px;"><i class="fas fa-calendar-check text-primary"></i> Data Pelayanan & Dokter</div>
        </div>
        <div class="card-body" style="padding:16px 18px;">
          
          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">Poliklinik Tujuan <span style="color:#ef4444;">*</span></label>
              <select name="kd_poli" id="selectPoli" class="form-control" required>
                <?php foreach ($poli_list as $pl): ?>
                  <option value="<?= $pl['kd_poli'] ?>" <?= $reg['kd_poli']===$pl['kd_poli'] ? 'selected':'' ?>>
                    <?= htmlspecialchars($pl['nm_poli']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Dokter Pemeriksa <span style="color:#ef4444;">*</span></label>
              <select name="kd_dokter" id="selectDokter" class="form-control" required>
                <?php foreach ($dokter_list as $d): ?>
                  <option value="<?= $d['kd_dokter'] ?>" <?= $reg['kd_dokter']===$d['kd_dokter'] ? 'selected':'' ?>>
                    <?= htmlspecialchars($d['nm_dokter']) ?><?= !empty($d['nm_spesialis']) ? ' ('.$d['nm_spesialis'].')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">Penjamin / Cara Bayar <span style="color:#ef4444;">*</span></label>
              <select name="kd_pj" id="selectPj" class="form-control" required>
                <?php foreach ($penjab_list as $pj): ?>
                  <option value="<?= $pj['kd_pj'] ?>" <?= $reg['kd_pj']===$pj['kd_pj'] ? 'selected':'' ?>>
                    <?= htmlspecialchars($pj['nm_penjab']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">No. Kartu Peserta (BPJS / Asuransi)</label>
              <input type="text" name="no_peserta" class="form-control"
                     value="<?= htmlspecialchars($reg['no_peserta'] ?? '') ?>" placeholder="000123456789">
            </div>
          </div>

          <div class="form-row col-3 mb-14">
            <div class="form-group">
              <label class="form-label">No. Urut Antrian <span style="color:#ef4444;">*</span></label>
              <input type="text" name="no_reg" class="form-control" required
                     value="<?= htmlspecialchars($reg['no_reg']) ?>" style="font-weight:700;color:var(--primary-700);">
            </div>

            <div class="form-group">
              <label class="form-label">Jam Registrasi</label>
              <input type="text" name="jam_reg" class="form-control" required
                     value="<?= htmlspecialchars($reg['jam_reg']) ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Status Kunjungan</label>
              <select name="stts" class="form-control" required>
                <option value="Belum"  <?= $reg['stts']==='Belum' ? 'selected':'' ?>>Menunggu</option>
                <option value="Sudah"  <?= $reg['stts']==='Sudah' ? 'selected':'' ?>>Sudah Diperiksa</option>
                <option value="Batal"  <?= $reg['stts']==='Batal' ? 'selected':'' ?>>Batal Kunjungan</option>
                <option value="Dirawat" <?= $reg['stts']==='Dirawat' ? 'selected':'' ?>>Dirawat</option>
                <option value="Dirujuk" <?= $reg['stts']==='Dirujuk' ? 'selected':'' ?>>Dirujuk</option>
              </select>
            </div>
          </div>

        </div>
      </div>

      <!-- Card Penanggung Jawab -->
      <div class="card" style="margin-bottom:0;">
        <div class="card-header" style="padding:12px 18px;">
          <div class="card-title" style="font-size:13.5px;"><i class="fas fa-user-shield text-primary"></i> Data Penanggung Jawab Pasien</div>
        </div>
        <div class="card-body" style="padding:16px 18px;">
          <div class="form-row col-2 mb-14">
            <div class="form-group">
              <label class="form-label">Nama Penanggung Jawab</label>
              <input type="text" name="p_jawab" class="form-control"
                     value="<?= htmlspecialchars($reg['p_jawab'] ?? '') ?>" placeholder="Nama PJ">
            </div>
            <div class="form-group">
              <label class="form-label">Hubungan dengan Pasien</label>
              <select name="hubunganpj" class="form-control">
                <option value="DIRI SENDIRI" <?= ($reg['hubunganpj']??'')==='DIRI SENDIRI'?'selected':'' ?>>DIRI SENDIRI</option>
                <option value="SUAMI" <?= ($reg['hubunganpj']??'')==='SUAMI'?'selected':'' ?>>SUAMI</option>
                <option value="ISTRI" <?= ($reg['hubunganpj']??'')==='ISTRI'?'selected':'' ?>>ISTRI</option>
                <option value="ORANG TUA" <?= ($reg['hubunganpj']??'')==='ORANG TUA'?'selected':'' ?>>ORANG TUA</option>
                <option value="ANAK" <?= ($reg['hubunganpj']??'')==='ANAK'?'selected':'' ?>>ANAK</option>
                <option value="SAUDARA" <?= ($reg['hubunganpj']??'')==='SAUDARA'?'selected':'' ?>>SAUDARA</option>
                <option value="LAIN-LAIN" <?= ($reg['hubunganpj']??'')==='LAIN-LAIN'?'selected':'' ?>>LAIN-LAIN</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Alamat Penanggung Jawab</label>
            <textarea name="almt_pj" class="form-control" rows="2"><?= htmlspecialchars($reg['almt_pj'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:6px;">
        <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="btn btn-secondary">Batal</a>
        <button type="submit" class="btn btn-primary" style="padding:8px 20px;font-size:13px;font-weight:700;">
          <i class="fas fa-save"></i> Simpan Perubahan
        </button>
      </div>

    </div>

    <!-- ─── Kolom Kanan: Ringkasan Pasien & No. Rawat ──────── -->
    <div style="display:flex;flex-direction:column;gap:14px;">

      <div class="card" style="margin-bottom:0;background:#f8fafc;">
        <div class="card-header" style="padding:12px 16px;">
          <div class="card-title" style="font-size:13px;"><i class="fas fa-id-card text-primary"></i> Identitas Pasien</div>
        </div>
        <div class="card-body" style="padding:16px;">
          
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
            <div style="width:42px;height:42px;border-radius:50%;background:<?= $reg['jk']==='L' ? 'var(--primary-50)' : '#fdf2f8' ?>;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:<?= $reg['jk']==='L' ? 'var(--primary-600)' : '#9d174d' ?>;flex-shrink:0;">
              <?= strtoupper(substr($reg['nm_pasien'], 0, 1)) ?>
            </div>
            <div>
              <div style="font-size:14px;font-weight:700;color:#0f172a;"><?= htmlspecialchars($reg['nm_pasien']) ?></div>
              <div style="font-size:11.5px;color:#64748b;">
                No. RM: <strong style="color:var(--primary-600);"><?= $reg['no_rkm_medis'] ?></strong>
              </div>
            </div>
          </div>

          <div style="border-top:1px solid #e2e8f0;padding-top:10px;display:flex;flex-direction:column;gap:8px;font-size:12px;">
            <div style="display:flex;justify-content:space-between;">
              <span style="color:#64748b;">No. Rawat:</span>
              <code style="font-weight:700;color:#0f172a;"><?= $reg['no_rawat'] ?></code>
            </div>
            <div style="display:flex;justify-content:space-between;">
              <span style="color:#64748b;">Tgl. Registrasi:</span>
              <strong style="color:#0f172a;"><?= tgl_indo($reg['tgl_registrasi']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;">
              <span style="color:#64748b;">Jenis Kelamin / Umur:</span>
              <span style="color:#0f172a;"><?= icon_jk($reg['jk']) ?> <?= hitung_umur($reg['tgl_lahir']) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;">
              <span style="color:#64748b;">NIK KTP:</span>
              <span style="color:#0f172a;"><?= htmlspecialchars($reg['no_ktp'] ?: '-') ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;">
              <span style="color:#64748b;">No. Telepon / WA:</span>
              <span style="color:#0f172a;"><?= htmlspecialchars($reg['no_tlp'] ?: '-') ?></span>
            </div>
          </div>

          <div style="margin-top:14px;border-top:1px solid #e2e8f0;padding-top:10px;">
            <a href="<?= BASE_URL ?>modules/pasien/detail.php?rm=<?= urlencode($reg['no_rkm_medis']) ?>" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;">
              <i class="fas fa-user"></i> Buka Rekam Medis Pasien
            </a>
          </div>

        </div>
      </div>

    </div>

  </div>
</form>

<script>
// ─── Dynamic Load Dokter saat Ganti Poli ───────────────────────
document.getElementById('selectPoli')?.addEventListener('change', function() {
  const kd_poli      = this.value;
  const selectDokter = document.getElementById('selectDokter');

  selectDokter.innerHTML = '<option value="">Memuat...</option>';
  selectDokter.disabled  = true;

  if (!kd_poli) {
    selectDokter.innerHTML = '<option value="">— Pilih Poliklinik Dulu —</option>';
    return;
  }

  fetch(`<?= BASE_URL ?>modules/pasien/ajax.php?action=dokter&kd_poli=${encodeURIComponent(kd_poli)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    selectDokter.disabled = false;
    if (data.data.length === 0) {
      selectDokter.innerHTML = '<option value="">Tidak ada dokter tersedia</option>';
    } else {
      selectDokter.innerHTML = data.data.map(d => `<option value="${d.kd_dokter}">${d.nm_dokter}${d.nm_spesialis?' ('+d.nm_spesialis+')':''}</option>`).join('');
    }
  });
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
