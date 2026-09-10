<?php
/**
 * SIMKlinik — Gudang Obat: Stok Opname Fisik vs Sistem
 */

$page_title    = 'Stok Opname Obat';
$active_module = 'gudang_obat';
$sub_active    = 'opname';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$user = current_user();
$petugas_nama = $user['fullname'] ?? 'Petugas Farmasi';

// ─── Proses Simpan Stok Opname ─────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['simpan_opname'])) {
    $kode_brng   = $conn->real_escape_string($_POST['kode_brng'] ?? '');
    $kd_bangsal  = $conn->real_escape_string($_POST['kd_bangsal'] ?? 'GF');
    $tanggal     = $conn->real_escape_string($_POST['tanggal'] ?? date('Y-m-d'));
    $stok_sistem = (float)($_POST['stok_sistem'] ?? 0);
    $stok_real   = (float)($_POST['stok_real'] ?? 0);
    $no_batch    = $conn->real_escape_string($_POST['no_batch'] ?? '-');
    $no_faktur   = $conn->real_escape_string($_POST['no_faktur'] ?? '-');
    $keterangan  = $conn->real_escape_string($_POST['keterangan'] ?? 'Stok Opname Rutin');

    if (empty($kode_brng)) {
        set_flash('danger', 'Silakan pilih obat yang akan di-opname.');
    } else {
        // Ambil harga beli obat
        $hb_res = $conn->query("SELECT h_beli, nama_brng FROM databarang WHERE kode_brng = '$kode_brng'");
        $hb_row = $hb_res ? $hb_res->fetch_assoc() : null;
        $h_beli = $hb_row ? (float)$hb_row['h_beli'] : 0;
        $nama_brng = $hb_row ? $hb_row['nama_brng'] : $kode_brng;

        $selisih = $stok_real - $stok_sistem;
        $nomihilang = ($selisih < 0) ? abs($selisih) * $h_beli : 0;
        $lebih      = ($selisih > 0) ? $selisih : 0;
        $nomilebih  = ($selisih > 0) ? $selisih * $h_beli : 0;

        // 1. Simpan ke tabel opname
        $sql_opname = "
            INSERT INTO opname (
                kode_brng, h_beli, tanggal, stok, `real`, selisih, nomihilang, lebih, nomilebih, keterangan, kd_bangsal, no_batch, no_faktur
            ) VALUES (
                '$kode_brng', '$h_beli', '$tanggal', '$stok_sistem', '$stok_real', '$selisih', '$nomihilang', '$lebih', '$nomilebih', '$keterangan', '$kd_bangsal', '$no_batch', '$no_faktur'
            ) ON DUPLICATE KEY UPDATE
                `real` = '$stok_real',
                selisih = '$selisih',
                nomihilang = '$nomihilang',
                lebih = '$lebih',
                nomilebih = '$nomilebih',
                keterangan = '$keterangan'
        ";
        $conn->query($sql_opname);

        // 2. Update stok di gudangbarang
        $conn->query("
            INSERT INTO gudangbarang (kode_brng, kd_bangsal, stok, no_batch, no_faktur)
            VALUES ('$kode_brng', '$kd_bangsal', '$stok_real', '$no_batch', '$no_faktur')
            ON DUPLICATE KEY UPDATE stok = '$stok_real'
        ");

        // 3. Catat di riwayat_barang_medis
        $now_time = date('H:i:s');
        $conn->query("
            INSERT INTO riwayat_barang_medis (
                kode_brng, stok_awal, masuk, keluar, stok_akhir, posisi, tanggal, jam, petugas, kd_bangsal, status, no_batch, no_faktur, keterangan
            ) VALUES (
                '$kode_brng', '$stok_sistem', '$lebih', " . ($selisih < 0 ? abs($selisih) : 0) . ", '$stok_real', 'Opname', '$tanggal', '$now_time', '$petugas_nama', '$kd_bangsal', 'Simpan', '$no_batch', '$no_faktur', '$keterangan'
            )
        ");

        set_flash('success', "Stok Opname obat <strong>$nama_brng</strong> berhasil disimpan. Stok fisik disesuaikan menjadi <strong>$stok_real</strong>.");
        redirect(BASE_URL . 'modules/gudang_obat/stok_opname.php');
    }
}

// ─── Filter & Riwayat Opname ───────────────────────────────────
$tgl_mulai  = sanitize($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_akhir  = sanitize($_GET['tgl_akhir'] ?? date('Y-m-d'));
$kd_bangsal_filter = sanitize($_GET['kd_bangsal'] ?? '');
$q_search   = sanitize($_GET['q'] ?? '');

$where = "o.tanggal BETWEEN '$tgl_mulai' AND '$tgl_akhir'";
if ($kd_bangsal_filter) {
    $where .= " AND o.kd_bangsal = '$kd_bangsal_filter'";
}
if ($q_search) {
    $s = $conn->real_escape_string($q_search);
    $where .= " AND (o.kode_brng LIKE '%$s%' OR db.nama_brng LIKE '%$s%')";
}

$riwayat_res = $conn->query("
    SELECT o.*, db.nama_brng, ks.satuan, b.nm_bangsal
    FROM opname o
    JOIN databarang db ON o.kode_brng = db.kode_brng
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN bangsal b ON o.kd_bangsal = b.kd_bangsal
    WHERE $where
    ORDER BY o.tanggal DESC, o.kode_brng ASC
");

$riwayat_list = [];
$total_hilang = 0;
$total_lebih  = 0;
if ($riwayat_res) {
    while ($r = $riwayat_res->fetch_assoc()) {
        $riwayat_list[] = $r;
        $total_hilang += (float)$r['nomihilang'];
        $total_lebih  += (float)$r['nomilebih'];
    }
}

// Daftar Obat untuk Form Input
$obat_options = [];
$ro = $conn->query("SELECT kode_brng, nama_brng, h_beli, ralan, stokminimal FROM databarang WHERE status='1' ORDER BY nama_brng");
if ($ro) while ($row = $ro->fetch_assoc()) $obat_options[] = $row;

// Daftar Bangsal
$bangsal_list = [];
$rb = $conn->query("SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status='1' ORDER BY nm_bangsal");
if ($rb) while ($row = $rb->fetch_assoc()) $bangsal_list[] = $row;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Sub-Navigation Gudang Obat ────────────────────────── -->
<?php include __DIR__ . '/header_nav.php'; ?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Stok Opname Obat & BHP</h1>
    <p class="page-subtitle">Penyesuaian dan pencocokan stok fisik nyata dengan catatan sistem</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="openOpnameModal()">
      <i class="fas fa-plus-circle"></i> Input Stok Opname
    </button>
  </div>
</div>

<!-- ─── Summary Cards ────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:18px;">
  
  <div class="card" style="border-left:4px solid #3b82f6;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
      <div style="width:42px;height:42px;background:#eff6ff;color:#2563eb;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-clipboard-list"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;color:#0f172a;"><?= count($riwayat_list) ?></div>
        <div style="font-size:12px;color:#64748b;">Item Ter-Opname Periode Ini</div>
      </div>
    </div>
  </div>

  <div class="card" style="border-left:4px solid #ef4444;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
      <div style="width:42px;height:42px;background:#fef2f2;color:#dc2626;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-arrow-trend-down"></i>
      </div>
      <div>
        <div style="font-size:18px;font-weight:800;color:#dc2626;"><?= rupiah($total_hilang) ?></div>
        <div style="font-size:12px;color:#64748b;">Total Selisih Kurang (Hilang)</div>
      </div>
    </div>
  </div>

  <div class="card" style="border-left:4px solid #10b981;">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
      <div style="width:42px;height:42px;background:#ecfdf5;color:#059669;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-arrow-trend-up"></i>
      </div>
      <div>
        <div style="font-size:18px;font-weight:800;color:#059669;"><?= rupiah($total_lebih) ?></div>
        <div style="font-size:12px;color:#64748b;">Total Selisih Lebih</div>
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

      <div style="width:180px;">
        <select name="kd_bangsal" class="form-control">
          <option value="">— Semua Gudang —</option>
          <?php foreach ($bangsal_list as $b): ?>
            <option value="<?= $b['kd_bangsal'] ?>" <?= $kd_bangsal_filter===$b['kd_bangsal']?'selected':'' ?>>
              <?= htmlspecialchars($b['nm_bangsal']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="flex:1;min-width:180px;">
        <input type="text" name="q" class="form-control" placeholder="Cari nama atau kode obat..." value="<?= htmlspecialchars($q_search) ?>">
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
      <?php if ($q_search || $kd_bangsal_filter || $tgl_mulai !== date('Y-m-01')): ?>
        <a href="<?= BASE_URL ?>modules/gudang_obat/stok_opname.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Table Riwayat Opname ─────────────────────────────── -->
<div class="card">
  <div class="card-header" style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
    <h3 style="font-size:14px;font-weight:700;color:#0f172a;margin:0;">Riwayat Pelaksanaan Stok Opname</h3>
    <span style="font-size:12px;color:#64748b;">Ditemukan <?= count($riwayat_list) ?> data transaksi</span>
  </div>
  <div class="card-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <th style="width:100px;">Tanggal</th>
            <th>Obat / Alkes</th>
            <th>Gudang</th>
            <th style="text-align:center;">Stok Sistem</th>
            <th style="text-align:center;">Stok Fisik Real</th>
            <th style="text-align:center;">Selisih</th>
            <th style="text-align:right;">Nilai Selisih</th>
            <th>Keterangan / Alasan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($riwayat_list)): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:36px;color:#94a3b8;">
                <i class="fas fa-clipboard-question" style="font-size:32px;margin-bottom:10px;display:block;"></i>
                Belum ada data riwayat stok opname pada rentang tanggal ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($riwayat_list as $row): ?>
              <?php
                $sel = (float)$row['selisih'];
              ?>
              <tr>
                <td style="font-size:12px;color:#475569;">
                  <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                </td>
                <td>
                  <div style="font-weight:600;color:#0f172a;font-size:13px;">
                    <?= htmlspecialchars($row['nama_brng']) ?>
                  </div>
                  <code style="font-size:11px;color:#64748b;"><?= htmlspecialchars($row['kode_brng']) ?></code>
                </td>
                <td><span class="badge badge-light"><?= htmlspecialchars($row['nm_bangsal'] ?: $row['kd_bangsal']) ?></span></td>
                <td style="text-align:center;font-size:13px;color:#64748b;"><?= (int)$row['stok'] ?></td>
                <td style="text-align:center;font-size:13px;font-weight:700;color:#0f172a;"><?= (int)$row['real'] ?></td>
                <td style="text-align:center;">
                  <?php if ($sel == 0): ?>
                    <span class="badge badge-success">Cocok (0)</span>
                  <?php elseif ($sel < 0): ?>
                    <span class="badge badge-danger"><?= (int)$sel ?></span>
                  <?php else: ?>
                    <span class="badge badge-info">+<?= (int)$sel ?></span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;font-family:monospace;font-size:12px;font-weight:600;color:<?= $sel<0?'#dc2626':($sel>0?'#059669':'#64748b') ?>;">
                  <?php if ($sel < 0): ?>
                    - <?= rupiah((float)$row['nomihilang']) ?>
                  <?php elseif ($sel > 0): ?>
                    + <?= rupiah((float)$row['nomilebih']) ?>
                  <?php else: ?>
                    Rp 0
                  <?php endif; ?>
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

<!-- ─── Modal Input Stok Opname ─────────────────────────── -->
<div class="modal fade" id="modalOpname" tabindex="-1" style="display:none;background:rgba(15,23,42,0.6);position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#ffffff;border-radius:14px;width:100%;max-width:600px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
    
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">Form Input Stok Opname</h3>
      <button type="button" onclick="closeOpnameModal()" style="background:none;border:none;font-size:18px;color:#94a3b8;cursor:pointer;">&times;</button>
    </div>

    <form method="POST" action="" style="overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;">
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Tanggal Opname <span style="color:#ef4444;">*</span></label>
          <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Lokasi Gudang / Depo <span style="color:#ef4444;">*</span></label>
          <select name="kd_bangsal" id="opname_bangsal" class="form-control" required onchange="loadStokSistem()">
            <?php foreach ($bangsal_list as $b): ?>
              <?php
                $is_selected = '';
                if ($kd_bangsal_filter) {
                  if ($b['kd_bangsal'] === $kd_bangsal_filter) $is_selected = 'selected';
                } else {
                  if ($b['kd_bangsal'] === 'GD' || $b['kd_bangsal'] === 'GF') $is_selected = 'selected';
                }
              ?>
              <option value="<?= $b['kd_bangsal'] ?>" <?= $is_selected ?>><?= htmlspecialchars($b['nm_bangsal']) ?> (<?= $b['kd_bangsal'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Pilih Obat / Alkes <span style="color:#ef4444;">*</span></label>
        <select name="kode_brng" id="opname_kode_brng" class="form-control" required onchange="loadStokSistem()">
          <option value="">— Pilih Obat —</option>
          <?php foreach ($obat_options as $opt): ?>
            <option value="<?= $opt['kode_brng'] ?>" data-hb="<?= $opt['h_beli'] ?>">
              <?= htmlspecialchars($opt['nama_brng']) ?> (<?= $opt['kode_brng'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;">
        <div class="form-group">
          <label class="form-label" style="font-size:11.5px;color:#64748b;">Stok Sistem (Lokasi Ini)</label>
          <input type="number" name="stok_sistem" id="opname_stok_sistem" class="form-control" readonly style="background:#e2e8f0;font-weight:700;" value="0">
          <span id="opname_stok_info" style="font-size:10.5px;color:#64748b;display:block;margin-top:3px;"></span>
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11.5px;font-weight:700;color:#0f172a;">Stok Fisik Real <span style="color:#ef4444;">*</span></label>
          <input type="number" name="stok_real" id="opname_stok_real" class="form-control" min="0" required value="0" oninput="calcSelisih()">
        </div>

        <div class="form-group">
          <label class="form-label" style="font-size:11.5px;color:#64748b;">Selisih</label>
          <input type="number" id="opname_selisih" class="form-control" readonly style="background:#e2e8f0;font-weight:700;" value="0">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="font-size:12px;font-weight:600;color:#475569;">Keterangan / Alasan Penyesuaian</label>
        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Selisih penghitungan fisik rutin / rusak / expired">
      </div>

      <div style="padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeOpnameModal()">Batal</button>
        <button type="submit" name="simpan_opname" class="btn btn-primary">
          <i class="fas fa-save"></i> Simpan Stok Opname
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openOpnameModal() {
  document.getElementById('modalOpname').style.display = 'flex';
  loadStokSistem();
}

function closeOpnameModal() {
  document.getElementById('modalOpname').style.display = 'none';
}

function loadStokSistem() {
  const kode = document.getElementById('opname_kode_brng').value;
  const bangsalSel = document.getElementById('opname_bangsal');
  const bangsal = bangsalSel.value;
  const bangsalText = bangsalSel.options[bangsalSel.selectedIndex] ? bangsalSel.options[bangsalSel.selectedIndex].text : bangsal;
  const infoEl = document.getElementById('opname_stok_info');
  
  if (!kode) {
    document.getElementById('opname_stok_sistem').value = 0;
    if (infoEl) infoEl.innerText = '';
    calcSelisih();
    return;
  }

  fetch('<?= BASE_URL ?>modules/gudang_obat/ajax.php?action=get_stok&kode=' + encodeURIComponent(kode) + '&bangsal=' + encodeURIComponent(bangsal))
    .then(res => res.json())
    .then(data => {
      const stokLoc = data.stok || 0;
      const stokTot = data.total_stok || 0;
      document.getElementById('opname_stok_sistem').value = stokLoc;
      document.getElementById('opname_stok_real').value = stokLoc;
      if (infoEl) {
        infoEl.innerHTML = `<span style="color:#0284c7;font-weight:600;">Lokasi: ${stokLoc}</span> | <span style="color:#64748b;">Total Semua: ${stokTot}</span>`;
      }
      calcSelisih();
    })
    .catch(() => {
      document.getElementById('opname_stok_sistem').value = 0;
      if (infoEl) infoEl.innerText = '';
      calcSelisih();
    });
}

function calcSelisih() {
  const sistem = parseFloat(document.getElementById('opname_stok_sistem').value) || 0;
  const real   = parseFloat(document.getElementById('opname_stok_real').value) || 0;
  const diff   = real - sistem;
  const selEl  = document.getElementById('opname_selisih');
  selEl.value = diff;
  if (diff < 0) {
    selEl.style.color = '#dc2626';
  } else if (diff > 0) {
    selEl.style.color = '#059669';
  } else {
    selEl.style.color = '#0f172a';
  }
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
