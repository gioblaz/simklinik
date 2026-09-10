<?php
/**
 * SIMKlinik — Gudang Obat: Mutasi Obat Antar Unit
 */

$page_title    = 'Mutasi Obat Antar Unit';
$active_module = 'gudang_obat';
$sub_active    = 'mutasi';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$user = current_user();
$petugas_nama = $user['fullname'] ?? 'Petugas Farmasi';

// ─── Proses Simpan Mutasi Obat ────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['simpan_mutasi'])) {
    $tgl_mutasi  = $conn->real_escape_string($_POST['tanggal'] ?? date('Y-m-d'));
    $dari_bangsal= $conn->real_escape_string($_POST['kd_bangsaldari'] ?? 'GF');
    $ke_bangsal  = $conn->real_escape_string($_POST['kd_bangsalke'] ?? 'APT');
    $kode_brng   = $conn->real_escape_string($_POST['kode_brng'] ?? '');
    $jml_mutasi  = (float)($_POST['jml'] ?? 1);
    $keterangan  = $conn->real_escape_string($_POST['keterangan'] ?? 'Distribusi Stok');
    $no_batch    = $conn->real_escape_string($_POST['no_batch'] ?? '-');
    $no_faktur   = $conn->real_escape_string($_POST['no_faktur'] ?? '-');
    $now_dt      = $tgl_mutasi . ' ' . date('H:i:s');
    $now_time    = date('H:i:s');

    if (empty($kode_brng)) {
        set_flash('danger', 'Silakan pilih obat yang akan dimutasi.');
    } elseif ($dari_bangsal === $ke_bangsal) {
        set_flash('danger', 'Gudang asal dan gudang tujuan tidak boleh sama.');
    } elseif ($jml_mutasi <= 0) {
        set_flash('danger', 'Jumlah mutasi harus lebih besar dari 0.');
    } else {
        // Cek stok di unit asal
        $stk_res = $conn->query("SELECT SUM(stok) as s FROM gudangbarang WHERE kode_brng = '$kode_brng' AND kd_bangsal = '$dari_bangsal'");
        $stok_asal_awal = $stk_res ? (float)($stk_res->fetch_assoc()['s'] ?? 0) : 0;

        if ($stok_asal_awal < $jml_mutasi) {
            set_flash('danger', "Stok di unit asal tidak mencukupi. Stok saat ini: <strong>$stok_asal_awal</strong>, jumlah mutasi: <strong>$jml_mutasi</strong>.");
        } else {
            // Ambil harga beli obat
            $hb_res = $conn->query("SELECT h_beli, nama_brng FROM databarang WHERE kode_brng = '$kode_brng'");
            $hb_row = $hb_res ? $hb_res->fetch_assoc() : null;
            $h_beli = $hb_row ? (float)$hb_row['h_beli'] : 0;
            $nama_brng = $hb_row ? $hb_row['nama_brng'] : $kode_brng;

            // 1. Kurangi stok di unit asal
            $conn->query("
                UPDATE gudangbarang SET stok = GREATEST(0, stok - $jml_mutasi)
                WHERE kode_brng = '$kode_brng' AND kd_bangsal = '$dari_bangsal'
                LIMIT 1
            ");
            $stok_asal_akhir = $stok_asal_awal - $jml_mutasi;

            // 2. Tambah stok di unit tujuan
            $stk_ke_res = $conn->query("SELECT SUM(stok) as s FROM gudangbarang WHERE kode_brng = '$kode_brng' AND kd_bangsal = '$ke_bangsal'");
            $stok_tujuan_awal = $stk_ke_res ? (float)($stk_ke_res->fetch_assoc()['s'] ?? 0) : 0;
            $stok_tujuan_akhir = $stok_tujuan_awal + $jml_mutasi;

            $conn->query("
                INSERT INTO gudangbarang (kode_brng, kd_bangsal, stok, no_batch, no_faktur)
                VALUES ('$kode_brng', '$ke_bangsal', '$jml_mutasi', '$no_batch', '$no_faktur')
                ON DUPLICATE KEY UPDATE stok = stok + $jml_mutasi
            ");

            // 3. Simpan transaksi ke mutasibarang
            $conn->query("
                INSERT INTO mutasibarang (
                    kode_brng, jml, harga, kd_bangsaldari, kd_bangsalke, tanggal, keterangan, no_batch, no_faktur
                ) VALUES (
                    '$kode_brng', '$jml_mutasi', '$h_beli', '$dari_bangsal', '$ke_bangsal', '$now_dt', '$keterangan', '$no_batch', '$no_faktur'
                )
            ");

            // 4. Catat riwayat kartu stok unit asal (Keluar)
            $conn->query("
                INSERT INTO riwayat_barang_medis (
                    kode_brng, stok_awal, masuk, keluar, stok_akhir, posisi, tanggal, jam, petugas, kd_bangsal, status, no_batch, no_faktur, keterangan
                ) VALUES (
                    '$kode_brng', '$stok_asal_awal', 0, '$jml_mutasi', '$stok_asal_akhir', 'Mutasi', '$tgl_mutasi', '$now_time', '$petugas_nama', '$dari_bangsal', 'Simpan', '$no_batch', '$no_faktur', 'Mutasi Keluar ke $ke_bangsal'
                )
            ");

            // 5. Catat riwayat kartu stok unit tujuan (Masuk)
            $conn->query("
                INSERT INTO riwayat_barang_medis (
                    kode_brng, stok_awal, masuk, keluar, stok_akhir, posisi, tanggal, jam, petugas, kd_bangsal, status, no_batch, no_faktur, keterangan
                ) VALUES (
                    '$kode_brng', '$stok_tujuan_awal', '$jml_mutasi', 0, '$stok_tujuan_akhir', 'Mutasi', '$tgl_mutasi', '$now_time', '$petugas_nama', '$ke_bangsal', 'Simpan', '$no_batch', '$no_faktur', 'Mutasi Masuk dari $dari_bangsal'
                )
            ");

            set_flash('success', "Mutasi <strong>$jml_mutasi</strong> item obat <strong>$nama_brng</strong> berhasil dipindahkan.");
            redirect(BASE_URL . 'modules/gudang_obat/mutasi.php');
        }
    }
}

// ─── Filter & Riwayat Mutasi ──────────────────────────────────
$tgl_mulai  = sanitize($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_akhir  = sanitize($_GET['tgl_akhir'] ?? date('Y-m-d'));
$dari_flt   = sanitize($_GET['dari'] ?? '');
$ke_flt     = sanitize($_GET['ke'] ?? '');
$search     = sanitize($_GET['q'] ?? '');

$where = "DATE(m.tanggal) BETWEEN '$tgl_mulai' AND '$tgl_akhir'";
if ($dari_flt) $where .= " AND m.kd_bangsaldari = '$dari_flt'";
if ($ke_flt)   $where .= " AND m.kd_bangsalke = '$ke_flt'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (m.kode_brng LIKE '%$s%' OR db.nama_brng LIKE '%$s%')";
}

$mutasi_res = $conn->query("
    SELECT m.*, db.nama_brng, ks.satuan,
           b1.nm_bangsal as nm_bangsal_dari,
           b2.nm_bangsal as nm_bangsal_ke
    FROM mutasibarang m
    JOIN databarang db ON m.kode_brng = db.kode_brng
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN bangsal b1 ON m.kd_bangsaldari = b1.kd_bangsal
    LEFT JOIN bangsal b2 ON m.kd_bangsalke = b2.kd_bangsal
    WHERE $where
    ORDER BY m.tanggal DESC
");

$mutasi_list = [];
$total_qty_mutasi = 0;
if ($mutasi_res) {
    while ($row = $mutasi_res->fetch_assoc()) {
        $mutasi_list[] = $row;
        $total_qty_mutasi += (float)$row['jml'];
    }
}

// Master Bangsal & Obat
$bangsal_list = [];
$rb = $conn->query("SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status='1' ORDER BY nm_bangsal");
if ($rb) while ($row = $rb->fetch_assoc()) $bangsal_list[] = $row;

$obat_list = [];
$ro = $conn->query("SELECT kode_brng, nama_brng FROM databarang WHERE status='1' ORDER BY nama_brng");
if ($ro) while ($row = $ro->fetch_assoc()) $obat_list[] = $row;

$prefill_kode = sanitize($_GET['kode'] ?? '');

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Sub-Navigation Gudang Obat ────────────────────────── -->
<?php include __DIR__ . '/header_nav.php'; ?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Mutasi Obat Antar Unit</h1>
    <p class="page-subtitle">Distribusi stok obat dari gudang utama ke apotek, rawat jalan, atau unit pelayanan</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="openMutasiModal()">
      <i class="fas fa-arrows-split-up-and-left"></i> Input Mutasi Baru
    </button>
  </div>
</div>

<!-- ─── Summary Cards ────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:18px;">
  <div class="card" style="border-left:4px solid #3b82f6;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
      <div style="width:42px;height:42px;background:#eff6ff;color:#2563eb;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-arrow-right-arrow-left"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;color:#0f172a;"><?= count($mutasi_list) ?></div>
        <div style="font-size:12px;color:#64748b;">Transaksi Mutasi Periode Ini</div>
      </div>
    </div>
  </div>

  <div class="card" style="border-left:4px solid #10b981;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
      <div style="width:42px;height:42px;background:#ecfdf5;color:#059669;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-boxes-stacked"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;color:#059669;"><?= number_format($total_qty_mutasi) ?></div>
        <div style="font-size:12px;color:#64748b;">Total Kuantitas Obat Dipindahkan</div>
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

      <div style="width:150px;">
        <select name="dari" class="form-control">
          <option value="">— Unit Asal —</option>
          <?php foreach ($bangsal_list as $b): ?>
            <option value="<?= $b['kd_bangsal'] ?>" <?= $dari_flt===$b['kd_bangsal']?'selected':'' ?>>
              <?= htmlspecialchars($b['nm_bangsal']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="width:150px;">
        <select name="ke" class="form-control">
          <option value="">— Unit Tujuan —</option>
          <?php foreach ($bangsal_list as $b): ?>
            <option value="<?= $b['kd_bangsal'] ?>" <?= $ke_flt===$b['kd_bangsal']?'selected':'' ?>>
              <?= htmlspecialchars($b['nm_bangsal']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="flex:1;min-width:180px;">
        <input type="text" name="q" class="form-control" placeholder="Cari kode atau nama obat..." value="<?= htmlspecialchars($search) ?>">
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
      <?php if ($search || $dari_flt || $ke_flt || $tgl_mulai !== date('Y-m-01')): ?>
        <a href="<?= BASE_URL ?>modules/gudang_obat/mutasi.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Table Riwayat Mutasi ─────────────────────────────── -->
<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <th style="width:140px;">Waktu Mutasi</th>
            <th>Item Obat</th>
            <th>Unit Asal</th>
            <th style="width:40px;text-align:center;">&rarr;</th>
            <th>Unit Tujuan</th>
            <th style="text-align:center;">Jumlah Mutasi</th>
            <th>Keterangan / Alasan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($mutasi_list)): ?>
            <tr>
              <td colspan="7" style="text-align:center;padding:36px;color:#94a3b8;">
                <i class="fas fa-arrows-split-up-and-left" style="font-size:32px;margin-bottom:10px;display:block;"></i>
                Belum ada data riwayat mutasi obat pada rentang tanggal ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($mutasi_list as $row): ?>
              <tr>
                <td style="font-size:12px;color:#475569;">
                  <?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?>
                </td>
                <td>
                  <div style="font-weight:600;color:#0f172a;font-size:13px;">
                    <?= htmlspecialchars($row['nama_brng']) ?>
                  </div>
                  <code style="font-size:11px;color:#64748b;"><?= htmlspecialchars($row['kode_brng']) ?></code>
                </td>
                <td>
                  <span class="badge badge-light" style="font-size:11.5px;">
                    <i class="fas fa-warehouse" style="color:#64748b;margin-right:3px;"></i>
                    <?= htmlspecialchars($row['nm_bangsal_dari'] ?: $row['kd_bangsaldari']) ?>
                  </span>
                </td>
                <td style="text-align:center;color:#94a3b8;font-weight:bold;">&rarr;</td>
                <td>
                  <span class="badge badge-light" style="font-size:11.5px;color:#0284c7;">
                    <i class="fas fa-clinic-medical" style="margin-right:3px;"></i>
                    <?= htmlspecialchars($row['nm_bangsal_ke'] ?: $row['kd_bangsalke']) ?>
                  </span>
                </td>
                <td style="text-align:center;font-size:13px;font-weight:700;color:#0f172a;">
                  <?= (int)$row['jml'] ?> <?= htmlspecialchars($row['satuan'] ?? '') ?>
                </td>
                <td style="font-size:12px;color:#64748b;">
                  <?= htmlspecialchars($row['keterangan'] ?: '-') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ─── Modal Input Mutasi Baru ──────────────────────────── -->
<div class="modal fade" id="modalMutasi" tabindex="-1" style="display:none;background:rgba(15,23,42,0.6);position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#ffffff;border-radius:14px;width:100%;max-width:600px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
    
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">Form Input Mutasi Stok Obat</h3>
      <button type="button" onclick="closeMutasiModal()" style="background:none;border:none;font-size:18px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <form method="POST" action="" style="overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;">
      
      <div class="form-group">
        <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Tanggal Mutasi <span style="color:#ef4444;">*</span></label>
        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Dari Gudang / Unit Asal <span style="color:#ef4444;">*</span></label>
          <select name="kd_bangsaldari" id="mutasi_dari" class="form-control" required onchange="loadStokAsal()">
            <?php foreach ($bangsal_list as $b): ?>
              <option value="<?= $b['kd_bangsal'] ?>" <?= $b['kd_bangsal']==='GF'?'selected':'' ?>><?= htmlspecialchars($b['nm_bangsal']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Ke Unit / Gudang Tujuan <span style="color:#ef4444;">*</span></label>
          <select name="kd_bangsalke" id="mutasi_ke" class="form-control" required>
            <?php foreach ($bangsal_list as $b): ?>
              <option value="<?= $b['kd_bangsal'] ?>" <?= $b['kd_bangsal']==='APT'?'selected':'' ?>><?= htmlspecialchars($b['nm_bangsal']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Pilih Obat Yang Dimutasi <span style="color:#ef4444;">*</span></label>
        <select name="kode_brng" id="mutasi_kode_brng" class="form-control" required onchange="loadStokAsal()">
          <option value="">— Pilih Obat —</option>
          <?php foreach ($obat_list as $o): ?>
            <option value="<?= $o['kode_brng'] ?>" <?= $prefill_kode===$o['kode_brng']?'selected':'' ?>>
              <?= htmlspecialchars($o['nama_brng']) ?> (<?= $o['kode_brng'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;">
        <div class="form-group">
          <label class="form-label" style="font-size:11.5px;color:#64748b;">Stok Tersedia Di Asal</label>
          <input type="number" id="mutasi_stok_tersedia" class="form-control" readonly style="background:#e2e8f0;font-weight:700;" value="0">
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11.5px;font-weight:700;color:#0f172a;">Jumlah Mutasi <span style="color:#ef4444;">*</span></label>
          <input type="number" name="jml" id="mutasi_jml" class="form-control" min="1" value="1" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Keterangan / Keperluan Distribusi</label>
        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Distribusi harian Apotek / Permintaan IGD">
      </div>

      <div style="padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeMutasiModal()">Batal</button>
        <button type="submit" name="simpan_mutasi" class="btn btn-primary">
          <i class="fas fa-save"></i> Proses Mutasi Stok
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openMutasiModal() {
  document.getElementById('modalMutasi').style.display = 'flex';
  loadStokAsal();
}

function closeMutasiModal() {
  document.getElementById('modalMutasi').style.display = 'none';
}

function loadStokAsal() {
  const kode = document.getElementById('mutasi_kode_brng').value;
  const dari = document.getElementById('mutasi_dari').value;
  
  if (!kode) {
    document.getElementById('mutasi_stok_tersedia').value = 0;
    return;
  }

  fetch('<?= BASE_URL ?>modules/gudang_obat/ajax.php?action=get_stok&kode=' + encodeURIComponent(kode) + '&bangsal=' + encodeURIComponent(dari))
    .then(res => res.json())
    .then(data => {
      const s = data.stok || 0;
      document.getElementById('mutasi_stok_tersedia').value = s;
      document.getElementById('mutasi_jml').max = s;
    })
    .catch(() => {
      document.getElementById('mutasi_stok_tersedia').value = 0;
    });
}

<?php if ($prefill_kode): ?>
window.addEventListener('DOMContentLoaded', () => {
  openMutasiModal();
});
<?php endif; ?>
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
