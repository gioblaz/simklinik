<?php
/**
 * SIMKlinik — Daftar Permintaan & Antrean Pemeriksaan Laboratorium
 */

$page_title    = 'Pelayanan Laboratorium';
$active_module = 'laboratorium';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Filter & Parameter
$today       = date('Y-m-d');
$tgl_awal    = sanitize($_GET['tgl_awal'] ?? date('Y-m-01'));
$tgl_akhir   = sanitize($_GET['tgl_akhir'] ?? $today);
$status_req  = sanitize($_GET['status_req'] ?? 'semua');
$keyword     = sanitize($_GET['keyword'] ?? '');

$tgl_awal_esc  = $conn->real_escape_string($tgl_awal);
$tgl_akhir_esc = $conn->real_escape_string($tgl_akhir);
$kw_esc        = $conn->real_escape_string($keyword);

// Base WHERE
$where = "pl.tgl_permintaan BETWEEN '$tgl_awal_esc' AND '$tgl_akhir_esc'";

if (!empty($keyword)) {
    $where .= " AND (pl.noorder LIKE '%$kw_esc%' OR pl.no_rawat LIKE '%$kw_esc%' OR p.no_rkm_medis LIKE '%$kw_esc%' OR p.nm_pasien LIKE '%$kw_esc%' OR d.nm_dokter LIKE '%$kw_esc%')";
}

if ($status_req === 'menunggu_sampel') {
    $where .= " AND (pl.tgl_sampel = '0000-00-00' OR pl.jam_sampel = '00:00:00' OR pl.tgl_sampel IS NULL)";
} elseif ($status_req === 'menunggu_hasil') {
    $where .= " AND (pl.tgl_sampel != '0000-00-00' AND pl.tgl_sampel IS NOT NULL) AND (pl.tgl_hasil = '0000-00-00' OR pl.jam_hasil = '00:00:00' OR pl.tgl_hasil IS NULL)";
} elseif ($status_req === 'selesai') {
    $where .= " AND (pl.tgl_hasil != '0000-00-00' AND pl.tgl_hasil IS NOT NULL)";
}

// ─── Query List Permintaan ───────────────────────────────────
$sql = "
    SELECT pl.*, 
           p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir, p.alamat,
           d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab,
           (SELECT GROUP_CONCAT(jpl.nm_perawatan SEPARATOR ', ') 
            FROM permintaan_pemeriksaan_lab ppl 
            JOIN jns_perawatan_lab jpl ON ppl.kd_jenis_prw = jpl.kd_jenis_prw 
            WHERE ppl.noorder = pl.noorder) as list_paket,
           (SELECT COUNT(*) FROM permintaan_pemeriksaan_lab ppl WHERE ppl.noorder = pl.noorder) as total_paket,
           (SELECT SUM(jpl.total_byr) 
            FROM permintaan_pemeriksaan_lab ppl 
            JOIN jns_perawatan_lab jpl ON ppl.kd_jenis_prw = jpl.kd_jenis_prw 
            WHERE ppl.noorder = pl.noorder) as total_biaya
    FROM permintaan_lab pl
    JOIN reg_periksa r ON pl.no_rawat = r.no_rawat
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE $where
    ORDER BY pl.tgl_permintaan DESC, pl.jam_permintaan DESC
";

$result = $conn->query($sql);
$permintaan_list = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $permintaan_list[] = $row;
    }
}

// ─── Hitung Statistik Real-time ──────────────────────────────
$stat_today_total = 0;
$stat_menunggu_sampel = 0;
$stat_menunggu_hasil = 0;
$stat_selesai = 0;

$stat_res = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN tgl_sampel = '0000-00-00' OR jam_sampel = '00:00:00' OR tgl_sampel IS NULL THEN 1 ELSE 0 END) as menunggu_sampel,
        SUM(CASE WHEN (tgl_sampel != '0000-00-00' AND tgl_sampel IS NOT NULL) AND (tgl_hasil = '0000-00-00' OR jam_hasil = '00:00:00' OR tgl_hasil IS NULL) THEN 1 ELSE 0 END) as menunggu_hasil,
        SUM(CASE WHEN tgl_hasil != '0000-00-00' AND tgl_hasil IS NOT NULL THEN 1 ELSE 0 END) as selesai
    FROM permintaan_lab
    WHERE tgl_permintaan = '$today'
");

if ($stat_res && $row_st = $stat_res->fetch_assoc()) {
    $stat_today_total     = (int)$row_st['total'];
    $stat_menunggu_sampel = (int)$row_st['menunggu_sampel'];
    $stat_menunggu_hasil  = (int)$row_st['menunggu_hasil'];
    $stat_selesai         = (int)$row_st['selesai'];
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Header & Quick Actions Bar ────────────────────────── -->
<div class="page-header" style="margin-bottom:16px;">
  <div class="page-header-left">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:42px;height:42px;background:linear-gradient(135deg, #0284c7, #0369a1);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;box-shadow:0 2px 6px rgba(2,132,199,0.25);">
        <i class="fas fa-flask-vial"></i>
      </div>
      <div>
        <h1 class="page-title" style="margin:0;font-size:18px;font-weight:800;color:#0f172a;">Pelayanan Laboratorium</h1>
        <p class="page-subtitle" style="margin:2px 0 0;font-size:12px;color:#64748b;">Pengelolaan Permintaan Order Lab, Pengambilan Sampel & Input Hasil Pemeriksaan</p>
      </div>
    </div>
  </div>

  <div class="page-header-right" style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>modules/laboratorium/master_lab.php" class="btn btn-outline" style="border-color:#cbd5e1;color:#334155;">
      <i class="fas fa-cogs" style="color:#0284c7;"></i> Master Tarif & Template Lab
    </a>
    <a href="<?= BASE_URL ?>modules/laboratorium/permintaan.php" class="btn btn-primary" style="background:linear-gradient(135deg,#0284c7,#0369a1);border:none;">
      <i class="fas fa-plus-circle"></i> + Order Permintaan Lab
    </a>
  </div>
</div>

<!-- ─── Summary Statistic Cards ───────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:20px;">
  
  <!-- Card 1: Total Order Hari Ini -->
  <div class="card" style="padding:14px 18px;border-left:4px solid #0284c7;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.05);border-radius:10px;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <div style="font-size:11.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Order Lab Hari Ini</div>
        <div style="font-size:22px;font-weight:800;color:#0f172a;margin-top:2px;"><?= $stat_today_total ?> <span style="font-size:12px;font-weight:500;color:#64748b;">Permintaan</span></div>
      </div>
      <div style="width:40px;height:40px;background:#e0f2fe;color:#0284c7;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-file-medical"></i>
      </div>
    </div>
  </div>

  <!-- Card 2: Menunggu Sampel -->
  <a href="?tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>&status_req=menunggu_sampel" style="text-decoration:none;">
    <div class="card" style="padding:14px 18px;border-left:4px solid #d97706;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.05);border-radius:10px;transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-size:11.5px;font-weight:700;color:#d97706;text-transform:uppercase;letter-spacing:0.04em;">1. Menunggu Sampel</div>
          <div style="font-size:22px;font-weight:800;color:#d97706;margin-top:2px;"><?= $stat_menunggu_sampel ?> <span style="font-size:12px;font-weight:500;color:#64748b;">Pasien</span></div>
        </div>
        <div style="width:40px;height:40px;background:#fef3c7;color:#d97706;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-vial"></i>
        </div>
      </div>
    </div>
  </a>

  <!-- Card 3: Menunggu Hasil / Proses -->
  <a href="?tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>&status_req=menunggu_hasil" style="text-decoration:none;">
    <div class="card" style="padding:14px 18px;border-left:4px solid #4f46e5;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.05);border-radius:10px;transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-size:11.5px;font-weight:700;color:#4f46e5;text-transform:uppercase;letter-spacing:0.04em;">2. Proses Analisis</div>
          <div style="font-size:22px;font-weight:800;color:#4f46e5;margin-top:2px;"><?= $stat_menunggu_hasil ?> <span style="font-size:12px;font-weight:500;color:#64748b;">Pemeriksaan</span></div>
        </div>
        <div style="width:40px;height:40px;background:#e0e7ff;color:#4f46e5;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-microscope"></i>
        </div>
      </div>
    </div>
  </a>

  <!-- Card 4: Selesai Hasil -->
  <a href="?tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>&status_req=selesai" style="text-decoration:none;">
    <div class="card" style="padding:14px 18px;border-left:4px solid #059669;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.05);border-radius:10px;transition:transform 0.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-size:11.5px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:0.04em;">3. Selesai Hasil</div>
          <div style="font-size:22px;font-weight:800;color:#059669;margin-top:2px;"><?= $stat_selesai ?> <span style="font-size:12px;font-weight:500;color:#64748b;">Valid</span></div>
        </div>
        <div style="width:40px;height:40px;background:#d1fae5;color:#059669;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-check-double"></i>
        </div>
      </div>
    </div>
  </a>

</div>

<!-- ─── Filter & Search Bar ───────────────────────────────── -->
<div class="card" style="margin-bottom:16px;border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
  <div class="card-body" style="padding:14px 18px;">
    <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
      
      <!-- Date Range -->
      <div style="display:flex;gap:8px;align-items:center;">
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11px;font-weight:700;color:#64748b;">Dari Tgl</label>
          <input type="date" name="tgl_awal" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_awal) ?>" style="padding:5px 8px;font-size:12px;">
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="font-size:11px;font-weight:700;color:#64748b;">Sampai Tgl</label>
          <input type="date" name="tgl_akhir" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_akhir) ?>" style="padding:5px 8px;font-size:12px;">
        </div>
      </div>

      <!-- Status Filter -->
      <div class="form-group" style="margin:0;min-width:160px;">
        <label class="form-label" style="font-size:11px;font-weight:700;color:#64748b;">Status Pemeriksaan</label>
        <select name="status_req" class="form-control form-control-sm" style="padding:5px 8px;font-size:12px;">
          <option value="semua" <?= $status_req === 'semua' ? 'selected' : '' ?>>Semua Status</option>
          <option value="menunggu_sampel" <?= $status_req === 'menunggu_sampel' ? 'selected' : '' ?>>1. Menunggu Sampel</option>
          <option value="menunggu_hasil" <?= $status_req === 'menunggu_hasil' ? 'selected' : '' ?>>2. Proses / Menunggu Hasil</option>
          <option value="selesai" <?= $status_req === 'selesai' ? 'selected' : '' ?>>3. Selesai (Hasil Keluar)</option>
        </select>
      </div>

      <!-- Keyword Search -->
      <div class="form-group" style="margin:0;flex:1;min-width:200px;">
        <label class="form-label" style="font-size:11px;font-weight:700;color:#64748b;">Pencarian Pasien / Order</label>
        <div style="position:relative;">
          <input type="text" name="keyword" class="form-control form-control-sm" placeholder="No. Order, No. RM, Nama Pasien, Dokter..."
                 value="<?= htmlspecialchars($keyword) ?>" style="padding-left:30px;font-size:12px;">
          <i class="fas fa-search" style="position:absolute;left:10px;top:8px;font-size:12px;color:#94a3b8;"></i>
        </div>
      </div>

      <!-- Submit & Reset -->
      <div style="display:flex;gap:6px;">
        <button type="submit" class="btn btn-primary btn-sm" style="padding:6px 14px;font-size:12px;">
          <i class="fas fa-filter"></i> Terapkan
        </button>
        <a href="<?= BASE_URL ?>modules/laboratorium/index.php" class="btn btn-outline btn-sm" style="padding:6px 12px;font-size:12px;color:#64748b;" title="Reset Filter">
          <i class="fas fa-undo"></i>
        </a>
      </div>

    </form>
  </div>
</div>

<!-- ─── Data Table Permintaan Lab ─────────────────────────── -->
<div class="card" style="border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
  <div class="card-header" style="background:#f8fafc;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;">
    <div class="card-title" style="font-size:13.5px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-list text-primary"></i> Daftar Permintaan Laboratorium
      <span class="badge badge-primary" style="font-size:11px;"><?= count($permintaan_list) ?> Data</span>
    </div>
    <div style="font-size:11.5px;color:#64748b;">
      Periode: <strong><?= tgl_indo($tgl_awal) ?></strong> s/d <strong><?= tgl_indo($tgl_akhir) ?></strong>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover" style="margin:0;font-size:12px;">
      <thead style="background:#f1f5f9;color:#475569;font-weight:700;">
        <tr>
          <th style="width:130px;">No. Order & Waktu</th>
          <th>Pasien & No. RM</th>
          <th>Dokter & Poli</th>
          <th>Pemeriksaan Diminta</th>
          <th style="width:140px;text-align:center;">Status Alur</th>
          <th style="width:180px;text-align:center;">Aksi Tindakan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($permintaan_list)): ?>
          <tr>
            <td colspan="6" style="text-align:center;padding:36px;color:#94a3b8;">
              <i class="fas fa-flask" style="font-size:36px;color:#cbd5e1;display:block;margin-bottom:10px;"></i>
              <strong>Tidak ada data permintaan laboratorium.</strong>
              <div style="font-size:11px;margin-top:4px;">Gunakan tombol <em>+ Order Permintaan Lab</em> untuk membuat pemeriksaan baru.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($permintaan_list as $row): ?>
            <?php
              $is_sampel_taken = ($row['tgl_sampel'] !== '0000-00-00' && !empty($row['tgl_sampel']));
              $is_hasil_done   = ($row['tgl_hasil'] !== '0000-00-00' && !empty($row['tgl_hasil']));
            ?>
            <tr style="border-bottom:1px solid #f1f5f9;">
              
              <!-- 1. No Order & Tanggal -->
              <td>
                <div style="font-family:monospace;font-weight:700;color:#0284c7;font-size:12.5px;">
                  <?= htmlspecialchars($row['noorder']) ?>
                </div>
                <div style="font-size:11px;color:#64748b;margin-top:2px;">
                  <i class="far fa-calendar-alt"></i> <?= tgl_indo($row['tgl_permintaan']) ?>
                </div>
                <div style="font-size:10.5px;color:#94a3b8;font-family:monospace;">
                  <i class="far fa-clock"></i> <?= substr($row['jam_permintaan'], 0, 5) ?> WIB
                </div>
              </td>

              <!-- 2. Pasien & No RM -->
              <td>
                <div style="font-weight:700;color:#0f172a;font-size:13px;">
                  <?= htmlspecialchars($row['nm_pasien']) ?>
                </div>
                <div style="font-size:11px;color:#64748b;margin-top:2px;display:flex;align-items:center;gap:6px;">
                  <span>RM: <strong style="color:#0f172a;"><?= htmlspecialchars($row['no_rkm_medis']) ?></strong></span>
                  <span>&bull; <?= icon_jk($row['jk']) ?> <?= hitung_umur($row['tgl_lahir']) ?></span>
                </div>
                <div style="margin-top:4px;display:flex;align-items:center;gap:6px;">
                  <?= badge_penjab($row['nm_penjab'] ?? 'Umum') ?>
                  <span style="font-size:10px;font-family:monospace;color:#64748b;background:#f8fafc;padding:1px 5px;border-radius:4px;border:1px solid #e2e8f0;">
                    <?= htmlspecialchars($row['no_rawat']) ?>
                  </span>
                </div>
              </td>

              <!-- 3. Dokter & Poli -->
              <td>
                <div style="font-weight:600;color:#334155;">
                  <i class="fas fa-user-md" style="color:#059669;margin-right:4px;"></i> <?= htmlspecialchars($row['nm_dokter'] ?: '-') ?>
                </div>
                <div style="font-size:11px;color:#64748b;margin-top:3px;">
                  <i class="fas fa-clinic-medical" style="color:#4f46e5;margin-right:4px;"></i> <?= htmlspecialchars($row['nm_poli'] ?: 'Rawat Jalan') ?>
                </div>
                <?php if (!empty($row['diagnosa_klinis'])): ?>
                  <div style="font-size:10.5px;color:#92400e;background:#fef3c7;padding:2px 6px;border-radius:4px;margin-top:4px;display:inline-block;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($row['diagnosa_klinis']) ?>">
                    <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($row['diagnosa_klinis']) ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- 4. Paket Pemeriksaan -->
              <td>
                <div style="font-weight:600;color:#0284c7;line-height:1.4;">
                  <?= htmlspecialchars($row['list_paket'] ?: 'Pemeriksaan Rutin') ?>
                </div>
                <?php if (!empty($row['informasi_tambahan'])): ?>
                  <div style="font-size:10.5px;color:#64748b;font-style:italic;margin-top:2px;">
                    Catatan: <?= htmlspecialchars($row['informasi_tambahan']) ?>
                  </div>
                <?php endif; ?>
                <div style="font-size:11px;font-weight:700;color:#059669;margin-top:4px;">
                  Est. Biaya: <?= rupiah((float)$row['total_biaya']) ?>
                </div>
              </td>

              <!-- 5. Status Alur -->
              <td style="text-align:center;">
                <?php if ($is_hasil_done): ?>
                  <span class="badge" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;padding:4px 8px;font-size:11px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-check-circle"></i> Selesai
                  </span>
                  <div style="font-size:10px;color:#64748b;margin-top:3px;">
                    <?= tgl_indo($row['tgl_hasil']) ?> <?= substr($row['jam_hasil'],0,5) ?>
                  </div>
                <?php elseif ($is_sampel_taken): ?>
                  <span class="badge" style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;padding:4px 8px;font-size:11px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-spinner fa-spin"></i> Proses Lab
                  </span>
                  <div style="font-size:10px;color:#64748b;margin-top:3px;">
                    Sampel: <?= substr($row['jam_sampel'],0,5) ?>
                  </div>
                <?php else: ?>
                  <span class="badge" style="background:#fffbeb;color:#d97706;border:1px solid #fde68a;padding:4px 8px;font-size:11px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-clock"></i> Tunggu Sampel
                  </span>
                <?php endif; ?>
              </td>

              <!-- 6. Aksi Tindakan -->
              <td style="text-align:center;">
                <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                  
                  <!-- Ambil Sampel Button -->
                  <?php if (!$is_sampel_taken): ?>
                    <button type="button" class="btn btn-sm btn-warning" 
                            onclick="ambilSampel('<?= htmlspecialchars($row['noorder']) ?>', '<?= htmlspecialchars($row['nm_pasien']) ?>')"
                            style="padding:4px 8px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;"
                            title="Konfirmasi Pengambilan Sampel">
                      <i class="fas fa-vial"></i> Ambil Sampel
                    </button>
                  <?php endif; ?>

                  <!-- Input / Edit Hasil Button -->
                  <a href="<?= BASE_URL ?>modules/laboratorium/input_hasil.php?noorder=<?= urlencode($row['noorder']) ?>" 
                     class="btn btn-sm <?= $is_hasil_done ? 'btn-outline' : 'btn-primary' ?>"
                     style="padding:4px 8px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;"
                     title="<?= $is_hasil_done ? 'Lihat / Edit Hasil Pemeriksaan' : 'Input Nilai Hasil Pemeriksaan' ?>">
                    <i class="fas <?= $is_hasil_done ? 'fa-edit' : 'fa-flask' ?>"></i> <?= $is_hasil_done ? 'Edit Hasil' : 'Input Hasil' ?>
                  </a>

                  <!-- Cetak Hasil Button -->
                  <?php if ($is_hasil_done): ?>
                    <a href="<?= BASE_URL ?>modules/laboratorium/cetak_hasil.php?noorder=<?= urlencode($row['noorder']) ?>&no_rawat=<?= urlencode($row['no_rawat']) ?>" 
                       target="_blank" class="btn btn-sm btn-success"
                       style="padding:4px 8px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;"
                       title="Cetak Lembar Hasil Laboratorium">
                      <i class="fas fa-print"></i> Cetak
                    </a>
                  <?php endif; ?>

                  <!-- Batalkan Order (Jika belum ada hasil) -->
                  <?php if (!$is_hasil_done): ?>
                    <button type="button" class="btn btn-sm btn-outline" 
                            onclick="hapusOrder('<?= htmlspecialchars($row['noorder']) ?>', '<?= htmlspecialchars($row['nm_pasien']) ?>')"
                            style="padding:4px 6px;font-size:11px;color:#ef4444;border-color:#fca5a5;"
                            title="Batalkan Permintaan">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  <?php endif; ?>

                </div>
              </td>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// ─── AJAX Ambil Sampel ─────────────────────────────────────────
function ambilSampel(noorder, namaPasien) {
  if (!confirm('Konfirmasi penerimaan sampel laboratorium untuk pasien ' + namaPasien + ' (' + noorder + ')?')) return;

  const formData = new FormData();
  formData.append('action', 'ambil_sampel');
  formData.append('noorder', noorder);

  fetch('<?= BASE_URL ?>modules/laboratorium/ajax.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      alert(data.message);
      location.reload();
    } else {
      alert('Gagal: ' + data.message);
    }
  })
  .catch(err => {
    alert('Terjadi kesalahan jaringan: ' + err.message);
  });
}

// ─── AJAX Hapus / Batal Order ──────────────────────────────────
function hapusOrder(noorder, namaPasien) {
  if (!confirm('Yakin ingin membatalkan permintaan laboratorium untuk ' + namaPasien + ' (' + noorder + ')?')) return;

  const formData = new FormData();
  formData.append('action', 'hapus_order');
  formData.append('noorder', noorder);

  fetch('<?= BASE_URL ?>modules/laboratorium/ajax.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      alert(data.message);
      location.reload();
    } else {
      alert('Gagal: ' + data.message);
    }
  })
  .catch(err => {
    alert('Terjadi kesalahan jaringan: ' + err.message);
  });
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
