<?php
/**
 * SIMKlinik — Edit Pasien
 */

$page_title    = 'Edit Data Pasien';
$active_module = 'pasien';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_rm = sanitize($_GET['rm'] ?? '');
if (empty($no_rm)) redirect(BASE_URL . 'modules/pasien/index.php');

$rm_esc = $conn->real_escape_string($no_rm);

// Load data eksisting
$res    = $conn->query("SELECT * FROM pasien WHERE no_rkm_medis = '$rm_esc' LIMIT 1");
if (!$res || $res->num_rows === 0) {
    set_flash('danger', 'Pasien tidak ditemukan.');
    redirect(BASE_URL . 'modules/pasien/index.php');
}
$pasien = $res->fetch_assoc();

// Load penjab
$penjab_list = [];
$rj = $conn->query("SELECT kd_pj, png_jawab as nm_penjab FROM penjab WHERE status = '1' ORDER BY png_jawab");
if ($rj) while ($row = $rj->fetch_assoc()) $penjab_list[] = $row;

$errors = [];

// ─── Proses Update ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $required = ['nm_pasien', 'jk', 'tgl_lahir', 'kd_pj'];
    foreach ($required as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $errors[$field] = 'Wajib diisi.';
        }
    }

    if (empty($errors)) {
        $f = [];
        $fields = ['nm_pasien','no_ktp','jk','tmp_lahir','tgl_lahir','nm_ibu','alamat',
                   'gol_darah','pekerjaan','stts_nikah','agama','no_tlp','pnd',
                   'keluarga','namakeluarga','kd_pj','no_peserta','email'];
        foreach ($fields as $field) {
            $f[$field] = $conn->real_escape_string(trim($_POST[$field] ?? ''));
        }

        $umur = hitung_umur($f['tgl_lahir']);

        $kd_pj = !empty($f['kd_pj']) ? $f['kd_pj'] : 'UMU';

        $sql = "UPDATE pasien SET
            nm_pasien   = '{$f['nm_pasien']}',
            no_ktp      = '{$f['no_ktp']}',
            jk          = '{$f['jk']}',
            tmp_lahir   = '{$f['tmp_lahir']}',
            tgl_lahir   = '{$f['tgl_lahir']}',
            nm_ibu      = '{$f['nm_ibu']}',
            alamat      = '{$f['alamat']}',
            gol_darah   = '{$f['gol_darah']}',
            pekerjaan   = '{$f['pekerjaan']}',
            stts_nikah  = '{$f['stts_nikah']}',
            agama       = '{$f['agama']}',
            no_tlp      = '{$f['no_tlp']}',
            pnd         = '{$f['pnd']}',
            keluarga    = '{$f['keluarga']}',
            namakeluarga= '{$f['namakeluarga']}',
            kd_pj       = '{$kd_pj}',
            no_peserta  = '{$f['no_peserta']}',
            email       = '{$f['email']}',
            umur        = '$umur'
            WHERE no_rkm_medis = '$rm_esc'";

        if ($conn->query($sql)) {
            set_flash('success', 'Data pasien berhasil diperbarui.');
            redirect(BASE_URL . 'modules/pasien/detail.php?rm=' . urlencode($no_rm));
        } else {
            $errors['db'] = 'Gagal update: ' . $conn->error;
        }
    }

    // Merge dengan data POST untuk re-fill
    $pasien = array_merge($pasien, $_POST);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>modules/pasien/index.php">Data Pasien</a>
  <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
  <a href="<?= BASE_URL ?>modules/pasien/detail.php?rm=<?= urlencode($no_rm) ?>"><?= htmlspecialchars($pasien['nm_pasien']) ?></a>
  <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
  <span class="breadcrumb-current">Edit</span>
</div>

<div class="page-header">
  <div>
    <h1 class="page-title">Edit Data Pasien</h1>
    <p class="page-subtitle">No. RM: <strong><?= $no_rm ?></strong></p>
  </div>
  <a href="<?= BASE_URL ?>modules/pasien/detail.php?rm=<?= urlencode($no_rm) ?>" class="btn btn-outline">
    <i class="fas fa-arrow-left"></i> Kembali
  </a>
</div>

<?php if (!empty($errors['db'])): ?>
  <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= $errors['db'] ?></div>
<?php endif; ?>

<form method="POST" action="">

  <div class="card mb-16">
    <div class="card-header"><div class="card-title"><i class="fas fa-user-edit"></i> Identitas Pasien</div></div>
    <div class="card-body">

      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Nama Lengkap <span class="required">*</span></label>
          <input type="text" name="nm_pasien" class="form-control <?= isset($errors['nm_pasien']) ? 'is-invalid':'' ?>"
                 value="<?= htmlspecialchars($pasien['nm_pasien'] ?? '') ?>"
                 style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="form-group">
          <label class="form-label">NIK / KTP</label>
          <input type="text" name="no_ktp" class="form-control"
                 value="<?= htmlspecialchars($pasien['no_ktp'] ?? '') ?>" maxlength="20">
        </div>
      </div>

      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Jenis Kelamin <span class="required">*</span></label>
          <select name="jk" class="form-control">
            <option value="L" <?= ($pasien['jk']??'')==='L' ? 'selected':'' ?>>Laki-laki</option>
            <option value="P" <?= ($pasien['jk']??'')==='P' ? 'selected':'' ?>>Perempuan</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tempat Lahir</label>
          <input type="text" name="tmp_lahir" class="form-control" value="<?= htmlspecialchars($pasien['tmp_lahir'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Tanggal Lahir <span class="required">*</span></label>
          <input type="date" name="tgl_lahir" class="form-control <?= isset($errors['tgl_lahir']) ? 'is-invalid':'' ?>"
                 value="<?= htmlspecialchars($pasien['tgl_lahir'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Agama</label>
          <select name="agama" class="form-control">
            <?php foreach (['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'] as $ag): ?>
              <option value="<?= $ag ?>" <?= ($pasien['agama']??'')===$ag ? 'selected':'' ?>><?= $ag ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status Nikah</label>
          <select name="stts_nikah" class="form-control">
            <?php foreach (['BELUM MENIKAH','MENIKAH','JANDA','DUDHA'] as $s): ?>
              <option value="<?= $s ?>" <?= ($pasien['stts_nikah']??'')===$s ? 'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">No. Telepon</label>
          <input type="text" name="no_tlp" class="form-control" value="<?= htmlspecialchars($pasien['no_tlp'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Pekerjaan</label>
          <input type="text" name="pekerjaan" class="form-control" value="<?= htmlspecialchars($pasien['pekerjaan'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($pasien['email'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Alamat</label>
        <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($pasien['alamat'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <div class="card mb-16">
    <div class="card-header"><div class="card-title"><i class="fas fa-shield-alt"></i> Penjamin</div></div>
    <div class="card-body">
      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Cara Bayar <span class="required">*</span></label>
          <select name="kd_pj" class="form-control">
            <?php foreach ($penjab_list as $pj): ?>
              <option value="<?= $pj['kd_pj'] ?>" <?= ($pasien['kd_pj']??'')===$pj['kd_pj'] ? 'selected':'' ?>>
                <?= htmlspecialchars($pj['nm_penjab']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">No. Peserta / Kartu</label>
          <input type="text" name="no_peserta" class="form-control" value="<?= htmlspecialchars($pasien['no_peserta'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Hubungan Keluarga</label>
          <select name="keluarga" class="form-control">
            <?php foreach (['AYAH','IBU','ISTRI','SUAMI','SAUDARA','ANAK'] as $k): ?>
              <option value="<?= $k ?>" <?= ($pasien['keluarga']??'')===$k ? 'selected':'' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Penanggung Jawab</label>
          <input type="text" name="namakeluarga" class="form-control" value="<?= htmlspecialchars($pasien['namakeluarga'] ?? '') ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body" style="display:flex;gap:10px;justify-content:flex-end;">
      <a href="<?= BASE_URL ?>modules/pasien/detail.php?rm=<?= urlencode($no_rm) ?>" class="btn btn-outline">
        <i class="fas fa-times"></i> Batal
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Simpan Perubahan
      </button>
    </div>
  </div>

</form>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
