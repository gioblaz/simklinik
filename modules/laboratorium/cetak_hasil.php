<?php
/**
 * SIMKlinik — Cetak Lembar Hasil Pemeriksaan Laboratorium Resmi (A4 Format)
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$noorder  = sanitize($_GET['noorder'] ?? '');
$no_rawat = sanitize($_GET['no_rawat'] ?? '');

if (empty($noorder) && empty($no_rawat)) {
    die('Parameter tidak lengkap.');
}

$rawat_esc   = $conn->real_escape_string($no_rawat);
$noorder_esc = $conn->real_escape_string($noorder);

// Where clause
$where = !empty($noorder) ? "pl.noorder = '$noorder_esc'" : "pl.no_rawat = '$rawat_esc'";

// ─── Query Data Permintaan & Pasien ───────────────────────────
$res = $conn->query("
    SELECT pl.*, 
           r.no_reg, r.tgl_registrasi, r.jam_reg,
           p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir, p.alamat, p.no_ktp, p.no_tlp,
           d.nm_dokter as nm_dokter_perujuk,
           pol.nm_poli, pj.png_jawab as nm_penjab
    FROM permintaan_lab pl
    JOIN reg_periksa r ON pl.no_rawat = r.no_rawat
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE $where
    LIMIT 1
");

if (!$res || $res->num_rows === 0) {
    die('Data pemeriksaan laboratorium tidak ditemukan.');
}
$order = $res->fetch_assoc();
$no_rawat = $order['no_rawat'];
$rawat_esc = $conn->real_escape_string($no_rawat);

// ─── Load Hasil Pemeriksaan Periksa Lab & Detail ───────────────
$res_pkg = $conn->query("
    SELECT pl.*, jpl.nm_perawatan, jpl.kategori,
           d.nm_dokter as nm_dokter_pj,
           pg.nama as nm_petugas_lab
    FROM periksa_lab pl
    JOIN jns_perawatan_lab jpl ON pl.kd_jenis_prw = jpl.kd_jenis_prw
    LEFT JOIN dokter d ON pl.kd_dokter = d.kd_dokter
    LEFT JOIN petugas pg ON pl.nip = pg.nip
    WHERE pl.no_rawat = '$rawat_esc'
    ORDER BY jpl.kategori ASC, jpl.nm_perawatan ASC
");

$packages = [];
$dokter_pj_nama = '';
$petugas_nama   = '';
$tgl_pemeriksaan = $order['tgl_hasil'];
$jam_pemeriksaan = $order['jam_hasil'];

if ($res_pkg) {
    while ($pkg = $res_pkg->fetch_assoc()) {
        $kd_pkg_esc = $conn->real_escape_string($pkg['kd_jenis_prw']);
        if (!empty($pkg['nm_dokter_pj'])) $dokter_pj_nama = $pkg['nm_dokter_pj'];
        if (!empty($pkg['nm_petugas_lab'])) $petugas_nama = $pkg['nm_petugas_lab'];
        if (!empty($pkg['tgl_periksa'])) $tgl_pemeriksaan = $pkg['tgl_periksa'];
        if (!empty($pkg['jam'])) $jam_pemeriksaan = $pkg['jam'];

        // Detail items
        $res_det = $conn->query("
            SELECT d.*, t.Pemeriksaan, t.satuan, t.urut
            FROM detail_periksa_lab d
            JOIN template_laboratorium t ON d.id_template = t.id_template
            WHERE d.no_rawat = '$rawat_esc' AND d.kd_jenis_prw = '$kd_pkg_esc'
            ORDER BY t.urut ASC, t.id_template ASC
        ");
        $items = [];
        if ($res_det) {
            while ($det = $res_det->fetch_assoc()) {
                $items[] = $det;
            }
        }
        $pkg['items'] = $items;
        $packages[] = $pkg;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hasil Laboratorium - <?= htmlspecialchars($order['nm_pasien']) ?> (<?= htmlspecialchars($order['noorder']) ?>)</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @page {
      size: A4 portrait;
      margin: 12mm 15mm 15mm 15mm;
    }
    *, *::before, *::after {
      box-sizing: border-box;
    }
    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: 11pt;
      line-height: 1.4;
      color: #0f172a;
      background: #f8fafc;
      margin: 0;
      padding: 20px;
    }
    .print-wrapper {
      max-width: 800px;
      margin: 0 auto;
      background: #ffffff;
      padding: 30px 35px;
      border-radius: 8px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    
    /* Kop Surat */
    .kop {
      display: flex;
      align-items: center;
      gap: 18px;
      border-bottom: 2.5px solid #0f172a;
      padding-bottom: 12px;
      margin-bottom: 14px;
    }
    .kop-logo {
      width: 70px;
      height: 70px;
      object-fit: contain;
    }
    .kop-text {
      flex: 1;
      text-align: center;
    }
    .kop-text h1 {
      margin: 0;
      font-size: 16pt;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: 0.02em;
    }
    .kop-text h2 {
      margin: 2px 0 0;
      font-size: 11pt;
      font-weight: 700;
      color: #0284c7;
      letter-spacing: 0.05em;
    }
    .kop-text p {
      margin: 3px 0 0;
      font-size: 9pt;
      color: #475569;
    }

    /* Document Title */
    .doc-title {
      text-align: center;
      margin: 12px 0 16px;
    }
    .doc-title h3 {
      margin: 0;
      font-size: 13pt;
      font-weight: 800;
      text-decoration: underline;
      color: #0f172a;
      letter-spacing: 0.04em;
    }
    .doc-title .doc-num {
      font-size: 9.5pt;
      font-family: monospace;
      color: #64748b;
      margin-top: 3px;
    }

    /* Patient Info Grid */
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 10px 14px;
      margin-bottom: 16px;
      font-size: 9.5pt;
    }
    .info-col table {
      width: 100%;
      border-collapse: collapse;
    }
    .info-col td {
      padding: 2px 4px;
      vertical-align: top;
    }
    .info-col td.label {
      color: #64748b;
      width: 115px;
    }
    .info-col td.sep {
      width: 8px;
    }
    .info-col td.val {
      font-weight: 600;
      color: #0f172a;
    }

    /* Test Results Table */
    .results-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 16px;
      font-size: 9.5pt;
    }
    .results-table th {
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      padding: 6px 8px;
      font-weight: 700;
      color: #334155;
      text-align: left;
    }
    .results-table td {
      border: 1px solid #e2e8f0;
      padding: 5px 8px;
      vertical-align: middle;
    }
    .package-header-row td {
      background: #f0f9ff;
      font-weight: 800;
      color: #0369a1;
      padding: 6px 8px;
      border-top: 1.5px solid #93c5fd;
      border-bottom: 1.5px solid #93c5fd;
    }
    .val-highlight {
      font-weight: 800;
      color: #0f172a;
    }
    .flag-abnormal {
      display: inline-block;
      padding: 1px 6px;
      border-radius: 4px;
      background: #fee2e2;
      color: #dc2626;
      font-weight: 700;
      font-size: 8.5pt;
    }
    .flag-normal {
      display: inline-block;
      padding: 1px 6px;
      border-radius: 4px;
      background: #f0fdf4;
      color: #16a34a;
      font-weight: 600;
      font-size: 8.5pt;
    }

    /* Signatures Section */
    .sign-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      margin-top: 24px;
      page-break-inside: avoid;
    }
    .sign-box {
      text-align: center;
      font-size: 9.5pt;
    }
    .sign-box .sign-space {
      height: 60px;
    }
    .sign-box .sign-name {
      font-weight: 700;
      text-decoration: underline;
      color: #0f172a;
    }
    .sign-box .sign-nip {
      font-size: 8.5pt;
      color: #64748b;
      margin-top: 2px;
    }

    /* Floating Print Controls */
    .print-controls {
      position: fixed;
      bottom: 20px;
      right: 20px;
      display: flex;
      gap: 10px;
      background: #ffffff;
      padding: 10px 14px;
      border-radius: 99px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
      border: 1px solid #e2e8f0;
      z-index: 999;
    }
    .btn-ctrl {
      padding: 8px 16px;
      border-radius: 99px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border: none;
    }
    .btn-ctrl-primary {
      background: #0284c7;
      color: #ffffff;
    }
    .btn-ctrl-secondary {
      background: #f1f5f9;
      color: #475569;
      border: 1px solid #cbd5e1;
    }

    @media print {
      body {
        background: #ffffff;
        padding: 0;
      }
      .print-wrapper {
        box-shadow: none;
        padding: 0;
        max-width: 100%;
      }
      .print-controls {
        display: none !important;
      }
    }
  </style>
</head>
<body>

  <!-- Floating Controls -->
  <div class="print-controls">
    <a href="<?= BASE_URL ?>modules/laboratorium/index.php" class="btn-ctrl btn-ctrl-secondary">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>
    <a href="<?= BASE_URL ?>modules/laboratorium/input_hasil.php?noorder=<?= urlencode($order['noorder']) ?>" class="btn-ctrl btn-ctrl-secondary">
      <i class="fas fa-edit"></i> Edit Hasil
    </a>
    <button type="button" onclick="window.print()" class="btn-ctrl btn-ctrl-primary">
      <i class="fas fa-print"></i> Cetak Hasil (A4)
    </button>
  </div>

  <div class="print-wrapper">
    
    <!-- ─── KOP SURAT ───────────────────────────────────────── -->
    <div class="kop">
      <?php if (!empty(INSTANSI_LOGO) && file_exists(BASE_PATH . INSTANSI_LOGO)): ?>
        <img src="<?= BASE_URL . INSTANSI_LOGO ?>" alt="Logo" class="kop-logo">
      <?php else: ?>
        <div style="width:65px;height:65px;background:#0284c7;color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:32px;">
          <i class="fas fa-hospital-alt"></i>
        </div>
      <?php endif; ?>

      <div class="kop-text">
        <h1><?= htmlspecialchars(INSTANSI_NAMA) ?></h1>
        <h2>INSTALASI LABORATORIUM KLINIK</h2>
        <p><?= htmlspecialchars(INSTANSI_ALAMAT) ?> &bull; <?= htmlspecialchars(INSTANSI_KOTA) ?> &bull; Telp: <?= htmlspecialchars(INSTANSI_TELP) ?></p>
      </div>
    </div>

    <!-- ─── JUDUL DOKUMEN ───────────────────────────────────── -->
    <div class="doc-title">
      <h3>SURAT HASIL PEMERIKSAAN LABORATORIUM</h3>
      <div class="doc-num">No. Order: <?= htmlspecialchars($order['noorder']) ?> &bull; No. Rawat: <?= htmlspecialchars($order['no_rawat']) ?></div>
    </div>

    <!-- ─── IDENTITAS PASIEN & PEMERIKSAAN ─────────────────── -->
    <div class="info-grid">
      
      <!-- Kolom Kiri -->
      <div class="info-col">
        <table>
          <tr>
            <td class="label">No. Rekam Medis</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($order['no_rkm_medis']) ?></td>
          </tr>
          <tr>
            <td class="label">Nama Pasien</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($order['nm_pasien']) ?></td>
          </tr>
          <tr>
            <td class="label">Jenis Kelamin / Umur</td>
            <td class="sep">:</td>
            <td class="val"><?= ($order['jk'] === 'L' ? 'Laki-laki' : 'Perempuan') ?> / <?= hitung_umur($order['tgl_lahir']) ?></td>
          </tr>
          <tr>
            <td class="label">Alamat</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($order['alamat'] ?: '-') ?></td>
          </tr>
        </table>
      </div>

      <!-- Kolom Kanan -->
      <div class="info-col">
        <table>
          <tr>
            <td class="label">Dokter Pengirim</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($order['nm_dokter_perujuk'] ?: '-') ?></td>
          </tr>
          <tr>
            <td class="label">Unit / Poli Asal</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($order['nm_poli'] ?: 'Rawat Jalan') ?> (<?= htmlspecialchars($order['nm_penjab'] ?? 'Umum') ?>)</td>
          </tr>
          <tr>
            <td class="label">Tgl. Permintaan</td>
            <td class="sep">:</td>
            <td class="val"><?= tgl_indo($order['tgl_permintaan']) ?> <?= substr($order['jam_permintaan'], 0, 5) ?></td>
          </tr>
          <tr>
            <td class="label">Tgl. Selesai Hasil</td>
            <td class="sep">:</td>
            <td class="val"><?= tgl_indo($tgl_pemeriksaan) ?> <?= substr($jam_pemeriksaan, 0, 5) ?> WIB</td>
          </tr>
        </table>
      </div>

    </div>

    <!-- ─── TABEL HASIL PEMERIKSAAN ─────────────────────────── -->
    <table class="results-table">
      <thead>
        <tr>
          <th style="width:35px;text-align:center;">No</th>
          <th>Pemeriksaan</th>
          <th style="width:130px;text-align:center;">Hasil</th>
          <th style="width:90px;">Satuan</th>
          <th style="width:140px;">Nilai Rujukan</th>
          <th style="width:120px;">Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($packages)): ?>
          <tr>
            <td colspan="6" style="text-align:center;padding:20px;color:#94a3b8;">
              Belum ada hasil pemeriksaan laboratorium yang disimpan.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($packages as $pkg): ?>
            <!-- Package Header Row -->
            <tr class="package-header-row">
              <td colspan="6">
                <i class="fas fa-flask" style="font-size:10px;margin-right:4px;"></i> <?= htmlspecialchars($pkg['nm_perawatan']) ?> 
                <span style="font-size:8.5pt;font-weight:normal;color:#64748b;">(<?= $pkg['kategori'] ?>)</span>
              </td>
            </tr>

            <!-- Detail Parameter Rows -->
            <?php $no = 1; ?>
            <?php foreach ($pkg['items'] as $item): ?>
              <?php
                $is_flag_abnormal = false;
                $ket_lower = strtolower($item['keterangan'] ?? '');
                if (str_contains($ket_lower, 'high') || str_contains($ket_lower, 'low') || str_contains($ket_lower, 'tinggi') || str_contains($ket_lower, 'rendah') || str_contains($ket_lower, 'positif') || str_contains($ket_lower, 'reaktif')) {
                    $is_flag_abnormal = true;
                }
              ?>
              <tr>
                <td style="text-align:center;color:#64748b;"><?= $no++ ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($item['Pemeriksaan']) ?></td>
                <td style="text-align:center;" class="val-highlight">
                  <?= htmlspecialchars($item['nilai'] ?: '-') ?>
                  <?php if ($is_flag_abnormal): ?>
                    <span style="color:#dc2626;font-weight:bold;margin-left:2px;">*</span>
                  <?php endif; ?>
                </td>
                <td style="color:#64748b;font-family:monospace;"><?= htmlspecialchars($item['satuan'] ?: '-') ?></td>
                <td style="color:#475569;"><?= htmlspecialchars($item['nilai_rujukan'] ?: '-') ?></td>
                <td>
                  <?php if (!empty($item['keterangan'])): ?>
                    <span class="<?= $is_flag_abnormal ? 'flag-abnormal' : 'flag-normal' ?>">
                      <?= htmlspecialchars($item['keterangan']) ?>
                    </span>
                  <?php else: ?>
                    <span style="color:#94a3b8;">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>

          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- ─── CATATAN TAMBAHAN ─────────────────────────────────── -->
    <?php if (!empty($order['informasi_tambahan']) || !empty($order['diagnosa_klinis'])): ?>
      <div style="font-size:9pt;background:#f8fafc;border:1px dashed #cbd5e1;padding:8px 12px;border-radius:6px;margin-bottom:16px;">
        <?php if (!empty($order['diagnosa_klinis'])): ?>
          <div><strong>Diagnosa Klinis:</strong> <?= htmlspecialchars($order['diagnosa_klinis']) ?></div>
        <?php endif; ?>
        <?php if (!empty($order['informasi_tambahan'])): ?>
          <div style="margin-top:2px;"><strong>Catatan Klinis:</strong> <?= htmlspecialchars($order['informasi_tambahan']) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- ─── TANDA TANGAN ────────────────────────────────────── -->
    <div class="sign-section">
      
      <!-- Kiri: Analis Laboratorium -->
      <div class="sign-box">
        <div>Pemeriksa / Analis Laboratorium,</div>
        <div class="sign-space"></div>
        <div class="sign-name"><?= htmlspecialchars($petugas_nama ?: 'Analis Laboratorium') ?></div>
        <div class="sign-nip">Petugas Pelaksana Lab</div>
      </div>

      <!-- Kanan: Dokter Penanggung Jawab -->
      <div class="sign-box">
        <div><?= htmlspecialchars(INSTANSI_KOTA) ?>, <?= tgl_indo($tgl_pemeriksaan) ?></div>
        <div>Dokter Penanggung Jawab Lab,</div>
        <div class="sign-space"></div>
        <div class="sign-name"><?= htmlspecialchars($dokter_pj_nama ?: $order['nm_dokter_perujuk'] ?: 'dr. Penanggung Jawab') ?></div>
        <div class="sign-nip">SIP: <?= date('Y') ?>/LAB-KLINIK/001</div>
      </div>

    </div>

  </div>

</body>
</html>
