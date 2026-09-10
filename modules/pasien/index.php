<?php
/**
 * SIMKlinik — Daftar Pasien
 */

$page_title    = 'Data Pasien';
$active_module = 'pasien';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// ─── Filter & Pencarian ──────────────────────────────────────
$search  = sanitize($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset  = ($page - 1) * $per_page;

$where = "1=1";
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (p.nm_pasien LIKE '%$s%' OR p.no_rkm_medis LIKE '%$s%' OR p.no_ktp LIKE '%$s%' OR p.no_peserta LIKE '%$s%' OR p.no_tlp LIKE '%$s%')";
}

// Total data
$total_result = $conn->query("SELECT COUNT(*) as t FROM pasien p WHERE $where");
$total        = $total_result ? (int)$total_result->fetch_assoc()['t'] : 0;
$total_pages  = (int)ceil($total / $per_page);

// Data pasien
$result = $conn->query("
    SELECT p.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir, p.alamat,
           p.no_tlp, p.no_ktp, p.no_peserta, p.tgl_daftar,
           pj.png_jawab as nm_penjab,
           (SELECT COUNT(*) FROM reg_periksa r WHERE r.no_rkm_medis = p.no_rkm_medis) as total_kunjungan,
           (SELECT r2.tgl_registrasi FROM reg_periksa r2 WHERE r2.no_rkm_medis = p.no_rkm_medis ORDER BY r2.tgl_registrasi DESC LIMIT 1) as last_visit
    FROM pasien p
    LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
    WHERE $where
    ORDER BY p.tgl_daftar DESC, p.nm_pasien ASC
    LIMIT $per_page OFFSET $offset
");

$pasien_list = [];
if ($result) {
    while ($row = $result->fetch_assoc()) $pasien_list[] = $row;
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Data Pasien</h1>
    <p class="page-subtitle">Total <strong><?= number_format($total) ?></strong> pasien terdaftar</p>
  </div>
  <div class="page-actions">
    <a href="<?= BASE_URL ?>modules/pasien/tambah.php" class="btn btn-primary">
      <i class="fas fa-user-plus"></i> Pasien Baru
    </a>
  </div>
</div>

<!-- ─── Filter & Search ──────────────────────────────────── -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px;">
    <form method="GET" action="" style="display:flex;gap:10px;align-items:center;">
      <div class="search-bar" style="flex:1;max-width:420px;">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Cari nama, No. RM, NIK, No. BPJS, telepon..."
               value="<?= htmlspecialchars($search) ?>" autocomplete="off">
      </div>
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-search"></i> Cari
      </button>
      <?php if ($search): ?>
        <a href="<?= BASE_URL ?>modules/pasien/index.php" class="btn btn-outline btn-sm">
          <i class="fas fa-times"></i> Reset
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- ─── Tabel Pasien ─────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-users"></i> Daftar Pasien</div>
    <?php if ($search): ?>
      <span style="font-size:12px;color:var(--gray-500);">
        Hasil pencarian: "<strong><?= htmlspecialchars($search) ?></strong>" — <?= $total ?> data
      </span>
    <?php endif; ?>
  </div>

  <div class="card-body" style="padding:0;">
    <?php if (empty($pasien_list)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
        <div class="empty-state-title">
          <?= $search ? 'Pasien tidak ditemukan' : 'Belum ada data pasien' ?>
        </div>
        <div class="empty-state-desc">
          <?= $search ? 'Coba kata kunci lain.' : '' ?>
          <a href="<?= BASE_URL ?>modules/pasien/tambah.php" class="btn btn-primary btn-sm" style="margin-top:12px;">
            <i class="fas fa-user-plus"></i> Tambah Pasien
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="table-wrapper">
        <table class="table" id="tablePasien">
          <thead>
            <tr>
              <th style="width:120px;">No. RM</th>
              <th>Nama Pasien</th>
              <th>JK</th>
              <th>Umur</th>
              <th>No. Telp</th>
              <th>Penjamin</th>
              <th>Kunjungan</th>
              <th>Terakhir Visit</th>
              <th style="width:120px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pasien_list as $p): ?>
              <tr>
                <td>
                  <span style="font-family:monospace;font-size:12px;color:var(--primary-600);font-weight:600;">
                    <?= htmlspecialchars($p['no_rkm_medis']) ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:50%;background:<?= $p['jk']==='L' ? 'var(--primary-50)' : '#fdf2f8' ?>;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:<?= $p['jk']==='L' ? 'var(--primary-600)' : '#9d174d' ?>;flex-shrink:0;">
                      <?= strtoupper(substr($p['nm_pasien'], 0, 1)) ?>
                    </div>
                    <div>
                      <div style="font-weight:500;font-size:13px;"><?= htmlspecialchars($p['nm_pasien']) ?></div>
                      <?php if (!empty($p['no_ktp'])): ?>
                        <div style="font-size:11px;color:var(--gray-400);">NIK: <?= htmlspecialchars($p['no_ktp']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td><?= icon_jk($p['jk']) ?></td>
                <td style="font-size:12px;"><?= hitung_umur($p['tgl_lahir']) ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($p['no_tlp'] ?: '-') ?></td>
                <td>
                  <?= badge_penjab($p['nm_penjab'] ?? 'Umum') ?>
                </td>
                <td style="text-align:center;">
                  <span style="font-weight:700;color:var(--gray-700);"><?= $p['total_kunjungan'] ?></span>
                  <span style="font-size:10px;color:var(--gray-400);display:block;">kunjungan</span>
                </td>
                <td style="font-size:11px;color:var(--gray-500);">
                  <?= $p['last_visit'] ? tgl_indo($p['last_visit']) : '<span style="color:var(--gray-300);">Belum pernah</span>' ?>
                </td>
                <td>
                  <div style="display:flex;gap:4px;">
                    <a href="<?= BASE_URL ?>modules/pasien/detail.php?rm=<?= urlencode($p['no_rkm_medis']) ?>"
                       class="btn btn-sm btn-outline-primary btn-icon" title="Detail Pasien">
                      <i class="fas fa-eye"></i>
                    </a>
                    <a href="<?= BASE_URL ?>modules/pendaftaran/tambah.php?rm=<?= urlencode($p['no_rkm_medis']) ?>"
                       class="btn btn-sm btn-success btn-icon" title="Daftarkan Kunjungan">
                      <i class="fas fa-plus"></i>
                    </a>
                    <a href="<?= BASE_URL ?>modules/pasien/edit.php?rm=<?= urlencode($p['no_rkm_medis']) ?>"
                       class="btn btn-sm btn-outline btn-icon" title="Edit">
                      <i class="fas fa-edit"></i>
                    </a>
                  </div>
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
            <span class="pagination-info">
              Halaman <?= $page ?> dari <?= $total_pages ?> (<?= number_format($total) ?> pasien)
            </span>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
