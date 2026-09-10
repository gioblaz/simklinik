<?php
/**
 * SIMKlinik — Farmasi: Cetak Etiket Obat & Bukti Resep
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_resep = $conn->real_escape_string($_GET['no_resep'] ?? '');
if (empty($no_resep)) {
    die("Nomor resep tidak valid.");
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
    WHERE ro.no_resep = '$no_resep'
");

$resep = $res ? $res->fetch_assoc() : null;
if (!$resep) {
    die("Data resep obat tidak ditemukan.");
}

$items_res = $conn->query("
    SELECT rd.*, db.nama_brng, db.ralan as harga, ks.satuan, kb.nama as kategori, j.nama as jenis
    FROM resep_dokter rd
    JOIN databarang db ON rd.kode_brng = db.kode_brng
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
    LEFT JOIN jenis j ON db.kdjns = j.kdjns
    WHERE rd.no_resep = '$no_resep'
");

$items = [];
if ($items_res) while ($r = $items_res->fetch_assoc()) $items[] = $r;

// Query obat racikan
$racikan_res = $conn->query("
    SELECT rdr.*, mr.nm_racik
    FROM resep_dokter_racikan rdr
    LEFT JOIN metode_racik mr ON rdr.kd_racik = mr.kd_racik
    WHERE rdr.no_resep = '$no_resep'
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
            WHERE rdrd.no_resep = '$no_resep' AND rdrd.no_racik = '$no_rck'
            ORDER BY db.nama_brng ASC
        ");
        $details = [];
        if ($detail_res) while ($det = $detail_res->fetch_assoc()) $details[] = $det;
        $rck['detail'] = $details;
        $racikan[] = $rck;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Etiket Obat — <?= htmlspecialchars($no_resep) ?></title>
  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 11px;
      margin: 0;
      padding: 15px;
      color: #0f172a;
    }
    .etiket-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 15px;
    }
    .etiket-card {
      border: 1.5px solid #0f172a;
      border-radius: 6px;
      padding: 10px;
      background: #ffffff;
      page-break-inside: avoid;
    }
    .etiket-card.obat-luar {
      border-color: #0284c7;
      background: #f0f9ff;
    }
    .etiket-header {
      text-align: center;
      border-bottom: 1px dashed #64748b;
      padding-bottom: 6px;
      margin-bottom: 8px;
    }
    .instansi-nama {
      font-weight: 800;
      font-size: 12px;
      text-transform: uppercase;
    }
    .instansi-info {
      font-size: 9px;
      color: #64748b;
    }
    .pasien-info {
      margin-bottom: 6px;
      font-size: 10.5px;
    }
    .aturan-box {
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      padding: 6px 8px;
      text-align: center;
      margin: 8px 0;
      font-weight: 800;
      font-size: 13px;
      color: #0f172a;
    }
    .etiket-card.obat-luar .aturan-box {
      background: #e0f2fe;
      border-color: #7dd3fc;
      color: #0369a1;
    }
    .etiket-footer {
      display: flex;
      justify-content: space-between;
      font-size: 9px;
      color: #64748b;
      margin-top: 6px;
      border-top: 1px dashed #cbd5e1;
      padding-top: 4px;
    }
    @media print {
      .no-print { display: none; }
      body { padding: 0; }
    }
  </style>
</head>
<body>

  <div class="no-print" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 6px;">
    <div>
      <strong>Cetak Etiket Resep: <?= htmlspecialchars($no_resep) ?></strong> (<?= htmlspecialchars($resep['nm_pasien']) ?>)
    </div>
    <button onclick="window.print()" style="padding: 6px 14px; background: #0284c7; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
      Print / Cetak Etiket
    </button>
  </div>

  <div class="etiket-grid">
    <?php foreach ($items as $idx => $it): ?>
      <?php
        $is_luar = (stripos($it['jenis'] ?? '', 'salep') !== false || stripos($it['nama_brng'] ?? '', 'salep') !== false || stripos($it['nama_brng'] ?? '', 'tetes') !== false || stripos($it['nama_brng'] ?? '', 'betadine') !== false);
      ?>
      <div class="etiket-card <?= $is_luar ? 'obat-luar' : '' ?>">
        <div class="etiket-header">
          <div class="instansi-nama"><?= INSTANSI_NAMA ?></div>
          <div class="instansi-info"><?= INSTANSI_ALAMAT ?>, Telp: <?= INSTANSI_TELP ?></div>
          <div style="font-weight: 700; font-size: 10px; margin-top: 2px; color: <?= $is_luar ? '#0284c7' : '#0f172a' ?>;">
            <?= $is_luar ? 'OBAT LUAR (TIDAK DITELAN)' : 'OBAT DALAM' ?>
          </div>
        </div>

        <div class="pasien-info">
          <div><strong>No. RM:</strong> <?= htmlspecialchars($resep['no_rkm_medis']) ?> | <strong>Tgl:</strong> <?= date('d/m/Y', strtotime($resep['tgl_peresepan'])) ?></div>
          <div><strong>Nama:</strong> <?= htmlspecialchars($resep['nm_pasien']) ?> (<?= hitung_umur($resep['tgl_lahir']) ?>)</div>
        </div>

        <div style="font-weight: 700; font-size: 12px; color: #0f172a; margin-top: 4px;">
          <?= htmlspecialchars($it['nama_brng']) ?>
        </div>
        <div style="font-size: 10px; color: #64748b;">
          Jumlah: <strong><?= $it['jml'] ?> <?= htmlspecialchars($it['satuan'] ?: 'Item') ?></strong>
        </div>

        <div class="aturan-box">
          <?= htmlspecialchars($it['aturan_pakai'] ?: 'Sesuai Petunjuk Dokter') ?>
        </div>

        <div class="etiket-footer">
          <div>Dr: <?= htmlspecialchars($resep['nm_dokter']) ?></div>
          <div>No: <?= htmlspecialchars($no_resep) ?></div>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- Etiket Obat Racikan -->
    <?php foreach ($racikan as $idx => $rck): ?>
      <?php
        $is_luar_rck = (stripos($rck['nm_racik'] ?? '', 'salep') !== false || stripos($rck['nama_racik'] ?? '', 'salep') !== false);
      ?>
      <div class="etiket-card <?= $is_luar_rck ? 'obat-luar' : '' ?>" style="border-width: 2px; border-style: solid;">
        <div class="etiket-header">
          <div class="instansi-nama"><?= INSTANSI_NAMA ?></div>
          <div class="instansi-info"><?= INSTANSI_ALAMAT ?>, Telp: <?= INSTANSI_TELP ?></div>
          <div style="font-weight: 700; font-size: 10px; margin-top: 2px; color: <?= $is_luar_rck ? '#0284c7' : '#059669' ?>;">
            🧪 <?= $is_luar_rck ? 'OBAT LUAR RACIKAN' : 'OBAT RACIKAN (' . htmlspecialchars(strtoupper($rck['nm_racik'] ?? 'RACIKAN')) . ')' ?>
          </div>
        </div>

        <div class="pasien-info">
          <div><strong>No. RM:</strong> <?= htmlspecialchars($resep['no_rkm_medis']) ?> | <strong>Tgl:</strong> <?= date('d/m/Y', strtotime($resep['tgl_peresepan'])) ?></div>
          <div><strong>Nama:</strong> <?= htmlspecialchars($resep['nm_pasien']) ?> (<?= hitung_umur($resep['tgl_lahir']) ?>)</div>
        </div>

        <div style="font-weight: 700; font-size: 12px; color: #0f172a; margin-top: 4px;">
          <?= htmlspecialchars($rck['nama_racik']) ?>
        </div>
        <div style="font-size: 10px; color: #64748b;">
          Bentuk / Jumlah: <strong><?= $rck['jml_dr'] ?> <?= htmlspecialchars($rck['nm_racik'] ?? 'Bungkus') ?></strong>
        </div>

        <?php if (!empty($rck['detail'])): ?>
          <div style="margin: 4px 0; padding: 4px 6px; background: #fff; border: 1px dashed #cbd5e1; border-radius: 4px; font-size: 9px; color: #475569;">
            <strong>Komposisi:</strong>
            <?php 
              $ing_arr = [];
              foreach ($rck['detail'] as $det) {
                $ing_arr[] = htmlspecialchars($det['nama_brng']) . ' (' . $det['jml'] . ')';
              }
              echo implode(', ', $ing_arr);
            ?>
          </div>
        <?php endif; ?>

        <div class="aturan-box">
          <?= htmlspecialchars($rck['aturan_pakai'] ?: 'Sesuai Petunjuk Dokter') ?>
        </div>

        <?php if (!empty($rck['keterangan'])): ?>
          <div style="font-size: 9.5px; font-style: italic; color: #475569; margin-bottom: 4px;">
            Ket: <?= htmlspecialchars($rck['keterangan']) ?>
          </div>
        <?php endif; ?>

        <div class="etiket-footer">
          <div>Dr: <?= htmlspecialchars($resep['nm_dokter']) ?></div>
          <div>No: <?= htmlspecialchars($no_resep) ?> (R.<?= $rck['no_racik'] ?>)</div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</body>
</html>
