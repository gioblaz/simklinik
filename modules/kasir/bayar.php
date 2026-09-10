<?php
/**
 * SIMKlinik — Rincian Tagihan, Proses Pembayaran & Cetak Kwitansi
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$no_rawat = sanitize($_GET['no_rawat'] ?? '');
if (empty($no_rawat)) redirect(BASE_URL . 'modules/kasir/index.php');

$rawat_esc = $conn->real_escape_string($no_rawat);

// ─── Load Data Pasien & Registrasi ───────────────────────────
$res = $conn->query("
    SELECT r.*, p.nm_pasien, p.jk, p.tgl_lahir, p.no_ktp, p.no_peserta,
           p.alamat, p.no_tlp,
           d.nm_dokter, pol.nm_poli, pol.registrasi as tarif_poli,
           pj.png_jawab as nm_penjab
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE r.no_rawat = '$rawat_esc'
    LIMIT 1
");

if (!$res || $res->num_rows === 0) {
    set_flash('danger', 'Data kunjungan tidak ditemukan.');
    redirect(BASE_URL . 'modules/kasir/index.php');
}
$pasien = $res->fetch_assoc();

// ─── Hitung Item Rincian Tagihan ─────────────────────────────
// 1. Biaya Pendaftaran & Jasa Poli
$biaya_registrasi = (float)($pasien['biaya_reg'] > 0 ? $pasien['biaya_reg'] : ($pasien['tarif_poli'] ?? 0));

// 2. Biaya Obat Farmasi
$obat_res = $conn->query("
    SELECT rd.kode_brng, rd.jml, rd.aturan_pakai,
           db.nama_brng, db.ralan as harga, (rd.jml * db.ralan) as subtotal,
           ks.satuan
    FROM resep_obat ro
    JOIN resep_dokter rd ON ro.no_resep = rd.no_resep
    JOIN databarang db ON rd.kode_brng = db.kode_brng
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    WHERE ro.no_rawat = '$rawat_esc'
");
$obat_items = [];
$total_obat = 0;
if ($obat_res) {
    while ($row = $obat_res->fetch_assoc()) {
        $total_obat += (float)$row['subtotal'];
        $obat_items[] = $row;
    }
}

// 3. Biaya Tindakan Rawat Jalan
$tindakan_res = $conn->query("
    SELECT rj.biaya_rawat, jp.nm_perawatan
    FROM rawat_jl_dr rj
    JOIN jns_perawatan jp ON rj.kd_jenis_prw = jp.kd_jenis_prw
    WHERE rj.no_rawat = '$rawat_esc'
");
$tindakan_items = [];
$total_tindakan = 0;
if ($tindakan_res) {
    while ($row = $tindakan_res->fetch_assoc()) {
        $total_tindakan += (float)$row['biaya_rawat'];
        $tindakan_items[] = $row;
    }
}

// 4. Biaya Pemeriksaan Laboratorium
$lab_res = $conn->query("
    SELECT pl.biaya, jpl.nm_perawatan
    FROM periksa_lab pl
    JOIN jns_perawatan_lab jpl ON pl.kd_jenis_prw = jpl.kd_jenis_prw
    WHERE pl.no_rawat = '$rawat_esc'
");
$lab_items = [];
$total_lab = 0;
if ($lab_res) {
    while ($row = $lab_res->fetch_assoc()) {
        $total_lab += (float)$row['biaya'];
        $lab_items[] = $row;
    }
}

// Grand Total
$grand_total = $biaya_registrasi + $total_obat + $total_tindakan + $total_lab;

// ─── Proses Pelunasan Kasir ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_bayar'])) {
    $metode_bayar = $conn->real_escape_string(sanitize($_POST['metode_bayar'] ?? 'Tunai'));
    $nominal_bayar = (float)($_POST['nominal_bayar'] ?? $grand_total);
    $kembalian     = max(0, $nominal_bayar - $grand_total);

    // Update status bayar pasien
    $upd = $conn->query("
        UPDATE reg_periksa
        SET status_bayar = 'Sudah Bayar',
            biaya_reg = '$biaya_registrasi'
        WHERE no_rawat = '$rawat_esc'
    ");

    if ($upd) {
        set_flash('success', 'Pembayaran sebesar <strong>' . rupiah($grand_total) . '</strong> berhasil dicatat.');
        redirect(BASE_URL . 'modules/kasir/bayar.php?no_rawat=' . urlencode($no_rawat));
    } else {
        set_flash('danger', 'Gagal memproses pembayaran: ' . $conn->error);
    }
}

// Check jika mode cetak langsung
$mode_cetak = isset($_GET['print']);

if ($mode_cetak) {
    // Tampilkan lembar kwitansi cetak
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <title>Kwitansi - <?= htmlspecialchars($pasien['nm_pasien']) ?></title>
      <style>
        @page { size: 148mm 210mm; margin: 8mm; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 10pt; color: #000; margin: 0; padding: 15px; }
        .kop { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 8px; margin-bottom: 10px; }
        .kop h2 { margin: 0; font-size: 14pt; }
        .kop p { margin: 2px 0; font-size: 8.5pt; }
        .title { text-align: center; font-weight: bold; margin-bottom: 10px; text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        td { padding: 2px 4px; vertical-align: top; }
        .line { border-bottom: 1px dashed #000; margin: 6px 0; }
        .text-right { text-align: right; }
        .footer-sign { margin-top: 20px; display: flex; justify-content: space-between; text-align: center; }
        .btn-print { margin-bottom: 15px; text-align: right; font-family: sans-serif; }
        @media print { .btn-print { display: none; } }
      </style>
    </head>
    <body onload="window.print()">
      <div class="btn-print">
        <button onclick="window.print()" style="padding:6px 14px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;">🖨️ Cetak Kwitansi</button>
      </div>

      <div class="kop">
        <h2><?= INSTANSI_NAMA ?></h2>
        <p><?= INSTANSI_ALAMAT ?>, <?= INSTANSI_KOTA ?> | Telp: <?= INSTANSI_TELP ?></p>
      </div>

      <div class="title">KWITANSI / BUKTI PEMBAYARAN</div>

      <table>
        <tr><td style="width:28%;">No. Rawat</td><td style="width:2%;">:</td><td><?= $pasien['no_rawat'] ?></td></tr>
        <tr><td>No. RM</td><td>:</td><td><strong><?= $pasien['no_rkm_medis'] ?></strong></td></tr>
        <tr><td>Nama Pasien</td><td>:</td><td><strong><?= htmlspecialchars($pasien['nm_pasien']) ?></strong></td></tr>
        <tr><td>Poli / Dokter</td><td>:</td><td><?= htmlspecialchars($pasien['nm_poli']) ?> / <?= htmlspecialchars($pasien['nm_dokter']) ?></td></tr>
        <tr><td>Penjamin</td><td>:</td><td><?= htmlspecialchars($pasien['nm_penjab'] ?: 'Umum') ?></td></tr>
        <tr><td>Tanggal</td><td>:</td><td><?= tgl_indo(date('Y-m-d')) ?></td></tr>
      </table>

      <div class="line"></div>
      <table>
        <tr style="font-weight:bold;"><td>RINCIAN LAYANAN</td><td class="text-right">BIAYA</td></tr>
        <tr>
          <td>Pendaftaran & Konsultasi Dokter (<?= htmlspecialchars($pasien['nm_poli']) ?>)</td>
          <td class="text-right"><?= rupiah($biaya_registrasi) ?></td>
        </tr>
        <?php foreach ($tindakan_items as $t): ?>
          <tr>
            <td>Tindakan: <?= htmlspecialchars($t['nm_perawatan']) ?></td>
            <td class="text-right"><?= rupiah((float)$t['biaya_rawat']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($lab_items as $l): ?>
          <tr>
            <td>Laboratorium: <?= htmlspecialchars($l['nm_perawatan']) ?></td>
            <td class="text-right"><?= rupiah((float)$l['biaya']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($obat_items as $o): ?>
          <tr>
            <td><?= htmlspecialchars($o['nama_brng']) ?> (<?= $o['jml'] ?> x <?= rupiah((float)$o['harga']) ?>)</td>
            <td class="text-right"><?= rupiah((float)$o['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>

      <div class="line"></div>
      <table>
        <tr style="font-weight:bold;font-size:11pt;">
          <td>TOTAL BIAYA</td>
          <td class="text-right"><?= rupiah($grand_total) ?></td>
        </tr>
        <tr>
          <td>STATUS</td>
          <td class="text-right"><strong>LUNAS (<?= strtoupper($pasien['status_bayar']) ?>)</strong></td>
        </tr>
      </table>

      <div class="footer-sign">
        <div>
          Pasien / Keluarga,<br><br><br>
          ( ....................... )
        </div>
        <div>
          Kasir / Petugas,<br><br><br>
          ( <strong><?= htmlspecialchars(explode(' ', current_user()['fullname'] ?? 'Kasir')[0]) ?></strong> )
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$page_title = 'Pembayaran: ' . $pasien['nm_pasien'];
$active_module = 'kasir';
include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Breadcrumb ───────────────────────────────────────── -->
<div class="breadcrumb">
  <a href="<?= BASE_URL ?>modules/kasir/index.php">Kasir & Billing</a>
  <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
  <span class="breadcrumb-current">Rincian Pembayaran</span>
</div>

<div class="page-header">
  <div>
    <h1 class="page-title">Rincian Pembayaran & Nota</h1>
    <p class="page-subtitle">No. Rawat: <strong><?= $pasien['no_rawat'] ?></strong> &mdash; Pasien: <strong><?= htmlspecialchars($pasien['nm_pasien']) ?></strong></p>
  </div>
  <div class="page-actions">
    <a href="<?= BASE_URL ?>modules/kasir/bayar.php?no_rawat=<?= urlencode($no_rawat) ?>&print=1" target="_blank" class="btn btn-outline">
      <i class="fas fa-print"></i> Cetak Kwitansi
    </a>
    <a href="<?= BASE_URL ?>modules/kasir/index.php" class="btn btn-outline">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:16px;align-items:start;">

  <!-- ─── Left Column: Itemized Invoice ───────────────────── -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Identitas Pasien Banner -->
    <div class="card">
      <div class="card-body" style="padding:16px 20px;">
        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:12px;font-size:12px;">
          <div>
            <span style="color:var(--gray-400);display:block;">No. Rekam Medis</span>
            <strong style="color:var(--primary-600);font-size:13px;"><?= $pasien['no_rkm_medis'] ?></strong>
          </div>
          <div>
            <span style="color:var(--gray-400);display:block;">Nama Pasien</span>
            <strong style="color:var(--gray-900);font-size:13px;"><?= htmlspecialchars($pasien['nm_pasien']) ?></strong>
          </div>
          <div>
            <span style="color:var(--gray-400);display:block;">Penjamin</span>
            <span class="badge badge-<?= str_contains(strtolower($pasien['nm_penjab']??''), 'bpjs') ? 'primary' : 'secondary' ?>">
              <?= htmlspecialchars($pasien['nm_penjab'] ?: 'Umum') ?>
            </span>
          </div>
          <div>
            <span style="color:var(--gray-400);display:block;">Poliklinik</span>
            <strong><?= htmlspecialchars($pasien['nm_poli']) ?></strong>
          </div>
          <div>
            <span style="color:var(--gray-400);display:block;">Dokter</span>
            <strong><?= htmlspecialchars($pasien['nm_dokter']) ?></strong>
          </div>
          <div>
            <span style="color:var(--gray-400);display:block;">Status Pelunasan</span>
            <span class="badge <?= $pasien['status_bayar']==='Sudah Bayar' ? 'badge-success' : 'badge-danger' ?>">
              <?= htmlspecialchars($pasien['status_bayar']) ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Rincian Layanan & Tarif -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-file-invoice-dollar text-primary"></i> Rincian Biaya Pelayanan</div>
      </div>
      <div class="card-body" style="padding:0;">
        <table class="table">
          <thead>
            <tr>
              <th>Deskripsi Layanan / Item</th>
              <th style="width:80px;text-align:center;">Qty</th>
              <th style="width:120px;text-align:right;">Tarif Satuan</th>
              <th style="width:130px;text-align:right;">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <!-- Pendaftaran & Konsul -->
            <tr>
              <td>
                <div style="font-weight:600;color:var(--gray-900);">Registrasi & Jasa Konsultasi Poliklinik</div>
                <div style="font-size:11px;color:var(--gray-400);"><?= htmlspecialchars($pasien['nm_poli']) ?> &mdash; <?= htmlspecialchars($pasien['nm_dokter']) ?></div>
              </td>
              <td style="text-align:center;">1</td>
              <td style="text-align:right;"><?= rupiah($biaya_registrasi) ?></td>
              <td style="text-align:right;font-weight:600;"><?= rupiah($biaya_registrasi) ?></td>
            </tr>

            <!-- Tindakan Medis -->
            <?php foreach ($tindakan_items as $t): ?>
              <tr>
                <td>
                  <div style="font-weight:600;color:var(--gray-900);">Tindakan: <?= htmlspecialchars($t['nm_perawatan']) ?></div>
                </td>
                <td style="text-align:center;">1</td>
                <td style="text-align:right;"><?= rupiah((float)$t['biaya_rawat']) ?></td>
                <td style="text-align:right;font-weight:600;"><?= rupiah((float)$t['biaya_rawat']) ?></td>
              </tr>
            <?php endforeach; ?>

            <!-- Pemeriksaan Laboratorium -->
            <?php foreach ($lab_items as $l): ?>
              <tr>
                <td>
                  <div style="font-weight:600;color:var(--gray-900);">Laboratorium: <?= htmlspecialchars($l['nm_perawatan']) ?></div>
                  <div style="font-size:11px;color:#0284c7;"><i class="fas fa-flask"></i> Pemeriksaan Lab</div>
                </td>
                <td style="text-align:center;">1</td>
                <td style="text-align:right;"><?= rupiah((float)$l['biaya']) ?></td>
                <td style="text-align:right;font-weight:600;"><?= rupiah((float)$l['biaya']) ?></td>
              </tr>
            <?php endforeach; ?>

            <!-- Obat Farmasi -->
            <?php foreach ($obat_items as $o): ?>
              <tr>
                <td>
                  <div style="font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($o['nama_brng']) ?></div>
                  <div style="font-size:11px;color:var(--primary-600);"><i class="fas fa-clock"></i> <?= htmlspecialchars($o['aturan_pakai'] ?: '-') ?></div>
                </td>
                <td style="text-align:center;"><?= $o['jml'] ?> <?= htmlspecialchars($o['satuan']?:'tab') ?></td>
                <td style="text-align:right;"><?= rupiah((float)$o['harga']) ?></td>
                <td style="text-align:right;font-weight:600;"><?= rupiah((float)$o['subtotal']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--gray-50);font-size:14px;">
              <td colspan="3" style="font-weight:700;text-align:right;padding:14px;">TOTAL TAGIHAN:</td>
              <td style="text-align:right;font-weight:700;color:var(--primary-700);font-size:16px;padding:14px;">
                <?= rupiah($grand_total) ?>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

  </div>

  <!-- ─── Right Column: Payment Settlement ────────────────── -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <div class="card" style="border-top:4px solid <?= $pasien['status_bayar']==='Sudah Bayar' ? 'var(--success)' : 'var(--primary-600)' ?>;">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-cash-register"></i> Pembayaran Kasir</div>
      </div>
      <div class="card-body">
        <?php if ($pasien['status_bayar'] === 'Sudah Bayar'): ?>
          <div style="text-align:center;padding:20px 0;">
            <div style="width:64px;height:64px;background:var(--success-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--success);font-size:28px;">
              <i class="fas fa-check-circle"></i>
            </div>
            <h3 style="font-size:16px;font-weight:700;color:var(--gray-900);margin-bottom:4px;">Tagihan Telah Lunas</h3>
            <p style="font-size:12px;color:var(--gray-500);margin-bottom:16px;">Transaksi pembayaran telah diselesaikan.</p>
            <a href="<?= BASE_URL ?>modules/kasir/bayar.php?no_rawat=<?= urlencode($no_rawat) ?>&print=1" target="_blank" class="btn btn-primary" style="width:100%;justify-content:center;">
              <i class="fas fa-print"></i> Cetak Ulang Kwitansi
            </a>
          </div>
        <?php else: ?>
          <form method="POST" action="">
            <input type="hidden" name="proses_bayar" value="1">

            <div class="form-group">
              <label class="form-label">Metode Pembayaran</label>
              <select name="metode_bayar" class="form-control">
                <option value="Tunai">Tunai / Cash</option>
                <option value="Transfer">Transfer Bank / QRIS</option>
                <option value="BPJS">Jaminan BPJS Kesehatan</option>
                <option value="Asuransi Lain">Asuransi Swasta / Perusahaan</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Total Tagihan (Rp)</label>
              <input type="text" class="form-control" readonly value="<?= rupiah($grand_total) ?>" style="font-size:16px;font-weight:700;color:var(--primary-700);background:var(--primary-50);">
            </div>

            <div class="form-group">
              <label class="form-label">Uang Diterima (Rp)</label>
              <input type="number" name="nominal_bayar" id="inputBayar" class="form-control" placeholder="<?= $grand_total ?>" value="<?= $grand_total ?>" min="<?= $grand_total ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Kembalian (Rp)</label>
              <input type="text" id="outputKembali" class="form-control" readonly value="Rp 0" style="font-weight:600;background:var(--gray-100);">
            </div>

            <button type="submit" class="btn btn-success" style="width:100%;justify-content:center;padding:12px;font-size:14px;font-weight:600;margin-top:8px;">
              <i class="fas fa-check-circle"></i> Selesaikan Pembayaran
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>

<script>
const inputBayar    = document.getElementById('inputBayar');
const outputKembali = document.getElementById('outputKembali');
const grandTotal    = <?= (float)$grand_total ?>;

inputBayar?.addEventListener('input', function() {
  const bayar = parseFloat(this.value) || 0;
  const kembali = Math.max(0, bayar - grandTotal);
  outputKembali.value = formatRupiah(kembali);
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
