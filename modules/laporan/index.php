<?php
/**
 * SIMKlinik — Laporan & Statistik
 */

$page_title    = 'Laporan & Statistik';
$active_module = 'laporan';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('laporan');

// ─── Filter Periode & Paginasi ────────────────────────────────
$tgl_mulai  = sanitize($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_akhir  = sanitize($_GET['tgl_akhir'] ?? date('Y-m-d'));
$tab        = sanitize($_GET['tab'] ?? 'kunjungan');
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 25;
$offset     = ($page - 1) * $per_page;

// 1. Data Laporan Kunjungan (dengan Paginasi)
$count_kunjungan_sql = "
    SELECT COUNT(*) as total
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    WHERE r.tgl_registrasi BETWEEN '$tgl_mulai' AND '$tgl_akhir'
";
$count_kunj_res = $conn->query($count_kunjungan_sql);
$total_kunjungan = $count_kunj_res ? (int)$count_kunj_res->fetch_assoc()['total'] : 0;
$total_pages_kunj = max(1, (int)ceil($total_kunjungan / $per_page));

$pag_kunjungan = [
    'page'        => $page,
    'per_page'    => $per_page,
    'total'       => $total_kunjungan,
    'total_pages' => $total_pages_kunj,
    'offset'      => $offset,
    'has_prev'    => $page > 1,
    'has_next'    => $page < $total_pages_kunj,
];

$kunjungan_sql = "
    SELECT r.tgl_registrasi, r.no_rawat, r.no_rkm_medis, p.nm_pasien, p.jk,
           pol.nm_poli, d.nm_dokter, pj.png_jawab as nm_penjab, r.stts, r.status_bayar
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE r.tgl_registrasi BETWEEN '$tgl_mulai' AND '$tgl_akhir'
    ORDER BY r.tgl_registrasi DESC, r.jam_reg ASC
    LIMIT $offset, $per_page
";
$kunjungan_res = $conn->query($kunjungan_sql);
$kunjungan_data = [];
if ($kunjungan_res) while ($row = $kunjungan_res->fetch_assoc()) $kunjungan_data[] = $row;

// 2. Data Laporan 10 Besar Penyakit (ICD-10 - Hanya diagnosa valid)
$penyakit_sql = "
    SELECT p.kd_penyakit, p.nm_penyakit, COUNT(*) as jumlah,
           SUM(CASE WHEN ps.jk = 'L' THEN 1 ELSE 0 END) as jml_pria,
           SUM(CASE WHEN ps.jk = 'P' THEN 1 ELSE 0 END) as jml_wanita
    FROM diagnosa_pasien dp
    JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
    JOIN reg_periksa r ON dp.no_rawat = r.no_rawat
    JOIN pasien ps ON r.no_rkm_medis = ps.no_rkm_medis
    WHERE r.tgl_registrasi BETWEEN '$tgl_mulai' AND '$tgl_akhir'
      AND dp.kd_penyakit IS NOT NULL 
      AND dp.kd_penyakit != '' 
      AND dp.kd_penyakit != '-'
      AND TRIM(dp.kd_penyakit) != ''
      AND p.kd_penyakit IS NOT NULL 
      AND p.kd_penyakit != '' 
      AND p.kd_penyakit != '-'
      AND TRIM(p.kd_penyakit) != ''
      AND p.nm_penyakit IS NOT NULL
      AND TRIM(p.nm_penyakit) != ''
      AND TRIM(p.nm_penyakit) != '-'
    GROUP BY dp.kd_penyakit
    ORDER BY jumlah DESC
    LIMIT 10
";
$penyakit_res = $conn->query($penyakit_sql);
$penyakit_data = [];
if ($penyakit_res) while ($row = $penyakit_res->fetch_assoc()) $penyakit_data[] = $row;

// 3. Data Laporan Pendapatan Kasir (dengan Paginasi)
$count_pendapatan_sql = "
    SELECT COUNT(DISTINCT r.tgl_registrasi) as total
    FROM reg_periksa r
    WHERE r.tgl_registrasi BETWEEN '$tgl_mulai' AND '$tgl_akhir'
";
$count_pend_res = $conn->query($count_pendapatan_sql);
$total_pend_days = $count_pend_res ? (int)$count_pend_res->fetch_assoc()['total'] : 0;
$total_pages_pend = max(1, (int)ceil($total_pend_days / $per_page));

$pag_pendapatan = [
    'page'        => $page,
    'per_page'    => $per_page,
    'total'       => $total_pend_days,
    'total_pages' => $total_pages_pend,
    'offset'      => $offset,
    'has_prev'    => $page > 1,
    'has_next'    => $page < $total_pages_pend,
];

// Kalkulasi Grand Omset Keseluruhan Periode
$omset_sum_sql = "
    SELECT 
        SUM(COALESCE(r.biaya_reg, 0)) as total_reg,
        SUM(COALESCE((SELECT SUM(dpo.total) FROM detail_pemberian_obat dpo WHERE dpo.no_rawat = r.no_rawat), 0)) as total_obat
    FROM reg_periksa r
    WHERE r.tgl_registrasi BETWEEN '$tgl_mulai' AND '$tgl_akhir'
";
$omset_sum_res = $conn->query($omset_sum_sql);
$grand_omset = 0;
if ($omset_sum_res && ($row = $omset_sum_res->fetch_assoc())) {
    $grand_omset = (float)($row['total_reg'] ?? 0) + (float)($row['total_obat'] ?? 0);
}

// Data pendapatan per hari dengan limit paginasi
$pendapatan_sql = "
    SELECT r.tgl_registrasi,
           COUNT(*) as total_pasien,
           SUM(CASE WHEN r.status_bayar = 'Sudah Bayar' THEN 1 ELSE 0 END) as total_lunas,
           SUM(COALESCE(r.biaya_reg, 0)) as total_reg,
           SUM(COALESCE((SELECT SUM(dpo.total) FROM detail_pemberian_obat dpo WHERE dpo.no_rawat = r.no_rawat), 0)) as total_obat
    FROM reg_periksa r
    WHERE r.tgl_registrasi BETWEEN '$tgl_mulai' AND '$tgl_akhir'
    GROUP BY r.tgl_registrasi
    ORDER BY r.tgl_registrasi DESC
    LIMIT $offset, $per_page
";
$pendapatan_res = $conn->query($pendapatan_sql);
$pendapatan_data = [];
if ($pendapatan_res) {
    while ($row = $pendapatan_res->fetch_assoc()) {
        $row['total_omset'] = (float)$row['total_reg'] + (float)$row['total_obat'];
        $pendapatan_data[] = $row;
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Laporan & Statistik Klinik</h1>
    <p class="page-subtitle">Rekapitulasi Kunjungan, Morbiditas Penyakit (10 Besar), dan Arus Kas Pendapatan</p>
  </div>
  <div class="page-actions">
    <button onclick="window.print()" class="btn btn-outline">
      <i class="fas fa-print"></i> Cetak Laporan
    </button>
  </div>
</div>

<!-- ─── Filter Periode ───────────────────────────────────── -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px;">
    <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:var(--gray-500);">Mulai:</label>
        <input type="date" name="tgl_mulai" class="form-control" style="width:150px;" value="<?= $tgl_mulai ?>">
      </div>

      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:var(--gray-500);">Sampai:</label>
        <input type="date" name="tgl_akhir" class="form-control" style="width:150px;" value="<?= $tgl_akhir ?>" max="<?= date('Y-m-d') ?>">
      </div>

      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> Tampilkan Data</button>
    </form>
  </div>
</div>

<!-- ─── Tab Navigation ───────────────────────────────────── -->
<div style="display:flex;gap:8px;margin-bottom:16px;border-bottom:1px solid var(--gray-200);padding-bottom:8px;">
  <a href="?tab=kunjungan&tgl_mulai=<?= $tgl_mulai ?>&tgl_akhir=<?= $tgl_akhir ?>"
     class="btn <?= $tab==='kunjungan' ? 'btn-primary':'btn-outline' ?> btn-sm">
    <i class="fas fa-users"></i> Laporan Kunjungan (<?= number_format($total_kunjungan) ?>)
  </a>
  <a href="?tab=penyakit&tgl_mulai=<?= $tgl_mulai ?>&tgl_akhir=<?= $tgl_akhir ?>"
     class="btn <?= $tab==='penyakit' ? 'btn-primary':'btn-outline' ?> btn-sm">
    <i class="fas fa-virus"></i> 10 Besar Penyakit
  </a>
  <a href="?tab=pendapatan&tgl_mulai=<?= $tgl_mulai ?>&tgl_akhir=<?= $tgl_akhir ?>"
     class="btn <?= $tab==='pendapatan' ? 'btn-primary':'btn-outline' ?> btn-sm">
    <i class="fas fa-chart-line"></i> Laporan Pendapatan (<?= rupiah($grand_omset) ?>)
  </a>
</div>

<!-- ─── Tab Content ──────────────────────────────────────── -->
<?php if ($tab === 'kunjungan'): ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-clipboard-list"></i> Rekapitulasi Kunjungan Pasien</div>
      <span style="font-size:12px;color:var(--gray-500);">Periode: <?= tgl_indo($tgl_mulai) ?> s/d <?= tgl_indo($tgl_akhir) ?></span>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($kunjungan_data)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
          <div class="empty-state-title">Tidak ada data kunjungan pada periode ini</div>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>No. Rawat</th>
                <th>Pasien</th>
                <th>Poli / Dokter</th>
                <th>Penjamin</th>
                <th>Status Kunjungan</th>
                <th>Status Bayar</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($kunjungan_data as $i => $k): ?>
                <tr>
                  <td style="color:var(--gray-400);font-size:12px;"><?= $offset + $i + 1 ?></td>
                  <td style="font-size:12px;"><?= tgl_indo($k['tgl_registrasi']) ?></td>
                  <td><span style="font-family:monospace;font-size:11px;"><?= $k['no_rawat'] ?></span></td>
                  <td>
                    <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($k['nm_pasien']) ?></div>
                    <div style="font-size:11px;color:var(--gray-400);">RM: <?= $k['no_rkm_medis'] ?> | <?= icon_jk($k['jk']) ?></div>
                  </td>
                  <td>
                    <div style="font-size:12px;"><?= htmlspecialchars($k['nm_poli']) ?></div>
                    <div style="font-size:11px;color:var(--gray-500);"><?= htmlspecialchars($k['nm_dokter']) ?></div>
                  </td>
                  <td>
                    <span class="badge badge-<?= str_contains(strtolower($k['nm_penjab']??''), 'bpjs') ? 'primary':'secondary' ?>">
                      <?= htmlspecialchars($k['nm_penjab'] ?: 'Umum') ?>
                    </span>
                  </td>
                  <td><?= badge_status($k['stts']) ?></td>
                  <td>
                    <span class="badge <?= $k['status_bayar']==='Sudah Bayar' ? 'badge-success':'badge-danger' ?>">
                      <?= htmlspecialchars($k['status_bayar']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?= render_pagination($pag_kunjungan) ?>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'penyakit'): ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-stethoscope"></i> 10 Besar Morbiditas Penyakit (ICD-10)</div>
      <span style="font-size:12px;color:var(--gray-500);">Periode: <?= tgl_indo($tgl_mulai) ?> s/d <?= tgl_indo($tgl_akhir) ?></span>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($penyakit_data)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="fas fa-virus"></i></div>
          <div class="empty-state-title">Belum ada diagnosa tercatat pada periode ini</div>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th style="width:50px;">Peringkat</th>
                <th style="width:120px;">Kode ICD-10</th>
                <th>Nama Diagnosa Penyakit</th>
                <th style="width:100px;text-align:center;">Laki-laki</th>
                <th style="width:100px;text-align:center;">Perempuan</th>
                <th style="width:120px;text-align:center;">Total Kasus</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($penyakit_data as $i => $p): ?>
                <tr>
                  <td style="text-align:center;font-weight:700;font-size:14px;color:var(--primary-600);">
                    #<?= $i + 1 ?>
                  </td>
                  <td>
                    <span style="font-family:monospace;font-weight:700;font-size:13px;color:var(--gray-800);">
                      <?= htmlspecialchars($p['kd_penyakit']) ?>
                    </span>
                  </td>
                  <td style="font-weight:500;font-size:13px;"><?= htmlspecialchars($p['nm_penyakit']) ?></td>
                  <td style="text-align:center;font-size:12px;"><?= $p['jml_pria'] ?></td>
                  <td style="text-align:center;font-size:12px;"><?= $p['jml_wanita'] ?></td>
                  <td style="text-align:center;">
                    <span class="badge badge-primary" style="font-size:13px;padding:4px 10px;">
                      <?= $p['jumlah'] ?> Kasus
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'pendapatan'): ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-coins"></i> Laporan Penerimaan Pendapatan Kasir</div>
      <span style="font-size:12px;color:var(--gray-500);">Total Omset: <strong><?= rupiah($grand_omset) ?></strong></span>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($pendapatan_data)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="fas fa-cash-register"></i></div>
          <div class="empty-state-title">Belum ada transaksi pendapatan pada periode ini</div>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Tanggal Transaksi</th>
                <th style="text-align:center;">Jumlah Pasien</th>
                <th style="text-align:center;">Pasien Lunas</th>
                <th style="text-align:right;">Pendapatan Registrasi / Poli</th>
                <th style="text-align:right;">Pendapatan Farmasi</th>
                <th style="text-align:right;">Total Omset Harian</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendapatan_data as $pd): ?>
                <tr>
                  <td style="font-weight:600;"><?= tgl_indo($pd['tgl_registrasi']) ?></td>
                  <td style="text-align:center;"><?= $pd['total_pasien'] ?></td>
                  <td style="text-align:center;"><span class="badge badge-success"><?= $pd['total_lunas'] ?></span></td>
                  <td style="text-align:right;"><?= rupiah((float)$pd['total_reg']) ?></td>
                  <td style="text-align:right;"><?= rupiah((float)$pd['total_obat']) ?></td>
                  <td style="text-align:right;font-weight:700;color:var(--primary-700);font-size:13px;">
                    <?= rupiah((float)$pd['total_omset']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="background:var(--gray-50);font-weight:700;font-size:14px;">
                <td colspan="5" style="text-align:right;padding:14px;">GRAND TOTAL PENDAPATAN:</td>
                <td style="text-align:right;color:var(--primary-700);font-size:16px;padding:14px;">
                  <?= rupiah($grand_omset) ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?= render_pagination($pag_pendapatan) ?>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
