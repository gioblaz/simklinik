<?php
/**
 * SIMKlinik — Cetak Lembar Rekam Medis / Resume Medis Rawat Jalan
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_rawat = sanitize($_GET['no_rawat'] ?? '');
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

// Load SOAP
$soap_res = $conn->query("
    SELECT * FROM pemeriksaan_ralan
    WHERE no_rawat = '$rawat_esc'
    ORDER BY tgl_perawatan DESC, jam_rawat DESC
    LIMIT 1
");
$soap = ($soap_res && $soap_res->num_rows > 0) ? $soap_res->fetch_assoc() : [];

// Load Diagnosa ICD-10
$diag_res = $conn->query("
    SELECT dp.*, p.nm_penyakit
    FROM diagnosa_pasien dp
    JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
    WHERE dp.no_rawat = '$rawat_esc'
    ORDER BY dp.prioritas ASC
");
$diagnosa_list = [];
if ($diag_res) while ($row = $diag_res->fetch_assoc()) $diagnosa_list[] = $row;

// Load Resep
$resep_res = $conn->query("
    SELECT rd.jml, rd.aturan_pakai, db.nama_brng, ks.satuan
    FROM resep_obat ro
    JOIN resep_dokter rd ON ro.no_resep = rd.no_resep
    JOIN databarang db ON rd.kode_brng = db.kode_brng
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    WHERE ro.no_rawat = '$rawat_esc'
");
$resep_list = [];
if ($resep_res) while ($row = $resep_res->fetch_assoc()) $resep_list[] = $row;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Resume Medis - <?= htmlspecialchars($pasien['nm_pasien']) ?></title>
  <style>
    @page { size: A4 portrait; margin: 15mm; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #111; line-height: 1.4; margin: 0; padding: 20px; }
    .header-kop { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; }
    .kop-title { font-size: 16pt; font-weight: bold; text-transform: uppercase; margin: 0; }
    .kop-sub { font-size: 9pt; color: #444; margin-top: 2px; }
    .doc-title { text-align: center; font-size: 13pt; font-weight: bold; text-decoration: underline; margin-bottom: 15px; text-transform: uppercase; }
    .table-info { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .table-info td { padding: 3px 6px; font-size: 10.5pt; vertical-align: top; }
    .table-info td.label { width: 22%; color: #333; }
    .table-info td.sep { width: 2%; text-align: center; }
    .section-head { background: #f0f0f0; padding: 4px 8px; font-weight: bold; font-size: 10.5pt; border: 1px solid #ccc; margin-top: 10px; margin-bottom: 6px; }
    .content-box { border: 1px solid #ddd; padding: 8px 10px; font-size: 10.5pt; min-height: 25px; margin-bottom: 8px; background: #fafafa; border-radius: 4px; }
    .vitals-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 8px; }
    .vital-item { border: 1px solid #ddd; padding: 6px; border-radius: 4px; font-size: 9.5pt; }
    .vital-item strong { display: block; font-size: 11pt; color: #000; }
    .signature-row { display: flex; justify-content: flex-end; margin-top: 30px; text-align: center; }
    .signature-box { width: 220px; }
    .no-print { margin-bottom: 20px; text-align: right; }
    @media print {
      body { padding: 0; }
      .no-print { display: none; }
    }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()" style="padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:bold;">
    🖨️ Cetak Dokumen
  </button>
</div>

<!-- ─── KOP SURAT ────────────────────────────────────────── -->
<div class="header-kop">
  <div>
    <h1 class="kop-title"><?= INSTANSI_NAMA ?></h1>
    <div class="kop-sub"><?= INSTANSI_ALAMAT ?>, <?= INSTANSI_KOTA ?> &mdash; Telp: <?= INSTANSI_TELP ?></div>
  </div>
  <div style="text-align:right;font-size:9pt;color:#666;">
    <div>No. Rawat: <strong><?= $pasien['no_rawat'] ?></strong></div>
    <div>Tanggal: <?= tgl_indo($pasien['tgl_registrasi']) ?></div>
  </div>
</div>

<div class="doc-title">Lembar Ringkasan Rekam Medis (Resume Medis)</div>

<!-- ─── DATA PASIEN ──────────────────────────────────────── -->
<table class="table-info">
  <tr>
    <td class="label">Nama Pasien</td><td class="sep">:</td>
    <td><strong><?= htmlspecialchars($pasien['nm_pasien']) ?></strong></td>
    <td class="label">No. Rekam Medis</td><td class="sep">:</td>
    <td><strong><?= $pasien['no_rkm_medis'] ?></strong></td>
  </tr>
  <tr>
    <td class="label">Jenis Kelamin / Umur</td><td class="sep">:</td>
    <td><?= $pasien['jk']==='L'?'Laki-laki':'Perempuan' ?> / <?= hitung_umur($pasien['tgl_lahir']) ?></td>
    <td class="label">Tanggal Lahir</td><td class="sep">:</td>
    <td><?= tgl_indo($pasien['tgl_lahir']) ?></td>
  </tr>
  <tr>
    <td class="label">Poliklinik Tujuan</td><td class="sep">:</td>
    <td><?= htmlspecialchars($pasien['nm_poli']) ?></td>
    <td class="label">Dokter Pemeriksa</td><td class="sep">:</td>
    <td><?= htmlspecialchars($pasien['nm_dokter']) ?></td>
  </tr>
  <tr>
    <td class="label">Cara Bayar / Penjamin</td><td class="sep">:</td>
    <td><?= htmlspecialchars($pasien['nm_penjab'] ?: 'Umum') ?> <?= $pasien['no_peserta'] ? '('.$pasien['no_peserta'].')' : '' ?></td>
    <td class="label">Alamat</td><td class="sep">:</td>
    <td><?= htmlspecialchars($pasien['alamat'] ?: '-') ?></td>
  </tr>
</table>

<!-- ─── TANDA VITAL ──────────────────────────────────────── -->
<div class="section-head">I. TANDA-TANDA VITAL & FISIK</div>
<div class="vitals-grid">
  <div class="vital-item">
    Tekanan Darah
    <strong><?= htmlspecialchars(!empty($soap['tensi']) ? $soap['tensi'] : '-') ?> mmHg</strong>
  </div>
  <div class="vital-item">
    Nadi
    <strong><?= htmlspecialchars(!empty($soap['nadi']) ? $soap['nadi'] : '-') ?> x/m</strong>
  </div>
  <div class="vital-item">
    Suhu Tubuh
    <strong><?= htmlspecialchars(!empty($soap['suhu_tubuh']) ? $soap['suhu_tubuh'] : '-') ?> °C</strong>
  </div>
  <div class="vital-item">
    Respirasi (RR)
    <strong><?= htmlspecialchars(!empty($soap['respirasi']) ? $soap['respirasi'] : '-') ?> x/m</strong>
  </div>
  <div class="vital-item">
    SpO2
    <strong><?= htmlspecialchars(!empty($soap['spo2']) ? $soap['spo2'] : '-') ?> %</strong>
  </div>
  <div class="vital-item">
    Tinggi / Berat Badan
    <strong><?= htmlspecialchars(!empty($soap['tinggi']) ? $soap['tinggi'] : '-') ?> cm / <?= htmlspecialchars(!empty($soap['berat']) ? $soap['berat'] : '-') ?> kg</strong>
  </div>
  <div class="vital-item">
    Kesadaran
    <strong><?= htmlspecialchars(!empty($soap['kesadaran']) ? $soap['kesadaran'] : 'Compos Mentis') ?></strong>
  </div>
  <div class="vital-item">
    Riwayat Alergi
    <strong><?= htmlspecialchars(!empty($soap['alergi']) ? $soap['alergi'] : 'Tidak Ada') ?></strong>
  </div>
</div>

<!-- ─── SOAP ─────────────────────────────────────────────── -->
<div class="section-head">II. CATATAN MEDIS (SOAP)</div>

<div style="margin-bottom:6px;"><strong>Anamnesis / Keluhan Utama (S):</strong></div>
<div class="content-box"><?= nl2br(htmlspecialchars($soap['keluhan'] ?? '-')) ?></div>

<div style="margin-bottom:6px;"><strong>Pemeriksaan Fisik & Penunjang (O):</strong></div>
<div class="content-box"><?= nl2br(htmlspecialchars($soap['pemeriksaan'] ?? '-')) ?></div>

<div style="margin-bottom:6px;"><strong>Asesmen / Penilaian Klinis (A):</strong></div>
<div class="content-box"><?= nl2br(htmlspecialchars($soap['penilaian'] ?? '-')) ?></div>

<div style="margin-bottom:6px;"><strong>Rencana Tindak Lanjut / Plan (P):</strong></div>
<div class="content-box"><?= nl2br(htmlspecialchars($soap['rtl'] ?? '-')) ?></div>

<!-- ─── DIAGNOSA ICD-10 ──────────────────────────────────── -->
<div class="section-head">III. DIAGNOSA ICD-10</div>
<?php if (empty($diagnosa_list)): ?>
  <div class="content-box">-</div>
<?php else: ?>
  <table style="width:100%;border-collapse:collapse;margin-bottom:8px;font-size:10pt;">
    <thead>
      <tr style="background:#f4f4f4;">
        <th style="border:1px solid #ccc;padding:4px 8px;text-align:left;width:15%;">Jenis</th>
        <th style="border:1px solid #ccc;padding:4px 8px;text-align:left;width:15%;">Kode ICD-10</th>
        <th style="border:1px solid #ccc;padding:4px 8px;text-align:left;">Nama Penyakit</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($diagnosa_list as $d): ?>
        <tr>
          <td style="border:1px solid #ccc;padding:4px 8px;"><?= $d['prioritas']==1 ? 'Utama / Primer' : 'Sekunder' ?></td>
          <td style="border:1px solid #ccc;padding:4px 8px;font-weight:bold;"><?= htmlspecialchars($d['kd_penyakit']) ?></td>
          <td style="border:1px solid #ccc;padding:4px 8px;"><?= htmlspecialchars($d['nm_penyakit']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<!-- ─── RESEP OBAT ───────────────────────────────────────── -->
<div class="section-head">IV. TERAPI / RESEP OBAT DIBERIKAN</div>
<?php if (empty($resep_list)): ?>
  <div class="content-box">Tidak ada peresepan obat.</div>
<?php else: ?>
  <table style="width:100%;border-collapse:collapse;margin-bottom:8px;font-size:10pt;">
    <thead>
      <tr style="background:#f4f4f4;">
        <th style="border:1px solid #ccc;padding:4px 8px;text-align:left;">Nama Obat</th>
        <th style="border:1px solid #ccc;padding:4px 8px;text-align:center;width:15%;">Jumlah</th>
        <th style="border:1px solid #ccc;padding:4px 8px;text-align:left;width:35%;">Aturan Pakai</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($resep_list as $r): ?>
        <tr>
          <td style="border:1px solid #ccc;padding:4px 8px;font-weight:bold;"><?= htmlspecialchars($r['nama_brng']) ?></td>
          <td style="border:1px solid #ccc;padding:4px 8px;text-align:center;"><?= $r['jml'] ?> <?= htmlspecialchars($r['satuan']?:'tab') ?></td>
          <td style="border:1px solid #ccc;padding:4px 8px;"><?= htmlspecialchars($r['aturan_pakai'] ?: '-') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<!-- ─── TANDA TANGAN DOKTER ──────────────────────────────── -->
<div class="signature-row">
  <div class="signature-box">
    <div><?= INSTANSI_KOTA ?>, <?= tgl_indo(date('Y-m-d')) ?></div>
    <div style="margin-bottom:60px;">Dokter Pemeriksa,</div>
    <div style="font-weight:bold;text-decoration:underline;"><?= htmlspecialchars($pasien['nm_dokter']) ?></div>
    <div style="font-size:9pt;color:#555;">SIP: <?= htmlspecialchars($pasien['no_rawat']) ?></div>
  </div>
</div>

</body>
</html>
