<?php
/**
 * SIMKlinik — Cetak Lembar Rekam Medis Odontogram (Poli Gigi & Mulut)
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_rawat = sanitize($_GET['no_rawat'] ?? '');
if (empty($no_rawat)) die('No. Rawat tidak valid.');

$rawat_esc = $conn->real_escape_string($no_rawat);

// Load Data Pasien & Registrasi
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

// Load Header Odontogram
$odont_res = $conn->query("SELECT * FROM mlite_odontogram WHERE no_rawat = '$rawat_esc' LIMIT 1");
if (!$odont_res || $odont_res->num_rows === 0) {
    // Fallback to latest for patient
    $odont_res = $conn->query("SELECT * FROM mlite_odontogram WHERE no_rkm_medis = '$no_rkm_medis' ORDER BY tgl_perawatan DESC LIMIT 1");
}
$odont = ($odont_res && $odont_res->num_rows > 0) ? $odont_res->fetch_assoc() : [];

// Load Detail Gigi
$teeth_data = [];
$qT = $conn->query("SELECT * FROM mlite_odontogram_detail WHERE no_rkm_medis = '$no_rkm_medis' ORDER BY no_gigi ASC");
if ($qT) {
    while ($r = $qT->fetch_assoc()) {
        $key = $r['no_gigi'] . '_' . $r['posisi'];
        $teeth_data[$key] = $r;
    }
}

// Hitung DMF-T
$d_cnt = (int)($odont['d_val'] ?? 0);
$m_cnt = (int)($odont['m_val'] ?? 0);
$f_cnt = (int)($odont['f_val'] ?? 0);
$dmft_total = (int)($odont['dmft_val'] ?? ($d_cnt + $m_cnt + $f_cnt));

// Data Klinik
$clinic_name    = setting('nama_instansi', 'SIM KLINIK PRATAMA');
$clinic_address = setting('alamat_instansi', 'Jl. Kesehatan No. 123');
$clinic_phone   = setting('kontak_instansi', 'Telp. 0812-3456-7890');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekam Medis Odontogram - <?= htmlspecialchars($pasien['nm_pasien']) ?></title>
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
    .section-head { background: #f1f5f9; padding: 4px 8px; font-weight: bold; font-size: 10pt; border: 1px solid #cbd5e1; margin-top: 10px; margin-bottom: 6px; border-radius: 4px; }
    
    /* Odontogram Grid */
    .odont-container { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; background: #fafafa; margin-bottom: 12px; }
    .quadrant-title { font-size: 8.5pt; font-weight: bold; color: #64748b; text-align: center; margin-bottom: 4px; }
    .teeth-row { display: flex; justify-content: center; gap: 4px; margin-bottom: 6px; }
    .tooth-box { width: 34px; border: 1px solid #94a3b8; border-radius: 4px; background: #ffffff; text-align: center; font-size: 8pt; padding: 2px; }
    .tooth-num { font-weight: bold; font-size: 7.5pt; background: #e2e8f0; border-radius: 2px; margin-bottom: 2px; padding: 1px 0; }
    .tooth-cond { font-size: 7.5pt; font-weight: bold; min-height: 14px; display: flex; align-items: center; justify-content: center; border-radius: 2px; }
    
    .cond-Car { background: #fee2e2; color: #dc2626; }
    .cond-Amf { background: #e2e8f0; color: #334155; }
    .cond-Gif { background: #ecfdf5; color: #059669; }
    .cond-Mis { background: #1e293b; color: #ffffff; }
    .cond-Rad { background: #fef3c7; color: #d97706; }
    .cond-Cro { background: #e0e7ff; color: #4338ca; }
    .cond-Imp { background: #f3e8ff; color: #7e22ce; }
    .cond-Sou { background: #f8fafc; color: #64748b; }

    .table-eval { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 9pt; }
    .table-eval th, .table-eval td { border: 1px solid #cbd5e1; padding: 4px 6px; }
    .table-eval th { background: #f8fafc; text-align: left; }

    .legend-box { display: flex; flex-wrap: wrap; gap: 6px; font-size: 8pt; margin-top: 6px; }
    .legend-item { display: inline-flex; align-items: center; gap: 3px; padding: 2px 5px; border-radius: 3px; border: 1px solid #e2e8f0; }

    .signature-row { display: flex; justify-content: flex-end; margin-top: 20px; text-align: center; }
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
    <button onclick="window.print()" style="padding: 7px 15px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
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

  <div class="doc-title">REKAM MEDIS ODONTOGRAM (POLI GIGI & MULUT)</div>

  <!-- Identitas Pasien -->
  <table class="table-info">
    <tr>
      <td class="label">No. Rekam Medis</td>
      <td class="sep">:</td>
      <td><strong><?= htmlspecialchars($pasien['no_rkm_medis']) ?></strong></td>
      <td class="label">Dokter Pemeriksa</td>
      <td class="sep">:</td>
      <td><?= htmlspecialchars($pasien['nm_dokter'] ?? '-') ?></td>
    </tr>
    <tr>
      <td class="label">Nama Pasien</td>
      <td class="sep">:</td>
      <td><strong><?= htmlspecialchars($pasien['nm_pasien']) ?></strong> (<?= $pasien['jk'] == 'L' ? 'Laki-laki' : 'Perempuan' ?>)</td>
      <td class="label">Poli / Unit</td>
      <td class="sep">:</td>
      <td><?= htmlspecialchars($pasien['nm_poli'] ?? 'Poli Gigi & Mulut') ?></td>
    </tr>
    <tr>
      <td class="label">Tgl Lahir / Umur</td>
      <td class="sep">:</td>
      <td><?= date('d/m/Y', strtotime($pasien['tgl_lahir'])) ?> (<?= hitung_umur($pasien['tgl_lahir']) ?> th)</td>
      <td class="label">Tgl Pemeriksaan</td>
      <td class="sep">:</td>
      <td><?= !empty($odont['tgl_perawatan']) ? date('d/m/Y', strtotime($odont['tgl_perawatan'])) : date('d/m/Y') ?></td>
    </tr>
  </table>

  <!-- Visual Odontogram FDI Chart -->
  <div class="section-head">I. DIAGRAM ODONTOGRAM (FDI TWO-DIGIT SYSTEM)</div>
  <div class="odont-container">
    
    <!-- Gigi Dewasa Rahang Atas (18-11 | 21-28) -->
    <div class="quadrant-title">RAHANG ATAS (MAXILLA) - PERMANEN</div>
    <div class="teeth-row">
      <?php
      $upper_perm = [18,17,16,15,14,13,12,11, 21,22,23,24,25,26,27,28];
      foreach ($upper_perm as $idx => $t):
        if ($idx == 8) echo '<div style="width:12px;border-left:2px dashed #94a3b8;margin:0 4px;"></div>';
        $k = $t . '_ALL';
        $cond = $teeth_data[$k]['kondisi'] ?? 'Sou';
      ?>
        <div class="tooth-box">
          <div class="tooth-num"><?= $t ?></div>
          <div class="tooth-cond cond-<?= $cond ?>"><?= $cond ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Gigi Susu (55-51 | 61-65 & 85-81 | 71-75) -->
    <div class="quadrant-title" style="margin-top:6px;">GIGI SUSU (DECIDUOUS)</div>
    <div class="teeth-row">
      <?php
      $upper_dec = [55,54,53,52,51, 61,62,63,64,65];
      foreach ($upper_dec as $idx => $t):
        if ($idx == 5) echo '<div style="width:12px;border-left:2px dashed #94a3b8;margin:0 4px;"></div>';
        $k = $t . '_ALL';
        $cond = $teeth_data[$k]['kondisi'] ?? 'Sou';
      ?>
        <div class="tooth-box" style="width:28px;">
          <div class="tooth-num" style="background:#e0f2fe;"><?= $t ?></div>
          <div class="tooth-cond cond-<?= $cond ?>"><?= $cond ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="teeth-row">
      <?php
      $lower_dec = [85,84,83,82,81, 71,72,73,74,75];
      foreach ($lower_dec as $idx => $t):
        if ($idx == 5) echo '<div style="width:12px;border-left:2px dashed #94a3b8;margin:0 4px;"></div>';
        $k = $t . '_ALL';
        $cond = $teeth_data[$k]['kondisi'] ?? 'Sou';
      ?>
        <div class="tooth-box" style="width:28px;">
          <div class="tooth-cond cond-<?= $cond ?>"><?= $cond ?></div>
          <div class="tooth-num" style="background:#e0f2fe;"><?= $t ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Gigi Dewasa Rahang Bawah (48-41 | 31-38) -->
    <div class="quadrant-title" style="margin-top:6px;">RAHANG BAWAH (MANDIBULA) - PERMANEN</div>
    <div class="teeth-row">
      <?php
      $lower_perm = [48,47,46,45,44,43,42,41, 31,32,33,34,35,36,37,38];
      foreach ($lower_perm as $idx => $t):
        if ($idx == 8) echo '<div style="width:12px;border-left:2px dashed #94a3b8;margin:0 4px;"></div>';
        $k = $t . '_ALL';
        $cond = $teeth_data[$k]['kondisi'] ?? 'Sou';
      ?>
        <div class="tooth-box">
          <div class="tooth-cond cond-<?= $cond ?>"><?= $cond ?></div>
          <div class="tooth-num"><?= $t ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Legenda Simbol -->
    <div class="legend-box">
      <span class="legend-item"><span style="width:10px;height:10px;background:#fee2e2;border:1px solid #dc2626;display:inline-block;"></span> <strong>Car:</strong> Karies</span>
      <span class="legend-item"><span style="width:10px;height:10px;background:#e2e8f0;border:1px solid #334155;display:inline-block;"></span> <strong>Amf:</strong> Amalgam</span>
      <span class="legend-item"><span style="width:10px;height:10px;background:#ecfdf5;border:1px solid #059669;display:inline-block;"></span> <strong>Gif:</strong> Komposit/GIC</span>
      <span class="legend-item"><span style="width:10px;height:10px;background:#1e293b;border:1px solid #000;display:inline-block;"></span> <strong>Mis:</strong> Missing</span>
      <span class="legend-item"><span style="width:10px;height:10px;background:#fef3c7;border:1px solid #d97706;display:inline-block;"></span> <strong>Rad:</strong> Sisa Akar</span>
      <span class="legend-item"><span style="width:10px;height:10px;background:#e0e7ff;border:1px solid #4338ca;display:inline-block;"></span> <strong>Cro:</strong> Mahkota</span>
      <span class="legend-item"><span style="width:10px;height:10px;background:#f3e8ff;border:1px solid #7e22ce;display:inline-block;"></span> <strong>Imp:</strong> Impaksi</span>
      <span class="legend-item"><span style="width:10px;height:10px;background:#f8fafc;border:1px solid #cbd5e1;display:inline-block;"></span> <strong>Sou:</strong> Normal</span>
    </div>
  </div>

  <!-- Detail Kondisi Gigi & Pemeriksaan Khusus -->
  <div class="section-head">II. EVALUASI KLINIS GIGI, MULUT & INDEKS KESEHATAN</div>
  <table class="table-eval">
    <tr>
      <th style="width: 25%;">Oklusi</th>
      <td style="width: 25%;"><?= htmlspecialchars($odont['oklusi'] ?? 'Normal') ?></td>
      <th style="width: 25%;">Torus Palatinus</th>
      <td style="width: 25%;"><?= htmlspecialchars($odont['torus_palatinus'] ?? 'Tidak Ada') ?></td>
    </tr>
    <tr>
      <th>Torus Mandibularis</th>
      <td><?= htmlspecialchars($odont['torus_mandibularis'] ?? 'Tidak Ada') ?></td>
      <th>Palatum</th>
      <td><?= htmlspecialchars($odont['palatum'] ?? 'Sedang') ?></td>
    </tr>
    <tr>
      <th>Diastema</th>
      <td><?= htmlspecialchars($odont['diastema'] ?? 'Tidak Ada') ?></td>
      <th>Gigi Anomali</th>
      <td><?= htmlspecialchars($odont['gigi_anomali'] ?? 'Tidak Ada') ?></td>
    </tr>
    <tr>
      <th>Indeks DMF-T</th>
      <td>
        <strong>D:</strong> <?= $d_cnt ?> | 
        <strong>M:</strong> <?= $m_cnt ?> | 
        <strong>F:</strong> <?= $f_cnt ?> &rarr; 
        <strong>Total: <?= $dmft_total ?></strong>
      </td>
      <th>Indeks OHIS</th>
      <td>
        Debris: <?= htmlspecialchars($odont['ohis_debris'] ?? '0') ?> | 
        Calc: <?= htmlspecialchars($odont['ohis_calculus'] ?? '0') ?> | 
        <strong>Score: <?= htmlspecialchars($odont['ohis_nilai'] ?? '0') ?> (<?= htmlspecialchars($odont['ohis_kriteria'] ?? 'Baik') ?>)</strong>
      </td>
    </tr>
    <tr>
      <th>Catatan Khusus / Anomali</th>
      <td colspan="3"><?= nl2br(htmlspecialchars($odont['lain_lain'] ?? '-')) ?></td>
    </tr>
  </table>

  <!-- Tanda Tangan -->
  <div class="signature-row">
    <div class="signature-box">
      <div><?= setting('kota_instansi', 'Indonesia') ?>, <?= date('d F Y') ?></div>
      <div style="margin-top: 5px;">Dokter Gigi Pemeriksa,</div>
      <div style="height: 60px;"></div>
      <div style="font-weight: bold; text-decoration: underline;"><?= htmlspecialchars($pasien['nm_dokter'] ?? 'Dokter Pemeriksa') ?></div>
      <div style="font-size: 8.5pt; color: #64748b;">SIP: -</div>
    </div>
  </div>

</body>
</html>
