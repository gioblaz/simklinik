<?php
/**
 * SIMKlinik — Form Pendaftaran Kunjungan
 * Mendaftarkan pasien lama atau baru ke poliklinik
 */

$page_title    = 'Daftar Kunjungan';
$active_module = 'pendaftaran';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$errors = [];

// ─── Load Master Data ─────────────────────────────────────────
// Poliklinik aktif
$poli_list = [];
$rp = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli");
if ($rp) while ($row = $rp->fetch_assoc()) $poli_list[] = $row;

// Dokter aktif (default kosong, diisi via AJAX saat poli dipilih)
$dokter_list = [];

// Penjamin
$penjab_list = [];
$rj = $conn->query("SELECT kd_pj, png_jawab as nm_penjab FROM penjab WHERE status = '1' ORDER BY png_jawab");
if ($rj) while ($row = $rj->fetch_assoc()) $penjab_list[] = $row;

// Pre-fill pasien dari parameter ?rm=
$prefill_rm   = sanitize($_GET['rm'] ?? '');
$prefill_data = null;
if ($prefill_rm) {
    $rm_e  = $conn->real_escape_string($prefill_rm);
    $res   = $conn->query("SELECT p.*, pj.png_jawab as nm_penjab FROM pasien p LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj WHERE p.no_rkm_medis = '$rm_e' LIMIT 1");
    if ($res && $res->num_rows > 0) $prefill_data = $res->fetch_assoc();
}

// ─── Proses Submit ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_rm   = $conn->real_escape_string(trim($_POST['no_rkm_medis'] ?? ''));
    $kd_poli = $conn->real_escape_string(trim($_POST['kd_poli'] ?? ''));
    $kd_dok  = $conn->real_escape_string(trim($_POST['kd_dokter'] ?? ''));
    $kd_pj   = $conn->real_escape_string(trim($_POST['kd_pj'] ?? ''));
    $no_pes  = $conn->real_escape_string(trim($_POST['no_peserta'] ?? ''));
    $keluhan = $conn->real_escape_string(trim($_POST['keluhan'] ?? ''));
    $jns_pasien = $conn->real_escape_string(trim($_POST['jns_pasien'] ?? 'Baru'));

    // Validasi
    if (empty($no_rm))   $errors['no_rkm_medis'] = 'Pilih pasien terlebih dahulu.';
    if (empty($kd_poli)) $errors['kd_poli']       = 'Pilih poliklinik.';
    if (empty($kd_dok))  $errors['kd_dokter']      = 'Pilih dokter.';

    if (empty($errors)) {
        // Cek apakah pasien sudah terdaftar hari ini di poli yang sama
        $today = date('Y-m-d');
        $cek   = $conn->query("SELECT no_rawat FROM reg_periksa WHERE no_rkm_medis = '$no_rm' AND tgl_registrasi = '$today' AND kd_poli = '$kd_poli' AND stts != 'Batal' LIMIT 1");
        if ($cek && $cek->num_rows > 0) {
            $errors['duplicate'] = 'Pasien sudah terdaftar di poliklinik ini hari ini.';
        }
    }

    if (empty($errors)) {
        $no_rawat  = generate_no_rawat();
        $jam_reg   = date('H:i:s');
        $tgl_reg   = date('Y-m-d');

        // Ambil data pendukung pasien (umur, penanggung jawab) & tarif poli
        $p_info = $conn->query("
            SELECT p.namakeluarga, p.alamatpj, p.keluarga, p.tgl_lahir, p.kd_pj, p.no_peserta,
                   pol.registrasi as biaya_reg
            FROM pasien p
            LEFT JOIN poliklinik pol ON pol.kd_poli = '$kd_poli'
            WHERE p.no_rkm_medis = '$no_rm'
            LIMIT 1
        ")->fetch_assoc();

        $p_jawab    = $conn->real_escape_string($p_info['namakeluarga'] ?? '-');
        $almt_pj    = $conn->real_escape_string($p_info['alamatpj'] ?? '-');
        $hubunganpj = $conn->real_escape_string($p_info['keluarga'] ?? '-');
        $biaya_reg  = (float)($p_info['biaya_reg'] ?? 0);
        $umurdaftar = 0;
        $sttsumur   = 'Th';
        if (!empty($p_info['tgl_lahir'])) {
            $diff = date_diff(date_create($p_info['tgl_lahir']), date_create($tgl_reg));
            $umurdaftar = $diff->y > 0 ? $diff->y : ($diff->m > 0 ? $diff->m : $diff->d);
            $sttsumur   = $diff->y > 0 ? 'Th' : ($diff->m > 0 ? 'Bl' : 'Hr');
        }

        // Simpan no_peserta & kd_pj ke pasien jika ada perubahan
        if (!empty($no_pes) || !empty($kd_pj)) {
            $conn->query("UPDATE pasien SET no_peserta = '$no_pes', kd_pj = '$kd_pj' WHERE no_rkm_medis = '$no_rm'");
        }

        // Ambil no_reg (nomor urut harian per poli)
        $r_noreg = $conn->query("SELECT COUNT(*) as cnt FROM reg_periksa WHERE tgl_registrasi = '$tgl_reg' AND kd_poli = '$kd_poli'");
        $no_reg  = ($r_noreg ? (int)$r_noreg->fetch_assoc()['cnt'] : 0) + 1;

        $sql = "INSERT INTO reg_periksa (
            no_rawat, tgl_registrasi, jam_reg, no_rkm_medis,
            kd_dokter, kd_poli, kd_pj, p_jawab, almt_pj, hubunganpj,
            biaya_reg, stts, stts_daftar, status_lanjut, no_reg,
            umurdaftar, sttsumur, status_bayar, status_poli
        ) VALUES (
            '$no_rawat', '$tgl_reg', '$jam_reg', '$no_rm',
            '$kd_dok', '$kd_poli', '$kd_pj', '$p_jawab', '$almt_pj', '$hubunganpj',
            $biaya_reg, 'Belum', '$jns_pasien', 'Ralan', '$no_reg',
            $umurdaftar, '$sttsumur', 'Belum Bayar', '$jns_pasien'
        )";

        if ($conn->query($sql)) {
            set_flash('success', "Pasien berhasil didaftarkan. No. Rawat: <strong>$no_rawat</strong> | No. Antrian: <strong>$no_reg</strong>");
            redirect(BASE_URL . 'modules/pendaftaran/index.php');
        } else {
            $errors['db'] = 'Gagal mendaftar: ' . $conn->error;
        }
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Breadcrumb ───────────────────────────────────────── -->
<div class="breadcrumb">
  <a href="<?= BASE_URL ?>modules/pendaftaran/index.php">Pendaftaran</a>
  <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
  <span class="breadcrumb-current">Daftar Kunjungan</span>
</div>

<div class="page-header">
  <div>
    <h1 class="page-title">Daftar Kunjungan Pasien</h1>
    <p class="page-subtitle">Masukkan data kunjungan pasien ke poliklinik.</p>
  </div>
  <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="btn btn-outline">
    <i class="fas fa-arrow-left"></i> Kembali
  </a>
</div>

<?php if (!empty($errors['db']) || !empty($errors['duplicate'])): ?>
  <div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <span><?= $errors['db'] ?? $errors['duplicate'] ?></span>
  </div>
<?php endif; ?>

<form method="POST" action="" id="formDaftar">

  <div style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start;">

    <!-- ─── Form Utama ──────────────────────────────────────── -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Cari Pasien -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-search"></i> Cari / Pilih Pasien</div>
          <a href="<?= BASE_URL ?>modules/pasien/tambah.php?back=pendaftaran" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-user-plus"></i> Pasien Baru
          </a>
        </div>
        <div class="card-body">

          <!-- Search box -->
          <div class="form-group">
            <label class="form-label">Cari Pasien <span class="required">*</span></label>
            <div class="search-bar" id="searchPasienWrap" style="background:#fff;">
              <i class="fas fa-search"></i>
              <input type="text" id="inputCariPasien"
                     placeholder="Ketik nama, No. RM, NIK, atau No. BPJS..."
                     autocomplete="off">
            </div>
            <input type="hidden" name="no_rkm_medis" id="hiddenRm"
                   value="<?= htmlspecialchars($prefill_rm) ?>">
            <?php if (isset($errors['no_rkm_medis'])): ?>
              <div class="form-error"><?= $errors['no_rkm_medis'] ?></div>
            <?php endif; ?>

            <!-- Dropdown hasil cari -->
            <div id="searchResults" style="display:none;position:relative;z-index:50;border:1px solid var(--gray-200);border-radius:8px;background:#fff;box-shadow:var(--shadow-md);margin-top:4px;max-height:260px;overflow-y:auto;"></div>
          </div>

          <!-- Info Pasien Terpilih -->
          <div id="infoPasien" style="<?= $prefill_data ? '' : 'display:none;' ?>background:var(--primary-50);border:1px solid var(--primary-200);border-radius:8px;padding:14px 16px;">
            <?php if ($prefill_data): ?>
              <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                  <div style="font-size:15px;font-weight:700;color:var(--primary-700);" id="infoPasienNama">
                    <?= htmlspecialchars($prefill_data['nm_pasien']) ?>
                  </div>
                  <div style="font-size:12px;color:var(--primary-500);margin-top:4px;display:flex;gap:12px;">
                    <span id="infoPasienRm">RM: <?= $prefill_data['no_rkm_medis'] ?></span>
                    <span id="infoPasienJkUmur">
                      <?= $prefill_data['jk']==='L' ? 'Laki-laki' : 'Perempuan' ?> |
                      <?= hitung_umur($prefill_data['tgl_lahir']) ?>
                    </span>
                    <span id="infoPasienPenjamin"><?= htmlspecialchars($prefill_data['nm_penjab'] ?? 'Umum') ?></span>
                  </div>
                </div>
                <button type="button" onclick="clearPasien()" style="background:none;border:none;cursor:pointer;color:var(--primary-500);font-size:18px;" title="Ganti pasien">
                  <i class="fas fa-times-circle"></i>
                </button>
              </div>
            <?php else: ?>
              <div>
                <div style="font-size:15px;font-weight:700;color:var(--primary-700);" id="infoPasienNama"></div>
                <div style="font-size:12px;color:var(--primary-500);margin-top:4px;display:flex;gap:12px;">
                  <span id="infoPasienRm"></span>
                  <span id="infoPasienJkUmur"></span>
                  <span id="infoPasienPenjamin"></span>
                </div>
              </div>
              <button type="button" onclick="clearPasien()" style="background:none;border:none;cursor:pointer;color:var(--primary-500);font-size:18px;" title="Ganti pasien">
                <i class="fas fa-times-circle"></i>
              </button>
            <?php endif; ?>
          </div>

        </div>
      </div>

      <!-- Pilih Poli & Dokter -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-hospital"></i> Poliklinik & Dokter</div>
        </div>
        <div class="card-body">
          <div class="form-row col-2">
            <div class="form-group">
              <label class="form-label">Poliklinik <span class="required">*</span></label>
              <select name="kd_poli" id="selectPoli" class="form-control <?= isset($errors['kd_poli']) ? 'is-invalid':'' ?>">
                <option value="">— Pilih Poliklinik —</option>
                <?php foreach ($poli_list as $pl): ?>
                  <option value="<?= $pl['kd_poli'] ?>"><?= htmlspecialchars($pl['nm_poli']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['kd_poli'])): ?>
                <div class="form-error"><?= $errors['kd_poli'] ?></div>
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label class="form-label">Dokter <span class="required">*</span></label>
              <select name="kd_dokter" id="selectDokter" class="form-control <?= isset($errors['kd_dokter']) ? 'is-invalid':'' ?>" disabled>
                <option value="">— Pilih Poliklinik Dulu —</option>
              </select>
              <?php if (isset($errors['kd_dokter'])): ?>
                <div class="form-error"><?= $errors['kd_dokter'] ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-row col-2">
            <div class="form-group">
              <label class="form-label">Jenis Kunjungan</label>
              <select name="jns_pasien" id="selectJnsPasien" class="form-control">
                <option value="Baru" <?= ($prefill_data && empty($prefill_data['total_kunjungan'])) ? 'selected':'' ?>>Pasien Baru</option>
                <option value="Lama" <?= ($prefill_data && !empty($prefill_data['total_kunjungan'])) ? 'selected':'' ?>>Pasien Lama</option>
                <option value="Rujukan">Rujukan</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Penjamin Kunjungan <small style="color:var(--primary-600);">(Otomatis dari Profil Pasien)</small></label>
              <select name="kd_pj" id="selectPenjaminDaftar" class="form-control">
                <option value="">— Pilih —</option>
                <?php foreach ($penjab_list as $pj): ?>
                  <option value="<?= $pj['kd_pj'] ?>" <?= ($prefill_data['kd_pj'] ?? '') === $pj['kd_pj'] ? 'selected':'' ?>>
                    <?= htmlspecialchars($pj['nm_penjab']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row col-2">
            <div class="form-group">
              <label class="form-label">No. Kartu BPJS / Asuransi <small style="color:var(--primary-600);">(Otomatis Terisi)</small></label>
              <input type="text" name="no_peserta" id="inputNoPeserta" class="form-control"
                     value="<?= htmlspecialchars($prefill_data['no_peserta'] ?? '') ?>"
                     placeholder="Nomor kartu BPJS atau asuransi">
            </div>
            <div class="form-group">
              <label class="form-label">Keluhan Utama</label>
              <input type="text" name="keluhan" class="form-control" placeholder="Keluhan singkat pasien">
            </div>
          </div>
        </div>
      </div>

    </div><!-- /.kiri -->

    <!-- ─── Sidebar Kanan: Preview & Submit ──────────────────── -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Preview Kunjungan -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-receipt"></i> Preview Pendaftaran</div>
        </div>
        <div class="card-body" style="padding:0;">
          <?php
          $preview = [
            ['Tanggal',     date('d/m/Y')],
            ['Jam',         date('H:i')],
            ['No. Rawat',   '<span style="color:var(--gray-400);font-style:italic;">Generate otomatis</span>'],
          ];
          foreach ($preview as [$label, $val]):
          ?>
            <div style="display:flex;align-items:center;padding:10px 16px;border-bottom:1px solid var(--gray-100);">
              <span style="width:90px;font-size:11px;color:var(--gray-400);"><?= $label ?></span>
              <span style="font-size:12px;font-weight:500;"><?= $val ?></span>
            </div>
          <?php endforeach; ?>
          <div style="display:flex;align-items:center;padding:10px 16px;border-bottom:1px solid var(--gray-100);">
            <span style="width:90px;font-size:11px;color:var(--gray-400);">Pasien</span>
            <span style="font-size:12px;font-weight:500;" id="previewNama">—</span>
          </div>
          <div style="display:flex;align-items:center;padding:10px 16px;border-bottom:1px solid var(--gray-100);">
            <span style="width:90px;font-size:11px;color:var(--gray-400);">Poli</span>
            <span style="font-size:12px;font-weight:500;" id="previewPoli">—</span>
          </div>
          <div style="display:flex;align-items:center;padding:10px 16px;">
            <span style="width:90px;font-size:11px;color:var(--gray-400);">Dokter</span>
            <span style="font-size:12px;font-weight:500;" id="previewDokter">—</span>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="card">
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
            <i class="fas fa-check-circle"></i> Daftarkan Kunjungan
          </button>
          <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="btn btn-outline" style="width:100%;justify-content:center;">
            <i class="fas fa-times"></i> Batal
          </a>
        </div>
      </div>

    </div><!-- /.kanan -->
  </div>
</form>

<script>
// ─── Cari Pasien Autocomplete ─────────────────────────────────
let searchTimer = null;
const inputCari   = document.getElementById('inputCariPasien');
const resultBox   = document.getElementById('searchResults');
const hiddenRm    = document.getElementById('hiddenRm');
const infoPasien  = document.getElementById('infoPasien');

<?php if ($prefill_data): ?>
// Pre-fill info
document.getElementById('previewNama').textContent = '<?= addslashes($prefill_data['nm_pasien']) ?>';
infoPasien.style.display = 'block';
inputCari.style.display  = 'none';
<?php endif; ?>

inputCari?.addEventListener('input', function() {
  clearTimeout(searchTimer);
  const q = this.value.trim();
  if (q.length < 2) { resultBox.style.display = 'none'; return; }

  searchTimer = setTimeout(() => {
    fetch(`<?= BASE_URL ?>modules/pasien/ajax.php?action=cari&q=${encodeURIComponent(q)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      if (!data.success || data.data.length === 0) {
        resultBox.innerHTML = '<div style="padding:12px 16px;font-size:12px;color:var(--gray-400);">Pasien tidak ditemukan</div>';
      } else {
        resultBox.innerHTML = data.data.map(p => `
          <div class="search-result-item" onclick="pilihPasien('${p.no_rkm_medis}','${p.nm_pasien.replace(/'/g,"\\'")}','${p.jk}','${p.umur}','${p.nm_penjab||'Umum'}','${p.no_peserta||''}','${p.kd_pj||'UMU'}')"
               style="padding:10px 16px;cursor:pointer;border-bottom:1px solid var(--gray-100);transition:background 0.15s;">
            <div style="font-weight:600;font-size:13px;">${p.nm_pasien}</div>
            <div style="font-size:11px;color:var(--gray-400);">
              RM: ${p.no_rkm_medis} | ${p.jk==='L'?'Laki-laki':'Perempuan'} | ${p.umur}
              ${p.no_peserta ? '| BPJS/Asuransi: '+p.no_peserta : ''}
            </div>
          </div>
        `).join('');
      }
      resultBox.style.display = 'block';
    });
  }, 350);
});

document.addEventListener('click', (e) => {
  if (!e.target.closest('#searchResults') && !e.target.closest('#inputCariPasien')) {
    resultBox.style.display = 'none';
  }
});

// Hover effect on results
document.getElementById('searchResults').addEventListener('mouseover', (e) => {
  const item = e.target.closest('.search-result-item');
  if (item) item.style.background = 'var(--gray-50)';
});
document.getElementById('searchResults').addEventListener('mouseout', (e) => {
  const item = e.target.closest('.search-result-item');
  if (item) item.style.background = '';
});

function pilihPasien(rm, nama, jk, umur, penjamin, noPeserta, kdPj) {
  hiddenRm.value = rm;
  resultBox.style.display = 'none';
  inputCari.style.display = 'none';
  infoPasien.style.display = 'block';
  document.getElementById('infoPasienNama').textContent  = nama;
  document.getElementById('infoPasienRm').textContent    = 'RM: ' + rm;
  document.getElementById('infoPasienJkUmur').textContent = (jk==='L'?'Laki-laki':'Perempuan') + ' | ' + umur;
  document.getElementById('infoPasienPenjamin').textContent = penjamin;
  document.getElementById('previewNama').textContent = nama;

  // Otomatis isi penjamin & nomor kartu BPJS
  if (kdPj) {
    const selPj = document.getElementById('selectPenjaminDaftar');
    if (selPj) selPj.value = kdPj;
  }
  document.getElementById('inputNoPeserta').value = noPeserta || '';

  // Otomatis set jenis kunjungan ke pasien lama
  const selJns = document.getElementById('selectJnsPasien');
  if (selJns) selJns.value = 'Lama';
}

function clearPasien() {
  hiddenRm.value = '';
  inputCari.value = '';
  inputCari.style.display = '';
  infoPasien.style.display = 'none';
  document.getElementById('previewNama').textContent = '—';
  document.getElementById('inputNoPeserta').value = '';
  inputCari.focus();
}

// ─── Load Dokter by Poli ──────────────────────────────────────
document.getElementById('selectPoli')?.addEventListener('change', function() {
  const kd_poli     = this.value;
  const selectDokter = document.getElementById('selectDokter');
  const previewPoli  = document.getElementById('previewPoli');

  previewPoli.textContent = this.options[this.selectedIndex].text || '—';
  selectDokter.innerHTML  = '<option value="">Memuat...</option>';
  selectDokter.disabled   = true;

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
      selectDokter.innerHTML = '<option value="">— Pilih Dokter —</option>' +
        data.data.map(d => `<option value="${d.kd_dokter}">${d.nm_dokter}${d.nm_spesialis?' ('+d.nm_spesialis+')':''}</option>`).join('');
    }
  });
});

// Update preview dokter
document.getElementById('selectDokter')?.addEventListener('change', function() {
  document.getElementById('previewDokter').textContent = this.options[this.selectedIndex].text || '—';
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
