<?php
/**
 * SIMKlinik — Gudang Obat: Master Data Obat & BHP
 */

$page_title    = 'Master Data Obat & BHP';
$active_module = 'gudang_obat';
$sub_active    = 'master';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('gudang_obat');

// ─── Tambah / Edit Obat ───────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['simpan_obat'])) {
    $kode_brng    = trim($conn->real_escape_string($_POST['kode_brng'] ?? ''));
    $nama_brng    = trim($conn->real_escape_string($_POST['nama_brng'] ?? ''));
    $kode_sat     = $conn->real_escape_string($_POST['kode_sat'] ?? '-');
    $kode_satbesar= $conn->real_escape_string($_POST['kode_satbesar'] ?? '-');
    $kdjns        = $conn->real_escape_string($_POST['kdjns'] ?? '-');
    $kode_kategori= $conn->real_escape_string($_POST['kode_kategori'] ?? '-');
    $kode_golongan= $conn->real_escape_string($_POST['kode_golongan'] ?? '-');
    $letak_barang = $conn->real_escape_string($_POST['letak_barang'] ?? '-');
    $h_beli       = (float)($_POST['h_beli'] ?? 0);
    $ralan        = (float)($_POST['ralan'] ?? 0);
    $stokminimal  = (float)($_POST['stokminimal'] ?? 10);
    $expire       = !empty($_POST['expire']) ? $_POST['expire'] : date('Y-m-d', strtotime('+2 years'));
    $status       = isset($_POST['status']) && $_POST['status'] === '0' ? '0' : '1';
    $is_edit      = !empty($_POST['is_edit']) && $_POST['is_edit'] === '1';

    // Auto generate kode jika kosong
    if (empty($kode_brng)) {
        $last_res = $conn->query("SELECT kode_brng FROM databarang WHERE kode_brng LIKE 'B%' ORDER BY kode_brng DESC LIMIT 1");
        if ($last_res && $last_res->num_rows > 0) {
            $last_code = $last_res->fetch_assoc()['kode_brng'];
            $num = (int)preg_replace('/[^0-9]/', '', $last_code) + 1;
            $kode_brng = 'B' . str_pad($num, 5, '0', STR_PAD_LEFT);
        } else {
            $kode_brng = 'B00001';
        }
    }

    if (empty($nama_brng)) {
        set_flash('danger', 'Nama obat wajib diisi.');
    } else {
        $sql = "INSERT INTO databarang (
            kode_brng, nama_brng, kode_satbesar, kode_sat, letak_barang, dasar,
            h_beli, ralan, kelas1, kelas2, kelas3, utama, vip, vvip, beliluar, jualbebas,
            karyawan, stokminimal, kdjns, isi, kapasitas, expire, status,
            kode_industri, kode_kategori, kode_golongan
        ) VALUES (
            '$kode_brng', '$nama_brng', '$kode_satbesar', '$kode_sat', '$letak_barang', '$h_beli',
            '$h_beli', '$ralan', '$ralan', '$ralan', '$ralan', '$ralan', '$ralan', '$ralan', '$ralan', '$ralan',
            '$ralan', '$stokminimal', '$kdjns', 1, 100, '$expire', '$status',
            '-', '$kode_kategori', '$kode_golongan'
        ) ON DUPLICATE KEY UPDATE
            nama_brng = '$nama_brng',
            kode_sat = '$kode_sat',
            kode_satbesar = '$kode_satbesar',
            kdjns = '$kdjns',
            kode_kategori = '$kode_kategori',
            kode_golongan = '$kode_golongan',
            letak_barang = '$letak_barang',
            h_beli = '$h_beli',
            ralan = '$ralan',
            stokminimal = '$stokminimal',
            expire = '$expire',
            status = '$status'";

        if ($conn->query($sql)) {
            set_flash('success', "Data obat <strong>$nama_brng</strong> ($kode_brng) berhasil disimpan.");
            redirect(BASE_URL . 'modules/gudang_obat/index.php');
        } else {
            set_flash('danger', 'Gagal menyimpan data obat: ' . $conn->error);
        }
    }
}

// ─── Hapus / Nonaktifkan Obat ────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['kode'])) {
    $kd = $conn->real_escape_string($_GET['kode']);
    $st = $_GET['val'] === '1' ? '1' : '0';
    $conn->query("UPDATE databarang SET status = '$st' WHERE kode_brng = '$kd'");
    set_flash('info', "Status obat $kd berhasil diubah.");
    redirect(BASE_URL . 'modules/gudang_obat/index.php');
}

// ─── Filter & Search ──────────────────────────────────────────
$q        = trim($conn->real_escape_string($_GET['q'] ?? ''));
$kat      = trim($conn->real_escape_string($_GET['kat'] ?? ''));
$jns      = trim($conn->real_escape_string($_GET['jns'] ?? ''));
$stts     = trim($conn->real_escape_string($_GET['stts'] ?? ''));

$where = "1=1";
if ($q !== '') {
    $where .= " AND (db.kode_brng LIKE '%$q%' OR db.nama_brng LIKE '%$q%' OR db.letak_barang LIKE '%$q%')";
}
if ($kat !== '') {
    $where .= " AND db.kode_kategori = '$kat'";
}
if ($jns !== '') {
    $where .= " AND db.kdjns = '$jns'";
}
if ($stts !== '') {
    $where .= " AND db.status = '$stts'";
}

// Pagination
$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) as t FROM databarang db WHERE $where";
$total_rows = (int)($conn->query($count_sql)->fetch_assoc()['t'] ?? 0);
$total_pages = ceil($total_rows / $per_page);

$result = $conn->query("
    SELECT db.*, ks.satuan, j.nama as nama_jenis, kb.nama as nama_kategori,
           COALESCE(SUM(gb.stok), 0) as total_stok
    FROM databarang db
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN jenis j ON db.kdjns = j.kdjns
    LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
    LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
    WHERE $where
    GROUP BY db.kode_brng
    ORDER BY db.nama_brng ASC
    LIMIT $per_page OFFSET $offset
");

$obat_list = [];
if ($result) while ($r = $result->fetch_assoc()) $obat_list[] = $r;

// Master Lookup Data
$satuan_list = [];
$rs = $conn->query("SELECT kode_sat, satuan FROM kodesatuan WHERE kode_sat != '-' ORDER BY satuan");
if ($rs) while ($r = $rs->fetch_assoc()) $satuan_list[] = $r;

$jenis_list = [];
$rj = $conn->query("SELECT kdjns, nama FROM jenis WHERE kdjns != '-' ORDER BY nama");
if ($rj) while ($r = $rj->fetch_assoc()) $jenis_list[] = $r;

$kategori_list = [];
$rk = $conn->query("SELECT kode, nama FROM kategori_barang WHERE kode != '-' ORDER BY nama");
if ($rk) while ($r = $rk->fetch_assoc()) $kategori_list[] = $r;

// Counter Ringkasan
$cnt_total  = (int)($conn->query("SELECT COUNT(*) as t FROM databarang")->fetch_assoc()['t'] ?? 0);
$cnt_aktif  = (int)($conn->query("SELECT COUNT(*) as t FROM databarang WHERE status='1'")->fetch_assoc()['t'] ?? 0);
$cnt_kategori = (int)($conn->query("SELECT COUNT(*) as t FROM kategori_barang WHERE kode != '-'")->fetch_assoc()['t'] ?? 0);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Sub-Navigation Gudang Obat ────────────────────────── -->
<?php include __DIR__ . '/header_nav.php'; ?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
  <div>
    <h1 class="page-title">Master Data Obat & BHP</h1>
    <p class="page-subtitle">Katalog referensi obat, alat kesehatan, tarif beli & harga jual</p>
  </div>
  <div class="page-actions" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <!-- Compact Summary Badges di Kanan Atas -->
    <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;padding:6px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;">
      <span class="badge badge-light" style="font-size:11px;font-weight:700;color:#2563eb;background:#eff6ff;">
        <i class="fas fa-pills"></i> <?= number_format($cnt_total) ?> Total Item
      </span>
      <span class="badge badge-success" style="font-size:11px;font-weight:700;">
        <i class="fas fa-check-circle"></i> <?= number_format($cnt_aktif) ?> Aktif
      </span>
      <span class="badge badge-warning" style="font-size:11px;font-weight:700;">
        <i class="fas fa-layer-group"></i> <?= number_format($cnt_kategori) ?> Kategori
      </span>
    </div>

    <button type="button" class="btn btn-primary" onclick="openTambahModal()">
      <i class="fas fa-plus"></i> Tambah Obat Baru
    </button>
  </div>
</div>

<!-- ─── Filter & Search Bar ──────────────────────────────── -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <div style="flex:1;min-width:220px;position:relative;">
        <input type="text" name="q" class="form-control" placeholder="Cari kode, nama obat, atau lokasi rak..." value="<?= htmlspecialchars($q) ?>">
      </div>

      <div style="min-width:160px;">
        <select name="kat" class="form-control">
          <option value="">— Semua Kategori —</option>
          <?php foreach ($kategori_list as $k): ?>
            <option value="<?= $k['kode'] ?>" <?= $kat===$k['kode']?'selected':'' ?>><?= htmlspecialchars($k['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:150px;">
        <select name="jns" class="form-control">
          <option value="">— Semua Jenis —</option>
          <?php foreach ($jenis_list as $j): ?>
            <option value="<?= $j['kdjns'] ?>" <?= $jns===$j['kdjns']?'selected':'' ?>><?= htmlspecialchars($j['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:130px;">
        <select name="stts" class="form-control">
          <option value="">— Status —</option>
          <option value="1" <?= $stts==='1'?'selected':'' ?>>Aktif</option>
          <option value="0" <?= $stts==='0'?'selected':'' ?>>Non-Aktif</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary" style="padding:8px 16px;">
        <i class="fas fa-search"></i> Filter
      </button>

      <?php if ($q || $kat || $jns || $stts): ?>
        <a href="<?= BASE_URL ?>modules/gudang_obat/index.php" class="btn btn-secondary">
          <i class="fas fa-times"></i> Reset
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Table Data Obat ──────────────────────────────────── -->
<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <th style="width:110px;">Kode</th>
            <th>Nama Obat / Alkes</th>
            <th>Satuan</th>
            <th>Kategori / Jenis</th>
            <th style="text-align:right;">Harga Beli (HPP)</th>
            <th style="text-align:right;">Harga Jual</th>
            <th style="text-align:center;">Stok Min</th>
            <th style="text-align:center;">Total Stok</th>
            <th style="text-align:center;">Kadaluarsa</th>
            <th style="text-align:center;width:90px;">Status</th>
            <th style="text-align:center;width:110px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($obat_list)): ?>
            <tr>
              <td colspan="11" style="text-align:center;padding:36px;color:#94a3b8;">
                <i class="fas fa-box-open" style="font-size:32px;margin-bottom:10px;display:block;"></i>
                Tidak ada data obat yang sesuai kriteria pencarian.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($obat_list as $o): ?>
              <?php
                $is_kritis = ($o['total_stok'] <= $o['stokminimal']);
                $is_exp    = (!empty($o['expire']) && $o['expire'] !== '0000-00-00' && strtotime($o['expire']) < time());
              ?>
              <tr>
                <td>
                  <code style="background:#f1f5f9;padding:3px 6px;border-radius:4px;font-size:11.5px;color:#0f172a;font-weight:600;">
                    <?= htmlspecialchars($o['kode_brng']) ?>
                  </code>
                </td>
                <td>
                  <div style="font-weight:600;color:#0f172a;font-size:13px;">
                    <?= htmlspecialchars($o['nama_brng']) ?>
                  </div>
                  <?php if (!empty($o['letak_barang']) && $o['letak_barang'] !== '-'): ?>
                    <div style="font-size:11px;color:#64748b;">
                      <i class="fas fa-map-pin" style="font-size:10px;"></i> Rak: <?= htmlspecialchars($o['letak_barang']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td><span class="badge badge-light" style="font-size:11px;"><?= htmlspecialchars($o['satuan'] ?: $o['kode_sat']) ?></span></td>
                <td>
                  <div style="font-size:12px;color:#334155;"><?= htmlspecialchars($o['nama_kategori'] ?: '-') ?></div>
                  <div style="font-size:11px;color:#94a3b8;"><?= htmlspecialchars($o['nama_jenis'] ?: '-') ?></div>
                </td>
                <td style="text-align:right;font-family:monospace;font-size:12px;color:#475569;">
                  <?= rupiah((float)$o['h_beli']) ?>
                </td>
                <td style="text-align:right;font-family:monospace;font-size:12px;font-weight:600;color:#0f172a;">
                  <?= rupiah((float)$o['ralan']) ?>
                </td>
                <td style="text-align:center;font-size:12px;color:#64748b;">
                  <?= (int)$o['stokminimal'] ?>
                </td>
                <td style="text-align:center;">
                  <span class="badge <?= $is_kritis ? 'badge-danger' : 'badge-success' ?>" style="font-size:12px;font-weight:700;">
                    <?= (int)$o['total_stok'] ?>
                  </span>
                </td>
                <td style="text-align:center;font-size:12px;">
                  <?php if (!empty($o['expire']) && $o['expire'] !== '0000-00-00'): ?>
                    <span style="color:<?= $is_exp ? '#ef4444' : '#64748b' ?>;font-weight:<?= $is_exp ? '700' : 'normal' ?>;">
                      <?= date('d/m/Y', strtotime($o['expire'])) ?>
                    </span>
                  <?php else: ?>
                    <span style="color:#cbd5e1;">-</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:center;">
                  <?php if ($o['status'] === '1'): ?>
                    <span class="badge badge-success" style="font-size:10.5px;">Aktif</span>
                  <?php else: ?>
                    <span class="badge badge-secondary" style="font-size:10.5px;">Non-Aktif</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:center;">
                  <div style="display:inline-flex;gap:4px;">
                    <button type="button" class="btn-icon btn-sm" title="Edit Data Obat"
                            onclick='editObat(<?= json_encode($o) ?>)'
                            style="width:28px;height:28px;border-radius:6px;border:1px solid #e2e8f0;background:#ffffff;color:#2563eb;cursor:pointer;">
                      <i class="fas fa-edit"></i>
                    </button>
                    <a href="<?= BASE_URL ?>modules/gudang_obat/index.php?action=toggle_status&kode=<?= urlencode($o['kode_brng']) ?>&val=<?= $o['status']==='1'?'0':'1' ?>"
                       class="btn-icon btn-sm"
                       title="<?= $o['status']==='1'?'Nonaktifkan':'Aktifkan' ?>"
                       style="width:28px;height:28px;border-radius:6px;border:1px solid #e2e8f0;background:#ffffff;color:<?= $o['status']==='1'?'#d97706':'#059669' ?>;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                      <i class="fas <?= $o['status']==='1'?'fa-ban':'fa-check' ?>"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div style="padding:16px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:12.5px;color:#64748b;">
          Menampilkan halaman <strong><?= $page ?></strong> dari <strong><?= $total_pages ?></strong> (Total <?= $total_rows ?> item)
        </span>
        <div style="display:flex;gap:6px;">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>&kat=<?= urlencode($kat) ?>&jns=<?= urlencode($jns) ?>&stts=<?= urlencode($stts) ?>" class="btn btn-secondary btn-sm">&laquo; Sebelumnya</a>
          <?php endif; ?>
          <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>&kat=<?= urlencode($kat) ?>&jns=<?= urlencode($jns) ?>&stts=<?= urlencode($stts) ?>" class="btn btn-secondary btn-sm">Selanjutnya &raquo;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ─── Modal Tambah / Edit Obat ─────────────────────────── -->
<div class="modal fade" id="modalObat" tabindex="-1" style="display:none;background:rgba(15,23,42,0.6);position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#ffffff;border-radius:14px;width:100%;max-width:680px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
    
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;" id="modalObatTitle">Tambah Data Obat Baru</h3>
      <button type="button" onclick="closeModalObat()" style="background:none;border:none;font-size:18px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <form method="POST" action="" style="overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" name="is_edit" id="form_is_edit" value="0">

      <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Kode Obat / Barcode</label>
          <input type="text" name="kode_brng" id="form_kode_brng" class="form-control" placeholder="Auto (B00001)">
          <span style="font-size:11px;color:#94a3b8;">Kosongkan untuk auto-generate</span>
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Nama Obat / Alkes <span style="color:#ef4444;">*</span></label>
          <input type="text" name="nama_brng" id="form_nama_brng" class="form-control" required placeholder="Contoh: Amoxicillin 500mg">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Satuan Kecil</label>
          <select name="kode_sat" id="form_kode_sat" class="form-control">
            <option value="-">— Pilih Satuan —</option>
            <?php foreach ($satuan_list as $s): ?>
              <option value="<?= $s['kode_sat'] ?>"><?= htmlspecialchars($s['satuan']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Kategori</label>
          <select name="kode_kategori" id="form_kode_kategori" class="form-control">
            <option value="-">— Pilih Kategori —</option>
            <?php foreach ($kategori_list as $k): ?>
              <option value="<?= $k['kode'] ?>"><?= htmlspecialchars($k['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Jenis / Sediaan</label>
          <select name="kdjns" id="form_kdjns" class="form-control">
            <option value="-">— Pilih Jenis —</option>
            <?php foreach ($jenis_list as $j): ?>
              <option value="<?= $j['kdjns'] ?>"><?= htmlspecialchars($j['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Harga Beli (HPP)</label>
          <div style="position:relative;">
            <input type="number" name="h_beli" id="form_h_beli" class="form-control" min="0" step="100" value="0" placeholder="0">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Harga Jual Ralan</label>
          <div style="position:relative;">
            <input type="number" name="ralan" id="form_ralan" class="form-control" min="0" step="100" value="0" placeholder="0">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Stok Minimum</label>
          <input type="number" name="stokminimal" id="form_stokminimal" class="form-control" min="0" value="10">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Tgl Kadaluarsa</label>
          <input type="date" name="expire" id="form_expire" class="form-control" value="<?= date('Y-m-d', strtotime('+2 years')) ?>">
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Lokasi Rak</label>
          <input type="text" name="letak_barang" id="form_letak_barang" class="form-control" placeholder="Contoh: Rak A-01">
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Status</label>
          <select name="status" id="form_status" class="form-control">
            <option value="1">Aktif</option>
            <option value="0">Non-Aktif</option>
          </select>
        </div>
      </div>

      <div style="padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeModalObat()">Batal</button>
        <button type="submit" name="simpan_obat" class="btn btn-primary">
          <i class="fas fa-save"></i> Simpan Data Obat
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openTambahModal() {
  document.getElementById('modalObatTitle').innerText = 'Tambah Data Obat Baru';
  document.getElementById('form_is_edit').value = '0';
  document.getElementById('form_kode_brng').value = '';
  document.getElementById('form_kode_brng').removeAttribute('readonly');
  document.getElementById('form_nama_brng').value = '';
  document.getElementById('form_kode_sat').value = '-';
  document.getElementById('form_kode_kategori').value = '-';
  document.getElementById('form_kdjns').value = '-';
  document.getElementById('form_h_beli').value = '0';
  document.getElementById('form_ralan').value = '0';
  document.getElementById('form_stokminimal').value = '10';
  document.getElementById('form_expire').value = '<?= date('Y-m-d', strtotime('+2 years')) ?>';
  document.getElementById('form_letak_barang').value = '';
  document.getElementById('form_status').value = '1';

  const modal = document.getElementById('modalObat');
  modal.style.display = 'flex';
}

function editObat(data) {
  document.getElementById('modalObatTitle').innerText = 'Edit Data Obat: ' + data.nama_brng;
  document.getElementById('form_is_edit').value = '1';
  document.getElementById('form_kode_brng').value = data.kode_brng;
  document.getElementById('form_kode_brng').setAttribute('readonly', 'readonly');
  document.getElementById('form_nama_brng').value = data.nama_brng || '';
  document.getElementById('form_kode_sat').value = data.kode_sat || '-';
  document.getElementById('form_kode_kategori').value = data.kode_kategori || '-';
  document.getElementById('form_kdjns').value = data.kdjns || '-';
  document.getElementById('form_h_beli').value = data.h_beli || 0;
  document.getElementById('form_ralan').value = data.ralan || 0;
  document.getElementById('form_stokminimal').value = data.stokminimal || 10;
  document.getElementById('form_expire').value = data.expire && data.expire !== '0000-00-00' ? data.expire : '<?= date('Y-m-d', strtotime('+2 years')) ?>';
  document.getElementById('form_letak_barang').value = data.letak_barang || '';
  document.getElementById('form_status').value = data.status || '1';

  const modal = document.getElementById('modalObat');
  modal.style.display = 'flex';
}

function closeModalObat() {
  document.getElementById('modalObat').style.display = 'none';
}

// Close on outside click
document.getElementById('modalObat').addEventListener('click', function(e) {
  if (e.target === this) closeModalObat();
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
