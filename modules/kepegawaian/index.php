<?php
/**
 * SIMKlinik — Kepegawaian (Dokter, Pegawai & Jadwal Praktek)
 */

$page_title    = 'Kepegawaian & Dokter';
$active_module = 'kepegawaian';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ─── Tambah Dokter / Pegawai Baru ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_dokter'])) {
    $kd_dokter   = $conn->real_escape_string(sanitize($_POST['kd_dokter'] ?? ''));
    $nm_dokter   = $conn->real_escape_string(sanitize($_POST['nm_dokter'] ?? ''));
    $jk          = $conn->real_escape_string(sanitize($_POST['jk'] ?? 'L'));
    $kd_sps      = $conn->real_escape_string(sanitize($_POST['kd_sps'] ?? 'UMUM'));
    $no_telp     = $conn->real_escape_string(sanitize($_POST['no_telp'] ?? ''));
    $almt_tgl    = $conn->real_escape_string(sanitize($_POST['almt_tgl'] ?? ''));
    $no_ijn      = $conn->real_escape_string(sanitize($_POST['no_ijn_praktek'] ?? ''));

    if (empty($kd_dokter) || empty($nm_dokter)) {
        set_flash('danger', 'Kode dan Nama Dokter wajib diisi.');
    } else {
        // 1. Pastikan terdaftar di tabel pegawai dulu (FK constraint)
        $conn->query("
            INSERT INTO pegawai (nik, nama, jk, jbtn, jnj_jabatan, kode_kelompok, kode_resiko, kode_emergency, departemen, bidang, stts_wp, stts_kerja, npwp, pendidikan, gapok, tmp_lahir, tgl_lahir, alamat, kota, mulai_kerja, ms_kerja, indexins, bpd, rekening, stts_aktif, wajibmasuk, pengurang, indek, mulai_kontrak, cuti_diambil, dankes, photo, no_ktp)
            VALUES ('$kd_dokter', '$nm_dokter', '".($jk==='L'?'Pria':'Wanita')."', 'Dokter', '-', '-', '-', '-', '-', '-', '-', '-', '-', '-', 0, '-', '2000-01-01', '$almt_tgl', '-', CURDATE(), '<1', '-', '-', '-', 'AKTIF', 0, 0, 0, CURDATE(), 0, 0, '-', '0')
            ON DUPLICATE KEY UPDATE nama = '$nm_dokter', jk = '".($jk==='L'?'Pria':'Wanita')."', alamat = '$almt_tgl'
        ");

        // 2. Insert ke dokter
        $sql = "INSERT INTO dokter (kd_dokter, nm_dokter, jk, tmp_lahir, tgl_lahir, gol_drh, agama, almt_tgl, no_telp, stts_nikah, kd_sps, alumni, no_ijn_praktek, status)
                VALUES ('$kd_dokter', '$nm_dokter', '$jk', '-', '2000-01-01', 'O', 'Islam', '$almt_tgl', '$no_telp', 'MENIKAH', '$kd_sps', '-', '$no_ijn', '1')
                ON DUPLICATE KEY UPDATE nm_dokter = '$nm_dokter', jk = '$jk', no_telp = '$no_telp', almt_tgl = '$almt_tgl', kd_sps = '$kd_sps', no_ijn_praktek = '$no_ijn'";

        if ($conn->query($sql)) {
            set_flash('success', "Data dokter <strong>$nm_dokter</strong> ($kd_dokter) berhasil disimpan.");
            redirect(BASE_URL . 'modules/kepegawaian/index.php');
        } else {
            set_flash('danger', 'Gagal menyimpan: ' . $conn->error);
        }
    }
}

// ─── Tambah Jadwal Praktek ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_jadwal'])) {
    $kd_dok_j = $conn->real_escape_string(sanitize($_POST['kd_dokter'] ?? ''));
    $kd_pol_j = $conn->real_escape_string(sanitize($_POST['kd_poli'] ?? ''));
    $hari_j   = $conn->real_escape_string(sanitize($_POST['hari_kerja'] ?? 'SENIN'));
    $jam_m    = $conn->real_escape_string(sanitize($_POST['jam_mulai'] ?? '08:00:00'));
    $jam_s    = $conn->real_escape_string(sanitize($_POST['jam_selesai'] ?? '14:00:00'));
    $kuota_j  = (int)($_POST['kuota'] ?? 30);

    if (empty($kd_dok_j) || empty($kd_pol_j)) {
        set_flash('danger', 'Dokter dan Poliklinik wajib dipilih.');
    } else {
        $sql_j = "INSERT INTO jadwal (kd_dokter, hari_kerja, jam_mulai, jam_selesai, kd_poli, kuota)
                  VALUES ('$kd_dok_j', '$hari_j', '$jam_m', '$jam_s', '$kd_pol_j', $kuota_j)
                  ON DUPLICATE KEY UPDATE jam_selesai = '$jam_s', kd_poli = '$kd_pol_j', kuota = $kuota_j";
        if ($conn->query($sql_j)) {
            set_flash('success', 'Jadwal praktek berhasil disimpan.');
            redirect(BASE_URL . 'modules/kepegawaian/index.php');
        } else {
            set_flash('danger', 'Gagal menyimpan jadwal: ' . $conn->error);
        }
    }
}

// ─── Data Dokter ──────────────────────────────────────────────
$dokter_res = $conn->query("
    SELECT d.*, s.nm_sps as nm_spesialis
    FROM dokter d
    LEFT JOIN spesialis s ON d.kd_sps = s.kd_sps
    WHERE d.status = '1'
    ORDER BY d.nm_dokter ASC
");
$dokter_list = [];
if ($dokter_res) while ($row = $dokter_res->fetch_assoc()) $dokter_list[] = $row;

// ─── Data Jadwal Praktek ──────────────────────────────────────
$jadwal_res = $conn->query("
    SELECT j.*, d.nm_dokter, pol.nm_poli
    FROM jadwal j
    JOIN dokter d ON j.kd_dokter = d.kd_dokter
    JOIN poliklinik pol ON j.kd_poli = pol.kd_poli
    ORDER BY FIELD(j.hari_kerja, 'SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','AKHAD','AHAD'), j.jam_mulai ASC
");
$jadwal_list = [];
if ($jadwal_res) while ($row = $jadwal_res->fetch_assoc()) $jadwal_list[] = $row;

// Poliklinik List
$poli_list = [];
$p_res = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status='1' ORDER BY nm_poli");
if ($p_res) while ($row = $p_res->fetch_assoc()) $poli_list[] = $row;

// Spesialis List
$sps_list = [];
$s_res = $conn->query("SELECT kd_sps, nm_sps as nm_spesialis FROM spesialis ORDER BY nm_sps");
if ($s_res) while ($row = $s_res->fetch_assoc()) $sps_list[] = $row;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Manajemen Kepegawaian & Dokter</h1>
    <p class="page-subtitle">Master Data Dokter, Tenaga Medis, dan Jadwal Praktek Poliklinik</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-outline-primary" data-open-modal="modalTambahJadwal">
      <i class="fas fa-calendar-plus"></i> Atur Jadwal Praktek
    </button>
    <button type="button" class="btn btn-primary" data-open-modal="modalTambahDokter">
      <i class="fas fa-user-md"></i> Tambah Dokter Baru
    </button>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">

  <!-- ─── Kolom Kiri: Daftar Dokter ────────────────────────── -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-user-md text-primary"></i> Daftar Dokter Klinik</div>
      <span style="font-size:12px;color:var(--gray-500);"><?= count($dokter_list) ?> Dokter Aktif</span>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($dokter_list)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
          <div class="empty-state-title">Belum ada dokter terdaftar</div>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Dokter</th>
                <th>Spesialis</th>
                <th>Kontak</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dokter_list as $d): ?>
                <tr>
                  <td>
                    <span style="font-family:monospace;font-weight:700;color:var(--primary-600);font-size:12px;">
                      <?= htmlspecialchars($d['kd_dokter']) ?>
                    </span>
                  </td>
                  <td>
                    <div style="font-weight:600;font-size:13px;color:var(--gray-900);">
                      <?= htmlspecialchars($d['nm_dokter']) ?>
                    </div>
                    <div style="font-size:11px;color:var(--gray-400);">
                      SIP: <?= htmlspecialchars($d['no_ijn_praktek'] ?: '-') ?>
                    </div>
                  </td>
                  <td>
                    <span class="badge badge-primary"><?= htmlspecialchars($d['nm_spesialis'] ?: 'Umum') ?></span>
                  </td>
                  <td style="font-size:12px;color:var(--gray-600);"><?= htmlspecialchars($d['no_telp'] ?: '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ─── Kolom Kanan: Jadwal Praktek ──────────────────────── -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-calendar-alt text-success"></i> Jadwal Praktek Poliklinik</div>
      <button type="button" class="btn btn-sm btn-outline-primary" data-open-modal="modalTambahJadwal">
        <i class="fas fa-plus"></i> Tambah Jadwal
      </button>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($jadwal_list)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="fas fa-calendar-times"></i></div>
          <div class="empty-state-title">Belum ada jadwal praktek</div>
          <div class="empty-state-desc">Jadwal praktek dokter dapat dikonfigurasi.</div>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Hari</th>
                <th>Dokter</th>
                <th>Poli</th>
                <th>Jam Praktek</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($jadwal_list as $j): ?>
                <tr>
                  <td>
                    <span class="badge badge-secondary" style="font-weight:700;"><?= htmlspecialchars($j['hari_kerja']) ?></span>
                  </td>
                  <td style="font-weight:600;font-size:12px;"><?= htmlspecialchars($j['nm_dokter']) ?></td>
                  <td style="font-size:12px;"><?= htmlspecialchars($j['nm_poli']) ?></td>
                  <td style="font-size:11px;color:var(--gray-600);">
                    <i class="fas fa-clock text-primary"></i> <?= substr($j['jam_mulai'],0,5) ?> - <?= substr($j['jam_selesai'],0,5) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ─── Modal Tambah Dokter ──────────────────────────────── -->
<div class="modal-overlay" id="modalTambahDokter">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Data Dokter Baru</h3>
      <button class="modal-close" data-close-modal="modalTambahDokter"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="simpan_dokter" value="1">
      <div class="modal-body">
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Kode / NIP Dokter <span class="required">*</span></label>
            <input type="text" name="kd_dokter" class="form-control" placeholder="cth: DR002" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap & Gelar <span class="required">*</span></label>
            <input type="text" name="nm_dokter" class="form-control" placeholder="cth: dr. Budi Santoso, Sp.A" required>
          </div>
        </div>

        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Jenis Kelamin</label>
            <select name="jk" class="form-control">
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Spesialisasi</label>
            <select name="kd_sps" class="form-control">
              <option value="UMUM">Dokter Umum</option>
              <?php foreach ($sps_list as $sp): ?>
                <option value="<?= $sp['kd_sps'] ?>"><?= htmlspecialchars($sp['nm_spesialis']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">No. Telepon / WA</label>
            <input type="text" name="no_telp" class="form-control" placeholder="08xxxxxxxxxx">
          </div>
          <div class="form-group">
            <label class="form-label">Nomor SIP (Surat Izin Praktik)</label>
            <input type="text" name="no_ijn_praktek" class="form-control" placeholder="SIP.xxx/xxx/xxx">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Alamat Tinggal</label>
          <textarea name="almt_tgl" class="form-control" rows="2" placeholder="Alamat lengkap..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modalTambahDokter">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Dokter</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── Modal Tambah Jadwal Praktek ──────────────────────── -->
<div class="modal-overlay" id="modalTambahJadwal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Atur Jadwal Praktek Poliklinik</h3>
      <button class="modal-close" data-close-modal="modalTambahJadwal"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="simpan_jadwal" value="1">
      <div class="modal-body">
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Dokter <span class="required">*</span></label>
            <select name="kd_dokter" class="form-control" required>
              <option value="">— Pilih Dokter —</option>
              <?php foreach ($dokter_list as $d): ?>
                <option value="<?= $d['kd_dokter'] ?>"><?= htmlspecialchars($d['nm_dokter']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Poliklinik <span class="required">*</span></label>
            <select name="kd_poli" class="form-control" required>
              <option value="">— Pilih Poli —</option>
              <?php foreach ($poli_list as $pl): ?>
                <option value="<?= $pl['kd_poli'] ?>"><?= htmlspecialchars($pl['nm_poli']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row col-3">
          <div class="form-group">
            <label class="form-label">Hari Kerja</label>
            <select name="hari_kerja" class="form-control">
              <?php foreach (['SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','AKHAD'] as $h): ?>
                <option value="<?= $h ?>"><?= $h ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Jam Mulai</label>
            <input type="time" name="jam_mulai" class="form-control" value="08:00:00" required>
          </div>
          <div class="form-group">
            <label class="form-label">Jam Selesai</label>
            <input type="time" name="jam_selesai" class="form-control" value="14:00:00" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Kuota Pasien per Sesi</label>
          <input type="number" name="kuota" class="form-control" value="30" min="1">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modalTambahJadwal">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Jadwal</button>
      </div>
    </form>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
