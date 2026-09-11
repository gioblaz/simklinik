<?php
/**
 * SIMKlinik — Integrasi E-RM BPJS Kesehatan (e-Rekam Medis Elektronik)
 */

$page_title    = 'Integrasi E-RM BPJS';
$active_module = 'bpjs_emr';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('bpjs_emr');

// Load BPJS EMR Settings
$consid   = ($conn->query("SELECT value FROM mlite_settings WHERE module='bpjs_emr' AND field='consid' LIMIT 1")->fetch_assoc()['value'] ?? '');
$userkey  = ($conn->query("SELECT value FROM mlite_settings WHERE module='bpjs_emr' AND field='userkey' LIMIT 1")->fetch_assoc()['value'] ?? '');
$koders   = ($conn->query("SELECT value FROM mlite_settings WHERE module='bpjs_emr' AND field='koders' LIMIT 1")->fetch_assoc()['value'] ?? '');
$baseurl  = ($conn->query("SELECT value FROM mlite_settings WHERE module='bpjs_emr' AND field='baseurl' LIMIT 1")->fetch_assoc()['value'] ?? 'https://apijkn-dev.bpjs-kesehatan.go.id/erekammedis_dev/');

$is_configured = !empty($consid) && !empty($userkey);

// Data Rekam Medis Pasien Siap Dikirim ke BPJS
$today = date('Y-m-d');
$emr_res = $conn->query("
    SELECT r.no_rawat, r.tgl_registrasi, r.jam_reg, r.stts,
           p.nm_pasien, p.no_rkm_medis, p.no_peserta,
           d.nm_dokter, pol.nm_poli,
           (SELECT COUNT(*) FROM pemeriksaan_ralan pr WHERE pr.no_rawat = r.no_rawat) as has_soap,
           (SELECT GROUP_CONCAT(dp.kd_penyakit SEPARATOR ', ') FROM diagnosa_pasien dp WHERE dp.no_rawat = r.no_rawat) as diagnosa_codes
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    WHERE (r.kd_pj = 'BPJ' OR p.kd_pj = 'BPJ' OR p.no_peserta != '')
      AND r.tgl_registrasi = '$today'
      AND r.stts = 'Sudah'
    ORDER BY r.jam_reg DESC
");
$emr_list = [];
if ($emr_res) while ($row = $emr_res->fetch_assoc()) $emr_list[] = $row;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Integrasi E-RM BPJS (e-Rekam Medis)</h1>
    <p class="page-subtitle">Kepatuhan Rekam Medis Elektronik Terintegrasi dengan Web Service BPJS Kesehatan</p>
  </div>
  <div class="page-actions">
    <a href="<?= BASE_URL ?>modules/settings/index.php" class="btn btn-outline">
      <i class="fas fa-cog"></i> Pengaturan Kredensial
    </a>
  </div>
</div>

<!-- ─── Status Koneksi Card ──────────────────────────────── -->
<div class="card mb-16" style="border-left:5px solid <?= $is_configured ? 'var(--success)' : 'var(--warning)' ?>;">
  <div class="card-body" style="padding:16px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="width:44px;height:44px;background:<?= $is_configured ? 'var(--success-bg)' : 'var(--warning-bg)' ?>;border-radius:12px;display:flex;align-items:center;justify-content:center;color:<?= $is_configured ? 'var(--success)' : 'var(--warning)' ?>;font-size:20px;">
        <i class="fas fa-laptop-medical"></i>
      </div>
      <div>
        <div style="font-size:15px;font-weight:700;color:var(--gray-900);">
          <?= $is_configured ? 'Service e-Rekam Medis BPJS Siap Terhubung' : 'Kredensial E-RM BPJS Belum Lengkap' ?>
        </div>
        <div style="font-size:12px;color:var(--gray-500);margin-top:2px;">
          Kode Faskes: <code><?= htmlspecialchars($koders ?: '(kosong)') ?></code> | Base URL: <code><?= htmlspecialchars($baseurl) ?></code>
        </div>
      </div>
    </div>
    <span class="integration-badge <?= $is_configured ? 'connected' : 'disconnected' ?>" style="font-size:12px;padding:6px 14px;">
      <span class="dot"></span><?= $is_configured ? 'E-RM Ready' : 'Belum Konfigurasi' ?>
    </span>
  </div>
</div>

<!-- ─── Antrian Transmisi E-RM ───────────────────────────── -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-upload text-success"></i> Berkas E-RM Siap Dikirim ke BPJS</div>
    <span style="font-size:12px;color:var(--gray-500);"><?= count($emr_list) ?> Rekam Medis</span>
  </div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($emr_list)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-notes-medical"></i></div>
        <div class="empty-state-title">Tidak ada antrian pengiriman E-RM BPJS</div>
        <div class="empty-state-desc">Pemeriksaan medis pasien BPJS yang telah selesai akan siap dikirim ke BPJS di sini.</div>
      </div>
    <?php else: ?>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>No. Rawat</th>
              <th>Pasien BPJS</th>
              <th>No. Kartu BPJS</th>
              <th>Poli / Dokter</th>
              <th>Diagnosa ICD-10</th>
              <th>Kelengkapan SOAP</th>
              <th style="width:160px;text-align:center;">Kirim E-RM</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($emr_list as $e): ?>
              <tr>
                <td>
                  <span style="font-family:monospace;font-size:11px;font-weight:700;color:var(--gray-600);">
                    <?= htmlspecialchars($e['no_rawat']) ?>
                  </span>
                </td>
                <td>
                  <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($e['nm_pasien']) ?></div>
                  <div style="font-size:11px;color:var(--gray-400);">RM: <?= $e['no_rkm_medis'] ?></div>
                </td>
                <td>
                  <span style="font-family:monospace;font-weight:600;color:var(--primary-600);font-size:12px;">
                    <?= htmlspecialchars($e['no_peserta'] ?: '-') ?>
                  </span>
                </td>
                <td>
                  <div style="font-size:12px;"><?= htmlspecialchars($e['nm_poli']) ?></div>
                  <div style="font-size:11px;color:var(--gray-500);"><?= htmlspecialchars($e['nm_dokter']) ?></div>
                </td>
                <td>
                  <span style="font-family:monospace;font-size:11px;font-weight:600;color:var(--gray-800);">
                    <?= htmlspecialchars($e['diagnosa_codes'] ?: '-') ?>
                  </span>
                </td>
                <td>
                  <span class="badge <?= $e['has_soap']?'badge-success':'badge-danger' ?>">
                    <?= $e['has_soap'] ? 'Lengkap (SOAP)' : 'Belum Ada' ?>
                  </span>
                </td>
                <td style="text-align:center;">
                  <button type="button" class="btn btn-sm btn-success" onclick="kirimBpjsEmr('<?= $e['no_rawat'] ?>', this)">
                    <i class="fas fa-paper-plane"></i> Kirim E-RM
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function kirimBpjsEmr(no_rawat, btn) {
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
  }

  showToast(`Mengirim payload Rekam Medis (${no_rawat}) ke BPJS e-RM...`, 'info');

  const fd = new FormData();
  fd.append('action', 'kirim_erm');
  fd.append('no_rawat', no_rawat);

  fetch(`<?= BASE_URL ?>modules/bpjs_emr/ajax.php`, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      if (btn) {
        btn.disabled = false;
        btn.className = 'btn btn-sm btn-secondary';
        btn.innerHTML = '<i class="fas fa-check-double"></i> Terkirim E-RM';
      }
      showToast(res.message, 'success');
    } else {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim E-RM';
      }
      showToast(res.message || 'Gagal mengirim E-RM', 'warning');
    }
  })
  .catch(() => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim E-RM';
    }
    showToast('Terjadi gangguan jaringan saat mengirim ke BPJS E-RM', 'danger');
  });
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
