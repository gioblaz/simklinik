<?php
/**
 * SIMKlinik — Registrasi Pasien Baru
 */

$page_title    = 'Tambah Pasien Baru';
$active_module = 'pasien';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$errors = [];
$data   = [];

// ─── Load Master Data ────────────────────────────────────────
$penjab_list = [];
$r = $conn->query("SELECT kd_pj, png_jawab as nm_penjab FROM penjab WHERE status = '1' ORDER BY png_jawab");
if ($r) while ($row = $r->fetch_assoc()) $penjab_list[] = $row;

$propinsi_list = [];
$r = $conn->query("SELECT kd_prop, nm_prop FROM propinsi ORDER BY nm_prop");
if ($r) while ($row = $r->fetch_assoc()) $propinsi_list[] = $row;

// ─── Proses Submit ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi wajib
    $required = ['nm_pasien', 'jk', 'tgl_lahir', 'kd_pj'];
    foreach ($required as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $errors[$field] = 'Field ini wajib diisi.';
        }
    }

    if (empty($errors)) {
        // Generate No. RM
        $no_rm = generate_no_rm();

        // Sanitasi semua input
        $f = [];
        $fields = ['nm_pasien','no_ktp','jk','tmp_lahir','tgl_lahir','nm_ibu','alamat',
                   'gol_darah','pekerjaan','stts_nikah','agama','no_tlp','pnd',
                   'keluarga','namakeluarga','kd_pj','no_peserta','kd_kel','kd_kec',
                   'kd_kab','pekerjaanpj','alamatpj','kelurahanpj','kecamatanpj',
                   'kabupatenpj','kd_prop'];
        foreach ($fields as $field) {
            $f[$field] = $conn->real_escape_string(trim($_POST[$field] ?? ''));
        }

        // Set defaults & Foreign Key safe values
        $tgl_daftar      = date('Y-m-d');
        $umur            = hitung_umur($f['tgl_lahir']);
        $perusahaan      = '-';
        $suku_bangsa     = 1;
        $bahasa_pasien   = 1;
        $cacat_fisik     = 1;
        $email           = $conn->real_escape_string(trim($_POST['email'] ?? ''));
        $nip             = '-';
        $propinsipj      = $conn->real_escape_string(trim($_POST['propinsipj'] ?? '-'));

        $kd_prop = !empty($f['kd_prop']) ? (int)$f['kd_prop'] : 1;
        $kd_kab  = !empty($f['kd_kab'])  ? (int)$f['kd_kab']  : 1;
        $kd_kec  = !empty($f['kd_kec'])  ? (int)$f['kd_kec']  : 1;
        $kd_kel  = !empty($f['kd_kel'])  ? $f['kd_kel']       : '1';
        $kd_pj   = !empty($f['kd_pj'])   ? $f['kd_pj']        : 'UMU';

        $sql = "INSERT INTO pasien (
            no_rkm_medis, nm_pasien, no_ktp, jk, tmp_lahir, tgl_lahir, nm_ibu,
            alamat, gol_darah, pekerjaan, stts_nikah, agama, tgl_daftar, no_tlp,
            umur, pnd, keluarga, namakeluarga, kd_pj, no_peserta, kd_kel,
            kd_kec, kd_kab, pekerjaanpj, alamatpj, kelurahanpj, kecamatanpj,
            kabupatenpj, perusahaan_pasien, suku_bangsa, bahasa_pasien, cacat_fisik,
            email, nip, kd_prop, propinsipj
        ) VALUES (
            '{$no_rm}', '{$f['nm_pasien']}', '{$f['no_ktp']}', '{$f['jk']}',
            '{$f['tmp_lahir']}', '{$f['tgl_lahir']}', '{$f['nm_ibu']}',
            '{$f['alamat']}', '{$f['gol_darah']}', '{$f['pekerjaan']}',
            '{$f['stts_nikah']}', '{$f['agama']}', '{$tgl_daftar}', '{$f['no_tlp']}',
            '$umur', '{$f['pnd']}', '{$f['keluarga']}', '{$f['namakeluarga']}',
            '{$kd_pj}', '{$f['no_peserta']}', '{$kd_kel}',
            {$kd_kec}, {$kd_kab}, '{$f['pekerjaanpj']}',
            '{$f['alamatpj']}', '{$f['kelurahanpj']}', '{$f['kecamatanpj']}',
            '{$f['kabupatenpj']}', '$perusahaan', $suku_bangsa, $bahasa_pasien,
            $cacat_fisik, '$email', '$nip', {$kd_prop}, '$propinsipj'
        )";

        if ($conn->query($sql)) {
            set_flash('success', "Pasien <strong>" . htmlspecialchars($_POST['nm_pasien']) . "</strong> berhasil didaftarkan dengan No. RM: <strong>$no_rm</strong>");

            // Jika ada flag langsung daftar kunjungan
            if (!empty($_POST['langsung_daftar'])) {
                redirect(BASE_URL . "modules/pendaftaran/tambah.php?rm=" . urlencode($no_rm));
            }
            redirect(BASE_URL . 'modules/pasien/detail.php?rm=' . urlencode($no_rm));
        } else {
            $errors['db'] = 'Gagal menyimpan data: ' . $conn->error;
        }
    }

    // Simpan data untuk re-fill form
    $data = $_POST;
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Breadcrumb ───────────────────────────────────────── -->
<div class="breadcrumb">
  <a href="<?= BASE_URL ?>modules/pasien/index.php">Data Pasien</a>
  <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
  <span class="breadcrumb-current">Tambah Pasien Baru</span>
</div>

<div class="page-header">
  <div>
    <h1 class="page-title">Registrasi Pasien Baru</h1>
    <p class="page-subtitle">Isi data pasien dengan lengkap dan benar.</p>
  </div>
  <a href="<?= BASE_URL ?>modules/pasien/index.php" class="btn btn-outline">
    <i class="fas fa-arrow-left"></i> Kembali
  </a>
</div>

<?php if (!empty($errors['db'])): ?>
  <div class="alert alert-danger" data-auto-dismiss="6000">
    <i class="fas fa-times-circle"></i>
    <span><?= $errors['db'] ?></span>
  </div>
<?php endif; ?>

<form method="POST" action="" id="formPasien">

  <!-- ─── Data Identitas ─────────────────────────────────── -->
  <div class="card mb-16">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-id-card"></i> Data Identitas Pasien</div>
    </div>
    <div class="card-body">

      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Nama Lengkap <span class="required">*</span></label>
          <input type="text" name="nm_pasien" class="form-control <?= isset($errors['nm_pasien']) ? 'is-invalid':'' ?>"
                 value="<?= htmlspecialchars($data['nm_pasien'] ?? '') ?>"
                 placeholder="Nama sesuai KTP" style="text-transform:uppercase;"
                 oninput="this.value=this.value.toUpperCase()">
          <?php if (isset($errors['nm_pasien'])): ?>
            <div class="form-error"><?= $errors['nm_pasien'] ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Nomor KTP / NIK</label>
          <input type="text" name="no_ktp" class="form-control"
                 value="<?= htmlspecialchars($data['no_ktp'] ?? '') ?>"
                 placeholder="16 digit NIK" maxlength="20">
        </div>
      </div>

      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Jenis Kelamin <span class="required">*</span></label>
          <select name="jk" class="form-control <?= isset($errors['jk']) ? 'is-invalid':'' ?>">
            <option value="">— Pilih —</option>
            <option value="L" <?= ($data['jk']??'')==='L' ? 'selected':'' ?>>Laki-laki</option>
            <option value="P" <?= ($data['jk']??'')==='P' ? 'selected':'' ?>>Perempuan</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tempat Lahir</label>
          <input type="text" name="tmp_lahir" class="form-control"
                 value="<?= htmlspecialchars($data['tmp_lahir'] ?? '') ?>"
                 placeholder="Kota lahir">
        </div>
        <div class="form-group">
          <label class="form-label">Tanggal Lahir <span class="required">*</span></label>
          <input type="date" name="tgl_lahir" class="form-control <?= isset($errors['tgl_lahir']) ? 'is-invalid':'' ?>"
                 value="<?= htmlspecialchars($data['tgl_lahir'] ?? '') ?>"
                 max="<?= date('Y-m-d') ?>" id="inputTglLahir">
          <div class="form-hint" id="hintUmur"></div>
        </div>
      </div>

      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Golongan Darah</label>
          <select name="gol_darah" class="form-control">
            <?php foreach (['-','A','B','O','AB'] as $g): ?>
              <option value="<?= $g ?>" <?= ($data['gol_darah']??'-')===$g ? 'selected':'' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status Pernikahan</label>
          <select name="stts_nikah" class="form-control">
            <option value="">— Pilih —</option>
            <?php foreach (['BELUM MENIKAH','MENIKAH','JANDA','DUDHA'] as $s): ?>
              <option value="<?= $s ?>" <?= ($data['stts_nikah']??'')===$s ? 'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Agama</label>
          <select name="agama" class="form-control">
            <option value="">— Pilih —</option>
            <?php foreach (['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'] as $ag): ?>
              <option value="<?= $ag ?>" <?= ($data['agama']??'')===$ag ? 'selected':'' ?>><?= $ag ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Pendidikan</label>
          <select name="pnd" class="form-control">
            <?php foreach (['-','TK','SD','SMP','SMA','D1','D2','D3','D4','S1','S2','S3'] as $pnd): ?>
              <option value="<?= $pnd ?>" <?= ($data['pnd']??'-')===$pnd ? 'selected':'' ?>><?= $pnd ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Pekerjaan</label>
          <input type="text" name="pekerjaan" class="form-control"
                 value="<?= htmlspecialchars($data['pekerjaan'] ?? '') ?>"
                 placeholder="Jenis pekerjaan">
        </div>
        <div class="form-group">
          <label class="form-label">No. Telepon</label>
          <input type="text" name="no_tlp" class="form-control"
                 value="<?= htmlspecialchars($data['no_tlp'] ?? '') ?>"
                 placeholder="08xxxxxxxxxx">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Alamat Lengkap</label>
        <textarea name="alamat" class="form-control" rows="2"
                  placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kota"><?= htmlspecialchars($data['alamat'] ?? '') ?></textarea>
      </div>

      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Nama Ibu Kandung</label>
          <input type="text" name="nm_ibu" class="form-control"
                 value="<?= htmlspecialchars($data['nm_ibu'] ?? '') ?>"
                 placeholder="Nama ibu kandung">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control"
                 value="<?= htmlspecialchars($data['email'] ?? '') ?>"
                 placeholder="email@contoh.com">
        </div>
      </div>

    </div>
  </div>

  <!-- ─── Data Penjamin / Asuransi ──────────────────────── -->
  <div class="card mb-16">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-shield-alt"></i> Data Penjamin / Asuransi</div>
    </div>
    <div class="card-body">
      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Cara Bayar / Penjamin <span class="required">*</span></label>
          <select name="kd_pj" class="form-control <?= isset($errors['kd_pj']) ? 'is-invalid':'' ?>" id="selectPenjamin">
            <option value="">— Pilih —</option>
            <?php foreach ($penjab_list as $pj): ?>
              <option value="<?= $pj['kd_pj'] ?>"
                <?= ($data['kd_pj']??'')===$pj['kd_pj'] ? 'selected':'' ?>>
                <?= htmlspecialchars($pj['nm_penjab']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" id="groupNoPeserta">
          <label class="form-label">No. Kartu / No. Peserta <small style="color:var(--gray-400);">(BPJS/Asuransi)</small></label>
          <input type="text" name="no_peserta" class="form-control"
                 value="<?= htmlspecialchars($data['no_peserta'] ?? '') ?>"
                 placeholder="Nomor kartu BPJS atau asuransi">
        </div>
      </div>

      <!-- Penanggung Jawab -->
      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Hubungan Penanggung Jawab</label>
          <select name="keluarga" class="form-control">
            <option value="">— Pilih —</option>
            <?php foreach (['AYAH','IBU','ISTRI','SUAMI','SAUDARA','ANAK'] as $k): ?>
              <option value="<?= $k ?>" <?= ($data['keluarga']??'')===$k ? 'selected':'' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Penanggung Jawab</label>
          <input type="text" name="namakeluarga" class="form-control"
                 value="<?= htmlspecialchars($data['namakeluarga'] ?? '') ?>"
                 placeholder="Nama penanggung jawab / wali">
        </div>
      </div>
    </div>
  </div>

  <!-- ─── Tombol Submit ─────────────────────────────────── -->
  <div class="card">
    <div class="card-body" style="display:flex;gap:10px;align-items:center;justify-content:flex-end;">
      <a href="<?= BASE_URL ?>modules/pasien/index.php" class="btn btn-outline">
        <i class="fas fa-times"></i> Batal
      </a>
      <button type="submit" name="action" value="save" class="btn btn-outline-primary">
        <i class="fas fa-save"></i> Simpan Saja
      </button>
      <button type="submit" name="langsung_daftar" value="1" class="btn btn-primary">
        <i class="fas fa-clipboard-list"></i> Simpan & Langsung Daftar Kunjungan
      </button>
    </div>
  </div>

</form>

<script>
// Hitung umur real-time
document.getElementById('inputTglLahir')?.addEventListener('change', function() {
  const tgl = this.value;
  if (!tgl) return;
  const birth = new Date(tgl);
  const now   = new Date();
  const diff  = now - birth;
  const days  = Math.floor(diff / (1000*60*60*24));
  const months = Math.floor(days / 30.4375);
  const years  = Math.floor(months / 12);
  let umur = '';
  if (years > 0)       umur = years + ' Tahun';
  else if (months > 0) umur = months + ' Bulan';
  else                 umur = days + ' Hari';
  document.getElementById('hintUmur').textContent = 'Umur: ' + umur;
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
