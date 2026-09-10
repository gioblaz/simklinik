<?php
/**
 * SIMKlinik — Kasir & Billing (Antrian Pembayaran Pasien)
 */

$page_title    = 'Kasir & Billing';
$active_module = 'kasir';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ─── Filter ──────────────────────────────────────────────────
$today  = date('Y-m-d');
$tgl    = sanitize($_GET['tgl'] ?? $today);
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['q'] ?? '');

$where = "r.tgl_registrasi = '$tgl'";
if ($status) $where .= " AND r.status_bayar = '" . $conn->real_escape_string($status) . "'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (p.nm_pasien LIKE '%$s%' OR p.no_rkm_medis LIKE '%$s%' OR r.no_rawat LIKE '%$s%')";
}

// Paginasi Kasir & Billing
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset   = ($page - 1) * $per_page;

$count_res = $conn->query("
    SELECT COUNT(DISTINCT r.no_rawat) as total
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    WHERE $where
");
$total_rows  = $count_res ? (int)$count_res->fetch_assoc()['total'] : 0;
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$pag = [
    'page'        => $page,
    'per_page'    => $per_page,
    'total'       => $total_rows,
    'total_pages' => $total_pages,
    'offset'      => $offset,
    'has_prev'    => $page > 1,
    'has_next'    => $page < $total_pages,
];

// Data kunjungan & kalkulasi tagihan
$result = $conn->query("
    SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg,
           r.status_bayar, r.stts, r.biaya_reg,
           p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir,
           d.nm_dokter, pol.nm_poli, pol.registrasi as tarif_poli,
           pj.png_jawab as nm_penjab,
           -- Total biaya obat farmasi
           COALESCE((SELECT SUM(dpo.total) FROM detail_pemberian_obat dpo WHERE dpo.no_rawat = r.no_rawat),
                    (SELECT SUM(rd.jml * db.ralan) FROM resep_obat ro JOIN resep_dokter rd ON ro.no_resep = rd.no_resep JOIN databarang db ON rd.kode_brng = db.kode_brng WHERE ro.no_rawat = r.no_rawat),
                    0) as total_obat,
           -- Total biaya lab
           COALESCE((SELECT SUM(biaya) FROM periksa_lab pl WHERE pl.no_rawat = r.no_rawat), 0) as total_lab
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE $where
    GROUP BY r.no_rawat
    ORDER BY r.status_bayar = 'Belum Bayar' DESC, r.jam_reg DESC
    LIMIT $per_page OFFSET $offset
");

$billing_list = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $biaya_reg   = (float)($row['biaya_reg'] > 0 ? $row['biaya_reg'] : ($row['tarif_poli'] ?? 0));
        $total_obat  = (float)$row['total_obat'];
        $total_lab   = (float)$row['total_lab'];
        $grand_total = $biaya_reg + $total_obat + $total_lab;

        $row['grand_total'] = $grand_total;
        $billing_list[]     = $row;
    }
}

// Counters
$c_belum = ($conn->query("SELECT COUNT(*) as t FROM reg_periksa WHERE tgl_registrasi = '$tgl' AND status_bayar = 'Belum Bayar'")->fetch_assoc()['t'] ?? 0);
$c_sudah = ($conn->query("SELECT COUNT(*) as t FROM reg_periksa WHERE tgl_registrasi = '$tgl' AND status_bayar = 'Sudah Bayar'")->fetch_assoc()['t'] ?? 0);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Kasir & Billing Pembayaran</h1>
    <p class="page-subtitle">Pelunasan Tagihan Pelayanan Pasien, Rincian Nota, dan Kwitansi Resmi</p>
  </div>
</div>

<!-- ─── Counter Cards ────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px;margin-bottom:16px;">
  <div class="card" style="border-left:4px solid var(--warning);">
    <div class="card-body" style="padding:16px 20px;display:flex;align-items:center;gap:16px;">
      <div style="width:44px;height:44px;background:var(--warning-bg);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--warning);font-size:20px;">
        <i class="fas fa-file-invoice-dollar"></i>
      </div>
      <div>
        <div style="font-size:24px;font-weight:700;color:var(--gray-900);"><?= $c_belum ?></div>
        <div style="font-size:12px;color:var(--gray-500);">Tagihan Belum Lunas</div>
      </div>
    </div>
  </div>

  <div class="card" style="border-left:4px solid var(--success);">
    <div class="card-body" style="padding:16px 20px;display:flex;align-items:center;gap:16px;">
      <div style="width:44px;height:44px;background:var(--success-bg);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--success);font-size:20px;">
        <i class="fas fa-check-circle"></i>
      </div>
      <div>
        <div style="font-size:24px;font-weight:700;color:var(--gray-900);"><?= $c_sudah ?></div>
        <div style="font-size:12px;color:var(--gray-500);">Tagihan Sudah Lunas</div>
      </div>
    </div>
  </div>

  <div class="card" style="border-left:4px solid var(--primary-600);">
    <div class="card-body" style="padding:16px 20px;display:flex;align-items:center;gap:16px;">
      <div style="width:44px;height:44px;background:var(--primary-50);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--primary-600);font-size:20px;">
        <i class="fas fa-cash-register"></i>
      </div>
      <div>
        <div style="font-size:24px;font-weight:700;color:var(--gray-900);"><?= $c_belum + $c_sudah ?></div>
        <div style="font-size:12px;color:var(--gray-500);">Total Pasien Hari Ini</div>
      </div>
    </div>
  </div>
</div>

<!-- ─── Filter Bar ───────────────────────────────────────── -->
<div class="card mb-16">
  <div class="card-body" style="padding:12px 20px;">
    <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <div class="search-bar" style="flex:1;max-width:320px;">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Cari Pasien / No. RM..."
               value="<?= htmlspecialchars($search) ?>" autocomplete="off">
      </div>

      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:var(--gray-500);">Tanggal:</label>
        <input type="date" name="tgl" class="form-control" style="width:150px;"
               value="<?= $tgl ?>" max="<?= date('Y-m-d') ?>">
      </div>

      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:var(--gray-500);">Status Bayar:</label>
        <select name="status" class="form-control" style="width:160px;">
          <option value="">— Semua Status —</option>
          <option value="Belum Bayar" <?= $status==='Belum Bayar' ? 'selected':'' ?>>Belum Bayar</option>
          <option value="Sudah Bayar" <?= $status==='Sudah Bayar' ? 'selected':'' ?>>Sudah Bayar</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
      <?php if ($search || $tgl !== $today || $status): ?>
        <a href="<?= BASE_URL ?>modules/kasir/index.php" class="btn btn-outline btn-sm"><i class="fas fa-redo"></i> Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Table Tagihan Pasien ─────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-receipt"></i> Daftar Tagihan Pasien</div>
    <span style="font-size:12px;color:var(--gray-500);">Tanggal: <?= tgl_indo($tgl) ?></span>
  </div>

  <div class="card-body" style="padding:0;">
    <?php if (empty($billing_list)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-receipt"></i></div>
        <div class="empty-state-title">Tidak ada transaksi tagihan</div>
        <div class="empty-state-desc">Pilih tanggal lain untuk melihat riwayat billing.</div>
      </div>
    <?php else: ?>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>No. Rawat</th>
              <th>Pasien</th>
              <th>Poli / Dokter</th>
              <th>Penjamin</th>
              <th>Obat & Farmasi</th>
              <th>Total Tagihan</th>
              <th>Status Bayar</th>
              <th style="width:140px;text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($billing_list as $b): ?>
              <?php $is_lunas = $b['status_bayar'] === 'Sudah Bayar'; ?>
              <tr>
                <td>
                  <span style="font-family:monospace;font-weight:700;color:var(--gray-600);font-size:11px;">
                    <?= htmlspecialchars($b['no_rawat']) ?>
                  </span>
                  <div style="font-size:10px;color:var(--gray-400);">Jam: <?= substr($b['jam_reg'], 0, 5) ?></div>
                </td>
                <td>
                  <div style="font-size:13px;font-weight:600;color:var(--gray-900);">
                    <?= htmlspecialchars($b['nm_pasien']) ?>
                  </div>
                  <div style="font-size:11px;color:var(--gray-400);">
                    RM: <?= $b['no_rkm_medis'] ?> &nbsp;|&nbsp; <?= icon_jk($b['jk']) ?> <?= hitung_umur($b['tgl_lahir']) ?>
                  </div>
                </td>
                <td>
                  <div style="font-size:12px;font-weight:500;"><?= htmlspecialchars($b['nm_poli']) ?></div>
                  <div style="font-size:11px;color:var(--gray-500);"><?= htmlspecialchars($b['nm_dokter']) ?></div>
                </td>
                <td>
                  <span class="badge badge-<?= str_contains(strtolower($b['nm_penjab']??''), 'bpjs') ? 'primary' : 'secondary' ?>">
                    <?= htmlspecialchars($b['nm_penjab'] ?: 'Umum') ?>
                  </span>
                </td>
                <td style="font-size:12px;color:var(--gray-700);">
                  <?= rupiah($b['total_obat']) ?>
                </td>
                <td>
                  <strong style="font-size:14px;color:var(--primary-700);">
                    <?= rupiah($b['grand_total']) ?>
                  </strong>
                </td>
                <td>
                  <span class="badge <?= $is_lunas ? 'badge-success' : 'badge-danger' ?>" style="font-size:11px;padding:3px 8px;">
                    <i class="fas <?= $is_lunas ? 'fa-check' : 'fa-clock' ?>" style="margin-right:3px;"></i>
                    <?= htmlspecialchars($b['status_bayar']) ?>
                  </span>
                </td>
                <td style="text-align:center;">
                  <a href="<?= BASE_URL ?>modules/kasir/bayar.php?no_rawat=<?= urlencode($b['no_rawat']) ?>"
                     class="btn btn-sm <?= $is_lunas ? 'btn-outline-primary' : 'btn-success' ?>">
                    <i class="fas <?= $is_lunas ? 'fa-print' : 'fa-cash-register' ?>"></i>
                    <?= $is_lunas ? 'Kwitansi' : 'Bayar' ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= render_pagination($pag) ?>
    <?php endif; ?>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
