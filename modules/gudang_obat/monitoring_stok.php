<?php
/**
 * SIMKlinik — Gudang Obat: Monitoring Stok (Stok Darurat & Expired)
 */

$page_title    = 'Monitoring Stok & Stok Darurat';
$active_module = 'gudang_obat';
$sub_active    = 'monitoring';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$tab_filter = sanitize($_GET['tab'] ?? 'kritis');
$kd_bangsal = sanitize($_GET['kd_bangsal'] ?? '');
$search     = sanitize($_GET['q'] ?? '');

$today = date('Y-m-d');
$exp_90 = date('Y-m-d', strtotime('+90 days'));
$exp_180 = date('Y-m-d', strtotime('+180 days'));

// ─── Query Bangsal / Lokasi Gudang ────────────────────────────
$bangsal_list = [];
$rb = $conn->query("SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status='1' ORDER BY nm_bangsal");
if ($rb) while ($row = $rb->fetch_assoc()) $bangsal_list[] = $row;

// ─── Query Monitoring Data ────────────────────────────────────
$where = "db.status = '1'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (db.kode_brng LIKE '%$s%' OR db.nama_brng LIKE '%$s%' OR db.letak_barang LIKE '%$s%')";
}

// Subquery per bangsal jika difilter
$join_gb = "LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng";
if ($kd_bangsal) {
    $join_gb .= " AND gb.kd_bangsal = '$kd_bangsal'";
}

$sub_query = "
    SELECT db.kode_brng, db.nama_brng, db.kode_sat, ks.satuan, db.letak_barang,
           db.h_beli, db.ralan, db.stokminimal, db.expire, kb.nama as nama_kategori,
           COALESCE(SUM(gb.stok), 0) as total_stok,
           GROUP_CONCAT(DISTINCT gb.no_batch SEPARATOR ', ') as batch_list
    FROM databarang db
    $join_gb
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
    WHERE $where
    GROUP BY db.kode_brng
";

$filter_outer = "1=1";
if ($tab_filter === 'habis') {
    $filter_outer .= " AND total_stok = 0";
} elseif ($tab_filter === 'menipis') {
    $filter_outer .= " AND total_stok > 0 AND total_stok <= stokminimal";
} elseif ($tab_filter === 'expired') {
    $filter_outer .= " AND expire <= '$today' AND expire != '0000-00-00'";
} elseif ($tab_filter === 'near_expired') {
    $filter_outer .= " AND expire > '$today' AND expire <= '$exp_90'";
} elseif ($tab_filter === 'kritis') {
    $filter_outer .= " AND (total_stok <= stokminimal OR (expire <= '$exp_90' AND expire != '0000-00-00'))";
}

$sql_final = "
    SELECT * FROM (
        $sub_query
    ) as t
    WHERE $filter_outer
    ORDER BY (total_stok <= stokminimal) DESC, total_stok ASC, expire ASC
";

$res = $conn->query($sql_final);
$items = [];
if ($res) while ($row = $res->fetch_assoc()) $items[] = $row;

// ─── Counters KPI ─────────────────────────────────────────────
$cnt_habis = (int)($conn->query("
    SELECT COUNT(*) as t FROM (
        SELECT db.kode_brng, COALESCE(SUM(gb.stok), 0) as s
        FROM databarang db LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
        WHERE db.status='1' GROUP BY db.kode_brng HAVING s = 0
    ) as sub
")->fetch_assoc()['t'] ?? 0);

$cnt_menipis = (int)($conn->query("
    SELECT COUNT(*) as t FROM (
        SELECT db.kode_brng, db.stokminimal, COALESCE(SUM(gb.stok), 0) as s
        FROM databarang db LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
        WHERE db.status='1' GROUP BY db.kode_brng HAVING s > 0 AND s <= db.stokminimal
    ) as sub
")->fetch_assoc()['t'] ?? 0);

$cnt_expired = (int)($conn->query("
    SELECT COUNT(*) as t FROM databarang WHERE status='1' AND expire <= '$today' AND expire != '0000-00-00'
")->fetch_assoc()['t'] ?? 0);

$cnt_near_exp = (int)($conn->query("
    SELECT COUNT(*) as t FROM databarang WHERE status='1' AND expire > '$today' AND expire <= '$exp_90'
")->fetch_assoc()['t'] ?? 0);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Sub-Navigation Gudang Obat ────────────────────────── -->
<?php include __DIR__ . '/header_nav.php'; ?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Monitoring Stok Darurat & Expired</h1>
    <p class="page-subtitle">Sistem peringatan dini persediaan obat kritis, stok menipis, dan masa kadaluarsa</p>
  </div>
  <div class="page-actions">
    <a href="<?= BASE_URL ?>modules/gudang_obat/pemesanan.php" class="btn btn-primary">
      <i class="fas fa-cart-plus"></i> Buat Order Pemesanan
    </a>
  </div>
</div>

<!-- ─── KPI Alert Cards ──────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:18px;">
  
  <a href="?tab=habis&kd_bangsal=<?= urlencode($kd_bangsal) ?>" style="text-decoration:none;">
    <div class="card" style="border-left:4px solid #ef4444;transition:transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
        <div style="width:42px;height:42px;background:#fef2f2;color:#dc2626;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-circle-xmark"></i>
        </div>
        <div>
          <div style="font-size:22px;font-weight:800;color:#dc2626;"><?= $cnt_habis ?></div>
          <div style="font-size:12px;color:#64748b;font-weight:600;">Stok Habis (Kosong)</div>
        </div>
      </div>
    </div>
  </a>

  <a href="?tab=menipis&kd_bangsal=<?= urlencode($kd_bangsal) ?>" style="text-decoration:none;">
    <div class="card" style="border-left:4px solid #f59e0b;transition:transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
        <div style="width:42px;height:42px;background:#fffbeb;color:#d97706;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-triangle-exclamation"></i>
        </div>
        <div>
          <div style="font-size:22px;font-weight:800;color:#d97706;"><?= $cnt_menipis ?></div>
          <div style="font-size:12px;color:#64748b;font-weight:600;">Stok Darurat / Menipis</div>
        </div>
      </div>
    </div>
  </a>

  <a href="?tab=near_expired&kd_bangsal=<?= urlencode($kd_bangsal) ?>" style="text-decoration:none;">
    <div class="card" style="border-left:4px solid #8b5cf6;transition:transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
        <div style="width:42px;height:42px;background:#f5f3ff;color:#7c3aed;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-hourglass-half"></i>
        </div>
        <div>
          <div style="font-size:22px;font-weight:800;color:#7c3aed;"><?= $cnt_near_exp ?></div>
          <div style="font-size:12px;color:#64748b;font-weight:600;">Mendekati Exp (&le; 90 Hari)</div>
        </div>
      </div>
    </div>
  </a>

  <a href="?tab=expired&kd_bangsal=<?= urlencode($kd_bangsal) ?>" style="text-decoration:none;">
    <div class="card" style="border-left:4px solid #b91c1c;transition:transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
        <div style="width:42px;height:42px;background:#fef2f2;color:#991b1b;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-skull-crossbones"></i>
        </div>
        <div>
          <div style="font-size:22px;font-weight:800;color:#991b1b;"><?= $cnt_expired ?></div>
          <div style="font-size:12px;color:#64748b;font-weight:600;">Sudah Kadaluarsa</div>
        </div>
      </div>
    </div>
  </a>

</div>

<!-- ─── Filter Bar & Category Tabs ───────────────────────── -->
<div class="card mb-16">
  <div class="card-body" style="padding:12px 20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      
      <!-- Tab Buttons -->
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <a href="?tab=kritis&kd_bangsal=<?= urlencode($kd_bangsal) ?>&q=<?= urlencode($search) ?>" 
           class="btn btn-sm <?= $tab_filter==='kritis'?'btn-primary':'btn-secondary' ?>">
          <i class="fas fa-fire"></i> Semua Darurat / Kritis
        </a>
        <a href="?tab=habis&kd_bangsal=<?= urlencode($kd_bangsal) ?>&q=<?= urlencode($search) ?>" 
           class="btn btn-sm <?= $tab_filter==='habis'?'btn-danger':'btn-secondary' ?>">
          Stok Habis (<?= $cnt_habis ?>)
        </a>
        <a href="?tab=menipis&kd_bangsal=<?= urlencode($kd_bangsal) ?>&q=<?= urlencode($search) ?>" 
           class="btn btn-sm <?= $tab_filter==='menipis'?'btn-warning':'btn-secondary' ?>">
          Stok Menipis (<?= $cnt_menipis ?>)
        </a>
        <a href="?tab=near_expired&kd_bangsal=<?= urlencode($kd_bangsal) ?>&q=<?= urlencode($search) ?>" 
           class="btn btn-sm <?= $tab_filter==='near_expired'?'btn-primary':'btn-secondary' ?>" style="<?= $tab_filter==='near_expired'?'background:#7c3aed;border-color:#7c3aed;':'' ?>">
          Mendekati Kadaluarsa (<?= $cnt_near_exp ?>)
        </a>
        <a href="?tab=expired&kd_bangsal=<?= urlencode($kd_bangsal) ?>&q=<?= urlencode($search) ?>" 
           class="btn btn-sm <?= $tab_filter==='expired'?'btn-danger':'btn-secondary' ?>">
          Expired (<?= $cnt_expired ?>)
        </a>
        <a href="?tab=semua&kd_bangsal=<?= urlencode($kd_bangsal) ?>&q=<?= urlencode($search) ?>" 
           class="btn btn-sm <?= $tab_filter==='semua'?'btn-primary':'btn-secondary' ?>">
          Semua Item
        </a>
      </div>

      <!-- Filter Form -->
      <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab_filter) ?>">
        <div style="width:180px;">
          <select name="kd_bangsal" class="form-control" onchange="this.form.submit()">
            <option value="">— Semua Lokasi Gudang —</option>
            <?php foreach ($bangsal_list as $b): ?>
              <option value="<?= $b['kd_bangsal'] ?>" <?= $kd_bangsal===$b['kd_bangsal']?'selected':'' ?>>
                <?= htmlspecialchars($b['nm_bangsal']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <input type="text" name="q" class="form-control" style="width:180px;" placeholder="Cari obat..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
      </form>

    </div>
  </div>
</div>

<!-- ─── Table Monitoring ─────────────────────────────────── -->
<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <th style="width:100px;">Kode</th>
            <th>Nama Obat & Sediaan</th>
            <th>Kategori</th>
            <th style="text-align:center;">Stok Min</th>
            <th style="text-align:center;">Stok Saat Ini</th>
            <th style="text-align:center;">Status Stok</th>
            <th style="text-align:center;">Kadaluarsa</th>
            <th style="text-align:center;">Status Expired</th>
            <th style="text-align:right;">Estimasi HPP</th>
            <th style="text-align:center;width:150px;">Aksi Cepat</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr>
              <td colspan="10" style="text-align:center;padding:40px;color:#94a3b8;">
                <i class="fas fa-shield-check" style="font-size:32px;color:#10b981;margin-bottom:10px;display:block;"></i>
                Semua kondisi stok terpantau aman dan tidak ada item dalam status ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($items as $it): ?>
              <?php
                $stok_val = (float)$it['total_stok'];
                $min_val  = (float)$it['stokminimal'];
                
                // Status Stok
                if ($stok_val == 0) {
                    $stok_badge = '<span class="badge badge-danger" style="font-weight:700;"><i class="fas fa-times-circle"></i> Stok Habis</span>';
                } elseif ($stok_val <= $min_val) {
                    $stok_badge = '<span class="badge badge-warning" style="font-weight:700;"><i class="fas fa-triangle-exclamation"></i> Menipis</span>';
                } else {
                    $stok_badge = '<span class="badge badge-success">Aman</span>';
                }

                // Status Expired
                $exp_date = $it['expire'];
                if (empty($exp_date) || $exp_date === '0000-00-00') {
                    $exp_badge = '<span style="color:#94a3b8;">-</span>';
                    $exp_txt   = '-';
                } else {
                    $exp_txt = date('d/m/Y', strtotime($exp_date));
                    $diff_days = (int)((strtotime($exp_date) - time()) / 86400);
                    if ($diff_days < 0) {
                        $exp_badge = '<span class="badge badge-danger" style="font-weight:700;"><i class="fas fa-skull"></i> Expired</span>';
                    } elseif ($diff_days <= 90) {
                        $exp_badge = '<span class="badge badge-warning" style="font-weight:700;">&le; ' . $diff_days . ' hari</span>';
                    } elseif ($diff_days <= 180) {
                        $exp_badge = '<span class="badge badge-info">' . $diff_days . ' hari</span>';
                    } else {
                        $exp_badge = '<span class="badge badge-light" style="color:#059669;">' . $diff_days . ' hari</span>';
                    }
                }
              ?>
              <tr style="<?= $stok_val == 0 ? 'background:#fffafa;' : '' ?>">
                <td>
                  <code style="font-weight:700;color:#0f172a;background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11.5px;">
                    <?= htmlspecialchars($it['kode_brng']) ?>
                  </code>
                </td>
                <td>
                  <div style="font-weight:700;font-size:13px;color:#0f172a;">
                    <?= htmlspecialchars($it['nama_brng']) ?>
                  </div>
                  <div style="font-size:11px;color:#64748b;">
                    Satuan: <?= htmlspecialchars($it['satuan'] ?: $it['kode_sat']) ?>
                    <?php if (!empty($it['letak_barang']) && $it['letak_barang'] !== '-'): ?>
                      | Rak: <?= htmlspecialchars($it['letak_barang']) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td><span class="badge badge-light" style="font-size:11px;"><?= htmlspecialchars($it['nama_kategori'] ?: '-') ?></span></td>
                <td style="text-align:center;font-size:12px;color:#64748b;"><?= (int)$min_val ?></td>
                <td style="text-align:center;">
                  <span style="font-size:14px;font-weight:800;color:<?= $stok_val==0?'#dc2626':($stok_val<=$min_val?'#d97706':'#059669') ?>;">
                    <?= (int)$stok_val ?>
                  </span>
                </td>
                <td style="text-align:center;"><?= $stok_badge ?></td>
                <td style="text-align:center;font-size:12px;font-weight:600;"><?= $exp_txt ?></td>
                <td style="text-align:center;"><?= $exp_badge ?></td>
                <td style="text-align:right;font-family:monospace;font-size:12px;color:#475569;">
                  <?= rupiah((float)$it['h_beli']) ?>
                </td>
                <td style="text-align:center;">
                  <div style="display:inline-flex;gap:5px;">
                    <!-- Tombol Reorder ke Pemesanan -->
                    <a href="<?= BASE_URL ?>modules/gudang_obat/pemesanan.php?action=order&kode=<?= urlencode($it['kode_brng']) ?>"
                       class="btn btn-sm btn-primary"
                       title="Buat Order Pemesanan ke Supplier"
                       style="padding:4px 8px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;">
                      <i class="fas fa-cart-plus"></i> Order
                    </a>

                    <!-- Tombol Mutasi Cepat -->
                    <a href="<?= BASE_URL ?>modules/gudang_obat/mutasi.php?kode=<?= urlencode($it['kode_brng']) ?>"
                       class="btn btn-sm btn-secondary"
                       title="Mutasi Antar Unit"
                       style="padding:4px 8px;font-size:11.5px;">
                      <i class="fas fa-arrows-split-up-and-left"></i>
                    </a>
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

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
