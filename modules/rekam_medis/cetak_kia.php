<?php
/**
 * SIMKlinik — Cetak Lembar Rekam Medis KIA, ANC & KMS Anak
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_rawat = sanitize($_GET['no_rawat'] ?? '');
$tipe     = sanitize($_GET['tipe'] ?? 'anc'); // 'anc' atau 'kms'
if (empty($no_rawat)) die('No. Rawat tidak valid.');

$rawat_esc = $conn->real_escape_string($no_rawat);

// Load Pasien & Registrasi
$res = $conn->query("
    SELECT r.*, p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_peserta,
           p.alamat, p.gol_darah, p.no_tlp, p.agama, p.pekerjaan,
           d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE r.no_rawat = '$rawat_esc'
    LIMIT 1
");

if (!$res || $res->num_rows === 0) die('Data pasien tidak ditemukan.');
$pasien = $res->fetch_assoc();
$no_rkm_medis = $conn->real_escape_string($pasien['no_rkm_medis']);

// Load ANC
$anc = null;
$qANC = $conn->query("SELECT * FROM mlite_kia_anc WHERE no_rawat = '$rawat_esc' LIMIT 1");
if (!$qANC || $qANC->num_rows === 0) {
    $qANC = $conn->query("SELECT * FROM mlite_kia_anc WHERE no_rkm_medis = '$no_rkm_medis' ORDER BY tgl_perawatan DESC LIMIT 1");
}
if ($qANC && $qANC->num_rows > 0) $anc = $qANC->fetch_assoc();

// Load KMS
$kms = null;
$qKMS = $conn->query("SELECT * FROM mlite_kia_kms WHERE no_rawat = '$rawat_esc' LIMIT 1");
if (!$qKMS || $qKMS->num_rows === 0) {
    $qKMS = $conn->query("SELECT * FROM mlite_kia_kms WHERE no_rkm_medis = '$no_rkm_medis' ORDER BY tgl_perawatan DESC LIMIT 1");
}
if ($qKMS && $qKMS->num_rows > 0) $kms = $qKMS->fetch_assoc();

// Load Riwayat Imunisasi
$imunisasi = [];
$qI = $conn->query("SELECT * FROM mlite_kia_imunisasi WHERE no_rkm_medis = '$no_rkm_medis' ORDER BY tgl_imunisasi ASC");
if ($qI) while ($r = $qI->fetch_assoc()) $imunisasi[] = $r;

// Data Klinik
$clinic_name    = setting('nama_instansi', 'SIM KLINIK PRATAMA');
$clinic_address = setting('alamat_instansi', 'Jl. Kesehatan No. 123');
$clinic_phone   = setting('kontak_instansi', 'Telp. 0812-3456-7890');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekam Medis KIA - <?= htmlspecialchars($pasien['nm_pasien']) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @page { size: A4 portrait; margin: 12mm; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; color: #1e293b; line-height: 1.4; margin: 0; padding: 15px; }
    .header-kop { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; }
    .kop-title { font-size: 15pt; font-weight: bold; text-transform: uppercase; margin: 0; color: #0f172a; }
    .kop-sub { font-size: 8.5pt; color: #475569; margin-top: 2px; }
    .doc-title { text-align: center; font-size: 12.5pt; font-weight: bold; text-decoration: underline; margin-bottom: 12px; text-transform: uppercase; color: #0f172a; }
    .table-info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .table-info td { padding: 2px 4px; font-size: 9.5pt; vertical-align: top; }
    .table-info td.label { width: 20%; color: #475569; font-weight: 600; }
    .table-info td.sep { width: 2%; text-align: center; }
    .section-head { background: #fdf2f8; color: #9d174d; padding: 4px 8px; font-weight: bold; font-size: 10pt; border: 1px solid #fbcfe8; margin-top: 10px; margin-bottom: 6px; border-radius: 4px; }
    .table-eval { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 9.5pt; }
    .table-eval th, .table-eval td { border: 1px solid #cbd5e1; padding: 5px 8px; }
    .table-eval th { background: #f8fafc; text-align: left; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 8.5pt; font-weight: bold; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .signature-row { display: flex; justify-content: flex-end; margin-top: 25px; text-align: center; }
    .signature-box { width: 220px; font-size: 9.5pt; }
    .no-print { margin-bottom: 15px; text-align: right; }
    @media print {
      body { padding: 0; }
      .no-print { display: none; }
    }
  </style>
</head>
<body>

  <div class="no-print">
    <button onclick="window.print()" style="padding: 7px 15px; background: #db2777; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
      <i class="fa fa-print"></i> Cetak Dokumen
    </button>
    <button onclick="window.close()" style="padding: 7px 15px; background: #64748b; color: #fff; border: none; border-radius: 6px; cursor: pointer; margin-left: 5px;">
      Tutup
    </button>
  </div>

  <!-- Header / Kop -->
  <div class="header-kop">
    <div>
      <div class="kop-title"><?= htmlspecialchars($clinic_name) ?></div>
      <div class="kop-sub"><?= htmlspecialchars($clinic_address) ?> | <?= htmlspecialchars($clinic_phone) ?></div>
    </div>
    <div style="text-align: right; font-size: 8.5pt; color: #64748b;">
      <div><strong>Tgl Cetak:</strong> <?= date('d/m/Y H:i') ?></div>
      <div><strong>No. Rawat:</strong> <?= htmlspecialchars($pasien['no_rawat']) ?></div>
    </div>
  </div>

  <div class="doc-title">REKAM MEDIS KESEHATAN IBU & ANAK (KIA / KMS)</div>

  <!-- Identitas Pasien -->
  <table class="table-info">
    <tr>
      <td class="label">No. Rekam Medis</td>
      <td class="sep">:</td>
      <td><strong><?= htmlspecialchars($pasien['no_rkm_medis']) ?></strong></td>
      <td class="label">Dokter / Bidan</td>
      <td class="sep">:</td>
      <td><?= htmlspecialchars($pasien['nm_dokter'] ?? '-') ?></td>
    </tr>
    <tr>
      <td class="label">Nama Pasien</td>
      <td class="sep">:</td>
      <td><strong><?= htmlspecialchars($pasien['nm_pasien']) ?></strong> (<?= $pasien['jk'] == 'L' ? 'Laki-laki' : 'Perempuan' ?>)</td>
      <td class="label">Poli / Unit</td>
      <td class="sep">:</td>
      <td><?= htmlspecialchars($pasien['nm_poli'] ?? 'Poli KIA') ?></td>
    </tr>
    <tr>
      <td class="label">Tgl Lahir / Umur</td>
      <td class="sep">:</td>
      <td><?= date('d/m/Y', strtotime($pasien['tgl_lahir'])) ?> (<?= hitung_umur($pasien['tgl_lahir']) ?> th)</td>
      <td class="label">No. HP / Telepon</td>
      <td class="sep">:</td>
      <td><?= htmlspecialchars($pasien['no_tlp'] ?? '-') ?></td>
    </tr>
  </table>

  <?php if ($anc): ?>
  <!-- Form ANC Kehamilan -->
  <div class="section-head"><i class="fa fa-person-breastfeeding"></i> I. PEMERIKSAAN ANTENATAL CARE (ANC IBU HAMIL)</div>
  <table class="table-eval">
    <tr>
      <th style="width: 25%;">Status Obstetri</th>
      <td style="width: 25%;"><strong>G:</strong> <?= $anc['g_hamil'] ?> <strong>P:</strong> <?= $anc['p_partus'] ?> <strong>A:</strong> <?= $anc['a_abortus'] ?> <strong>H:</strong> <?= $anc['h_hidup'] ?></td>
      <th style="width: 25%;">Skor KSPR / Risiko</th>
      <td style="width: 25%;">
        Skor <?= $anc['skor_kspr'] ?> - 
        <span class="badge <?= strpos($anc['resiko_kehamilan'], 'Tinggi') !== false ? 'badge-danger' : 'badge-success' ?>">
          <?= htmlspecialchars($anc['resiko_kehamilan']) ?>
        </span>
      </td>
    </tr>
    <tr>
      <th>HPHT & HPL</th>
      <td>
        HPHT: <?= !empty($anc['hpht']) ? date('d/m/Y', strtotime($anc['hpht'])) : '-' ?><br>
        HPL: <strong><?= !empty($anc['hpl']) ? date('d/m/Y', strtotime($anc['hpl'])) : '-' ?></strong>
      </td>
      <th>Usia Kehamilan (UK)</th>
      <td><strong><?= htmlspecialchars($anc['usia_kehamilan'] ?? '-') ?></strong></td>
    </tr>
    <tr>
      <th>TFU & DJJ</th>
      <td>TFU: <strong><?= htmlspecialchars($anc['tfu'] ?? '-') ?> cm</strong> | DJJ: <strong><?= htmlspecialchars($anc['djj'] ?? '-') ?> dpm</strong></td>
      <th>Letak / Presentasi Janin</th>
      <td><?= htmlspecialchars($anc['letak_janin'] ?? 'Kepala') ?></td>
    </tr>
    <tr>
      <th>Pemeriksaan Leopold</th>
      <td colspan="3">
        <strong>L1:</strong> <?= htmlspecialchars($anc['leopold_1'] ?? '-') ?> | 
        <strong>L2:</strong> <?= htmlspecialchars($anc['leopold_2'] ?? '-') ?><br>
        <strong>L3:</strong> <?= htmlspecialchars($anc['leopold_3'] ?? '-') ?> | 
        <strong>L4:</strong> <?= htmlspecialchars($anc['leopold_4'] ?? '-') ?>
      </td>
    </tr>
    <tr>
      <th>Edema & Refleks</th>
      <td>Edema: <?= htmlspecialchars($anc['edema'] ?? 'Tidak') ?> | Refl. Patella: <?= htmlspecialchars($anc['refl_patella'] ?? '+') ?></td>
      <th>Tindakan / Terapi Fe</th>
      <td><?= htmlspecialchars($anc['tindakan_kia'] ?? '-') ?></td>
    </tr>
    <tr>
      <th>Saran / Edukasi Bidan</th>
      <td colspan="3"><?= nl2br(htmlspecialchars($anc['saran_nasehat'] ?? '-')) ?></td>
    </tr>
  </table>
  <?php endif; ?>

  <?php if ($kms): ?>
  <!-- Form KMS Tumbuh Kembang -->
  <div class="section-head" style="background:#f0fdf4;color:#166534;border-color:#bbf7d0;margin-top:15px;">
    <i class="fa fa-baby"></i> II. EVALUASI TUMBUH KEMBANG & ANTROPOMETRI ANAK (KMS)
  </div>
  <table class="table-eval">
    <tr>
      <th style="width: 25%;">Umur Anak</th>
      <td style="width: 25%;"><strong><?= $kms['umur_bln'] ?> Bulan</strong></td>
      <th style="width: 25%;">Status Gizi (BB/U)</th>
      <td style="width: 25%;"><strong><?= htmlspecialchars($kms['status_gizi_bb_u'] ?? 'Normal') ?></strong></td>
    </tr>
    <tr>
      <th>Antropometri</th>
      <td colspan="3">
        <strong>BB:</strong> <?= htmlspecialchars($kms['bb'] ?? '-') ?> kg &nbsp;|&nbsp; 
        <strong>TB/PB:</strong> <?= htmlspecialchars($kms['tb'] ?? '-') ?> cm &nbsp;|&nbsp; 
        <strong>LK:</strong> <?= htmlspecialchars($kms['lk'] ?? '-') ?> cm &nbsp;|&nbsp; 
        <strong>LiLA:</strong> <?= htmlspecialchars($kms['lila'] ?? '-') ?> cm
      </td>
    </tr>
    <tr>
      <th>Nutrisi & Suplementasi</th>
      <td colspan="3">
        ASI Eksklusif: <strong><?= htmlspecialchars($kms['asi_eksklusif'] ?? 'Ya') ?></strong> &nbsp;|&nbsp;
        Vitamin A: <strong><?= htmlspecialchars($kms['vit_a'] ?? 'Tidak') ?></strong> &nbsp;|&nbsp;
        Obat Cacing: <strong><?= htmlspecialchars($kms['obat_cacing'] ?? 'Tidak') ?></strong>
      </td>
    </tr>
    <tr>
      <th>Perkembangan Motorik</th>
      <td colspan="3"><?= nl2br(htmlspecialchars($kms['perkembangan_motorik'] ?? '-')) ?></td>
    </tr>
  </table>
  <?php endif; ?>

  <!-- Riwayat Imunisasi Anak -->
  <?php if (!empty($imunisasi)): ?>
  <div class="section-head" style="background:#eff6ff;color:#1e40af;border-color:#bfdbfe;margin-top:15px;">
    <i class="fa fa-syringe"></i> III. BUKTI IMUNISASI DASAR LENGKAP
  </div>
  <table class="table-eval">
    <thead>
      <tr style="background:#f1f5f9;">
        <th style="width: 5%;">No</th>
        <th style="width: 20%;">Tanggal</th>
        <th style="width: 35%;">Jenis Vaksin / Imunisasi</th>
        <th style="width: 20%;">No. Batch</th>
        <th style="width: 20%;">Keterangan</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($imunisasi as $i => $im): ?>
      <tr>
        <td style="text-align:center;"><?= $i + 1 ?></td>
        <td><?= date('d/m/Y', strtotime($im['tgl_imunisasi'])) ?></td>
        <td><strong><?= htmlspecialchars($im['jenis_imunisasi']) ?></strong></td>
        <td><?= htmlspecialchars($im['no_batch'] ?? '-') ?></td>
        <td><?= htmlspecialchars($im['keterangan'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Tanda Tangan -->
  <div class="signature-row">
    <div class="signature-box">
      <div><?= setting('kota_instansi', 'Indonesia') ?>, <?= date('d F Y') ?></div>
      <div style="margin-top: 5px;">Tenaga Medis / Bidan Pemeriksa,</div>
      <div style="height: 60px;"></div>
      <div style="font-weight: bold; text-decoration: underline;"><?= htmlspecialchars($pasien['nm_dokter'] ?? 'Bidan Pemeriksa') ?></div>
      <div style="font-size: 8.5pt; color: #64748b;">SIP/STR: -</div>
    </div>
  </div>

</body>
</html>
