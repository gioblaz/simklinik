<?php
/**
 * SIMKlinik — Gudang Obat: Cetak Surat Pesanan (SP) Obat Resmi
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$po_id       = (int)($_GET['id'] ?? 0);
$no_po_param = sanitize($_GET['no_pemesanan'] ?? '');

if ($po_id) {
    $where_po = "po.id = '$po_id'";
} elseif (!empty($no_po_param)) {
    $no_po_esc_p = $conn->real_escape_string($no_po_param);
    $where_po = "po.no_pemesanan = '$no_po_esc_p'";
} else {
    die("Parameter pemesanan tidak valid.");
}

$po_res = $conn->query("
    SELECT po.*, sup.alamat as alamat_sup, sup.kota as kota_sup, sup.no_telp as telp_sup
    FROM mlite_farmasi_pemesanan_obat po
    LEFT JOIN datasuplier sup ON po.supplier_kode = sup.kode_suplier
    WHERE $where_po
    ORDER BY po.id ASC
    LIMIT 1
");

$po = $po_res ? $po_res->fetch_assoc() : null;
if (!$po) {
    die("Data pemesanan obat tidak ditemukan.");
}

$no_po_esc = $conn->real_escape_string($po['no_pemesanan']);
$items_res = $conn->query("
    SELECT po.*, db.nama_brng, ks.satuan, kb.nama as nama_kategori
    FROM mlite_farmasi_pemesanan_obat po
    JOIN databarang db ON po.kode_brng = db.kode_brng
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
    WHERE po.no_pemesanan = '$no_po_esc'
    ORDER BY po.id ASC
");

$po_items = [];
$grand_total = 0;
if ($items_res) {
    while ($it = $items_res->fetch_assoc()) {
        $po_items[] = $it;
        $grand_total += (float)$it['total_biaya'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Surat Pesanan Obat — <?= htmlspecialchars($po['no_pemesanan']) ?></title>
  <style>
    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      margin: 0;
      padding: 30px;
      color: #1e293b;
      font-size: 13px;
      line-height: 1.5;
    }
    .header {
      border-bottom: 2px solid #0f172a;
      padding-bottom: 12px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .instansi-title {
      font-size: 18px;
      font-weight: 800;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .instansi-sub {
      font-size: 12px;
      color: #64748b;
    }
    .sp-title {
      text-align: center;
      margin: 20px 0;
    }
    .sp-title h2 {
      margin: 0;
      font-size: 16px;
      font-weight: 800;
      text-transform: uppercase;
      text-decoration: underline;
    }
    .sp-title p {
      margin: 3px 0 0;
      font-size: 12px;
      color: #475569;
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
      background: #f8fafc;
      padding: 12px 16px;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 25px;
    }
    th, td {
      border: 1px solid #cbd5e1;
      padding: 8px 12px;
      text-align: left;
    }
    th {
      background: #f1f5f9;
      font-weight: 700;
      font-size: 12px;
    }
    .signatures {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      margin-top: 40px;
      text-align: center;
    }
    .sig-space {
      height: 70px;
    }
    @media print {
      body { padding: 0; }
      .no-print { display: none; }
    }
  </style>
</head>
<body>

  <div class="no-print" style="margin-bottom: 20px; text-align: right;">
    <button onclick="window.print()" style="padding: 8px 16px; background: #0284c7; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
      Print / Cetak Surat Pesanan
    </button>
  </div>

  <div class="header">
    <div>
      <div class="instansi-title"><?= INSTANSI_NAMA ?></div>
      <div class="instansi-sub"><?= INSTANSI_ALAMAT ?>, <?= INSTANSI_KOTA ?> | Telp: <?= INSTANSI_TELP ?></div>
    </div>
    <div style="text-align: right;">
      <div style="font-size: 11px; color: #64748b;">SURAT PESANAN OBAT & ALKES</div>
      <div style="font-weight: 800; font-size: 14px; color: #0f172a;"><?= htmlspecialchars($po['no_pemesanan']) ?></div>
    </div>
  </div>

  <div class="sp-title">
    <h2>SURAT PESANAN OBAT (SP)</h2>
    <p>Nomor: <?= htmlspecialchars($po['no_pemesanan']) ?></p>
  </div>

  <p>Yang bertanda tangan di bawah ini mengajukan pesanan obat / perbekalan farmasi kepada:</p>

  <div class="info-grid">
    <div>
      <strong>Kepada Distributor / PBF:</strong><br>
      <span style="font-size: 14px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($po['supplier']) ?></span><br>
      <?= htmlspecialchars($po['alamat_sup'] ?: '-') ?>, <?= htmlspecialchars($po['kota_sup'] ?: '') ?><br>
      Telp: <?= htmlspecialchars($po['telp_sup'] ?: '-') ?>
    </div>
    <div>
      <strong>Tanggal Pesanan:</strong> <?= tgl_indo($po['tanggal_pemesanan'], true) ?><br>
      <strong>Pemesan:</strong> <?= INSTANSI_NAMA ?><br>
      <strong>Alamat Kirim:</strong> <?= INSTANSI_ALAMAT ?>, <?= INSTANSI_KOTA ?>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width: 40px; text-align: center;">No</th>
        <th style="width: 110px;">Kode Barang</th>
        <th>Nama Obat / Bentuk Sediaan</th>
        <th>Satuan</th>
        <th style="width: 100px; text-align: center;">Jumlah Pesan</th>
        <th style="width: 120px; text-align: right;">Estimasi HPP</th>
        <th style="width: 130px; text-align: right;">Total Estimasi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($po_items as $idx => $it): ?>
      <tr>
        <td style="text-align: center;"><?= $idx + 1 ?></td>
        <td><code><?= htmlspecialchars($it['kode_brng']) ?></code></td>
        <td>
          <strong><?= htmlspecialchars($it['nama_brng']) ?></strong>
          <?php if (!empty($it['catatan'])): ?>
            <div style="font-size: 11px; color: #64748b;">Catatan: <?= htmlspecialchars($it['catatan']) ?></div>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($it['satuan'] ?: '-') ?></td>
        <td style="text-align: center; font-weight: 700;"><?= (int)$it['jumlah_pesan'] ?></td>
        <td style="text-align: right;"><?= rupiah((float)$it['h_beli']) ?></td>
        <td style="text-align: right; font-weight: 700;"><?= rupiah((float)$it['total_biaya']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="6" style="text-align: right;">TOTAL ESTIMASI PEMESANAN:</th>
        <th style="text-align: right; font-size: 13px;"><?= rupiah($grand_total) ?></th>
      </tr>
    </tfoot>
  </table>

  <div class="signatures">
    <div>
      Penerima Pesanan / Sales PBF,<br>
      <div class="sig-space"></div>
      ( .................................................... )
    </div>
    <div>
      <?= INSTANSI_KOTA ?>, <?= tgl_indo($po['tanggal_pemesanan']) ?><br>
      Apoteker Penanggung Jawab / Pengadaan,<br>
      <div class="sig-space"></div>
      <strong>( <?= htmlspecialchars($po['dibuat_oleh'] ?: 'Apoteker Pengelola') ?> )</strong><br>
      <span style="font-size: 11px; color: #64748b;">SIPA: ............................................</span>
    </div>
  </div>

</body>
</html>
