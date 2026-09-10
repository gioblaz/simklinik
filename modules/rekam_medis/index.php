<?php
/**
 * SIMKlinik — Rekam Medis (Daftar Antrian & Pemeriksaan)
 */

$page_title    = 'Rawat Jalan & Pemeriksaan';
$active_module = 'rekam_medis';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ─── Filter ──────────────────────────────────────────────────
$today     = date('Y-m-d');
$tgl       = sanitize($_GET['tgl'] ?? $today);
$kd_poli   = sanitize($_GET['kd_poli'] ?? '');
$kd_dokter = sanitize($_GET['kd_dokter'] ?? '');
$status    = sanitize($_GET['status'] ?? '');
$search    = sanitize($_GET['q'] ?? '');
$sort_by   = sanitize($_GET['sort_by'] ?? 'dokter_noreg');

$where = "r.tgl_registrasi = '$tgl'";
if ($kd_poli)   $where .= " AND r.kd_poli = '" . $conn->real_escape_string($kd_poli) . "'";
if ($kd_dokter) $where .= " AND r.kd_dokter = '" . $conn->real_escape_string($kd_dokter) . "'";
if ($status)    $where .= " AND r.stts = '" . $conn->real_escape_string($status) . "'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (p.nm_pasien LIKE '%$s%' OR p.no_rkm_medis LIKE '%$s%' OR r.no_rawat LIKE '%$s%')";
}

// Pengurutan (Sorting)
switch ($sort_by) {
    case 'noreg':
        $order_sql = "CAST(r.no_reg AS UNSIGNED) ASC, r.jam_reg ASC";
        break;
    case 'belum_jam':
        $order_sql = "(r.stts = 'Belum') DESC, r.jam_reg ASC";
        break;
    case 'jam_asc':
        $order_sql = "r.jam_reg ASC";
        break;
    case 'jam_desc':
        $order_sql = "r.jam_reg DESC";
        break;
    case 'nama_pasien':
        $order_sql = "p.nm_pasien ASC";
        break;
    case 'dokter_noreg':
    default:
        $order_sql = "d.nm_dokter ASC, CAST(r.no_reg AS UNSIGNED) ASC, r.jam_reg ASC";
        $sort_by   = 'dokter_noreg';
        break;
}

// Paginasi Rekam Medis
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

// Data kunjungan
$result = $conn->query("
    SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg,
           r.stts, r.status_lanjut, r.kd_dokter, r.kd_poli,
           p.nm_pasien, p.no_rkm_medis, p.jk, p.tgl_lahir, p.no_peserta,
           d.nm_dokter, pol.nm_poli, pj.png_jawab as nm_penjab,
           (SELECT COUNT(*) FROM pemeriksaan_ralan pr WHERE pr.no_rawat = r.no_rawat) as has_soap,
           (SELECT GROUP_CONCAT(CONCAT(dp.kd_penyakit, ' - ', py.nm_penyakit) SEPARATOR '<br>')
            FROM diagnosa_pasien dp
            JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
            WHERE dp.no_rawat = r.no_rawat) as diagnosa_list,
           (SELECT COUNT(*) FROM resep_obat ro WHERE ro.no_rawat = r.no_rawat) as has_resep
    FROM reg_periksa r
    JOIN pasien p      ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE $where
    GROUP BY r.no_rawat
    ORDER BY $order_sql
    LIMIT $per_page OFFSET $offset
");

$kunjungan_list = [];
if ($result) {
    while ($row = $result->fetch_assoc()) $kunjungan_list[] = $row;
}

// Poliklinik & Dokter list
$poli_list = [];
$rp = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status='1' ORDER BY nm_poli");
if ($rp) while ($row = $rp->fetch_assoc()) $poli_list[] = $row;

$dokter_list = [];
$rd = $conn->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1' ORDER BY nm_dokter");
if ($rd) while ($row = $rd->fetch_assoc()) $dokter_list[] = $row;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Pelayanan Rawat Jalan & Pemeriksaan Dokter</h1>
    <p class="page-subtitle">Pemeriksaan SOAP E-RM, Input Diagnosa ICD-10, Tindakan Medis, dan Resep Elektronik Pasien</p>
  </div>
  <div class="page-actions">
    <a href="<?= BASE_URL ?>modules/pendaftaran/index.php" class="btn btn-outline">
      <i class="fas fa-clipboard-list"></i> Antrian Pendaftaran
    </a>
  </div>
</div>

<!-- ─── Filter Bar ───────────────────────────────────────── -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px;">
    <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <div class="search-bar" style="flex:1;min-width:240px;max-width:320px;">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Cari Pasien / No. RM / No. Rawat..."
               value="<?= htmlspecialchars($search) ?>" autocomplete="off">
      </div>

      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:var(--gray-500);">Tanggal:</label>
        <input type="date" name="tgl" class="form-control" style="width:150px;"
               value="<?= $tgl ?>" max="<?= date('Y-m-d') ?>">
      </div>

      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:var(--gray-500);">Poli:</label>
        <select name="kd_poli" class="form-control" style="width:150px;">
          <option value="">— Semua Poli —</option>
          <?php foreach ($poli_list as $pl): ?>
            <option value="<?= $pl['kd_poli'] ?>" <?= $kd_poli===$pl['kd_poli'] ? 'selected':'' ?>>
              <?= htmlspecialchars($pl['nm_poli']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:var(--gray-500);">Dokter:</label>
        <select name="kd_dokter" class="form-control" style="width:170px;">
          <option value="">— Semua Dokter —</option>
          <?php foreach ($dokter_list as $dl): ?>
            <option value="<?= $dl['kd_dokter'] ?>" <?= $kd_dokter===$dl['kd_dokter'] ? 'selected':'' ?>>
              <?= htmlspecialchars($dl['nm_dokter']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:var(--gray-500);">Status:</label>
        <select name="status" class="form-control" style="width:120px;">
          <option value="">— Semua —</option>
          <option value="Belum"  <?= $status==='Belum' ? 'selected':'' ?>>Menunggu</option>
          <option value="Sudah"  <?= $status==='Sudah' ? 'selected':'' ?>>Sudah Diperiksa</option>
        </select>
      </div>

      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:12px;color:var(--gray-500);">Urutkan:</label>
        <select name="sort_by" class="form-control" style="width:170px;font-weight:600;color:var(--primary-700);">
          <option value="dokter_noreg" <?= $sort_by==='dokter_noreg' ? 'selected':'' ?>>👨‍⚕️ Dokter & No. Antrian</option>
          <option value="noreg"        <?= $sort_by==='noreg' ? 'selected':'' ?>>🔢 No. Antrian Saja</option>
          <option value="belum_jam"    <?= $sort_by==='belum_jam' ? 'selected':'' ?>>⏳ Menunggu & Jam</option>
          <option value="jam_asc"      <?= $sort_by==='jam_asc' ? 'selected':'' ?>>🕒 Jam Masuk (Terlama)</option>
          <option value="jam_desc"     <?= $sort_by==='jam_desc' ? 'selected':'' ?>>🕒 Jam Masuk (Terbaru)</option>
          <option value="nama_pasien"  <?= $sort_by==='nama_pasien' ? 'selected':'' ?>>🔤 Nama Pasien (A-Z)</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Terapkan</button>
      <?php if ($search || $kd_poli || $kd_dokter || $status || $sort_by !== 'dokter_noreg' || $tgl !== $today): ?>
        <a href="<?= BASE_URL ?>modules/rekam_medis/index.php" class="btn btn-outline btn-sm"><i class="fas fa-redo"></i> Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Tabel Antrian Rekam Medis ────────────────────────── -->
<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div class="card-title" style="display:flex;align-items:center;gap:8px;">
      <i class="fas fa-notes-medical"></i> 
      <span>Daftar Pasien Pemeriksaan</span>
      <span class="badge-online" id="liveSyncBadge" style="font-size:10px;font-weight:600;padding:2px 8px;" title="Pembaruan otomatis real-time aktif">
        <i class="fas fa-circle"></i> Live Sync Aktif
      </span>
    </div>

    <!-- Status Legend Indikator Warna Baris -->
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:11.5px;color:var(--gray-600);background:#f8fafc;padding:4px 12px;border-radius:20px;border:1px solid #e2e8f0;">
      <span style="font-weight:700;color:var(--gray-500);font-size:10.5px;text-transform:uppercase;letter-spacing:0.5px;">Warna Status:</span>
      <span style="display:inline-flex;align-items:center;gap:4px;">
        <span style="width:10px;height:10px;border-radius:50%;background:#ffffff;border:1.5px solid #cbd5e1;display:inline-block;"></span> Belum
      </span>
      <span style="display:inline-flex;align-items:center;gap:4px;">
        <span style="width:10px;height:10px;border-radius:50%;background:#22c55e;display:inline-block;"></span> TTV
      </span>
      <span style="display:inline-flex;align-items:center;gap:4px;">
        <span style="width:10px;height:10px;border-radius:50%;background:#3b82f6;display:inline-block;"></span> Berkas Diterima
      </span>
      <span style="display:inline-flex;align-items:center;gap:4px;">
        <span style="width:10px;height:10px;border-radius:50%;background:#ef4444;display:inline-block;"></span> Sudah
      </span>
      <span style="display:inline-flex;align-items:center;gap:4px;">
        <span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;display:inline-block;"></span> Batal
      </span>
    </div>

    <span style="font-size:12px;color:var(--gray-500);" id="totalKunjunganText">
      Total <strong><?= count($kunjungan_list) ?></strong> kunjungan pada <?= tgl_indo($tgl) ?>
    </span>
  </div>

  <div class="card-body" style="padding:0;">
    <?php if (empty($kunjungan_list)): ?>
      <div class="empty-state" id="emptyStateBox">
        <div class="empty-state-icon"><i class="fas fa-user-clock"></i></div>
        <div class="empty-state-title">Tidak ada pasien yang sesuai kriteria</div>
        <div class="empty-state-desc">Silakan periksa tanggal atau daftarkan pasien di modul Pendaftaran.</div>
      </div>
      <div class="table-wrapper" id="tableWrapperBox" style="display:none;">
        <table class="table">
          <thead>
            <tr>
              <th style="width:40px;">Antrian</th>
              <th>Pasien</th>
              <th>Poli / Dokter</th>
              <th>Penjamin</th>
              <th>Diagnosa Utama</th>
              <th>Kelengkapan E-RM</th>
              <th>Status</th>
              <th style="width:150px;text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody id="antrianTableBody"></tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="table-wrapper" id="tableWrapperBox">
        <table class="table">
          <thead>
            <tr>
              <th style="width:40px;">Antrian</th>
              <th>Pasien</th>
              <th>Poli / Dokter</th>
              <th>Penjamin</th>
              <th>Diagnosa Utama</th>
              <th>Kelengkapan E-RM</th>
              <th>Status</th>
              <th style="width:150px;text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody id="antrianTableBody">
            <?php foreach ($kunjungan_list as $k): ?>
              <tr id="row-<?= htmlspecialchars($k['no_rawat']) ?>" class="<?= get_status_row_class($k['stts']) ?>" style="<?= get_status_row_style($k['stts']) ?>">
                <td style="text-align:center;font-weight:700;font-size:15px;color:var(--primary-700);">
                  <?= htmlspecialchars($k['no_reg']) ?>
                </td>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:50%;background:<?= $k['jk']==='L' ? 'var(--primary-50)' : '#fdf2f8' ?>;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:<?= $k['jk']==='L' ? 'var(--primary-600)' : '#9d174d' ?>;flex-shrink:0;">
                      <?= strtoupper(substr($k['nm_pasien'], 0, 1)) ?>
                    </div>
                    <div>
                      <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($k['nm_pasien']) ?></div>
                      <div style="font-size:11px;color:var(--gray-400);">
                        RM: <strong style="color:var(--primary-600);"><?= $k['no_rkm_medis'] ?></strong> &nbsp;|&nbsp;
                        <?= icon_jk($k['jk']) ?> <?= hitung_umur($k['tgl_lahir']) ?>
                      </div>
                      <div style="font-size:10px;color:var(--gray-400);font-family:monospace;">
                        <?= $k['no_rawat'] ?>
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <div style="font-size:12px;font-weight:600;color:var(--gray-800);"><?= htmlspecialchars($k['nm_poli'] ?? '-') ?></div>
                  <div style="font-size:11px;color:var(--gray-500);"><?= htmlspecialchars($k['nm_dokter'] ?? '-') ?></div>
                  <div style="font-size:10px;color:var(--gray-400);"><?= substr($k['jam_reg'], 0, 5) ?> WIB</div>
                </td>
                <td>
                  <span class="badge badge-<?= str_contains(strtolower($k['nm_penjab'] ?? ''), 'bpjs') ? 'primary' : 'secondary' ?>">
                    <?= htmlspecialchars($k['nm_penjab'] ?? 'Umum') ?>
                  </span>
                </td>
                <td>
                  <?php if (!empty($k['diagnosa_list'])): ?>
                    <div style="font-size:11px;color:var(--gray-700);line-height:1.4;">
                      <?= $k['diagnosa_list'] ?>
                    </div>
                  <?php else: ?>
                    <span style="font-size:11px;color:var(--gray-300);font-style:italic;">Belum ada diagnosa</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <?php if ($k['has_soap'] > 0): ?>
                      <span class="badge badge-success" title="SOAP Tersimpan"><i class="fas fa-check" style="margin-right:2px;"></i> SOAP</span>
                    <?php else: ?>
                      <span class="badge badge-secondary" title="SOAP Belum Diisi">SOAP</span>
                    <?php endif; ?>

                    <?php if (!empty($k['diagnosa_list'])): ?>
                      <span class="badge badge-success" title="Diagnosa ICD-10 Tersimpan"><i class="fas fa-check" style="margin-right:2px;"></i> ICD-10</span>
                    <?php else: ?>
                      <span class="badge badge-secondary">ICD-10</span>
                    <?php endif; ?>

                    <?php if ($k['has_resep'] > 0): ?>
                      <span class="badge badge-info" title="Resep Obat Diberikan"><i class="fas fa-pills" style="margin-right:2px;"></i> Resep</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td id="status-cell-<?= htmlspecialchars($k['no_rawat']) ?>">
                  <div style="display:flex;align-items:center;gap:6px;">
                    <span id="badge-status-<?= htmlspecialchars($k['no_rawat']) ?>"><?= badge_status($k['stts']) ?></span>
                    <button type="button" class="btn btn-sm btn-outline btn-icon" style="width:24px;height:24px;padding:0;font-size:10px;border-radius:4px;"
                            title="Ubah Status Pasien"
                            onclick="openUbahStatusModal('<?= htmlspecialchars($k['no_rawat']) ?>', '<?= htmlspecialchars(addslashes($k['nm_pasien'])) ?>', '<?= htmlspecialchars($k['stts']) ?>')">
                      <i class="fas fa-edit"></i>
                    </button>
                  </div>
                </td>
                <td style="text-align:center;">
                  <div style="display:flex;gap:5px;justify-content:center;align-items:center;">
                    <a href="<?= BASE_URL ?>modules/rekam_medis/periksa.php?no_rawat=<?= urlencode($k['no_rawat']) ?>"
                       class="btn btn-sm <?= $k['stts']==='Sudah' ? 'btn-outline-primary' : 'btn-primary' ?>" title="Periksa Medis (SOAP)">
                      <i class="fas fa-stethoscope"></i> <?= $k['stts']==='Sudah' ? 'Edit EMR' : 'Periksa' ?>
                    </a>
                    <?php if ($k['stts'] === 'Sudah'): ?>
                      <a href="<?= BASE_URL ?>modules/rekam_medis/cetak_resume.php?no_rawat=<?= urlencode($k['no_rawat']) ?>"
                         target="_blank" class="btn btn-sm btn-outline btn-icon" title="Cetak Resume Medis">
                        <i class="fas fa-print"></i>
                      </a>
                    <?php endif; ?>
                  </div>
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

<!-- ─── Modal Ubah Status Registrasi Pasien ─────────────────── -->
<div id="modalUbahStatus" class="modal-backdrop" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(3px);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#ffffff;border-radius:14px;width:100%;max-width:480px;box-shadow:0 20px 40px rgba(0,0,0,0.2);overflow:hidden;animation:slideDown 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
    
    <div style="padding:16px 20px;background:linear-gradient(135deg, #0f766e 0%, #115e59 100%);color:#ffffff;display:flex;justify-content:space-between;align-items:center;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-exchange-alt" style="font-size:14px;"></i>
        </div>
        <div>
          <h3 style="margin:0;font-size:15px;font-weight:700;color:#ffffff;">Ubah Status Kunjungan</h3>
          <p style="margin:0;font-size:11px;color:rgba(255,255,255,0.8);" id="modalStatusSubtitle">No. Rawat: -</p>
        </div>
      </div>
      <button type="button" onclick="closeUbahStatusModal()" style="background:none;border:none;color:#ffffff;font-size:18px;cursor:pointer;opacity:0.8;">&times;</button>
    </div>

    <form id="formUbahStatus" onsubmit="submitUbahStatus(event)" style="padding:20px;">
      <input type="hidden" name="no_rawat" id="statusModalNoRawat">
      
      <div style="margin-bottom:14px;background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;">
        <div style="font-size:11px;color:#64748b;">Nama Pasien:</div>
        <div style="font-size:14px;font-weight:700;color:#0f172a;" id="statusModalNamaPasien">-</div>
      </div>

      <label style="font-size:12.5px;font-weight:700;color:#334155;margin-bottom:8px;display:block;">
        Pilih Status Kunjungan:
      </label>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:20px;">
        <!-- Status: Belum (Putih) -->
        <label style="border:1.5px solid #cbd5e1;border-radius:8px;padding:10px 12px;background:#ffffff;cursor:pointer;display:flex;align-items:center;gap:8px;transition:all 0.2s;" class="status-option-label">
          <input type="radio" name="stts" value="Belum" required style="cursor:pointer;">
          <div>
            <div style="font-size:12.5px;font-weight:700;color:#334155;">Belum</div>
            <div style="font-size:10.5px;color:#64748b;">Menunggu panggilan</div>
          </div>
        </label>

        <!-- Status: TTV (Hijau) -->
        <label style="border:1.5px solid #86efac;border-radius:8px;padding:10px 12px;background:#f0fdf4;cursor:pointer;display:flex;align-items:center;gap:8px;transition:all 0.2s;" class="status-option-label">
          <input type="radio" name="stts" value="TTV" required style="cursor:pointer;">
          <div>
            <div style="font-size:12.5px;font-weight:700;color:#15803d;">TTV</div>
            <div style="font-size:10.5px;color:#16a34a;">Pemeriksaan vital</div>
          </div>
        </label>

        <!-- Status: Berkas Diterima (Biru) -->
        <label style="border:1.5px solid #93c5fd;border-radius:8px;padding:10px 12px;background:#eff6ff;cursor:pointer;display:flex;align-items:center;gap:8px;transition:all 0.2s;" class="status-option-label">
          <input type="radio" name="stts" value="Berkas Diterima" required style="cursor:pointer;">
          <div>
            <div style="font-size:12.5px;font-weight:700;color:#1d4ed8;">Berkas Diterima</div>
            <div style="font-size:10.5px;color:#3b82f6;">RM siap di poli</div>
          </div>
        </label>

        <!-- Status: Sudah (Merah) -->
        <label style="border:1.5px solid #fca5a5;border-radius:8px;padding:10px 12px;background:#fef2f2;cursor:pointer;display:flex;align-items:center;gap:8px;transition:all 0.2s;" class="status-option-label">
          <input type="radio" name="stts" value="Sudah" required style="cursor:pointer;">
          <div>
            <div style="font-size:12.5px;font-weight:700;color:#b91c1c;">Sudah</div>
            <div style="font-size:10.5px;color:#dc2626;">Selesai diperiksa</div>
          </div>
        </label>

        <!-- Status: Batal (Kuning) -->
        <label style="border:1.5px solid #fcd34d;border-radius:8px;padding:10px 12px;background:#fffbeb;cursor:pointer;display:flex;align-items:center;gap:8px;transition:all 0.2s;" class="status-option-label">
          <input type="radio" name="stts" value="Batal" required style="cursor:pointer;">
          <div>
            <div style="font-size:12.5px;font-weight:700;color:#b45309;">Batal</div>
            <div style="font-size:10.5px;color:#d97706;">Batal kunjungan</div>
          </div>
        </label>

        <!-- Status: Dirujuk -->
        <label style="border:1.5px solid #e2e8f0;border-radius:8px;padding:10px 12px;background:#f8fafc;cursor:pointer;display:flex;align-items:center;gap:8px;transition:all 0.2s;" class="status-option-label">
          <input type="radio" name="stts" value="Dirujuk" required style="cursor:pointer;">
          <div>
            <div style="font-size:12.5px;font-weight:700;color:#475569;">Dirujuk</div>
            <div style="font-size:10.5px;color:#64748b;">Rujuk ke RS / FKRTL</div>
          </div>
        </label>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:12px;border-top:1px solid #f1f5f9;">
        <button type="button" class="btn btn-secondary" onclick="closeUbahStatusModal()">Batal</button>
        <button type="submit" id="btnSimpanStatus" class="btn btn-primary" style="padding:8px 20px;font-weight:700;">
          <i class="fas fa-check"></i> Simpan Perubahan
        </button>
      </div>
    </form>

  </div>
</div>

<script>
// ─── Modal Ubah Status Registrasi ─────────────────────────────
function openUbahStatusModal(noRawat, nmPasien, currentStatus) {
  document.getElementById('statusModalNoRawat').value = noRawat;
  document.getElementById('statusModalNamaPasien').textContent = nmPasien;
  document.getElementById('modalStatusSubtitle').textContent = `No. Rawat: ${noRawat}`;

  const radios = document.getElementsByName('stts');
  for (let r of radios) {
    r.checked = (r.value === currentStatus);
  }

  const modal = document.getElementById('modalUbahStatus');
  modal.style.display = 'flex';
}

function closeUbahStatusModal() {
  const modal = document.getElementById('modalUbahStatus');
  modal.style.display = 'none';
}

// Close modal when clicking on backdrop
window.addEventListener('click', function(e) {
  const modal = document.getElementById('modalUbahStatus');
  if (e.target === modal) {
    closeUbahStatusModal();
  }
});

function submitUbahStatus(e) {
  e.preventDefault();
  const form = document.getElementById('formUbahStatus');
  const btn = document.getElementById('btnSimpanStatus');
  const formData = new FormData(form);
  const noRawat = formData.get('no_rawat');
  const stts = formData.get('stts');

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

  fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=ubah_status`, {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Simpan Perubahan';

    if (res.success) {
      showToast(res.message, 'success');
      closeUbahStatusModal();

      // Update baris tabel secara real-time tanpa refresh
      const row = document.getElementById(`row-${noRawat}`);
      if (row) {
        // Reset classes
        row.className = res.row_class || '';
        if (res.row_style) {
          row.style.cssText = res.row_style;
        }
      }

      // Update badge status
      const badgeEl = document.getElementById(`badge-status-${noRawat}`);
      if (badgeEl && res.badge_html) {
        badgeEl.innerHTML = res.badge_html;
      }
    } else {
      showToast(res.message || 'Gagal mengubah status', 'danger');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Simpan Perubahan';
    showToast('Terjadi kesalahan jaringan: ' + err.message, 'danger');
  });
}

// ─── Real-Time Live Background Polling ────────────────────────
(function() {
  let lastCount = <?= (int)$total_rows ?>;
  const currentPage = <?= (int)$page ?>;
  const perPage = <?= (int)$per_page ?>;
  const filterTgl = '<?= urlencode($tgl) ?>';
  const filterPoli = '<?= urlencode($kd_poli) ?>';
  const filterDokter = '<?= urlencode($kd_dokter) ?>';
  const filterStatus = '<?= urlencode($status) ?>';
  const filterSort = '<?= urlencode($sort_by) ?>';
  const filterQ = '<?= urlencode($search) ?>';
  const isToday = (filterTgl === '<?= date('Y-m-d') ?>');

  function pollLiveAntrian() {
    // Hanya polling jika melihat tanggal hari ini
    if (!isToday) return;

    fetch(`<?= BASE_URL ?>modules/rekam_medis/ajax.php?action=get_live_antrian&tgl=${filterTgl}&kd_poli=${filterPoli}&kd_dokter=${filterDokter}&status=${filterStatus}&sort_by=${filterSort}&q=${filterQ}&page=${currentPage}&per_page=${perPage}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        const tbody = document.getElementById('antrianTableBody');
        const countEl = document.getElementById('totalKunjunganText');
        const emptyBox = document.getElementById('emptyStateBox');
        const tableBox = document.getElementById('tableWrapperBox');

        // Notifikasi jika ada pasien baru masuk antrian
        if (res.count > lastCount) {
          showToast(`🔔 Pasien baru masuk antrian! (+${res.count - lastCount} Pasien)`, 'success');
        }

        lastCount = res.count;

        if (countEl) {
          countEl.innerHTML = `Total <strong>${res.count}</strong> kunjungan pada <?= tgl_indo($tgl) ?>`;
        }

        if (tbody && res.html) {
          tbody.innerHTML = res.html;
        }

        if (res.count > 0) {
          if (emptyBox) emptyBox.style.display = 'none';
          if (tableBox) tableBox.style.display = 'block';
        }
      }
    })
    .catch(err => console.debug('Live sync heartbeat:', err));
  }

  // Jalankan polling setiap 10 detik
  setInterval(pollLiveAntrian, 10000);
})();
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
