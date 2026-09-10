<?php
/**
 * SIMKlinik — Detail Pasien
 */

$page_title    = 'Detail Pasien';
$active_module = 'pasien';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_rm = sanitize($_GET['rm'] ?? '');
if (empty($no_rm)) redirect(BASE_URL . 'modules/pasien/index.php');

// Load data pasien
$rm_esc = $conn->real_escape_string($no_rm);
$result = $conn->query("
    SELECT p.*, pj.png_jawab as nm_penjab, kec.nm_kec, kab.nm_kab, prop.nm_prop
    FROM pasien p
    LEFT JOIN penjab pj    ON p.kd_pj   = pj.kd_pj
    LEFT JOIN kecamatan kec ON p.kd_kec = kec.kd_kec
    LEFT JOIN kabupaten kab ON p.kd_kab = kab.kd_kab
    LEFT JOIN propinsi prop ON p.kd_prop = prop.kd_prop
    WHERE p.no_rkm_medis = '$rm_esc'
    LIMIT 1
");

if (!$result || $result->num_rows === 0) {
    set_flash('danger', 'Data pasien tidak ditemukan.');
    redirect(BASE_URL . 'modules/pasien/index.php');
}

$pasien = $result->fetch_assoc();
$page_title = 'Pasien: ' . $pasien['nm_pasien'];

// Riwayat kunjungan
$kunjungan = $conn->query("
    SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.stts, r.status_lanjut,
           d.nm_dokter, pol.nm_poli,
           (SELECT GROUP_CONCAT(py.nm_penyakit SEPARATOR ', ') FROM diagnosa_pasien dp JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit WHERE dp.no_rawat = r.no_rawat LIMIT 1) as diagnosa
    FROM reg_periksa r
    LEFT JOIN dokter d    ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    WHERE r.no_rkm_medis = '$rm_esc'
    ORDER BY r.tgl_registrasi DESC, r.jam_reg DESC
    LIMIT 20
");
$kunjungan_list = [];
if ($kunjungan) while ($row = $kunjungan->fetch_assoc()) $kunjungan_list[] = $row;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Breadcrumb ───────────────────────────────────────── -->
<div class="breadcrumb">
  <a href="<?= BASE_URL ?>modules/pasien/index.php">Data Pasien</a>
  <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
  <span class="breadcrumb-current"><?= htmlspecialchars($pasien['nm_pasien']) ?></span>
</div>

<!-- ─── Header Profil ────────────────────────────────────── -->
<div class="card mb-16" style="background:linear-gradient(135deg,var(--primary-600),var(--primary-800));color:#fff;border:none;">
  <div class="card-body" style="padding:24px 28px;">
    <div style="display:flex;align-items:center;gap:20px;">
      <!-- Avatar -->
      <div style="width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;flex-shrink:0;border:3px solid rgba(255,255,255,0.3);">
        <?= strtoupper(substr($pasien['nm_pasien'], 0, 1)) ?>
      </div>
      <!-- Info -->
      <div style="flex:1;">
        <div style="font-size:22px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($pasien['nm_pasien']) ?></div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:13px;opacity:0.85;">
          <span><i class="fas fa-id-card" style="margin-right:5px;opacity:0.7;"></i>No. RM: <strong><?= $pasien['no_rkm_medis'] ?></strong></span>
          <span><?= $pasien['jk']==='L' ? '👨 Laki-laki' : '👩 Perempuan' ?></span>
          <span><i class="fas fa-birthday-cake" style="margin-right:5px;opacity:0.7;"></i><?= tgl_indo($pasien['tgl_lahir']) ?> (<?= hitung_umur($pasien['tgl_lahir']) ?>)</span>
          <span><i class="fas fa-shield-alt" style="margin-right:5px;opacity:0.7;"></i><?= htmlspecialchars($pasien['nm_penjab'] ?: 'Umum') ?></span>
        </div>
      </div>
      <!-- Actions -->
      <div style="display:flex;gap:8px;flex-shrink:0;">
        <a href="<?= BASE_URL ?>modules/pendaftaran/tambah.php?rm=<?= urlencode($no_rm) ?>"
           class="btn" style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.3);">
          <i class="fas fa-plus"></i> Daftar Kunjungan
        </a>
        <a href="<?= BASE_URL ?>modules/pasien/edit.php?rm=<?= urlencode($no_rm) ?>"
           class="btn" style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.3);">
          <i class="fas fa-edit"></i> Edit
        </a>
      </div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:320px 1fr;gap:16px;align-items:start;">

  <!-- ─── Kolom Kiri: Data Pasien ─────────────────────────── -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Identitas -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-user"></i> Identitas</div>
      </div>
      <div class="card-body" style="padding:0;">
        <?php
        $rows = [
          ['NIK / KTP',     $pasien['no_ktp'] ?: '-'],
          ['Tmp. Lahir',    $pasien['tmp_lahir'] ?: '-'],
          ['Gol. Darah',    $pasien['gol_darah'] ?: '-'],
          ['Agama',         $pasien['agama'] ?: '-'],
          ['Pendidikan',    $pasien['pnd'] ?: '-'],
          ['Pekerjaan',     $pasien['pekerjaan'] ?: '-'],
          ['Status Nikah',  $pasien['stts_nikah'] ?: '-'],
          ['No. Telepon',   $pasien['no_tlp'] ?: '-'],
          ['Email',         $pasien['email'] ?: '-'],
        ];
        foreach ($rows as [$label, $val]):
        ?>
          <div style="display:flex;padding:10px 16px;border-bottom:1px solid var(--gray-100);">
            <span style="width:120px;font-size:11px;color:var(--gray-400);flex-shrink:0;"><?= $label ?></span>
            <span style="font-size:12px;color:var(--gray-700);font-weight:500;"><?= htmlspecialchars($val) ?></span>
          </div>
        <?php endforeach; ?>
        <div style="padding:10px 16px;border-bottom:1px solid var(--gray-100);">
          <span style="display:block;font-size:11px;color:var(--gray-400);">Alamat</span>
          <span style="font-size:12px;color:var(--gray-700);"><?= htmlspecialchars($pasien['alamat'] ?: '-') ?></span>
        </div>
        <div style="padding:10px 16px;">
          <span style="font-size:11px;color:var(--gray-400);">Terdaftar Sejak</span>
          <span style="display:block;font-size:12px;color:var(--gray-700);"><?= tgl_indo($pasien['tgl_daftar']) ?></span>
        </div>
      </div>
    </div>

    <!-- Penjamin -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-shield-alt"></i> Penjamin</div>
      </div>
      <div class="card-body" style="padding:0;">
        <?php
        $rows2 = [
          ['Penjamin',    $pasien['nm_penjab'] ?: 'Umum'],
          ['No. Peserta', $pasien['no_peserta'] ?: '-'],
          ['Penanggung',  $pasien['keluarga'] . ' - ' . $pasien['namakeluarga']],
        ];
        foreach ($rows2 as [$label, $val]):
        ?>
          <div style="display:flex;padding:10px 16px;border-bottom:1px solid var(--gray-100);">
            <span style="width:100px;font-size:11px;color:var(--gray-400);flex-shrink:0;"><?= $label ?></span>
            <span style="font-size:12px;color:var(--gray-700);font-weight:500;"><?= htmlspecialchars($val) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- ─── Kolom Kanan: Riwayat Kunjungan ──────────────────── -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-history"></i> Riwayat Kunjungan</div>
      <span style="font-size:12px;color:var(--gray-500);"><?= count($kunjungan_list) ?> kunjungan terakhir</span>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($kunjungan_list)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="fas fa-file-medical"></i></div>
          <div class="empty-state-title">Belum ada riwayat kunjungan</div>
          <div class="empty-state-desc">
            <a href="<?= BASE_URL ?>modules/pendaftaran/tambah.php?rm=<?= urlencode($no_rm) ?>"
               class="btn btn-primary btn-sm" style="margin-top:10px;">
              <i class="fas fa-plus"></i> Daftar Kunjungan Sekarang
            </a>
          </div>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>No. Rawat</th>
                <th>Poliklinik / Dokter</th>
                <th>Diagnosa</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($kunjungan_list as $k): ?>
                <tr>
                  <td>
                    <div style="font-size:12px;font-weight:500;"><?= tgl_indo($k['tgl_registrasi']) ?></div>
                    <div style="font-size:11px;color:var(--gray-400);"><?= substr($k['jam_reg'],0,5) ?></div>
                  </td>
                  <td><span style="font-family:monospace;font-size:11px;color:var(--gray-500);"><?= $k['no_rawat'] ?></span></td>
                  <td>
                    <div style="font-size:12px;"><?= htmlspecialchars($k['nm_poli'] ?? '-') ?></div>
                    <div style="font-size:11px;color:var(--gray-400);"><?= htmlspecialchars($k['nm_dokter'] ?? '-') ?></div>
                  </td>
                  <td>
                    <div style="font-size:11px;color:var(--gray-600);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                      <?= htmlspecialchars($k['diagnosa'] ?: '-') ?>
                    </div>
                  </td>
                  <td><?= badge_status($k['stts']) ?></td>
                  <td>
                    <a href="<?= BASE_URL ?>modules/rekam_medis/periksa.php?no_rawat=<?= urlencode($k['no_rawat']) ?>"
                       class="btn btn-sm btn-outline-primary btn-icon" title="Buka rekam medis">
                      <i class="fas fa-file-medical"></i>
                    </a>
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

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
