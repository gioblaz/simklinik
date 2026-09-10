<?php
/**
 * SIMKlinik — Integrasi Satu Sehat (Kemenkes RI) — HL7 FHIR R4 Standard
 * Fitur: Test Koneksi, Lookup NIK IHS, Preview Bundle JSON & Transmisi Bundling Kunjungan
 */

$page_title    = 'Integrasi Satu Sehat (Kemenkes RI)';
$active_module = 'satu_sehat';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/satusehat_service.php';

$cfg = get_satusehat_config();
$org_id   = $cfg['organizationid'];
$client_id= $cfg['clientid'];
$fhir_url = $cfg['fhirurl'];
$is_configured = !empty($org_id) && !empty($client_id);

// ─── Filter Data Kunjungan ───────────────────────────────────
$tgl_awal    = sanitize($_GET['tgl_awal'] ?? date('Y-m-d'));
$tgl_akhir   = sanitize($_GET['tgl_akhir'] ?? date('Y-m-d'));
$search_q    = sanitize($_GET['q'] ?? '');
$filter_poli = sanitize($_GET['kd_poli'] ?? '');
$filter_sync = sanitize($_GET['status_sync'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = max(10, min(100, (int)($_GET['limit'] ?? 20)));

$where = "r.tgl_registrasi BETWEEN '$tgl_awal' AND '$tgl_akhir'";
if (!empty($search_q)) {
    $sq_esc = $conn->real_escape_string($search_q);
    $where .= " AND (r.no_rawat LIKE '%$sq_esc%' OR p.no_rkm_medis LIKE '%$sq_esc%' OR p.nm_pasien LIKE '%$sq_esc%' OR p.no_ktp LIKE '%$sq_esc%')";
}
if (!empty($filter_poli)) {
    $fp_esc = $conn->real_escape_string($filter_poli);
    $where .= " AND r.kd_poli = '$fp_esc'";
}
if ($filter_sync === 'synced') {
    $where .= " AND ss.status_sync = 'Synced'";
} elseif ($filter_sync === 'unsynced') {
    $where .= " AND (ss.status_sync IS NULL OR ss.status_sync != 'Synced')";
}

// Hitung Total Data & Paginasi
$count_res = $conn->query("
    SELECT COUNT(*) as c
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN mlite_satu_sehat_response ss ON r.no_rawat = ss.no_rawat
    WHERE $where
");
$total_rows = $count_res ? (int)$count_res->fetch_assoc()['c'] : 0;
$total_pages = max(1, ceil($total_rows / $limit));
$offset = ($page - 1) * $limit;

// Query Data Kunjungan
$kunjungan_res = $conn->query("
    SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.stts, r.kd_poli, r.kd_dokter,
           p.nm_pasien, p.no_rkm_medis, p.no_ktp, p.jk, p.tgl_lahir,
           d.nm_dokter, pol.nm_poli,
           ss.status_sync, ss.id_encounter, ss.id_condition, ss.last_sync_at,
           (SELECT COUNT(*) FROM pemeriksaan_ralan pr WHERE pr.no_rawat = r.no_rawat) as has_vitals,
           (SELECT COUNT(*) FROM diagnosa_pasien dp WHERE dp.no_rawat = r.no_rawat) as has_condition,
           (SELECT COUNT(*) FROM permintaan_lab pl WHERE pl.no_rawat = r.no_rawat) as has_lab,
           (SELECT COUNT(*) FROM resep_obat ro WHERE ro.no_rawat = r.no_rawat) as has_resep
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN mlite_satu_sehat_response ss ON r.no_rawat = ss.no_rawat
    WHERE $where
    ORDER BY r.tgl_registrasi DESC, r.jam_reg DESC
    LIMIT $limit OFFSET $offset
");
$sync_list = [];
if ($kunjungan_res) while ($row = $kunjungan_res->fetch_assoc()) $sync_list[] = $row;

// Daftar Poliklinik untuk filter
$poli_list = [];
$res_poli = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli ASC");
if ($res_poli) while ($rp = $res_poli->fetch_assoc()) $poli_list[] = $rp;

// Statistik Hari Ini
$stat_today_total = ($conn->query("SELECT COUNT(*) as c FROM reg_periksa WHERE tgl_registrasi = CURDATE()")->fetch_assoc()['c'] ?? 0);
$stat_today_synced = ($conn->query("SELECT COUNT(*) as c FROM reg_periksa r JOIN mlite_satu_sehat_response ss ON r.no_rawat = ss.no_rawat WHERE r.tgl_registrasi = CURDATE() AND ss.status_sync = 'Synced'")->fetch_assoc()['c'] ?? 0);
$stat_today_unsynced = max(0, $stat_today_total - $stat_today_synced);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Header Section ────────────────────────────────────── -->
<div class="page-header" style="margin-bottom:16px;">
  <div class="page-header-left">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:42px;height:42px;background:linear-gradient(135deg, #16a34a, #15803d);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;box-shadow:0 2px 6px rgba(22,163,74,0.25);">
        <i class="fas fa-shield-heart"></i>
      </div>
      <div>
        <h1 class="page-title" style="margin:0;font-size:18px;font-weight:800;color:#0f172a;">Integrasi Satu Sehat (Kemenkes RI)</h1>
        <p class="page-subtitle" style="margin:2px 0 0;font-size:12px;color:#64748b;">Standarisasi HL7 FHIR R4 &mdash; Encounter, Condition, Vital Signs, Lab DiagnosticReport & Medication</p>
      </div>
    </div>
  </div>

  <div class="page-header-right" style="display:flex;gap:8px;flex-wrap:wrap;">
    <button type="button" class="btn btn-success btn-sm" onclick="modalTestKoneksi()" style="background:#16a34a;border:none;font-weight:700;">
      <i class="fas fa-bolt"></i> Test Koneksi Satu Sehat
    </button>
    <button type="button" class="btn btn-outline btn-sm" onclick="modalLookupNik()" style="font-weight:700;color:#0284c7;border-color:#bae6fd;">
      <i class="fas fa-search"></i> Cek NIK Pasien / Dokter
    </button>
    <button type="button" class="btn btn-primary btn-sm" onclick="kirimBundlingMassal()" id="btn_batch_sync" style="background:#0284c7;border:none;font-weight:700;" disabled>
      <i class="fas fa-paper-plane"></i> Kirim Bundling Terpilih (<span id="selected_count">0</span>)
    </button>
  </div>
</div>

<!-- ─── Status Gateway Satu Sehat Bar ─────────────────────── -->
<div class="card mb-16" style="border-radius:10px;border-left:5px solid #16a34a;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
  <div class="card-body" style="padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="width:38px;height:38px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#16a34a;font-size:18px;">
        <i class="fas fa-network-wired"></i>
      </div>
      <div>
        <div style="font-size:14px;font-weight:800;color:#0f172a;">
          Gateway Satu Sehat Production Kemenkes RI
        </div>
        <div style="font-size:12px;color:#64748b;margin-top:2px;">
          Organization ID: <strong style="color:#0f172a;font-family:monospace;"><?= htmlspecialchars($org_id ?: '(kosong)') ?></strong> &bull; 
          FHIR Server: <code style="color:#0284c7;font-size:11px;"><?= htmlspecialchars($fhir_url) ?></code>
        </div>
      </div>
    </div>
    
    <div style="display:flex;align-items:center;gap:10px;">
      <span class="badge" style="background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;font-size:11.5px;padding:5px 12px;border-radius:20px;font-weight:700;">
        <i class="fas fa-circle" style="font-size:8px;margin-right:4px;"></i> FHIR R4 Ready
      </span>
      <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="btn btn-outline btn-sm" style="font-size:11.5px;padding:4px 10px;">
        <i class="fas fa-cog"></i> Pengaturan
      </a>
    </div>
  </div>
</div>

<!-- ─── Statistics Summary Bar ────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;margin-bottom:16px;">
  <div class="card" style="padding:12px 16px;border-left:4px solid #0284c7;background:#fff;border-radius:10px;">
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Kunjungan Hari Ini</div>
    <div style="font-size:20px;font-weight:800;color:#0f172a;margin-top:2px;">
      <?= number_format($stat_today_total) ?> <span style="font-size:11px;font-weight:500;color:#64748b;">Pasien</span>
    </div>
  </div>
  <div class="card" style="padding:12px 16px;border-left:4px solid #16a34a;background:#fff;border-radius:10px;">
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Terkirim Satu Sehat (Synced)</div>
    <div style="font-size:20px;font-weight:800;color:#16a34a;margin-top:2px;">
      <?= number_format($stat_today_synced) ?> <span style="font-size:11px;font-weight:500;color:#64748b;">Bundle Transmisi</span>
    </div>
  </div>
  <div class="card" style="padding:12px 16px;border-left:4px solid #f59e0b;background:#fff;border-radius:10px;">
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Belum Terkirim</div>
    <div style="font-size:20px;font-weight:800;color:#f59e0b;margin-top:2px;">
      <?= number_format($stat_today_unsynced) ?> <span style="font-size:11px;font-weight:500;color:#64748b;">Antrian Sync</span>
    </div>
  </div>
  <div class="card" style="padding:12px 16px;border-left:4px solid #7c3aed;background:#fff;border-radius:10px;">
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Resource Bundling</div>
    <div style="font-size:13px;font-weight:700;color:#7c3aed;margin-top:5px;">
      Encounter &bull; Condition &bull; TTV &bull; Lab &bull; Obat
    </div>
  </div>
</div>

<!-- ─── Filter & Search Bar ───────────────────────────────── -->
<div class="card" style="margin-bottom:14px;border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
  <div class="card-body" style="padding:12px 18px;">
    <form method="GET" action="" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0;">
      <div style="display:flex;align-items:center;gap:6px;">
        <span style="font-size:12px;font-weight:700;color:#475569;">Periode:</span>
        <input type="date" name="tgl_awal" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_awal) ?>" style="font-size:12px;width:130px;">
        <span style="font-size:12px;color:#94a3b8;">s/d</span>
        <input type="date" name="tgl_akhir" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_akhir) ?>" style="font-size:12px;width:130px;">
      </div>

      <div style="flex:1;min-width:180px;">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari No. Rawat, No. RM, NIK, Nama Pasien..." value="<?= htmlspecialchars($search_q) ?>" style="font-size:12px;">
      </div>

      <div style="width:160px;">
        <select name="kd_poli" class="form-control form-control-sm" style="font-size:12px;" onchange="this.form.submit()">
          <option value="">Semua Poliklinik</option>
          <?php foreach ($poli_list as $pl): ?>
            <option value="<?= htmlspecialchars($pl['kd_poli']) ?>" <?= ($filter_poli === $pl['kd_poli']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($pl['nm_poli']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="width:150px;">
        <select name="status_sync" class="form-control form-control-sm" style="font-size:12px;" onchange="this.form.submit()">
          <option value="">Semua Status Sync</option>
          <option value="unsynced" <?= ($filter_sync === 'unsynced') ? 'selected' : '' ?>>Belum Terkirim</option>
          <option value="synced" <?= ($filter_sync === 'synced') ? 'selected' : '' ?>>Sudah Terkirim (Synced)</option>
        </select>
      </div>

      <div style="width:100px;">
        <select name="limit" class="form-control form-control-sm" style="font-size:12px;" onchange="this.form.submit()">
          <option value="15" <?= ($limit === 15) ? 'selected' : '' ?>>15 / hal</option>
          <option value="25" <?= ($limit === 25) ? 'selected' : '' ?>>25 / hal</option>
          <option value="50" <?= ($limit === 50) ? 'selected' : '' ?>>50 / hal</option>
          <option value="100" <?= ($limit === 100) ? 'selected' : '' ?>>100 / hal</option>
        </select>
      </div>

      <button type="submit" class="btn btn-outline btn-sm" style="font-size:12px;">
        <i class="fas fa-search"></i> Filter
      </button>

      <?php if (!empty($search_q) || !empty($filter_poli) || !empty($filter_sync) || $tgl_awal !== date('Y-m-d') || $tgl_akhir !== date('Y-m-d')): ?>
        <a href="index.php" class="btn btn-outline btn-sm" style="color:#ef4444;border-color:#fca5a5;font-size:12px;" title="Reset Filter">
          <i class="fas fa-times"></i> Reset
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Antrian Transmisi Satu Sehat Table ─────────────────── -->
<div class="card" style="border-radius:10px;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
  <div class="table-responsive">
    <table class="table table-hover" style="margin:0;font-size:12px;">
      <thead style="background:#f8fafc;color:#475569;font-weight:700;border-bottom:1px solid #e2e8f0;">
        <tr>
          <th style="width:40px;text-align:center;">
            <input type="checkbox" id="check_all" onchange="toggleSelectAll(this)" style="cursor:pointer;">
          </th>
          <th style="width:170px;">No. Rawat & Waktu</th>
          <th>Pasien</th>
          <th style="width:150px;">NIK KTP</th>
          <th>Poli & Dokter</th>
          <th style="width:180px;">Kelengkapan FHIR</th>
          <th style="width:130px;text-align:center;">Status Transmisi</th>
          <th style="width:180px;text-align:center;">Aksi Transmisi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($sync_list)): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:35px;color:#94a3b8;">
              <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:8px;color:#cbd5e1;"></i>
              Tidak ada data kunjungan pasien yang sesuai filter.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($sync_list as $s): ?>
            <?php $is_synced = ($s['status_sync'] === 'Synced'); ?>
            <tr style="border-bottom:1px solid #f1f5f9; <?= $is_synced ? 'background:#f0fdf4;' : '' ?>">
              <td style="text-align:center;">
                <input type="checkbox" class="check_item" value="<?= htmlspecialchars($s['no_rawat']) ?>" data-pasien="<?= htmlspecialchars($s['nm_pasien']) ?>" onchange="updateSelectedCount()" style="cursor:pointer;">
              </td>
              <td>
                <span style="font-family:monospace;font-weight:700;color:#0284c7;font-size:12px;">
                  <?= htmlspecialchars($s['no_rawat']) ?>
                </span>
                <div style="font-size:11px;color:#64748b;margin-top:2px;">
                  <?= tgl_indo($s['tgl_registrasi']) ?> &bull; <?= htmlspecialchars($s['jam_reg']) ?>
                </div>
              </td>
              <td>
                <div style="font-weight:700;color:#0f172a;font-size:13px;">
                  <?= htmlspecialchars($s['nm_pasien']) ?>
                </div>
                <div style="font-size:11px;color:#64748b;">
                  RM: <span style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($s['no_rkm_medis']) ?></span> &bull; 
                  <?= ($s['jk'] === 'L') ? 'Laki-laki' : 'Perempuan' ?>
                </div>
              </td>
              <td>
                <?php if (!empty($s['no_ktp'])): ?>
                  <span style="font-family:monospace;font-weight:700;color:#0284c7;font-size:12px;">
                    <?= htmlspecialchars($s['no_ktp']) ?>
                  </span>
                <?php else: ?>
                  <span class="badge badge-danger" style="font-size:10px;">NIK Belum Ada</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight:600;color:#334155;"><?= htmlspecialchars($s['nm_poli']) ?></div>
                <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($s['nm_dokter']) ?></div>
              </td>
              <td>
                <div style="display:flex;gap:3px;flex-wrap:wrap;">
                  <span class="badge badge-success" style="font-size:9.5px;" title="Encounter Resource">Encounter</span>
                  <span class="badge <?= $s['has_vitals'] ? 'badge-success' : 'badge-secondary' ?>" style="font-size:9.5px;" title="Observation Vital Signs">TTV</span>
                  <span class="badge <?= $s['has_condition'] ? 'badge-success' : 'badge-secondary' ?>" style="font-size:9.5px;" title="Condition ICD-10">ICD10</span>
                  <?php if ($s['has_lab']): ?>
                    <span class="badge badge-info" style="font-size:9.5px;background:#e0f2fe;color:#0369a1;" title="DiagnosticReport & Observation Lab">Lab</span>
                  <?php endif; ?>
                  <?php if ($s['has_resep']): ?>
                    <span class="badge badge-warning" style="font-size:9.5px;background:#fef3c7;color:#92400e;" title="MedicationRequest Obat">Obat</span>
                  <?php endif; ?>
                </div>
              </td>
              <td style="text-align:center;">
                <?php if ($is_synced): ?>
                  <span class="badge badge-success" style="font-size:10.5px;background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;">
                    <i class="fas fa-check-circle"></i> Synced
                  </span>
                  <?php if (!empty($s['id_encounter'])): ?>
                    <div style="font-size:10px;font-family:monospace;color:#16a34a;margin-top:2px;" title="Encounter ID: <?= htmlspecialchars($s['id_encounter']) ?>">
                      <?= substr($s['id_encounter'], 0, 10) ?>...
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge badge-warning" style="font-size:10.5px;">Belum Sync</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <div style="display:flex;gap:4px;justify-content:center;align-items:center;">
                  <button type="button" class="btn btn-sm btn-info" onclick="kirimBundleSingle('<?= $s['no_rawat'] ?>', this)" style="padding:4px 8px;font-size:11px;font-weight:700;" title="Kirim Bundle FHIR R4">
                    <i class="fas fa-cloud-upload-alt"></i> Kirim Bundle
                  </button>
                  <button type="button" class="btn btn-outline btn-sm" onclick="previewBundleJson('<?= $s['no_rawat'] ?>')" style="padding:4px 7px;font-size:11px;color:#0284c7;border-color:#bae6fd;" title="Lihat Payload Bundle JSON">
                    <i class="fas fa-code"></i>
                  </button>
                  <?php if ($is_synced): ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="viewResponseLog('<?= $s['no_rawat'] ?>')" style="padding:4px 7px;font-size:11px;color:#16a34a;border-color:#bbf7d0;" title="Lihat Log Respon Kemenkes">
                      <i class="fas fa-receipt"></i>
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

  <!-- Paginasi -->
  <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-top:1px solid #f1f5f9;flex-wrap:wrap;gap:10px;font-size:12px;color:#64748b;">
    <div>Menampilkan <strong><?= number_format(min($total_rows, $offset + 1)) ?></strong> - <strong><?= number_format(min($total_rows, $offset + count($sync_list))) ?></strong> dari <strong><?= number_format($total_rows) ?></strong> kunjungan</div>
    <div style="display:flex;gap:4px;align-items:center;">
      <?php if ($page > 1): ?>
        <a href="?page=1&tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>&q=<?= urlencode($search_q) ?>&kd_poli=<?= urlencode($filter_poli) ?>&status_sync=<?= urlencode($filter_sync) ?>&limit=<?= $limit ?>" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;">&laquo;</a>
        <a href="?page=<?= $page - 1 ?>&tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>&q=<?= urlencode($search_q) ?>&kd_poli=<?= urlencode($filter_poli) ?>&status_sync=<?= urlencode($filter_sync) ?>&limit=<?= $limit ?>" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;">&lsaquo;</a>
      <?php endif; ?>

      <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <a href="?page=<?= $i ?>&tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>&q=<?= urlencode($search_q) ?>&kd_poli=<?= urlencode($filter_poli) ?>&status_sync=<?= urlencode($filter_sync) ?>&limit=<?= $limit ?>" class="btn btn-sm <?= ($i === $page) ? 'btn-primary' : 'btn-outline' ?>" style="padding:3px 8px;font-size:11px;font-weight:700;">
          <?= $i ?>
        </a>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>&tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>&q=<?= urlencode($search_q) ?>&kd_poli=<?= urlencode($filter_poli) ?>&status_sync=<?= urlencode($filter_sync) ?>&limit=<?= $limit ?>" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;">&rsaquo;</a>
        <a href="?page=<?= $total_pages ?>&tgl_awal=<?= urlencode($tgl_awal) ?>&tgl_akhir=<?= urlencode($tgl_akhir) ?>&q=<?= urlencode($search_q) ?>&kd_poli=<?= urlencode($filter_poli) ?>&status_sync=<?= urlencode($filter_sync) ?>&limit=<?= $limit ?>" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:11px;">&raquo;</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ─── MODAL 1: TEST KONEKSI SATU SEHAT ──────────────────── -->
<div id="modalTestConn" class="modal-overlay" onclick="if(event.target === this) closeSSModal('modalTestConn')" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#ffffff;border-radius:12px;width:100%;max-width:560px;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
    
    <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;">
        <i class="fas fa-bolt" style="color:#16a34a;margin-right:6px;"></i> Test Koneksi Satu Sehat Kemenkes
      </h3>
      <button type="button" class="modal-close-btn" onclick="closeSSModal('modalTestConn')" style="background:none;border:none;font-size:24px;line-height:1;color:#64748b;cursor:pointer;padding:4px 8px;border-radius:6px;transition:color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'">&times;</button>
    </div>

    <div style="padding:20px;" id="test_conn_content">
      <div style="text-align:center;padding:30px 0;" id="test_conn_loading">
        <i class="fas fa-spinner fa-spin" style="font-size:32px;color:#16a34a;margin-bottom:12px;"></i>
        <div style="font-size:13px;font-weight:700;color:#0f172a;">Menghubungi Auth & FHIR Server Kemenkes...</div>
        <div style="font-size:11.5px;color:#64748b;margin-top:4px;">Memvalidasi Client ID, Secret, Organization ID & Latency</div>
      </div>

      <div id="test_conn_result" style="display:none;"></div>
    </div>

    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
      <button type="button" class="btn btn-outline btn-sm" onclick="closeSSModal('modalTestConn')" style="cursor:pointer;font-weight:600;">Tutup</button>
      <button type="button" class="btn btn-success btn-sm" onclick="runTestKoneksi()" style="background:#16a34a;border:none;font-weight:700;cursor:pointer;">
        <i class="fas fa-redo"></i> Uji Ulang
      </button>
    </div>

  </div>
</div>

<!-- ─── MODAL 2: LOOKUP NIK IHS ───────────────────────────── -->
<div id="modalLookupNik" class="modal-overlay" onclick="if(event.target === this) closeSSModal('modalLookupNik')" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#ffffff;border-radius:12px;width:100%;max-width:520px;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
    
    <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;">
        <i class="fas fa-search" style="color:#0284c7;margin-right:6px;"></i> Cek Nomor IHS Pasien / Praktisi
      </h3>
      <button type="button" class="modal-close-btn" onclick="closeSSModal('modalLookupNik')" style="background:none;border:none;font-size:24px;line-height:1;color:#64748b;cursor:pointer;padding:4px 8px;border-radius:6px;transition:color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'">&times;</button>
    </div>

    <div style="padding:20px;display:flex;flex-direction:column;gap:12px;">
      <div class="form-group" style="margin:0;">
        <label class="form-label" style="font-size:11.5px;font-weight:700;">Tipe Pencarian:</label>
        <div style="display:flex;gap:16px;margin-top:4px;">
          <label style="font-size:12px;display:flex;align-items:center;gap:6px;cursor:pointer;">
            <input type="radio" name="lookup_type" value="patient" checked> Pasien (Patient IHS)
          </label>
          <label style="font-size:12px;display:flex;align-items:center;gap:6px;cursor:pointer;">
            <input type="radio" name="lookup_type" value="practitioner"> Dokter / Praktisi (Practitioner ID)
          </label>
        </div>
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label" style="font-size:11.5px;font-weight:700;">Nomor Induk Kependudukan (NIK KTP) <span class="text-danger">*</span></label>
        <div style="display:flex;gap:8px;">
          <input type="text" id="lookup_nik_input" class="form-control" placeholder="16 digit NIK..." style="font-size:13px;font-family:monospace;font-weight:700;">
          <button type="button" class="btn btn-primary btn-sm" onclick="runLookupNik()" style="background:#0284c7;border:none;font-weight:700;white-space:nowrap;padding:6px 16px;cursor:pointer;">
            <i class="fas fa-search"></i> Cari
          </button>
        </div>
      </div>

      <div id="lookup_result_box" style="display:none;margin-top:8px;"></div>
    </div>

    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;">
      <button type="button" class="btn btn-outline btn-sm" onclick="closeSSModal('modalLookupNik')" style="cursor:pointer;font-weight:600;">Tutup</button>
    </div>

  </div>
</div>

<!-- ─── MODAL 3: PREVIEW BUNDLE JSON ──────────────────────── -->
<div id="modalPreviewJson" class="modal-overlay" onclick="if(event.target === this) closeSSModal('modalPreviewJson')" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#ffffff;border-radius:12px;width:100%;max-width:700px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
    
    <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;" id="preview_title">Preview FHIR R4 Bundle Transaction</h3>
      <button type="button" class="modal-close-btn" onclick="closeSSModal('modalPreviewJson')" style="background:none;border:none;font-size:24px;line-height:1;color:#64748b;cursor:pointer;padding:4px 8px;border-radius:6px;transition:color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'">&times;</button>
    </div>

    <div style="padding:16px 20px;overflow-y:auto;max-height:calc(90vh - 120px);">
      <pre id="preview_json_content" style="background:#0f172a;color:#38bdf8;padding:16px;border-radius:8px;font-size:11.5px;font-family:Consolas, monospace;margin:0;overflow-x:auto;white-space:pre-wrap;"></pre>
    </div>

    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <button type="button" class="btn btn-outline btn-sm" onclick="copyPreviewJson()" style="cursor:pointer;font-weight:600;">
        <i class="fas fa-copy"></i> Salin JSON
      </button>
      <button type="button" class="btn btn-outline btn-sm" onclick="closeSSModal('modalPreviewJson')" style="cursor:pointer;font-weight:600;">Tutup</button>
    </div>

  </div>
</div>

<!-- ─── MODAL 4: LOG RESPONSE SATU SEHAT ──────────────────── -->
<div id="modalLogResp" class="modal-overlay" onclick="if(event.target === this) closeSSModal('modalLogResp')" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#ffffff;border-radius:12px;width:100%;max-width:640px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
    
    <div style="padding:14px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a;">Riwayat Respon Satu Sehat Kemenkes</h3>
      <button type="button" class="modal-close-btn" onclick="closeSSModal('modalLogResp')" style="background:none;border:none;font-size:24px;line-height:1;color:#64748b;cursor:pointer;padding:4px 8px;border-radius:6px;transition:color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'">&times;</button>
    </div>

    <div style="padding:20px;overflow-y:auto;max-height:calc(90vh - 120px);" id="log_resp_content"></div>

    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;">
      <button type="button" class="btn btn-outline btn-sm" onclick="closeSSModal('modalLogResp')" style="cursor:pointer;font-weight:600;">Tutup</button>
    </div>

  </div>
</div>

<!-- ─── MODAL 5: PROGRESS TRANSMISI BUNDLING MASSAL ───────── -->
<div id="modalBatchProgress" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.7);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);">
  <div style="background:#ffffff;border-radius:14px;width:100%;max-width:680px;display:flex;flex-direction:column;box-shadow:0 20px 40px rgba(0,0,0,0.25);overflow:hidden;border:1px solid #e2e8f0;animation:fadeIn 0.2s ease;">
    
    <!-- Modal Header -->
    <div style="padding:16px 22px;background:linear-gradient(135deg, #0284c7, #0369a1);display:flex;justify-content:space-between;align-items:center;color:#fff;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:34px;height:34px;background:rgba(255,255,255,0.2);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;">
          <i class="fas fa-paper-plane"></i>
        </div>
        <div>
          <h3 style="margin:0;font-size:15px;font-weight:800;color:#fff;" id="progress_modal_title">
            Transmisi Bundling Satu Sehat
          </h3>
          <p style="margin:2px 0 0;font-size:11.5px;color:#e0f2fe;">Mengirim bundle transaksi FHIR R4 ke Kemenkes RI</p>
        </div>
      </div>
      <button type="button" id="btn_close_progress_x" onclick="closeSSModal('modalBatchProgress'); window.location.reload();" style="background:none;border:none;font-size:22px;line-height:1;color:rgba(255,255,255,0.7);cursor:pointer;padding:4px 8px;border-radius:6px;display:none;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.7)'">&times;</button>
    </div>

    <!-- Modal Body -->
    <div style="padding:20px 22px;display:flex;flex-direction:column;gap:16px;">
      
      <!-- Counter Stats -->
      <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:10px;text-align:center;">
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 8px;">
          <div style="font-size:10.5px;font-weight:700;color:#64748b;text-transform:uppercase;">Total Antrian</div>
          <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:2px;" id="stat_prog_total">0</div>
        </div>
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 8px;">
          <div style="font-size:10.5px;font-weight:700;color:#0284c7;text-transform:uppercase;">Diproses</div>
          <div style="font-size:18px;font-weight:800;color:#0284c7;margin-top:2px;" id="stat_prog_current">0</div>
        </div>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 8px;">
          <div style="font-size:10.5px;font-weight:700;color:#15803d;text-transform:uppercase;">Berhasil</div>
          <div style="font-size:18px;font-weight:800;color:#16a34a;margin-top:2px;" id="stat_prog_success">0</div>
        </div>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 8px;">
          <div style="font-size:10.5px;font-weight:700;color:#991b1b;text-transform:uppercase;">Gagal</div>
          <div style="font-size:18px;font-weight:800;color:#ef4444;margin-top:2px;" id="stat_prog_fail">0</div>
        </div>
      </div>

      <!-- Progress Bar Container -->
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:12px;">
          <span id="progress_status_text" style="font-weight:700;color:#334155;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-spinner fa-spin text-primary"></i> Mempersiapkan pengiriman...
          </span>
          <span id="progress_pct_text" style="font-weight:800;color:#0284c7;font-size:13px;">0%</span>
        </div>
        
        <div style="width:100%;height:20px;background:#e2e8f0;border-radius:99px;overflow:hidden;position:relative;box-shadow:inset 0 1px 2px rgba(0,0,0,0.1);">
          <div id="progress_bar_fill" style="width:0%;height:100%;background:linear-gradient(90deg, #0284c7, #10b981);border-radius:99px;transition:width 0.3s ease;display:flex;align-items:center;justify-content:flex-end;padding-right:8px;color:#fff;font-size:10px;font-weight:700;">
          </div>
        </div>
      </div>

      <!-- Live Console Log -->
      <div>
        <div style="font-size:11.5px;font-weight:700;color:#475569;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;">
          <span><i class="fas fa-terminal" style="color:#0284c7;margin-right:4px;"></i> Log Transmisi Realtime:</span>
          <span style="font-size:10.5px;color:#94a3b8;">Auto-scroll</span>
        </div>
        <div id="progress_log_box" style="background:#0f172a;color:#f8fafc;padding:12px 14px;border-radius:8px;font-size:11.5px;font-family:Consolas, 'Courier New', monospace;height:160px;overflow-y:auto;display:flex;flex-direction:column;gap:5px;line-height:1.4;">
          <div style="color:#64748b;font-style:italic;">Menunggu antrian dimulai...</div>
        </div>
      </div>

    </div>

    <!-- Modal Footer -->
    <div style="padding:14px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;">
      <button type="button" id="btn_stop_batch" class="btn btn-outline btn-sm" onclick="stopBatchSync()" style="color:#ef4444;border-color:#fca5a5;font-size:12px;font-weight:600;">
        <i class="fas fa-stop-circle"></i> Hentikan Proses
      </button>
      <button type="button" id="btn_done_reload" class="btn btn-primary btn-sm" onclick="closeAndReload()" style="display:none;background:#16a34a;border:none;font-size:12px;font-weight:700;padding:7px 20px;">
        <i class="fas fa-check-circle"></i> Selesai & Muat Ulang Halaman
      </button>
    </div>

  </div>
</div>

<script>
// ─── Modal Helpers ────────────────────────────────────────────
function openSSModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.style.display = 'flex';
    el.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeSSModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.style.display = 'none';
    el.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// Fallback jika ada listener yang memanggil closeModal
window.closeSSModal = closeSSModal;
window.openSSModal  = openSSModal;
window.closeModal   = closeSSModal;

// Keyboard ESC to close modal
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    ['modalTestConn', 'modalLookupNik', 'modalPreviewJson', 'modalLogResp'].forEach(function(mId) {
      closeSSModal(mId);
    });
  }
});

// ─── Test Koneksi Satu Sehat ──────────────────────────────────
function modalTestKoneksi() {
  openSSModal('modalTestConn');
  runTestKoneksi();
}

function runTestKoneksi() {
  const loadingEl = document.getElementById('test_conn_loading');
  const resultEl  = document.getElementById('test_conn_result');

  loadingEl.style.display = 'block';
  resultEl.style.display  = 'none';

  fetch('<?= BASE_URL ?>modules/satu_sehat/ajax.php?action=test_koneksi')
    .then(r => r.json())
    .then(data => {
      loadingEl.style.display = 'none';
      resultEl.style.display  = 'block';

      if (data.success) {
        resultEl.innerHTML = `
          <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
              <i class="fas fa-check-circle" style="color:#16a34a;font-size:24px;"></i>
              <div>
                <div style="font-size:14px;font-weight:800;color:#15803d;">Koneksi Berhasil & Terverifikasi</div>
                <div style="font-size:12px;color:#166534;">Latency: <strong>${data.latency} ms</strong> &bull; HTTP Status: <strong>${data.http_code} OK</strong></div>
              </div>
            </div>

            <div style="background:#fff;border-radius:8px;padding:12px;font-size:12px;display:flex;flex-direction:column;gap:6px;border:1px solid #e2e8f0;">
              <div>Faskes: <strong>${data.org_name}</strong></div>
              <div>Organization ID: <code style="font-weight:700;color:#0284c7;">${data.org_id}</code></div>
              <div>Jenis Faskes: <span class="badge badge-info" style="font-size:10.5px;">${data.org_type}</span></div>
              <div>Alamat: <span style="color:#475569;">${data.address || '-'}</span></div>
              <div>Kontak: <span style="color:#475569;">${data.contact || '-'}</span></div>
              <div>Sample Token: <code style="font-size:11px;color:#7c3aed;">${data.token_sample || '-'}</code></div>
            </div>
          </div>
        `;
      } else {
        resultEl.innerHTML = `
          <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
              <i class="fas fa-times-circle" style="color:#ef4444;font-size:24px;"></i>
              <div>
                <div style="font-size:14px;font-weight:800;color:#991b1b;">Koneksi Gagal</div>
                <div style="font-size:12px;color:#b91c1c;">HTTP Status: ${data.http_code || 'Err'}</div>
              </div>
            </div>
            <div style="font-size:12px;color:#7f1d1d;background:#fff;padding:10px;border-radius:6px;border:1px solid #fecaca;">
              ${data.message || 'Terjadi kesalahan tidak dikenal saat menghubungi Satu Sehat.'}
            </div>
          </div>
        `;
      }
    })
    .catch(err => {
      loadingEl.style.display = 'none';
      resultEl.style.display  = 'block';
      resultEl.innerHTML = `
        <div style="background:#fef2f2;border:1px solid #fecaca;padding:14px;border-radius:8px;color:#991b1b;font-size:12px;">
          Terjadi kesalahan jaringan lokal: ${err.message}
        </div>
      `;
    });
}

// ─── Lookup NIK Pasien / Praktisi ─────────────────────────────
function modalLookupNik() {
  openSSModal('modalLookupNik');
  document.getElementById('lookup_result_box').style.display = 'none';
  document.getElementById('lookup_nik_input').value = '';
}

function runLookupNik() {
  const nik = document.getElementById('lookup_nik_input').value.trim();
  const type = document.querySelector('input[name="lookup_type"]:checked').value;
  const resultBox = document.getElementById('lookup_result_box');

  if (!nik) {
    alert('Masukkan NIK terlebih dahulu.');
    return;
  }

  resultBox.style.display = 'block';
  resultBox.innerHTML = '<div style="text-align:center;padding:15px;color:#0284c7;font-size:12px;"><i class="fas fa-spinner fa-spin"></i> Mencari di Satu Sehat Kemenkes...</div>';

  const fd = new FormData();
  fd.append('action', 'lookup_nik');
  fd.append('nik', nik);
  fd.append('type', type);

  fetch('<?= BASE_URL ?>modules/satu_sehat/ajax.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const idVal = (type === 'practitioner') ? data.practitioner_id : data.ihs_number;
      resultBox.innerHTML = `
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;font-size:12px;">
          <div style="color:#15803d;font-weight:700;margin-bottom:6px;"><i class="fas fa-check-circle"></i> Data Ditemukan di Satu Sehat:</div>
          <div>Nomor IHS / ID: <code style="font-weight:800;color:#0284c7;font-size:13px;">${idVal}</code></div>
          <div>Nama: <strong>${data.name || '-'}</strong></div>
          ${data.birth_date ? `<div>Tgl Lahir: ${data.birth_date}</div>` : ''}
          ${data.gender ? `<div>Gender: ${data.gender}</div>` : ''}
        </div>
      `;
    } else {
      resultBox.innerHTML = `
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;font-size:12px;color:#991b1b;">
          <i class="fas fa-info-circle"></i> ${data.message || 'Data tidak ditemukan di database Kemenkes.'}
        </div>
      `;
    }
  })
  .catch(err => {
    resultBox.innerHTML = `<div style="color:#ef4444;font-size:12px;">Kesalahan jaringan: ${err.message}</div>`;
  });
}

// ─── Preview Bundle JSON ──────────────────────────────────────
function previewBundleJson(noRawat) {
  openSSModal('modalPreviewJson');
  document.getElementById('preview_title').innerText = 'Preview FHIR R4 Bundle (' + noRawat + ')';
  document.getElementById('preview_json_content').innerText = 'Menyusun struktur FHIR R4 Bundle Transaction...';

  const fd = new FormData();
  fd.append('action', 'preview_bundle');
  fd.append('no_rawat', noRawat);

  fetch('<?= BASE_URL ?>modules/satu_sehat/ajax.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      document.getElementById('preview_json_content').innerText = data.json_payload;
    } else {
      document.getElementById('preview_json_content').innerText = 'Gagal menyusun bundle: ' + (data.message || 'Error tidak diketahui');
    }
  })
  .catch(err => {
    document.getElementById('preview_json_content').innerText = 'Kesalahan: ' + err.message;
  });
}

function copyPreviewJson() {
  const code = document.getElementById('preview_json_content').innerText;
  navigator.clipboard.writeText(code).then(() => {
    alert('JSON Bundle berhasil disalin ke clipboard!');
  });
}

// ─── Kirim Single Bundle Transaction ──────────────────────────
function kirimBundleSingle(noRawat, btn) {
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
  }

  const fd = new FormData();
  fd.append('action', 'kirim_bundle');
  fd.append('no_rawat', noRawat);

  fetch('<?= BASE_URL ?>modules/satu_sehat/ajax.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      alert(res.message || 'Bundle berhasil dikirim ke Satu Sehat!');
      window.location.reload();
    } else {
      alert('Gagal: ' + (res.message || 'Tidak dapat mengirim ke Satu Sehat'));
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Kirim Bundle';
      }
    }
  })
  .catch(err => {
    alert('Kesalahan jaringan: ' + err.message);
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Kirim Bundle';
    }
  });
}

// ─── Kirim Massal / Batch Bundling dengan Live Progress Bar ───
let isBatchRunning = false;
let stopRequested = false;

function toggleSelectAll(master) {
  const items = document.querySelectorAll('.check_item');
  items.forEach(it => it.checked = master.checked);
  updateSelectedCount();
}

function updateSelectedCount() {
  const checked = document.querySelectorAll('.check_item:checked');
  const count = checked.length;
  document.getElementById('selected_count').innerText = count;
  document.getElementById('btn_batch_sync').disabled = (count === 0);
}

function stopBatchSync() {
  if (isBatchRunning) {
    stopRequested = true;
    const btn = document.getElementById('btn_stop_batch');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghentikan...';
  }
}

function closeAndReload() {
  closeSSModal('modalBatchProgress');
  window.location.reload();
}

async function kirimBundlingMassal() {
  const checked = document.querySelectorAll('.check_item:checked');
  const list = [];
  checked.forEach(it => {
    list.push({
      no_rawat: it.value,
      nm_pasien: it.dataset.pasien || it.value
    });
  });

  if (list.length === 0) {
    alert('Pilih setidaknya satu kunjungan untuk dikirim.');
    return;
  }

  // Buka Modal Progress Bar
  openSSModal('modalBatchProgress');

  const totalItems = list.length;
  document.getElementById('stat_prog_total').innerText = totalItems;
  document.getElementById('stat_prog_current').innerText = '0';
  document.getElementById('stat_prog_success').innerText = '0';
  document.getElementById('stat_prog_fail').innerText = '0';
  document.getElementById('progress_pct_text').innerText = '0%';
  document.getElementById('progress_bar_fill').style.width = '0%';
  document.getElementById('progress_bar_fill').style.background = 'linear-gradient(90deg, #0284c7, #10b981)';
  document.getElementById('progress_log_box').innerHTML = '';
  document.getElementById('btn_stop_batch').style.display = 'inline-flex';
  document.getElementById('btn_stop_batch').disabled = false;
  document.getElementById('btn_stop_batch').innerHTML = '<i class="fas fa-stop-circle"></i> Hentikan Proses';
  document.getElementById('btn_done_reload').style.display = 'none';
  document.getElementById('btn_close_progress_x').style.display = 'none';

  isBatchRunning = true;
  stopRequested  = false;

  let successCount = 0;
  let failCount    = 0;

  const logBox = document.getElementById('progress_log_box');
  const addLog = (html) => {
    const div = document.createElement('div');
    div.innerHTML = html;
    logBox.appendChild(div);
    logBox.scrollTop = logBox.scrollHeight;
  };

  addLog(`<div style="color:#94a3b8;font-style:italic;">Memulai transmisi ${totalItems} bundle pasien ke Satu Sehat Kemenkes RI...</div>`);

  for (let i = 0; i < totalItems; i++) {
    if (stopRequested) {
      addLog(`<div style="color:#f59e0b;font-weight:700;padding-top:4px;"><i class="fas fa-exclamation-triangle"></i> Proses transmisi dihentikan oleh pengguna pada antrian ke-${i + 1}.</div>`);
      break;
    }

    const item = list[i];
    const itemNum = i + 1;
    document.getElementById('stat_prog_current').innerText = itemNum;
    document.getElementById('progress_status_text').innerHTML = `
      <i class="fas fa-spinner fa-spin" style="color:#0284c7;"></i> Mengirim [${itemNum}/${totalItems}]: <strong>${item.nm_pasien}</strong> <span style="color:#64748b;font-size:11px;">(${item.no_rawat})</span>...
    `;

    const now = new Date();
    const timeStr = String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0');

    try {
      const fd = new FormData();
      fd.append('action', 'kirim_bundle');
      fd.append('no_rawat', item.no_rawat);

      const res = await fetch('<?= BASE_URL ?>modules/satu_sehat/ajax.php', {
        method: 'POST',
        body: fd
      }).then(r => r.json());

      if (res && res.success) {
        successCount++;
        document.getElementById('stat_prog_success').innerText = successCount;
        addLog(`
          <div style="color:#86efac;padding:2px 0;">
            <span style="color:#64748b;">[${timeStr}]</span> <strong style="color:#38bdf8;">${item.no_rawat}</strong> (${item.nm_pasien}) &mdash; <i class="fas fa-check-circle" style="color:#4ade80;"></i> Berhasil! <span style="font-size:10.5px;color:#cbd5e1;">Encounter: ${res.encounter_id || 'OK'}</span>
          </div>
        `);
      } else {
        failCount++;
        document.getElementById('stat_prog_fail').innerText = failCount;
        const errMsg = res ? res.message : 'Respon tidak valid';
        addLog(`
          <div style="color:#fca5a5;padding:2px 0;">
            <span style="color:#64748b;">[${timeStr}]</span> <strong style="color:#38bdf8;">${item.no_rawat}</strong> (${item.nm_pasien}) &mdash; <i class="fas fa-times-circle" style="color:#f87171;"></i> Gagal: <span style="font-size:10.5px;color:#fecaca;">${errMsg}</span>
          </div>
        `);
      }
    } catch (err) {
      failCount++;
      document.getElementById('stat_prog_fail').innerText = failCount;
      addLog(`
        <div style="color:#fca5a5;padding:2px 0;">
          <span style="color:#64748b;">[${timeStr}]</span> <strong style="color:#38bdf8;">${item.no_rawat}</strong> (${item.nm_pasien}) &mdash; <i class="fas fa-times-circle" style="color:#f87171;"></i> Error Jaringan: <span style="font-size:10.5px;">${err.message}</span>
        </div>
      `);
    }

    // Update Progress Bar
    const pct = Math.round((itemNum / totalItems) * 100);
    document.getElementById('progress_pct_text').innerText = pct + '%';
    document.getElementById('progress_bar_fill').style.width = pct + '%';
  }

  isBatchRunning = false;

  // Final Summary
  if (stopRequested) {
    document.getElementById('progress_status_text').innerHTML = `
      <span style="color:#f59e0b;font-weight:700;"><i class="fas fa-exclamation-circle"></i> Proses Dihentikan. Selesai: ${successCount} berhasil, ${failCount} gagal.</span>
    `;
  } else {
    document.getElementById('progress_pct_text').innerText = '100%';
    document.getElementById('progress_bar_fill').style.width = '100%';
    document.getElementById('progress_status_text').innerHTML = `
      <span style="color:#16a34a;font-weight:800;"><i class="fas fa-check-double"></i> Seluruh Antrian Selesai Diproses! (${successCount} Berhasil, ${failCount} Gagal)</span>
    `;
    addLog(`<div style="color:#38bdf8;font-weight:700;margin-top:6px;border-top:1px dashed #334155;padding-top:6px;">🎉 Selesai! Berhasil: ${successCount} | Gagal: ${failCount}</div>`);
  }

  document.getElementById('btn_stop_batch').style.display = 'none';
  document.getElementById('btn_done_reload').style.display = 'inline-flex';
  document.getElementById('btn_close_progress_x').style.display = 'block';
}

// ─── Lihat Log Respon ─────────────────────────────────────────
function viewResponseLog(noRawat) {
  openSSModal('modalLogResp');
  const contentEl = document.getElementById('log_resp_content');
  contentEl.innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Memuat log respon...</div>';

  const fd = new FormData();
  fd.append('action', 'get_response_log');
  fd.append('no_rawat', noRawat);

  fetch('<?= BASE_URL ?>modules/satu_sehat/ajax.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      contentEl.innerHTML = `
        <div style="display:flex;flex-direction:column;gap:10px;font-size:12px;">
          <div style="background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;">
            <div>No. Rawat: <strong>${data.no_rawat}</strong></div>
            <div>Status Transmisi: <span class="badge badge-success">${data.status_sync}</span></div>
            <div>Waktu Sync: <strong>${data.last_sync_at}</strong></div>
            <div style="margin-top:4px;">Encounter ID: <code style="font-weight:700;color:#0284c7;">${data.id_encounter || '-'}</code></div>
            <div>Condition ID: <code style="font-weight:700;color:#16a34a;">${data.id_condition || '-'}</code></div>
          </div>
          <div>
            <div style="font-weight:700;color:#0f172a;margin-bottom:4px;">Raw Response Payload Kemenkes:</div>
            <pre style="background:#0f172a;color:#38bdf8;padding:12px;border-radius:8px;font-size:11px;font-family:Consolas, monospace;max-height:300px;overflow-y:auto;white-space:pre-wrap;">${data.response_payload || '(kosong)'}</pre>
          </div>
        </div>
      `;
    } else {
      contentEl.innerHTML = `<div style="color:#ef4444;font-size:12px;">${data.message}</div>`;
    }
  })
  .catch(err => {
    contentEl.innerHTML = `<div style="color:#ef4444;font-size:12px;">Kesalahan: ${err.message}</div>`;
  });
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
