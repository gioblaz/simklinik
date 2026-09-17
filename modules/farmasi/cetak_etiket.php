<?php
/**
 * SIMKlinik — Farmasi: Cetak Label / Etiket Obat PDF Viewer Style (Auto-Cut Per Halaman)
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_resep = sanitize($_GET['no_resep'] ?? '');
$no_rawat = sanitize($_GET['no_rawat'] ?? '');

if (empty($no_resep) && empty($no_rawat)) {
    die("Nomor resep atau Nomor rawat tidak valid.");
}

$resep_clause = "";
if (!empty($no_resep)) {
    $resep_esc = $conn->real_escape_string($no_resep);
    $resep_clause = "ro.no_resep = '$resep_esc'";
} else {
    $rawat_esc = $conn->real_escape_string($no_rawat);
    $resep_clause = "ro.no_rawat = '$rawat_esc' ORDER BY ro.tgl_peresepan DESC, ro.jam_peresepan DESC LIMIT 1";
}

$res = $conn->query("
    SELECT ro.*, r.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir, p.alamat,
           d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab
    FROM resep_obat ro
    JOIN reg_periksa r ON ro.no_rawat = r.no_rawat
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE $resep_clause
");

$resep = $res ? $res->fetch_assoc() : null;
if (!$resep) {
    die("Data resep obat tidak ditemukan.");
}
$no_resep = $resep['no_resep'];
$resep_esc = $conn->real_escape_string($no_resep);

// Load Obat Non-Racikan
$items_res = $conn->query("
    SELECT rd.*, db.nama_brng, db.ralan as harga, ks.satuan, kb.nama as kategori, j.nama as jenis,
           COALESCE(db.expire, '') as expire_date
    FROM resep_dokter rd
    JOIN databarang db ON rd.kode_brng = db.kode_brng
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
    LEFT JOIN jenis j ON db.kdjns = j.kdjns
    WHERE rd.no_resep = '$resep_esc'
");

$items = [];
if ($items_res) {
    while ($r = $items_res->fetch_assoc()) $items[] = $r;
}

// Load Obat Racikan
$racikan_res = $conn->query("
    SELECT rdr.*, mr.nm_racik
    FROM resep_dokter_racikan rdr
    LEFT JOIN metode_racik mr ON rdr.kd_racik = mr.kd_racik
    WHERE rdr.no_resep = '$resep_esc'
    ORDER BY rdr.no_racik ASC
");
$racikan = [];
if ($racikan_res) {
    while ($rck = $racikan_res->fetch_assoc()) {
        $no_rck = $rck['no_racik'];
        $detail_res = $conn->query("
            SELECT rdrd.*, db.nama_brng, ks.satuan
            FROM resep_dokter_racikan_detail rdrd
            JOIN databarang db ON rdrd.kode_brng = db.kode_brng
            LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
            WHERE rdrd.no_resep = '$resep_esc' AND rdrd.no_racik = '$no_rck'
            ORDER BY db.nama_brng ASC
        ");
        $details = [];
        if ($detail_res) while ($det = $detail_res->fetch_assoc()) $details[] = $det;
        $rck['detail'] = $details;
        $racikan[] = $rck;
    }
}

$total_pages = count($items) + count($racikan);
if ($total_pages === 0) $total_pages = 1;

$apoteker_nama = !empty($db_settings['apoteker']) ? $db_settings['apoteker'] : 'M Rifqi Rahman';
$apoteker_sipa = !empty($db_settings['sipa']) ? $db_settings['sipa'] : '123456';
$apoteker_sia  = !empty($db_settings['sia']) ? $db_settings['sia'] : '78901';

$tgl_resep_formatted = date('d-m-Y', strtotime($resep['tgl_peresepan'] ?? date('Y-m-d')));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>cetaketiket - <?= htmlspecialchars($no_resep) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --label-w: 60mm;
      --label-h: 40mm;
      --zoom-level: 1.5;
    }

    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    html, body {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      background: #2b2b2b;
      font-family: Arial, Helvetica, sans-serif;
      color: #000000;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    /* ─── PDF Viewer Top Navigation Bar (Chrome Style) ─── */
    .pdf-top-bar {
      height: 42px;
      background: #323639;
      color: #f1f3f4;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 16px;
      font-size: 13px;
      flex-shrink: 0;
      border-bottom: 1px solid #202124;
      user-select: none;
    }
    .pdf-title-left {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
    }
    .pdf-nav-center {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .page-indicator {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12.5px;
    }
    .page-input {
      width: 24px;
      height: 22px;
      background: #202124;
      border: 1px solid #5f6368;
      border-radius: 3px;
      color: #fff;
      text-align: center;
      font-size: 12px;
    }
    .pdf-tools-right {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .pdf-tool-btn {
      background: transparent;
      border: none;
      color: #f1f3f4;
      cursor: pointer;
      font-size: 14px;
      padding: 5px;
      border-radius: 4px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: background 0.15s;
    }
    .pdf-tool-btn:hover {
      background: rgba(255,255,255,0.15);
    }

    /* ─── PDF Canvas Container ─── */
    .pdf-canvas-body {
      flex: 1;
      overflow-y: auto;
      overflow-x: auto;
      background: #323639;
      padding: 20px 0 60px 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 18px;
    }

    /* ─── Each Label Page ─── */
    .label-page {
      width: calc(var(--label-w) * var(--zoom-level));
      height: calc(var(--label-h) * var(--zoom-level));
      max-height: calc(var(--label-h) * var(--zoom-level));
      background: #ffffff;
      padding: calc(2.2mm * var(--zoom-level)) calc(3mm * var(--zoom-level)) calc(1.8mm * var(--zoom-level)) calc(3mm * var(--zoom-level));
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0,0,0,0.5);
      border: 1px solid #111;
      position: relative;
      flex-shrink: 0;
      page-break-after: always !important;
      break-after: page !important;
      page-break-inside: avoid !important;
    }

    /* ─── Label Typography ─── */
    .kop-header {
      text-align: center;
      line-height: 1.15;
    }
    .kop-nama {
      font-size: calc(9.5pt * var(--zoom-level) * 0.7);
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.2px;
      margin: 0;
      color: #000;
    }
    .kop-alamat {
      font-size: calc(7.5pt * var(--zoom-level) * 0.7);
      margin: 1px 0 2px 0;
      color: #000;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .kop-meta-grid {
      display: flex;
      justify-content: space-between;
      font-size: calc(7pt * var(--zoom-level) * 0.7);
      line-height: 1.2;
      color: #000000;
      font-weight: 500;
    }
    .kop-meta-left {
      text-align: left;
    }
    .kop-meta-right {
      text-align: right;
    }

    .divider-solid {
      border-bottom: calc(1.8px * var(--zoom-level) * 0.7) solid #000000;
      margin: 1.5px 0 2px 0;
      width: 100%;
    }

    .content-body {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-around;
      padding: 1px 0;
    }
    .row-date {
      text-align: right;
      font-size: calc(7.8pt * var(--zoom-level) * 0.7);
      font-weight: 700;
      color: #000;
    }
    .row-patient {
      font-size: calc(9pt * var(--zoom-level) * 0.7);
      font-weight: 800;
      line-height: 1.2;
      color: #000;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .row-medicine {
      font-size: calc(9.2pt * var(--zoom-level) * 0.7);
      font-weight: 800;
      text-transform: uppercase;
      line-height: 1.2;
      color: #000;
      margin-top: 1px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .row-signa {
      text-align: center;
      font-size: calc(10pt * var(--zoom-level) * 0.7);
      font-weight: 800;
      color: #000;
      margin: 2px 0;
      line-height: 1.15;
    }

    .row-ed {
      text-align: center;
      font-size: calc(7.8pt * var(--zoom-level) * 0.7);
      font-weight: 700;
      color: #000;
    }

    .row-footer-slogan {
      text-align: center;
      font-size: calc(7.8pt * var(--zoom-level) * 0.7);
      font-weight: 600;
      color: #000;
      line-height: 1.1;
    }
    .divider-footer {
      border-bottom: calc(1.8px * var(--zoom-level) * 0.7) solid #000000;
      margin-top: 1.5px;
      width: 100%;
    }

    /* Red PDF badge floating icon */
    .pdf-badge-floating {
      position: fixed;
      bottom: 24px;
      right: 24px;
      width: 38px;
      height: 38px;
      background: #e11d48;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 16px;
      box-shadow: 0 4px 12px rgba(225,29,72,0.4);
      z-index: 100;
      pointer-events: none;
    }

    /* ─── PRINT EXACT CUTTING RULES ─── */
    @page {
      size: 60mm 40mm;
      margin: 0;
    }

    @media print {
      body, html {
        background: #fff !important;
        overflow: visible !important;
        height: auto !important;
      }
      .pdf-top-bar, .pdf-badge-floating {
        display: none !important;
      }
      .pdf-canvas-body {
        padding: 0 !important;
        background: transparent !important;
        gap: 0 !important;
      }
      .label-page {
        width: 60mm !important;
        height: 40mm !important;
        max-height: 40mm !important;
        box-shadow: none !important;
        border: none !important;
        margin: 0 !important;
        padding: 2.2mm 3mm 1.8mm 3mm !important;
        page-break-after: always !important;
        break-after: page !important;
        page-break-inside: avoid !important;
      }
      .kop-nama { font-size: 9pt !important; }
      .kop-alamat { font-size: 7.2pt !important; }
      .kop-meta-grid { font-size: 6.8pt !important; }
      .divider-solid { border-bottom: 1.8px solid #000 !important; }
      .row-date { font-size: 7.5pt !important; }
      .row-patient { font-size: 8.5pt !important; }
      .row-medicine { font-size: 8.8pt !important; }
      .row-signa { font-size: 9.5pt !important; }
      .row-ed { font-size: 7.5pt !important; }
      .row-footer-slogan { font-size: 7.5pt !important; }
      .divider-footer { border-bottom: 1.8px solid #000 !important; }
    }
  </style>
</head>
<body>

  <!-- Top PDF Style Viewer Bar -->
  <div class="pdf-top-bar">
    <div class="pdf-title-left">
      <button type="button" class="pdf-tool-btn" title="Menu"><i class="fas fa-bars"></i></button>
      <span>cetaketiket</span>
    </div>

    <div class="pdf-nav-center">
      <div class="page-indicator">
        <input type="text" class="page-input" value="1" readonly>
        <span>/ <?= $total_pages ?></span>
      </div>
      <div style="height: 16px; width: 1px; background: #5f6368; margin: 0 4px;"></div>
      <button type="button" class="pdf-tool-btn" onclick="changeZoom(-0.15)" title="Perkecil"><i class="fas fa-minus"></i></button>
      <span id="lblZoom" style="font-size: 12px; font-weight: 500; min-width: 40px; text-align: center;">150%</span>
      <button type="button" class="pdf-tool-btn" onclick="changeZoom(0.15)" title="Perbesar"><i class="fas fa-plus"></i></button>
      <div style="height: 16px; width: 1px; background: #5f6368; margin: 0 4px;"></div>
      <button type="button" class="pdf-tool-btn" onclick="changeZoom(0, 1.5)" title="Ukuran Pas"><i class="fas fa-arrows-alt-v"></i></button>
      <button type="button" class="pdf-tool-btn" title="Putar"><i class="fas fa-rotate-right"></i></button>
    </div>

    <div class="pdf-tools-right">
      <button type="button" class="pdf-tool-btn" onclick="window.print()" title="Cetak Dokumen (Auto-Cut)"><i class="fas fa-print"></i></button>
      <button type="button" class="pdf-tool-btn" onclick="window.print()" title="Simpan PDF"><i class="fas fa-download"></i></button>
      <button type="button" class="pdf-tool-btn" title="Opsi Lain"><i class="fas fa-ellipsis-vertical"></i></button>
    </div>
  </div>

  <!-- PDF Canvas View Area -->
  <div class="pdf-canvas-body" id="pdfCanvas">

    <!-- 1. Obat Non-Racikan -->
    <?php foreach ($items as $idx => $it): ?>
      <?php
        $ed_text = "-";
        if (!empty($it['expire_date']) && $it['expire_date'] !== '0000-00-00') {
            $ed_text = date('d-m-Y', strtotime($it['expire_date']));
        } else {
            $ed_text = date('d-m-Y', strtotime('+1 year', strtotime($resep['tgl_peresepan'] ?? date('Y-m-d'))));
        }

        $nama_obat_full = trim($it['nama_brng']) . '  ' . trim($it['jml']) . ' ' . trim($it['satuan'] ?: 'TAB');
        $aturan_full = trim($it['aturan_pakai'] ?: 'Sesuai Petunjuk Dokter');
      ?>
      <div class="label-page">
        
        <!-- Kop Apotek / Klinik -->
        <div class="kop-header">
          <div class="kop-nama"><?= htmlspecialchars(INSTANSI_NAMA) ?></div>
          <div class="kop-alamat"><?= htmlspecialchars(INSTANSI_ALAMAT) ?>, <?= htmlspecialchars(INSTANSI_KOTA) ?></div>
          <div class="kop-meta-grid">
            <div class="kop-meta-left">
              <div>Telp. <?= htmlspecialchars(INSTANSI_TELP) ?></div>
              <div>Apoteker: <?= htmlspecialchars($apoteker_nama) ?></div>
            </div>
            <div class="kop-meta-right">
              <div>SIPA: <?= htmlspecialchars($apoteker_sipa) ?></div>
              <div>SIA: <?= htmlspecialchars($apoteker_sia) ?></div>
            </div>
          </div>
          <div class="divider-solid"></div>
        </div>

        <!-- Body Resep -->
        <div class="content-body">
          <div class="row-date">Tgl : <?= $tgl_resep_formatted ?></div>
          <div class="row-patient"><?= htmlspecialchars($resep['nm_pasien']) ?> (<?= htmlspecialchars($resep['no_rkm_medis']) ?>)</div>
          <div class="row-medicine"><?= htmlspecialchars($nama_obat_full) ?></div>

          <div class="row-signa"><?= htmlspecialchars($aturan_full) ?></div>

          <div class="row-ed">ED: <?= $ed_text ?></div>
        </div>

        <!-- Slogan & Footer Divider -->
        <div>
          <div class="row-footer-slogan">Semoga Lekas Sembuh</div>
          <div class="divider-footer"></div>
        </div>

      </div>
    <?php endforeach; ?>

    <!-- 2. Obat Racikan -->
    <?php foreach ($racikan as $idx_r => $rck): ?>
      <?php
        $ed_racik = date('d-m-Y', strtotime('+3 months', strtotime($resep['tgl_peresepan'] ?? date('Y-m-d'))));
        $nama_racik_full = trim($rck['nama_racik']) . '  ' . trim($rck['jml_dr']) . ' ' . trim($rck['nm_racik'] ?: 'BKS');
        $aturan_racik = trim($rck['aturan_pakai'] ?: 'Sesuai Petunjuk Dokter');
      ?>
      <div class="label-page">
        
        <!-- Kop Apotek / Klinik -->
        <div class="kop-header">
          <div class="kop-nama"><?= htmlspecialchars(INSTANSI_NAMA) ?></div>
          <div class="kop-alamat"><?= htmlspecialchars(INSTANSI_ALAMAT) ?>, <?= htmlspecialchars(INSTANSI_KOTA) ?></div>
          <div class="kop-meta-grid">
            <div class="kop-meta-left">
              <div>Telp. <?= htmlspecialchars(INSTANSI_TELP) ?></div>
              <div>Apoteker: <?= htmlspecialchars($apoteker_nama) ?></div>
            </div>
            <div class="kop-meta-right">
              <div>SIPA: <?= htmlspecialchars($apoteker_sipa) ?></div>
              <div>SIA: <?= htmlspecialchars($apoteker_sia) ?></div>
            </div>
          </div>
          <div class="divider-solid"></div>
        </div>

        <!-- Body Resep -->
        <div class="content-body">
          <div class="row-date">Tgl : <?= $tgl_resep_formatted ?></div>
          <div class="row-patient"><?= htmlspecialchars($resep['nm_pasien']) ?> (<?= htmlspecialchars($resep['no_rkm_medis']) ?>)</div>
          <div class="row-medicine"><?= htmlspecialchars($nama_racik_full) ?></div>

          <div class="row-signa"><?= htmlspecialchars($aturan_racik) ?></div>

          <div class="row-ed">ED: <?= $ed_racik ?></div>
        </div>

        <!-- Slogan & Footer Divider -->
        <div>
          <div class="row-footer-slogan">Semoga Lekas Sembuh</div>
          <div class="divider-footer"></div>
        </div>

      </div>
    <?php endforeach; ?>

  </div>

  <!-- Red PDF Icon Badge -->
  <div class="pdf-badge-floating">
    <i class="fas fa-file-pdf"></i>
  </div>

  <script>
  let currentZoom = 1.5;

  function changeZoom(delta, exactVal = null) {
    if (exactVal !== null) {
      currentZoom = exactVal;
    } else {
      currentZoom = Math.min(Math.max(currentZoom + delta, 0.8), 2.5);
    }
    document.documentElement.style.setProperty('--zoom-level', currentZoom);
    document.getElementById('lblZoom').textContent = Math.round(currentZoom * 100) + '%';
  }
  </script>

</body>
</html>
