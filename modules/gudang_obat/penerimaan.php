<?php
/**
 * SIMKlinik — Gudang Obat: Penerimaan Obat & Faktur Masuk
 */

$page_title    = 'Penerimaan Obat & Faktur';
$active_module = 'gudang_obat';
$sub_active    = 'penerimaan';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Pastikan tabel penerimaan obat ada & memiliki kolom lengkap
$conn->query("
    CREATE TABLE IF NOT EXISTS `mlite_farmasi_penerimaan_obat` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `pemesanan_id` int(11) DEFAULT NULL,
      `no_pemesanan` varchar(50) DEFAULT NULL,
      `nomor_faktur` varchar(100) DEFAULT NULL,
      `kode_brng` varchar(15) DEFAULT NULL,
      `tanggal_penerimaan` date NOT NULL,
      `supplier_kode` varchar(50) DEFAULT NULL,
      `supplier` varchar(150) DEFAULT NULL,
      `jumlah_terima` int(11) NOT NULL DEFAULT '0',
      `h_beli` double NOT NULL DEFAULT '0',
      `total_biaya` double NOT NULL DEFAULT '0',
      `no_batch` varchar(50) DEFAULT '-',
      `kadaluarsa` date DEFAULT NULL,
      `kd_bangsal` varchar(5) DEFAULT 'GF',
      `jenis_pembayaran` varchar(20) NOT NULL DEFAULT 'Cash',
      `tanggal_jatuh_tempo` date DEFAULT NULL,
      `catatan` text,
      `dibuat_oleh` varchar(100) DEFAULT '-',
      `created_at` datetime NOT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_pemesanan_id` (`pemesanan_id`),
      KEY `idx_kode_brng` (`kode_brng`),
      KEY `idx_faktur` (`nomor_faktur`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Self-healing: pastikan kolom-kolom baru tersedia jika tabel dibuat dengan skema lama
$check_cols = [
    'no_pemesanan'  => "VARCHAR(50) NULL",
    'kode_brng'     => "VARCHAR(15) NULL",
    'supplier_kode' => "VARCHAR(50) NULL",
    'supplier'      => "VARCHAR(150) NULL",
    'h_beli'        => "DOUBLE NOT NULL DEFAULT 0",
    'total_biaya'   => "DOUBLE NOT NULL DEFAULT 0",
    'no_batch'      => "VARCHAR(50) NULL DEFAULT '-'",
    'kadaluarsa'    => "DATE NULL",
    'kd_bangsal'    => "VARCHAR(5) NULL DEFAULT 'GF'"
];
foreach ($check_cols as $c_name => $c_def) {
    $c_res = $conn->query("SHOW COLUMNS FROM mlite_farmasi_penerimaan_obat LIKE '$c_name'");
    if ($c_res && $c_res->num_rows === 0) {
        $conn->query("ALTER TABLE mlite_farmasi_penerimaan_obat ADD COLUMN $c_name $c_def");
    }
}

$user = current_user();
$petugas_nama = $user['fullname'] ?? 'Petugas Penerimaan';

// ─── Proses Simpan Penerimaan Obat ────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['simpan_penerimaan'])) {
    $po_id       = !empty($_POST['pemesanan_id']) ? (int)$_POST['pemesanan_id'] : null;
    $no_po       = $conn->real_escape_string($_POST['no_pemesanan'] ?? '-');
    $no_faktur   = trim($conn->real_escape_string($_POST['nomor_faktur'] ?? ''));
    $tgl_terima  = $conn->real_escape_string($_POST['tanggal_penerimaan'] ?? date('Y-m-d'));
    $sup_kode    = $conn->real_escape_string($_POST['supplier_kode'] ?? '');
    $sup_nama    = $conn->real_escape_string($_POST['supplier'] ?? '');
    $kode_brng   = $conn->real_escape_string($_POST['kode_brng'] ?? '');
    $jml_terima  = (int)($_POST['jumlah_terima'] ?? 1);
    $h_beli      = (float)($_POST['h_beli'] ?? 0);
    $no_batch    = trim($conn->real_escape_string($_POST['no_batch'] ?? '-'));
    $kadaluarsa  = !empty($_POST['kadaluarsa']) ? $_POST['kadaluarsa'] : date('Y-m-d', strtotime('+2 years'));
    $kd_bangsal  = $conn->real_escape_string($_POST['kd_bangsal'] ?? 'GD');
    $jns_bayar   = $conn->real_escape_string($_POST['jenis_pembayaran'] ?? 'Cash');
    $tgl_tempo   = !empty($_POST['tanggal_jatuh_tempo']) ? $_POST['tanggal_jatuh_tempo'] : null;
    $catatan     = $conn->real_escape_string($_POST['catatan'] ?? '');
    $total_biaya = $jml_terima * $h_beli;
    $now_dt      = date('Y-m-d H:i:s');
    $now_time    = date('H:i:s');

    if (empty($no_faktur) || empty($kode_brng) || empty($sup_nama)) {
        set_flash('danger', 'Nomor Faktur, Supplier, dan Obat wajib diisi.');
    } else {
        // 1. Simpan ke mlite_farmasi_penerimaan_obat
        $sql_terima = "
            INSERT INTO mlite_farmasi_penerimaan_obat (
                pemesanan_id, no_pemesanan, nomor_faktur, kode_brng, tanggal_penerimaan,
                supplier_kode, supplier, jumlah_terima, h_beli, total_biaya,
                no_batch, kadaluarsa, kd_bangsal, jenis_pembayaran, tanggal_jatuh_tempo,
                catatan, dibuat_oleh, created_at
            ) VALUES (
                " . ($po_id ? "'$po_id'" : "NULL") . ", '$no_po', '$no_faktur', '$kode_brng', '$tgl_terima',
                '$sup_kode', '$sup_nama', '$jml_terima', '$h_beli', '$total_biaya',
                '$no_batch', '$kadaluarsa', '$kd_bangsal', '$jns_bayar', " . ($tgl_tempo ? "'$tgl_tempo'" : "NULL") . ",
                '$catatan', '$petugas_nama', '$now_dt'
            )
        ";
        $conn->query($sql_terima);

        // 2. Ambil stok awal sebelum ditambah
        $stok_awal = 0;
        $stk_res = $conn->query("SELECT SUM(stok) as s FROM gudangbarang WHERE kode_brng = '$kode_brng' AND kd_bangsal = '$kd_bangsal'");
        if ($stk_res) $stok_awal = (float)($stk_res->fetch_assoc()['s'] ?? 0);
        $stok_akhir = $stok_awal + $jml_terima;

        // 3. Tambahkan stok di gudangbarang
        $conn->query("
            INSERT INTO gudangbarang (kode_brng, kd_bangsal, stok, no_batch, no_faktur)
            VALUES ('$kode_brng', '$kd_bangsal', '$jml_terima', '$no_batch', '$no_faktur')
            ON DUPLICATE KEY UPDATE stok = stok + $jml_terima
        ");

        // 4. Catat riwayat di riwayat_barang_medis
        $conn->query("
            INSERT INTO riwayat_barang_medis (
                kode_brng, stok_awal, masuk, keluar, stok_akhir, posisi, tanggal, jam, petugas, kd_bangsal, status, no_batch, no_faktur, keterangan
            ) VALUES (
                '$kode_brng', '$stok_awal', '$jml_terima', 0, '$stok_akhir', 'Penerimaan', '$tgl_terima', '$now_time', '$petugas_nama', '$kd_bangsal', 'Simpan', '$no_batch', '$no_faktur', 'Penerimaan Faktur $no_faktur dari $sup_nama'
            )
        ");

        // 5. Update harga beli & kadaluarsa di databarang jika lebih baru/valid
        $conn->query("
            UPDATE databarang SET 
                h_beli = '$h_beli',
                expire = '$kadaluarsa'
            WHERE kode_brng = '$kode_brng'
        ");

        // 6. Jika berasal dari PO, update status PO menjadi Selesai
        if ($po_id) {
            $conn->query("UPDATE mlite_farmasi_pemesanan_obat SET status_pemesanan = 'Selesai' WHERE id = '$po_id'");
        }

        set_flash('success', "Penerimaan faktur <strong>$no_faktur</strong> berhasil diproses. Stok obat bertambah <strong>+$jml_terima</strong>.");
        redirect(BASE_URL . 'modules/gudang_obat/penerimaan.php');
    }
}

// ─── Filter & Riwayat Penerimaan ──────────────────────────────
$tgl_mulai  = sanitize($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_akhir  = sanitize($_GET['tgl_akhir'] ?? date('Y-m-d'));
$search     = sanitize($_GET['q'] ?? '');

$where = "p.tanggal_penerimaan BETWEEN '$tgl_mulai' AND '$tgl_akhir'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (p.nomor_faktur LIKE '%$s%' OR p.supplier LIKE '%$s%' OR db.nama_brng LIKE '%$s%' OR p.no_pemesanan LIKE '%$s%')";
}

$terima_res = $conn->query("
    SELECT p.*, db.nama_brng, ks.satuan, b.nm_bangsal
    FROM mlite_farmasi_penerimaan_obat p
    JOIN databarang db ON p.kode_brng = db.kode_brng
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN bangsal b ON p.kd_bangsal = b.kd_bangsal
    WHERE $where
    ORDER BY p.tanggal_penerimaan DESC, p.id DESC
");

$terima_list = [];
$total_nilai_terima = 0;
if ($terima_res) {
    while ($row = $terima_res->fetch_assoc()) {
        $terima_list[] = $row;
        $total_nilai_terima += (float)$row['total_biaya'];
    }
}

// Data PO yang siap diterima (status: Dipesan atau Draft)
$open_pos = [];
$rpo = $conn->query("
    SELECT po.*, db.nama_brng
    FROM mlite_farmasi_pemesanan_obat po
    JOIN databarang db ON po.kode_brng = db.kode_brng
    WHERE po.status_pemesanan IN ('Dipesan', 'Draft')
    ORDER BY po.tanggal_pemesanan DESC
");
if ($rpo) while ($r = $rpo->fetch_assoc()) $open_pos[] = $r;

// Preload PO jika ada parameter ?po_id=...
$preload_po_id = (int)($_GET['po_id'] ?? 0);
$preload_po = null;
if ($preload_po_id) {
    $rpp = $conn->query("SELECT * FROM mlite_farmasi_pemesanan_obat WHERE id = '$preload_po_id'");
    if ($rpp) $preload_po = $rpp->fetch_assoc();
}

// Master Supplier, Obat, Bangsal
$suppliers = [];
$rsup = $conn->query("SELECT kode_suplier, nama_suplier FROM datasuplier ORDER BY nama_suplier");
if ($rsup) while ($r = $rsup->fetch_assoc()) $suppliers[] = $r;

$obat_list = [];
$ro = $conn->query("SELECT kode_brng, nama_brng, h_beli, ralan FROM databarang WHERE status='1' ORDER BY nama_brng");
if ($ro) while ($r = $ro->fetch_assoc()) $obat_list[] = $r;

$bangsal_list = [];
$rb = $conn->query("SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status='1' ORDER BY nm_bangsal");
if ($rb) while ($r = $rb->fetch_assoc()) $bangsal_list[] = $r;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Sub-Navigation Gudang Obat ────────────────────────── -->
<?php include __DIR__ . '/header_nav.php'; ?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Penerimaan Obat & Faktur Masuk</h1>
    <p class="page-subtitle">Pencatatan penerimaan barang dari pesanan (PO) atau faktur langsung PBF</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="openTerimaModal()">
      <i class="fas fa-truck-ramp-box"></i> Input Penerimaan Faktur
    </button>
  </div>
</div>

<!-- ─── Summary Cards ────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:18px;">
  <div class="card" style="border-left:4px solid #0284c7;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
      <div style="width:42px;height:42px;background:#f0f9ff;color:#0284c7;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-boxes-packing"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;color:#0f172a;"><?= count($terima_list) ?></div>
        <div style="font-size:12px;color:#64748b;">Faktur Diterima Periode Ini</div>
      </div>
    </div>
  </div>

  <div class="card" style="border-left:4px solid #10b981;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
      <div style="width:42px;height:42px;background:#ecfdf5;color:#059669;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-receipt"></i>
      </div>
      <div>
        <div style="font-size:18px;font-weight:800;color:#059669;"><?= rupiah($total_nilai_terima) ?></div>
        <div style="font-size:12px;color:#64748b;">Total Nilai Pembelian Faktur</div>
      </div>
    </div>
  </div>

  <div class="card" style="border-left:4px solid #f59e0b;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
      <div style="width:42px;height:42px;background:#fffbeb;color:#d97706;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-clock-rotate-left"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;color:#0f172a;"><?= count($open_pos) ?></div>
        <div style="font-size:12px;color:#64748b;">PO Menunggu Penerimaan</div>
      </div>
    </div>
  </div>
</div>

<!-- ─── Filter Bar ───────────────────────────────────────── -->
<div class="card mb-16">
  <div class="card-body" style="padding:12px 20px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:#64748b;">Periode:</label>
        <input type="date" name="tgl_mulai" class="form-control" style="width:145px;" value="<?= $tgl_mulai ?>">
        <span style="color:#94a3b8;">s/d</span>
        <input type="date" name="tgl_akhir" class="form-control" style="width:145px;" value="<?= $tgl_akhir ?>">
      </div>

      <div style="flex:1;min-width:200px;">
        <input type="text" name="q" class="form-control" placeholder="Cari No. Faktur, Supplier, Obat, No. PO..." value="<?= htmlspecialchars($search) ?>">
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
      <?php if ($search || $tgl_mulai !== date('Y-m-01')): ?>
        <a href="<?= BASE_URL ?>modules/gudang_obat/penerimaan.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Table Riwayat Penerimaan ─────────────────────────── -->
<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <th style="width:120px;">Tgl Terima</th>
            <th style="width:130px;">No. Faktur</th>
            <th>Distributor / PBF</th>
            <th>Item Obat</th>
            <th style="text-align:center;">No. Batch</th>
            <th style="text-align:center;">Kadaluarsa</th>
            <th style="text-align:center;">Jumlah Terima</th>
            <th style="text-align:right;">Harga Beli</th>
            <th style="text-align:right;">Total Nilai</th>
            <th>Gudang</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($terima_list)): ?>
            <tr>
              <td colspan="10" style="text-align:center;padding:36px;color:#94a3b8;">
                <i class="fas fa-truck-ramp-box" style="font-size:32px;margin-bottom:10px;display:block;"></i>
                Belum ada data penerimaan barang faktur pada rentang tanggal ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($terima_list as $row): ?>
              <tr>
                <td style="font-size:12px;color:#475569;">
                  <?= date('d/m/Y', strtotime($row['tanggal_penerimaan'])) ?>
                </td>
                <td>
                  <code style="font-weight:700;color:#0f172a;background:#f1f5f9;padding:3px 6px;border-radius:4px;font-size:12px;">
                    <?= htmlspecialchars($row['nomor_faktur']) ?>
                  </code>
                  <?php if (!empty($row['no_pemesanan']) && $row['no_pemesanan'] !== '-'): ?>
                    <div style="font-size:10.5px;color:#0284c7;margin-top:2px;">PO: <?= htmlspecialchars($row['no_pemesanan']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="font-weight:600;color:#0f172a;font-size:13px;">
                    <?= htmlspecialchars($row['supplier']) ?>
                  </div>
                  <span class="badge badge-light" style="font-size:10.5px;"><?= htmlspecialchars($row['jenis_pembayaran']) ?></span>
                </td>
                <td>
                  <div style="font-weight:600;color:#0f172a;font-size:13px;">
                    <?= htmlspecialchars($row['nama_brng']) ?>
                  </div>
                  <code style="font-size:11px;color:#64748b;"><?= htmlspecialchars($row['kode_brng']) ?></code>
                </td>
                <td style="text-align:center;font-family:monospace;font-size:12px;font-weight:600;">
                  <?= htmlspecialchars($row['no_batch'] ?: '-') ?>
                </td>
                <td style="text-align:center;font-size:12px;">
                  <?php if (!empty($row['kadaluarsa']) && $row['kadaluarsa'] !== '0000-00-00'): ?>
                    <?= date('d/m/Y', strtotime($row['kadaluarsa'])) ?>
                  <?php else: ?>
                    <span style="color:#cbd5e1;">-</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:center;font-size:13px;font-weight:700;color:#059669;">
                  +<?= (int)$row['jumlah_terima'] ?> <?= htmlspecialchars($row['satuan'] ?? '') ?>
                </td>
                <td style="text-align:right;font-family:monospace;font-size:12px;color:#475569;">
                  <?= rupiah((float)$row['h_beli']) ?>
                </td>
                <td style="text-align:right;font-family:monospace;font-size:12.5px;font-weight:700;color:#0f172a;">
                  <?= rupiah((float)$row['total_biaya']) ?>
                </td>
                <td>
                  <span class="badge badge-light"><?= htmlspecialchars($row['nm_bangsal'] ?: $row['kd_bangsal']) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ─── Modal Input Penerimaan Obat ─────────────────────── -->
<div class="modal fade" id="modalTerima" tabindex="-1" style="display:none;background:rgba(15,23,42,0.6);position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#ffffff;border-radius:14px;width:100%;max-width:680px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
    
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">Form Input Penerimaan Obat (Faktur)</h3>
      <button type="button" onclick="closeTerimaModal()" style="background:none;border:none;font-size:18px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <form method="POST" action="" style="overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;">
      
      <!-- Pilihan PO (Opsional) -->
      <div class="form-group" style="background:#f0fdf4;padding:12px;border-radius:8px;border:1px solid #bbf7d0;">
        <label class="form-label" style="font-size:12px;font-weight:700;color:#166534;">
          <i class="fas fa-link"></i> Hubungkan dengan Surat Pesanan / PO (Opsional)
        </label>
        <select name="pemesanan_id" id="terima_po_select" class="form-control" onchange="syncFromPO()">
          <option value="">— Penerimaan Langsung (Tanpa PO) —</option>
          <?php foreach ($open_pos as $po): ?>
            <option value="<?= $po['id'] ?>"
                    data-nopo="<?= htmlspecialchars($po['no_pemesanan']) ?>"
                    data-supkode="<?= htmlspecialchars($po['supplier_kode']) ?>"
                    data-supnama="<?= htmlspecialchars($po['supplier']) ?>"
                    data-kode="<?= htmlspecialchars($po['kode_brng']) ?>"
                    data-qty="<?= $po['jumlah_pesan'] ?>"
                    data-hb="<?= $po['h_beli'] ?>"
                    <?= ($preload_po_id===$po['id'])?'selected':'' ?>>
              <?= htmlspecialchars($po['no_pemesanan']) ?> — <?= htmlspecialchars($po['supplier']) ?> (<?= htmlspecialchars($po['nama_brng']) ?> : <?= $po['jumlah_pesan'] ?> item)
            </option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="no_pemesanan" id="terima_no_po" value="-">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Nomor Faktur PBF <span style="color:#ef4444;">*</span></label>
          <input type="text" name="nomor_faktur" id="terima_no_faktur" class="form-control" required placeholder="Contoh: FAK-2026/09/001">
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Tanggal Penerimaan <span style="color:#ef4444;">*</span></label>
          <input type="date" name="tanggal_penerimaan" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Distributor / PBF Supplier <span style="color:#ef4444;">*</span></label>
        <select name="supplier_kode" id="terima_sup_select" class="form-control" required onchange="syncTerimaSupName()">
          <option value="">— Pilih Supplier / PBF —</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= $s['kode_suplier'] ?>" data-nama="<?= htmlspecialchars($s['nama_suplier']) ?>">
              <?= htmlspecialchars($s['nama_suplier']) ?> (<?= $s['kode_suplier'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="supplier" id="terima_sup_name" value="">
      </div>

      <div class="form-group">
        <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Item Obat Diterima <span style="color:#ef4444;">*</span></label>
        <select name="kode_brng" id="terima_kode_brng" class="form-control" required onchange="syncTerimaHB()">
          <option value="">— Pilih Obat —</option>
          <?php foreach ($obat_list as $o): ?>
            <option value="<?= $o['kode_brng'] ?>" data-hb="<?= $o['h_beli'] ?>">
              <?= htmlspecialchars($o['nama_brng']) ?> (<?= $o['kode_brng'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;">
        <div class="form-group">
          <label class="form-label" style="font-size:11.5px;font-weight:700;color:#0f172a;">Jumlah Diterima <span style="color:#ef4444;">*</span></label>
          <input type="number" name="jumlah_terima" id="terima_jml" class="form-control" min="1" value="10" required oninput="calcTerimaTotal()">
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11.5px;color:#64748b;">Harga Beli Faktur</label>
          <input type="number" name="h_beli" id="terima_hb" class="form-control" min="0" step="100" value="0" oninput="calcTerimaTotal()">
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11.5px;color:#64748b;">Total Faktur</label>
          <input type="text" id="terima_total_preview" class="form-control" readonly style="background:#e2e8f0;font-weight:700;" value="Rp 0">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">No. Batch Pabrik</label>
          <input type="text" name="no_batch" class="form-control" placeholder="Contoh: BTH-2026-X1">
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Tgl Kadaluarsa Batch</label>
          <input type="date" name="kadaluarsa" class="form-control" value="<?= date('Y-m-d', strtotime('+2 years')) ?>">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Gudang Penerima</label>
          <select name="kd_bangsal" class="form-control">
            <?php foreach ($bangsal_list as $b): ?>
              <option value="<?= $b['kd_bangsal'] ?>" <?= ($b['kd_bangsal']==='GD' || $b['kd_bangsal']==='GF') ? 'selected' : '' ?>><?= htmlspecialchars($b['nm_bangsal']) ?> (<?= $b['kd_bangsal'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Jenis Pembayaran</label>
          <select name="jenis_pembayaran" class="form-control">
            <option value="Cash">Cash (Tunai / Lunas)</option>
            <option value="Tempo">Kredit / Jatuh Tempo</option>
          </select>
        </div>
      </div>

      <div style="padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeTerimaModal()">Batal</button>
        <button type="submit" name="simpan_penerimaan" class="btn btn-primary">
          <i class="fas fa-save"></i> Simpan Penerimaan Obat
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openTerimaModal() {
  document.getElementById('modalTerima').style.display = 'flex';
  syncTerimaSupName();
  syncTerimaHB();
  syncFromPO();
}

function closeTerimaModal() {
  document.getElementById('modalTerima').style.display = 'none';
}

function syncTerimaSupName() {
  const sel = document.getElementById('terima_sup_select');
  const opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.nama) {
    document.getElementById('terima_sup_name').value = opt.dataset.nama;
  }
}

function syncTerimaHB() {
  const sel = document.getElementById('terima_kode_brng');
  const opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.hb) {
    document.getElementById('terima_hb').value = opt.dataset.hb;
  }
  calcTerimaTotal();
}

function calcTerimaTotal() {
  const qty = parseFloat(document.getElementById('terima_jml').value) || 0;
  const hb  = parseFloat(document.getElementById('terima_hb').value) || 0;
  const tot = qty * hb;
  document.getElementById('terima_total_preview').value = 'Rp ' + tot.toLocaleString('id-ID');
}

function syncFromPO() {
  const sel = document.getElementById('terima_po_select');
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) {
    document.getElementById('terima_no_po').value = '-';
    return;
  }

  document.getElementById('terima_no_po').value = opt.dataset.nopo || '-';
  
  // Set Supplier
  const supSel = document.getElementById('terima_sup_select');
  for (let i=0; i<supSel.options.length; i++) {
    if (supSel.options[i].value === opt.dataset.supkode) {
      supSel.selectedIndex = i;
      break;
    }
  }
  syncTerimaSupName();

  // Set Obat
  const obatSel = document.getElementById('terima_kode_brng');
  for (let i=0; i<obatSel.options.length; i++) {
    if (obatSel.options[i].value === opt.dataset.kode) {
      obatSel.selectedIndex = i;
      break;
    }
  }

  // Set Qty & HB
  if (opt.dataset.qty) document.getElementById('terima_jml').value = opt.dataset.qty;
  if (opt.dataset.hb)  document.getElementById('terima_hb').value = opt.dataset.hb;
  calcTerimaTotal();
}

// Auto open modal if ?po_id= is present
<?php if ($preload_po_id): ?>
window.addEventListener('DOMContentLoaded', () => {
  openTerimaModal();
});
<?php endif; ?>
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
