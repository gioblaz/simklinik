<?php
/**
 * SIMKlinik — Farmasi: Master Obat & Manajemen Stok
 */

$page_title    = 'Stok Obat & BHP';
$active_module = 'farmasi';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ─── Tambah / Edit Obat ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_obat'])) {
    $kode_brng   = $conn->real_escape_string(sanitize($_POST['kode_brng'] ?? ''));
    $nama_brng   = $conn->real_escape_string(sanitize($_POST['nama_brng'] ?? ''));
    $h_beli      = (float)($_POST['h_beli'] ?? 0);
    $ralan       = (float)($_POST['ralan'] ?? 0);
    $stokminimal = (float)($_POST['stokminimal'] ?? 10);
    $stok_tambah = (float)($_POST['stok_tambah'] ?? 0);
    $expire      = sanitize($_POST['expire'] ?? date('Y-m-d', strtotime('+2 years')));

    if (empty($kode_brng) || empty($nama_brng)) {
        set_flash('danger', 'Kode dan Nama Obat wajib diisi.');
    } else {
        $sql = "INSERT INTO databarang (
            kode_brng, nama_brng, kode_satbesar, kode_sat, letak_barang, dasar,
            h_beli, ralan, kelas1, kelas2, kelas3, utama, vip, vvip, beliluar, jualbebas,
            karyawan, stokminimal, kdjns, isi, kapasitas, expire, status,
            kode_industri, kode_kategori, kode_golongan
        ) VALUES (
            '$kode_brng', '$nama_brng', '-', '-', '-', '$h_beli',
            '$h_beli', '$ralan', '$ralan', '$ralan', '$ralan', '$ralan', '$ralan', '$ralan', '$ralan', '$ralan',
            '$ralan', '$stokminimal', '-', 1, 100, '$expire', '1',
            '-', '-', '-'
        ) ON DUPLICATE KEY UPDATE
            nama_brng = '$nama_brng',
            h_beli = '$h_beli',
            ralan = '$ralan',
            stokminimal = '$stokminimal',
            expire = '$expire'";

        if ($conn->query($sql)) {
            // Update / Tambah stok di gudangbarang
            if ($stok_tambah > 0) {
                $conn->query("
                    INSERT INTO gudangbarang (kode_brng, kd_bangsal, stok, no_batch, no_faktur)
                    VALUES ('$kode_brng', '-', '$stok_tambah', '-', '-')
                    ON DUPLICATE KEY UPDATE stok = stok + $stok_tambah
                ");
            }
            set_flash('success', "Data obat <strong>$nama_brng</strong> ($kode_brng) berhasil disimpan.");
            redirect(BASE_URL . 'modules/farmasi/stok.php');
        } else {
            set_flash('danger', 'Gagal menyimpan: ' . $conn->error);
        }
    }
}

// ─── Filter & Search ──────────────────────────────────────────
$search = sanitize($_GET['q'] ?? '');
$filter_stok = sanitize($_GET['stok'] ?? '');

$where = "db.status = '1'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (db.kode_brng LIKE '%$s%' OR db.nama_brng LIKE '%$s%')";
}

// Pagination
$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$count_sql = "SELECT COUNT(DISTINCT db.kode_brng) as t FROM databarang db WHERE $where";
$total = (int)($conn->query($count_sql)->fetch_assoc()['t'] ?? 0);
$total_pages = ceil($total / $per_page);

$result = $conn->query("
    SELECT db.*, ks.satuan,
           COALESCE(SUM(gb.stok), 0) as stok_total
    FROM databarang db
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
    WHERE $where
    GROUP BY db.kode_brng
    " . ($filter_stok === 'menipis' ? "HAVING stok_total <= db.stokminimal" : "") . "
    ORDER BY db.nama_brng ASC
    LIMIT $per_page OFFSET $offset
");

$obat_list = [];
if ($result) while ($row = $result->fetch_assoc()) $obat_list[] = $row;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Master Obat & Manajemen Stok</h1>
    <p class="page-subtitle">Katalog Obat, Harga Jual Resep, Stok Gudang, dan Batas Minimal</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" data-open-modal="modalTambahObat">
      <i class="fas fa-plus"></i> Tambah Obat Baru
    </button>
  </div>
</div>

<!-- ─── Search & Filter Bar ──────────────────────────────── -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px;">
    <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <div class="search-bar" style="flex:1;max-width:360px;">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Cari kode obat atau nama obat..."
               value="<?= htmlspecialchars($search) ?>" autocomplete="off">
      </div>

      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:var(--gray-500);">Kondisi Stok:</label>
        <select name="stok" class="form-control" style="width:160px;">
          <option value="">— Semua Obat —</option>
          <option value="menipis" <?= $filter_stok==='menipis' ? 'selected':'' ?>>Stok Menipis / Habis</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Cari</button>
      <?php if ($search || $filter_stok): ?>
        <a href="<?= BASE_URL ?>modules/farmasi/stok.php" class="btn btn-outline btn-sm"><i class="fas fa-redo"></i> Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Table Katalog Obat ───────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-boxes"></i> Daftar Obat & BHP</div>
    <span style="font-size:12px;color:var(--gray-500);">Total <strong><?= number_format($total) ?></strong> item terdaftar</span>
  </div>

  <div class="card-body" style="padding:0;">
    <?php if (empty($obat_list)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-box-open"></i></div>
        <div class="empty-state-title">Obat tidak ditemukan</div>
        <div class="empty-state-desc">Tambahkan obat baru melalui tombol di atas.</div>
      </div>
    <?php else: ?>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th style="width:120px;">Kode Obat</th>
              <th>Nama Obat / BHP</th>
              <th>Harga Beli</th>
              <th>Harga Jual (Ralan)</th>
              <th>Stok Saat Ini</th>
              <th>Stok Min.</th>
              <th>Kadaluarsa</th>
              <th style="width:100px;text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($obat_list as $o): ?>
              <?php $is_menipis = $o['stok_total'] <= $o['stokminimal']; ?>
              <tr>
                <td>
                  <span style="font-family:monospace;font-weight:700;color:var(--primary-600);font-size:12px;">
                    <?= htmlspecialchars($o['kode_brng']) ?>
                  </span>
                </td>
                <td>
                  <div style="font-size:13px;font-weight:600;color:var(--gray-900);">
                    <?= htmlspecialchars($o['nama_brng']) ?>
                  </div>
                  <div style="font-size:10px;color:var(--gray-400);">
                    Satuan: <?= htmlspecialchars($o['satuan'] ?: 'Tablet/Botol') ?>
                  </div>
                </td>
                <td style="font-size:12px;color:var(--gray-600);"><?= rupiah((float)$o['h_beli']) ?></td>
                <td><strong style="font-size:13px;color:var(--success);"><?= rupiah((float)$o['ralan']) ?></strong></td>
                <td>
                  <span class="badge <?= $is_menipis ? 'badge-danger' : 'badge-success' ?>" style="font-size:12px;padding:3px 10px;">
                    <?= number_format($o['stok_total']) ?>
                  </span>
                </td>
                <td style="font-size:12px;color:var(--gray-500);"><?= number_format($o['stokminimal']) ?></td>
                <td style="font-size:11px;color:var(--gray-600);"><?= tgl_indo($o['expire']) ?></td>
                <td style="text-align:center;">
                  <button type="button" class="btn btn-sm btn-outline btn-icon" title="Tambah Stok / Edit"
                          onclick='editObat(<?= json_encode($o) ?>)'>
                    <i class="fas fa-edit"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div style="padding:12px 20px;border-top:1px solid var(--gray-100);">
          <div class="pagination-nav">
            <ul class="pagination">
              <?php if ($page > 1): ?>
                <li><a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>"><i class="fas fa-chevron-left"></i></a></li>
              <?php endif; ?>
              <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <li class="<?= $i===$page ? 'active':'' ?>">
                  <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <?php if ($page < $total_pages): ?>
                <li><a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>"><i class="fas fa-chevron-right"></i></a></li>
              <?php endif; ?>
            </ul>
            <span class="pagination-info">Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total ?> data)</span>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ─── Modal Tambah / Edit Obat ──────────────────────────── -->
<div class="modal-overlay" id="modalTambahObat">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="modalObatTitle">Tambah Obat / BHP Baru</h3>
      <button class="modal-close" data-close-modal="modalTambahObat"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="simpan_obat" value="1">
      <div class="modal-body">
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Kode Obat <span class="required">*</span></label>
            <input type="text" name="kode_brng" id="modalKodeBrng" class="form-control" placeholder="cth: B00002" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Obat <span class="required">*</span></label>
            <input type="text" name="nama_brng" id="modalNamaBrng" class="form-control" placeholder="cth: Amoxicillin 500mg" required>
          </div>
        </div>

        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Harga Beli (Rp)</label>
            <input type="number" name="h_beli" id="modalHBeli" class="form-control" placeholder="0" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Harga Jual Ralan (Rp) <span class="required">*</span></label>
            <input type="number" name="ralan" id="modalRalan" class="form-control" placeholder="0" value="0" required>
          </div>
        </div>

        <div class="form-row col-3">
          <div class="form-group">
            <label class="form-label">Tambah Stok</label>
            <input type="number" name="stok_tambah" id="modalStokTambah" class="form-control" placeholder="0" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Stok Minimal</label>
            <input type="number" name="stokminimal" id="modalStokMin" class="form-control" placeholder="10" value="10">
          </div>
          <div class="form-group">
            <label class="form-label">Kadaluarsa</label>
            <input type="date" name="expire" id="modalExpire" class="form-control" value="<?= date('Y-m-d', strtotime('+2 years')) ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modalTambahObat">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Data Obat</button>
      </div>
    </form>
  </div>
</div>

<script>
function editObat(o) {
  document.getElementById('modalObatTitle').textContent = 'Edit Data Obat: ' + o.nama_brng;
  document.getElementById('modalKodeBrng').value = o.kode_brng;
  document.getElementById('modalNamaBrng').value = o.nama_brng;
  document.getElementById('modalHBeli').value    = o.h_beli;
  document.getElementById('modalRalan').value    = o.ralan;
  document.getElementById('modalStokMin').value  = o.stokminimal;
  document.getElementById('modalExpire').value   = o.expire;
  document.getElementById('modalStokTambah').value = 0;
  openModal('modalTambahObat');
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
