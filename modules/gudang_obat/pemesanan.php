<?php
/**
 * SIMKlinik — Gudang Obat: Order / Pemesanan Obat (Purchase Order)
 */

$page_title    = 'Order / Pemesanan Obat ke Supplier';
$active_module = 'gudang_obat';
$sub_active    = 'pemesanan';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Pastikan tabel pemesanan obat ada
$conn->query("
    CREATE TABLE IF NOT EXISTS `mlite_farmasi_pemesanan_obat` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `no_pemesanan` varchar(30) NOT NULL,
      `no_pengajuan` varchar(30) DEFAULT '-',
      `pengajuan_id` int(11) DEFAULT 0,
      `kode_brng` varchar(15) NOT NULL,
      `tanggal_pemesanan` date NOT NULL,
      `supplier_kode` text,
      `supplier` varchar(255) NOT NULL,
      `jumlah_pengajuan` int(11) NOT NULL DEFAULT '0',
      `jumlah_pesan` int(11) NOT NULL DEFAULT '0',
      `h_beli` double NOT NULL DEFAULT '0',
      `total_biaya` double NOT NULL DEFAULT '0',
      `status_pemesanan` varchar(20) NOT NULL DEFAULT 'Dipesan',
      `catatan` text,
      `dibuat_oleh` varchar(100) DEFAULT '-',
      `created_at` datetime NOT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_no_pemesanan` (`no_pemesanan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$user = current_user();
$petugas_nama = $user['fullname'] ?? 'Bagian Pengadaan';

// ─── Proses Simpan / Edit Order Pemesanan (Multi-Item PO) ─────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['simpan_po'])) {
    $is_edit      = (!empty($_POST['is_edit']) && $_POST['is_edit'] === '1' && !empty($_POST['edit_no_pemesanan']));
    $edit_no_po   = trim($conn->real_escape_string($_POST['edit_no_pemesanan'] ?? ''));
    $no_po        = trim($conn->real_escape_string($_POST['no_pemesanan'] ?? ''));
    $tgl_pesan    = $conn->real_escape_string($_POST['tanggal_pemesanan'] ?? date('Y-m-d'));
    $sup_kode     = $conn->real_escape_string($_POST['supplier_kode'] ?? '');
    $sup_nama     = $conn->real_escape_string($_POST['supplier'] ?? '');
    $catatan      = $conn->real_escape_string($_POST['catatan'] ?? '');
    $status_po    = $conn->real_escape_string($_POST['status_pemesanan'] ?? 'Dipesan');
    $now_dt       = date('Y-m-d H:i:s');

    if ($is_edit) {
        $no_po = $edit_no_po;
    } elseif (empty($no_po)) {
        // Auto generate No PO jika kosong
        $today_prefix = 'PO-' . date('Ymd') . '-';
        $res_last = $conn->query("SELECT no_pemesanan FROM mlite_farmasi_pemesanan_obat WHERE no_pemesanan LIKE '$today_prefix%' ORDER BY id DESC LIMIT 1");
        if ($res_last && $res_last->num_rows > 0) {
            $last_po = $res_last->fetch_assoc()['no_pemesanan'];
            $last_num = (int)substr($last_po, -4);
            $no_po = $today_prefix . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $no_po = $today_prefix . '0001';
        }
    }

    // Ambil daftar items obat yang diposting
    $items = $_POST['items'] ?? [];
    if (empty($items) && !empty($_POST['kode_brng'])) {
        $items[] = [
            'kode_brng'    => $_POST['kode_brng'],
            'jumlah_pesan' => $_POST['jumlah_pesan'] ?? 1,
            'h_beli'       => $_POST['h_beli'] ?? 0
        ];
    }

    if (empty($sup_nama)) {
        set_flash('danger', 'Distributor / Supplier wajib dipilih.');
    } elseif (empty($items)) {
        set_flash('danger', 'Tambahkan setidaknya 1 item obat yang dipesan.');
    } else {
        if ($is_edit) {
            // Hapus item-item lama untuk nomor pemesanan ini
            $conn->query("DELETE FROM mlite_farmasi_pemesanan_obat WHERE no_pemesanan = '$no_po'");
        }

        $saved_count = 0;
        foreach ($items as $it) {
            $k_brng = trim($conn->real_escape_string($it['kode_brng'] ?? ''));
            $jml    = (int)($it['jumlah_pesan'] ?? 0);
            $hb     = (float)($it['h_beli'] ?? 0);
            $tot    = $jml * $hb;

            if (!empty($k_brng) && $jml > 0) {
                $sql = "
                    INSERT INTO mlite_farmasi_pemesanan_obat (
                        no_pemesanan, no_pengajuan, pengajuan_id, kode_brng, tanggal_pemesanan,
                        supplier_kode, supplier, jumlah_pengajuan, jumlah_pesan, h_beli, total_biaya,
                        status_pemesanan, catatan, dibuat_oleh, created_at
                    ) VALUES (
                        '$no_po', '-', 0, '$k_brng', '$tgl_pesan',
                        '$sup_kode', '$sup_nama', '$jml', '$jml', '$hb', '$tot',
                        '$status_po', '$catatan', '$petugas_nama', '$now_dt'
                    )
                ";
                if ($conn->query($sql)) {
                    $saved_count++;
                }
            }
        }

        if ($saved_count > 0) {
            $msg = $is_edit 
                ? "Order Pemesanan <strong>$no_po</strong> berhasil diperbarui ($saved_count item obat)." 
                : "Order Pemesanan <strong>$no_po</strong> berhasil dibuat ($saved_count item obat).";
            set_flash('success', $msg);
            redirect(BASE_URL . 'modules/gudang_obat/pemesanan.php');
        } else {
            set_flash('danger', 'Gagal menyimpan pesanan. Pastikan item obat dipilih dan jumlah lebih dari 0.');
        }
    }
}

// ─── Hapus Order Pemesanan ─────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete_po' && !empty($_GET['no_pemesanan'])) {
    $del_nopo = $conn->real_escape_string($_GET['no_pemesanan']);
    $conn->query("DELETE FROM mlite_farmasi_pemesanan_obat WHERE no_pemesanan = '$del_nopo'");
    set_flash('success', "Order Pemesanan <strong>$del_nopo</strong> berhasil dihapus.");
    redirect(BASE_URL . 'modules/gudang_obat/pemesanan.php');
}

// ─── Ubah Status PO ────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'set_status' && isset($_GET['id'])) {
    $po_id  = (int)$_GET['id'];
    $st_val = $conn->real_escape_string($_GET['status']);
    $conn->query("UPDATE mlite_farmasi_pemesanan_obat SET status_pemesanan = '$st_val' WHERE id = '$po_id'");
    set_flash('info', "Status pemesanan berhasil diperbarui.");
    redirect(BASE_URL . 'modules/gudang_obat/pemesanan.php');
}

// ─── Filter & Riwayat Pemesanan ────────────────────────────────
$tgl_mulai   = sanitize($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_akhir   = sanitize($_GET['tgl_akhir'] ?? date('Y-m-d'));
$status_flt  = sanitize($_GET['status'] ?? '');
$search      = sanitize($_GET['q'] ?? '');

$where = "po.tanggal_pemesanan BETWEEN '$tgl_mulai' AND '$tgl_akhir'";
if ($status_flt) {
    $where .= " AND po.status_pemesanan = '$status_flt'";
}
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (po.no_pemesanan LIKE '%$s%' OR po.supplier LIKE '%$s%' OR db.nama_brng LIKE '%$s%')";
}

$po_res = $conn->query("
    SELECT po.*, db.nama_brng, ks.satuan
    FROM mlite_farmasi_pemesanan_obat po
    JOIN databarang db ON po.kode_brng = db.kode_brng
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    WHERE $where
    ORDER BY po.tanggal_pemesanan DESC, po.id DESC
");

$grouped_pos = [];
$total_po_biaya = 0;
if ($po_res) {
    while ($row = $po_res->fetch_assoc()) {
        $nopo = $row['no_pemesanan'];
        if (!isset($grouped_pos[$nopo])) {
            $grouped_pos[$nopo] = [
                'first_id'          => (int)$row['id'],
                'no_pemesanan'      => $row['no_pemesanan'],
                'tanggal_pemesanan' => $row['tanggal_pemesanan'],
                'supplier_kode'     => $row['supplier_kode'],
                'supplier'          => $row['supplier'],
                'status_pemesanan'  => $row['status_pemesanan'],
                'catatan'           => $row['catatan'],
                'dibuat_oleh'       => $row['dibuat_oleh'],
                'created_at'        => $row['created_at'],
                'total_biaya'       => 0,
                'items'             => []
            ];
        }
        $grouped_pos[$nopo]['total_biaya'] += (float)$row['total_biaya'];
        $grouped_pos[$nopo]['items'][] = [
            'id'           => (int)$row['id'],
            'kode_brng'    => $row['kode_brng'],
            'nama_brng'    => $row['nama_brng'],
            'satuan'       => $row['satuan'] ?: 'Unit',
            'jumlah_pesan' => (int)$row['jumlah_pesan'],
            'h_beli'       => (float)$row['h_beli'],
            'total_biaya'  => (float)$row['total_biaya'],
            'catatan'      => $row['catatan']
        ];
        $total_po_biaya += (float)$row['total_biaya'];
    }
}

// Data Master Supplier
$suppliers = [];
$rsup = $conn->query("SELECT kode_suplier, nama_suplier FROM datasuplier ORDER BY nama_suplier");
if ($rsup) while ($r = $rsup->fetch_assoc()) $suppliers[] = $r;

// Data Master Obat
$obat_list = [];
$ro = $conn->query("
    SELECT db.kode_brng, db.nama_brng, db.h_beli, db.ralan, db.stokminimal, ks.satuan 
    FROM databarang db 
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    WHERE db.status='1' 
    ORDER BY db.nama_brng ASC
");
if ($ro) while ($r = $ro->fetch_assoc()) $obat_list[] = $r;

// Auto prefill kode jika ada parameter ?action=order&kode=...
$prefill_kode = sanitize($_GET['kode'] ?? '');

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Sub-Navigation Gudang Obat ────────────────────────── -->
<?php include __DIR__ . '/header_nav.php'; ?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Order & Pemesanan Obat (PO)</h1>
    <p class="page-subtitle">Pengadaan obat ke Pedagang Besar Farmasi (PBF) & Surat Pesanan resmi</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="openPOModal()">
      <i class="fas fa-cart-plus"></i> Buat Surat Pesanan (PO)
    </button>
  </div>
</div>

<!-- ─── Summary Cards ────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:18px;">
  <div class="card" style="border-left:4px solid #3b82f6;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
      <div style="width:42px;height:42px;background:#eff6ff;color:#2563eb;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-file-invoice"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;color:#0f172a;"><?= count($grouped_pos) ?></div>
        <div style="font-size:12px;color:#64748b;">Total PO Dibuat</div>
      </div>
    </div>
  </div>

  <div class="card" style="border-left:4px solid #10b981;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
      <div style="width:42px;height:42px;background:#ecfdf5;color:#059669;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-money-bill-wave"></i>
      </div>
      <div>
        <div style="font-size:18px;font-weight:800;color:#059669;"><?= rupiah($total_po_biaya) ?></div>
        <div style="font-size:12px;color:#64748b;">Total Estimasi Nilai Order</div>
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

      <div style="width:160px;">
        <select name="status" class="form-control">
          <option value="">— Semua Status —</option>
          <option value="Draft" <?= $status_flt==='Draft'?'selected':'' ?>>Draft</option>
          <option value="Dipesan" <?= $status_flt==='Dipesan'?'selected':'' ?>>Dipesan</option>
          <option value="Selesai" <?= $status_flt==='Selesai'?'selected':'' ?>>Selesai</option>
          <option value="Dibatalkan" <?= $status_flt==='Dibatalkan'?'selected':'' ?>>Dibatalkan</option>
        </select>
      </div>

      <div style="flex:1;min-width:180px;">
        <input type="text" name="q" class="form-control" placeholder="Cari No. PO, Supplier, Nama Obat..." value="<?= htmlspecialchars($search) ?>">
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
      <?php if ($search || $status_flt || $tgl_mulai !== date('Y-m-01')): ?>
        <a href="<?= BASE_URL ?>modules/gudang_obat/pemesanan.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Table Daftar Pemesanan ───────────────────────────── -->
<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <th style="width:140px;">No. Pemesanan</th>
            <th style="width:95px;">Tanggal</th>
            <th style="width:160px;">Supplier / PBF</th>
            <th>Item Obat Yang Dipesan</th>
            <th style="text-align:right;width:140px;">Total Estimasi</th>
            <th style="text-align:center;width:105px;">Status</th>
            <th style="text-align:center;width:150px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($grouped_pos)): ?>
            <tr>
              <td colspan="7" style="text-align:center;padding:36px;color:#94a3b8;">
                <i class="fas fa-cart-shopping" style="font-size:32px;margin-bottom:10px;display:block;"></i>
                Belum ada data pemesanan obat pada rentang tanggal ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($grouped_pos as $po): ?>
              <?php
                $st_badge = 'badge-secondary';
                if ($po['status_pemesanan'] === 'Dipesan') $st_badge = 'badge-warning';
                if ($po['status_pemesanan'] === 'Selesai') $st_badge = 'badge-success';
                if ($po['status_pemesanan'] === 'Dibatalkan') $st_badge = 'badge-danger';
              ?>
              <tr>
                <td>
                  <code style="font-weight:700;color:#0f172a;background:#f1f5f9;padding:3px 6px;border-radius:4px;font-size:12px;display:inline-block;">
                    <?= htmlspecialchars($po['no_pemesanan']) ?>
                  </code>
                  <div style="font-size:11px;color:#94a3b8;margin-top:3px;">
                    Oleh: <?= htmlspecialchars($po['dibuat_oleh']) ?>
                  </div>
                </td>
                <td style="font-size:12.5px;color:#475569;white-space:nowrap;">
                  <?= date('d/m/Y', strtotime($po['tanggal_pemesanan'])) ?>
                </td>
                <td>
                  <div style="font-weight:600;color:#0f172a;font-size:13px;">
                    <?= htmlspecialchars($po['supplier']) ?>
                  </div>
                  <code style="font-size:11px;color:#64748b;"><?= htmlspecialchars($po['supplier_kode'] ?: '-') ?></code>
                </td>
                <td>
                  <div style="display:flex;flex-direction:column;gap:4px;">
                    <?php foreach ($po['items'] as $it): ?>
                      <div style="font-size:12.5px;color:#1e293b;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <div>
                          <strong style="color:#0f172a;"><?= htmlspecialchars($it['nama_brng']) ?></strong>
                          <code style="font-size:10.5px;color:#64748b;margin-left:4px;"><?= htmlspecialchars($it['kode_brng']) ?></code>
                        </div>
                        <div style="white-space:nowrap;font-size:11.5px;color:#475569;">
                          <span class="badge badge-light" style="font-weight:700;background:#f1f5f9;color:#0f172a;border:1px solid #e2e8f0;">
                            <?= $it['jumlah_pesan'] ?> <?= htmlspecialchars($it['satuan']) ?>
                          </span>
                          <span style="font-family:monospace;color:#64748b;font-size:11px;margin-left:4px;">@ <?= rupiah($it['h_beli']) ?></span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php if (!empty($po['catatan'])): ?>
                    <div style="font-size:11px;color:#64748b;margin-top:4px;font-style:italic;">
                      <i class="fas fa-info-circle" style="color:#94a3b8;"></i> <?= htmlspecialchars($po['catatan']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;font-family:monospace;font-size:13px;font-weight:800;color:#0284c7;white-space:nowrap;">
                  <?= rupiah((float)$po['total_biaya']) ?>
                </td>
                <td style="text-align:center;">
                  <span class="badge <?= $st_badge ?>" style="font-size:11px;font-weight:700;">
                    <?= htmlspecialchars($po['status_pemesanan']) ?>
                  </span>
                </td>
                <td style="text-align:center;">
                  <div style="display:inline-flex;gap:4px;align-items:center;">
                    <!-- Cetak SP -->
                    <a href="<?= BASE_URL ?>modules/gudang_obat/cetak_sp.php?no_pemesanan=<?= urlencode($po['no_pemesanan']) ?>" target="_blank"
                       class="btn-icon btn-sm" title="Cetak Surat Pesanan (SP)"
                       style="width:29px;height:29px;border-radius:6px;border:1px solid #e2e8f0;background:#ffffff;color:#0284c7;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                      <i class="fas fa-print" style="font-size:12px;"></i>
                    </a>

                    <!-- Penerimaan Barang (Jika belum selesai) -->
                    <?php if ($po['status_pemesanan'] !== 'Selesai' && $po['status_pemesanan'] !== 'Dibatalkan'): ?>
                      <a href="<?= BASE_URL ?>modules/gudang_obat/penerimaan.php?po_id=<?= $po['first_id'] ?>"
                         class="btn-icon btn-sm" title="Proses Penerimaan Barang Faktur"
                         style="width:29px;height:29px;border-radius:6px;border:1px solid #e2e8f0;background:#ffffff;color:#059669;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                        <i class="fas fa-truck-ramp-box" style="font-size:12px;"></i>
                      </a>
                    <?php endif; ?>

                    <!-- Edit PO -->
                    <?php if ($po['status_pemesanan'] !== 'Selesai'): ?>
                      <button type="button" onclick="editPO(<?= htmlspecialchars(json_encode($po), ENT_QUOTES, 'UTF-8') ?>)"
                              class="btn-icon btn-sm" title="Edit Pemesanan (PO)"
                              style="width:29px;height:29px;border-radius:6px;border:1px solid #fde68a;background:#fffbeb;color:#d97706;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;">
                        <i class="fas fa-edit" style="font-size:12px;"></i>
                      </button>
                    <?php endif; ?>

                    <!-- Hapus PO -->
                    <button type="button" onclick="deletePO('<?= htmlspecialchars($po['no_pemesanan'], ENT_QUOTES) ?>')"
                            class="btn-icon btn-sm" title="Hapus Pemesanan Ini"
                            style="width:29px;height:29px;border-radius:6px;border:1px solid #fecaca;background:#fef2f2;color:#ef4444;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;">
                      <i class="fas fa-trash-alt" style="font-size:12px;"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ─── Modal Buat / Edit Surat Pesanan (PO) Multi-Item ─────── -->
<div class="modal fade" id="modalPO" tabindex="-1" style="display:none;background:rgba(15,23,42,0.6);position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#ffffff;border-radius:14px;width:100%;max-width:880px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.15);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
    
    <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:34px;height:34px;background:#e0f2fe;color:#0284c7;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;" id="po_modal_icon">
          <i class="fas fa-cart-plus"></i>
        </div>
        <div>
          <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;" id="po_modal_title">Buat Order Pemesanan Obat (PO)</h3>
          <p style="margin:2px 0 0;font-size:11.5px;color:#64748b;" id="po_modal_subtitle">Surat pesanan pengadaan obat & alkes ke Distributor / PBF Supplier</p>
        </div>
      </div>
      <button type="button" onclick="closePOModal()" style="background:none;border:none;font-size:22px;color:#94a3b8;cursor:pointer;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">&times;</button>
    </div>

    <form method="POST" action="" id="formPO" style="overflow-y:auto;padding:20px 22px;display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" name="is_edit" id="po_is_edit" value="0">
      <input type="hidden" name="edit_no_pemesanan" id="po_edit_no_pemesanan" value="">

      <!-- Top Info Grid -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1.5fr;gap:12px;">
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11.5px;font-weight:700;color:#475569;">No. Pemesanan / PO</label>
          <input type="text" name="no_pemesanan" id="po_input_no_pemesanan" class="form-control form-control-sm" placeholder="Auto Generate (PO-...)" style="font-size:12px;">
          <span style="font-size:10px;color:#94a3b8;" id="po_no_pemesanan_help">Kosongkan untuk auto-nomor</span>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11.5px;font-weight:700;color:#475569;">Tanggal Order <span style="color:#ef4444;">*</span></label>
          <input type="date" name="tanggal_pemesanan" id="po_input_tanggal" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required style="font-size:12px;">
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11.5px;font-weight:700;color:#475569;">Distributor / PBF Supplier <span style="color:#ef4444;">*</span></label>
          <select name="supplier_kode" id="po_supplier_select" class="form-control form-control-sm" required onchange="syncSupplierName()" style="font-size:12px;">
            <option value="">— Pilih Supplier / PBF —</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['kode_suplier'] ?>" data-nama="<?= htmlspecialchars($s['nama_suplier']) ?>">
                <?= htmlspecialchars($s['nama_suplier']) ?> (<?= $s['kode_suplier'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="supplier" id="po_supplier_name" value="">
        </div>
      </div>

      <!-- Item Obat Yang Dipesan (Multi-Item Table) -->
      <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
        <div style="padding:10px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
          <div style="font-size:12.5px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-pills" style="color:#0284c7;"></i> Daftar Obat Yang Dipesan
          </div>
          <button type="button" class="btn btn-sm btn-outline" onclick="addPOItemRow()" style="font-size:11.5px;padding:4px 10px;border-color:#bae6fd;color:#0284c7;font-weight:700;display:inline-flex;align-items:center;gap:5px;">
            <i class="fas fa-plus-circle"></i> Tambah Obat
          </button>
        </div>

        <div class="table-responsive" style="max-height:260px;overflow-y:auto;">
          <table class="table table-bordered mb-0" style="font-size:12px;" id="po_items_table">
            <thead style="background:#f1f5f9;color:#475569;font-weight:700;position:sticky;top:0;z-index:2;">
              <tr>
                <th style="width:35px;text-align:center;">#</th>
                <th>Obat / Barang Medis <span style="color:#ef4444;">*</span></th>
                <th style="width:120px;text-align:center;">Jumlah Pesan</th>
                <th style="width:145px;text-align:right;">Harga Beli Est. (Rp)</th>
                <th style="width:145px;text-align:right;">Subtotal (Rp)</th>
                <th style="width:45px;text-align:center;">Hapus</th>
              </tr>
            </thead>
            <tbody id="po_items_tbody">
              <!-- Baris dinamis dibuat via JS -->
            </tbody>
          </table>
        </div>

        <!-- Footer Ringkasan Item -->
        <div style="padding:10px 16px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
          <button type="button" class="btn btn-sm btn-outline" onclick="addPOItemRow()" style="font-size:11.5px;border-color:#cbd5e1;color:#0f172a;font-weight:600;">
            <i class="fas fa-plus"></i> Tambah Obat Lainnya
          </button>

          <div style="display:flex;align-items:center;gap:16px;">
            <div style="font-size:12px;color:#64748b;">
              Total Item: <strong id="po_summary_item_count" style="color:#0f172a;">1 Item</strong>
            </div>
            <div style="font-size:12.5px;color:#64748b;">
              Grand Total Estimasi: <strong id="po_grand_total_text" style="font-size:15px;color:#0284c7;font-family:monospace;">Rp 0</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Form Group: Status & Catatan -->
      <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;">
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Status Pemesanan</label>
          <select name="status_pemesanan" id="po_select_status" class="form-control form-control-sm" style="font-size:12px;">
            <option value="Dipesan">Dipesan</option>
            <option value="Draft">Draft</option>
            <option value="Selesai">Selesai</option>
            <option value="Dibatalkan">Dibatalkan</option>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Catatan Pemesanan</label>
          <input type="text" name="catatan" id="po_input_catatan" class="form-control form-control-sm" placeholder="Contoh: Pengiriman urgent / batas tgl kirim..." style="font-size:12px;">
        </div>
      </div>

      <div style="padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closePOModal()">Batal</button>
        <button type="submit" name="simpan_po" id="po_submit_btn" class="btn btn-primary btn-sm" style="background:#0284c7;border:none;font-weight:700;padding:7px 18px;">
          <i class="fas fa-save"></i> Simpan Surat Pesanan
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const medicinesList = <?= json_encode($obat_list) ?>;
let poRowCounter = 0;

function openPOModal() {
  document.getElementById('po_is_edit').value = '0';
  document.getElementById('po_edit_no_pemesanan').value = '';
  document.getElementById('po_modal_title').innerText = 'Buat Order Pemesanan Obat (PO)';
  document.getElementById('po_modal_subtitle').innerText = 'Surat pesanan pengadaan obat & alkes ke Distributor / PBF Supplier';
  document.getElementById('po_modal_icon').innerHTML = '<i class="fas fa-cart-plus"></i>';
  document.getElementById('po_submit_btn').innerHTML = '<i class="fas fa-save"></i> Simpan Surat Pesanan';
  
  const noPoInput = document.getElementById('po_input_no_pemesanan');
  noPoInput.value = '';
  noPoInput.readOnly = false;
  document.getElementById('po_no_pemesanan_help').innerText = 'Kosongkan untuk auto-nomor';
  
  document.getElementById('po_input_tanggal').value = '<?= date('Y-m-d') ?>';
  document.getElementById('po_supplier_select').value = '';
  document.getElementById('po_supplier_name').value = '';
  document.getElementById('po_select_status').value = 'Dipesan';
  document.getElementById('po_input_catatan').value = '';

  const tbody = document.getElementById('po_items_tbody');
  tbody.innerHTML = '';
  addPOItemRow('<?= htmlspecialchars($prefill_kode) ?>', 10);

  document.getElementById('modalPO').style.display = 'flex';
}

function editPO(poData) {
  if (!poData) return;
  document.getElementById('po_is_edit').value = '1';
  document.getElementById('po_edit_no_pemesanan').value = poData.no_pemesanan;
  document.getElementById('po_modal_title').innerText = 'Edit Order Pemesanan (' + poData.no_pemesanan + ')';
  document.getElementById('po_modal_subtitle').innerText = 'Ubah item obat, kuantitas, supplier, atau catatan pesanan';
  document.getElementById('po_modal_icon').innerHTML = '<i class="fas fa-edit"></i>';
  document.getElementById('po_submit_btn').innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan (PO)';

  const noPoInput = document.getElementById('po_input_no_pemesanan');
  noPoInput.value = poData.no_pemesanan;
  noPoInput.readOnly = true;
  document.getElementById('po_no_pemesanan_help').innerText = 'Nomor PO tidak dapat diubah';

  document.getElementById('po_input_tanggal').value = poData.tanggal_pemesanan;
  document.getElementById('po_supplier_select').value = poData.supplier_kode;
  document.getElementById('po_supplier_name').value = poData.supplier;
  document.getElementById('po_select_status').value = poData.status_pemesanan;
  document.getElementById('po_input_catatan').value = poData.catatan || '';

  const tbody = document.getElementById('po_items_tbody');
  tbody.innerHTML = '';

  if (poData.items && poData.items.length > 0) {
    poData.items.forEach(it => {
      addPOItemRow(it.kode_brng, it.jumlah_pesan, it.h_beli);
    });
  } else {
    addPOItemRow('', 10);
  }

  document.getElementById('modalPO').style.display = 'flex';
}

function deletePO(noPemesanan) {
  if (!noPemesanan) return;
  const msg = `Apakah Anda yakin ingin menghapus Order Pemesanan ${noPemesanan}?\nSeluruh item obat dalam PO ini akan dihapus secara permanen.`;
  
  if (typeof Swal !== 'undefined') {
    Swal.fire({
      title: 'Hapus Order Pemesanan?',
      text: `Order Pemesanan ${noPemesanan} dan seluruh item di dalamnya akan dihapus permanen.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = '<?= BASE_URL ?>modules/gudang_obat/pemesanan.php?action=delete_po&no_pemesanan=' + encodeURIComponent(noPemesanan);
      }
    });
  } else {
    if (confirm(msg)) {
      window.location.href = '<?= BASE_URL ?>modules/gudang_obat/pemesanan.php?action=delete_po&no_pemesanan=' + encodeURIComponent(noPemesanan);
    }
  }
}

function closePOModal() {
  document.getElementById('modalPO').style.display = 'none';
}

function syncSupplierName() {
  const sel = document.getElementById('po_supplier_select');
  const opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.nama) {
    document.getElementById('po_supplier_name').value = opt.dataset.nama;
  }
}

function addPOItemRow(selectedKode = '', defaultQty = 10, customHb = null) {
  const tbody = document.getElementById('po_items_tbody');
  const idx = poRowCounter++;

  let optHtml = '<option value="">— Pilih Obat —</option>';
  medicinesList.forEach(m => {
    const isSel = (selectedKode && selectedKode === m.kode_brng) ? 'selected' : '';
    optHtml += `<option value="${m.kode_brng}" data-hb="${m.h_beli}" data-sat="${m.satuan || ''}" ${isSel}>${m.nama_brng} (${m.kode_brng})</option>`;
  });

  const tr = document.createElement('tr');
  tr.id = `po_row_${idx}`;
  tr.innerHTML = `
    <td style="text-align:center;color:#64748b;font-weight:700;vertical-align:middle;" class="po-row-num">1</td>
    <td>
      <select name="items[${idx}][kode_brng]" class="form-control form-control-sm po-obat-select" required onchange="syncRowObat(this, ${idx})" style="font-size:12px;">
        ${optHtml}
      </select>
    </td>
    <td>
      <div style="display:flex;align-items:center;gap:4px;">
        <input type="number" name="items[${idx}][jumlah_pesan]" class="form-control form-control-sm po-qty-input" min="1" value="${defaultQty}" required oninput="calcRowSubtotal(${idx})" style="font-size:12px;text-align:center;">
        <span class="po-satuan-label" style="font-size:10.5px;color:#64748b;min-width:28px;">-</span>
      </div>
    </td>
    <td>
      <input type="number" name="items[${idx}][h_beli]" class="form-control form-control-sm po-hb-input" min="0" step="100" value="0" oninput="calcRowSubtotal(${idx})" style="font-size:12px;text-align:right;">
    </td>
    <td>
      <input type="text" class="form-control form-control-sm po-subtotal-input" readonly style="background:#f1f5f9;font-weight:700;text-align:right;font-size:12px;color:#0f172a;" value="Rp 0">
    </td>
    <td style="text-align:center;vertical-align:middle;">
      <button type="button" class="btn btn-xs btn-outline-danger" onclick="removePOItemRow(${idx})" style="border:none;background:#fef2f2;color:#ef4444;width:26px;height:26px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;" title="Hapus obat ini">
        <i class="fas fa-trash-alt" style="font-size:11px;"></i>
      </button>
    </td>
  `;

  tbody.appendChild(tr);

  // Trigger sync row for initial selected
  const selEl = tr.querySelector('.po-obat-select');
  syncRowObat(selEl, idx);
  
  if (customHb !== null) {
    tr.querySelector('.po-hb-input').value = customHb;
    calcRowSubtotal(idx);
  }

  reindexPORows();
}

function removePOItemRow(idx) {
  const tbody = document.getElementById('po_items_tbody');
  if (tbody.children.length <= 1) {
    alert('Minimal harus ada 1 item obat dalam surat pesanan.');
    return;
  }
  const tr = document.getElementById(`po_row_${idx}`);
  if (tr) {
    tr.remove();
    reindexPORows();
    recalcAllPOTotals();
  }
}

function reindexPORows() {
  const tbody = document.getElementById('po_items_tbody');
  Array.from(tbody.children).forEach((tr, i) => {
    const numEl = tr.querySelector('.po-row-num');
    if (numEl) numEl.innerText = (i + 1);
  });
  document.getElementById('po_summary_item_count').innerText = tbody.children.length + ' Item';
}

function syncRowObat(selEl, idx) {
  const tr = document.getElementById(`po_row_${idx}`);
  if (!tr) return;
  const opt = selEl.options[selEl.selectedIndex];
  const hbInput = tr.querySelector('.po-hb-input');
  const satLabel = tr.querySelector('.po-satuan-label');

  if (opt && opt.dataset.hb) {
    hbInput.value = opt.dataset.hb;
  }
  if (opt && opt.dataset.sat) {
    satLabel.innerText = opt.dataset.sat;
  } else {
    satLabel.innerText = '';
  }
  calcRowSubtotal(idx);
}

function calcRowSubtotal(idx) {
  const tr = document.getElementById(`po_row_${idx}`);
  if (!tr) return;
  const qty = parseFloat(tr.querySelector('.po-qty-input').value) || 0;
  const hb = parseFloat(tr.querySelector('.po-hb-input').value) || 0;
  const sub = qty * hb;
  tr.querySelector('.po-subtotal-input').value = 'Rp ' + sub.toLocaleString('id-ID');
  recalcAllPOTotals();
}

function recalcAllPOTotals() {
  const tbody = document.getElementById('po_items_tbody');
  let grandTotal = 0;
  Array.from(tbody.children).forEach(tr => {
    const qty = parseFloat(tr.querySelector('.po-qty-input').value) || 0;
    const hb = parseFloat(tr.querySelector('.po-hb-input').value) || 0;
    grandTotal += (qty * hb);
  });
  document.getElementById('po_grand_total_text').innerText = 'Rp ' + grandTotal.toLocaleString('id-ID');
}

// Auto open modal if ?action=order is present
<?php if ($prefill_kode): ?>
window.addEventListener('DOMContentLoaded', () => {
  openPOModal();
});
<?php endif; ?>
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>


