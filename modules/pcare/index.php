<?php
/**
 * SIMKlinik — Integrasi PCare BPJS Kesehatan (Bridging Faskes Tingkat Pertama)
 * Fitur: Validasi Peserta, Bridging Kunjungan, Pembuatan Rujukan Subspesialis/Khusus, & Cetak Surat Rujukan
 */

$page_title    = 'Integrasi PCare BPJS';
$active_module = 'pcare';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('pcare');

// Load PCare Settings
$consid   = ($conn->query("SELECT value FROM mlite_settings WHERE module='icare' AND field='consid' LIMIT 1")->fetch_assoc()['value'] ?? '');
$userkey  = ($conn->query("SELECT value FROM mlite_settings WHERE module='icare' AND field='userkey' LIMIT 1")->fetch_assoc()['value'] ?? '');
$username = ($conn->query("SELECT value FROM mlite_settings WHERE module='icare' AND field='usernameICare' LIMIT 1")->fetch_assoc()['value'] ?? '');
$url      = ($conn->query("SELECT value FROM mlite_settings WHERE module='icare' AND field='urlPCare' LIMIT 1")->fetch_assoc()['value'] ?? 'https://apijkn.bpjs-kesehatan.go.id/pcare-rest');

$is_configured = !empty($consid) && !empty($userkey);

// 1. Data Kunjungan Peserta BPJS Hari Ini
$today = date('Y-m-d');
$page_kunj     = max(1, (int)($_GET['page'] ?? 1));
$per_page_kunj = 25;
$offset_kunj   = ($page_kunj - 1) * $per_page_kunj;

$count_visits = $conn->query("
    SELECT COUNT(DISTINCT r.no_rawat) as total
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
    WHERE (r.kd_pj = 'BPJ' OR pj.png_jawab LIKE '%BPJS%' OR pj.png_jawab LIKE '%bpjs%')
      AND r.tgl_registrasi = '$today'
");
$total_visits = $count_visits ? (int)$count_visits->fetch_assoc()['total'] : 0;
$total_pages_kunj = max(1, (int)ceil($total_visits / $per_page_kunj));

$pag_kunj = [
    'page'        => $page_kunj,
    'per_page'    => $per_page_kunj,
    'total'       => $total_visits,
    'total_pages' => $total_pages_kunj,
    'offset'      => $offset_kunj,
    'has_prev'    => $page_kunj > 1,
    'has_next'    => $page_kunj < $total_pages_kunj,
];

$bpjs_visits = $conn->query("
    SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg, r.stts, r.kd_dokter, r.kd_poli,
           p.nm_pasien, p.no_rkm_medis, p.no_peserta, p.no_ktp, p.tgl_lahir, p.jk, p.alamat,
           d.nm_dokter, pol.nm_poli,
           pr.suhu_tubuh, pr.tensi, pr.nadi, pr.respirasi, pr.tinggi, pr.berat, pr.spo2, pr.gcs, pr.keluhan,
           (SELECT COUNT(*) FROM diagnosa_pasien dp WHERE dp.no_rawat = r.no_rawat) as has_diag,
           (SELECT dp.kd_penyakit FROM diagnosa_pasien dp WHERE dp.no_rawat = r.no_rawat ORDER BY dp.prioritas ASC LIMIT 1) as kd_diag_utama,
           (SELECT pen.nm_penyakit FROM diagnosa_pasien dp LEFT JOIN penyakit pen ON dp.kd_penyakit = pen.kd_penyakit WHERE dp.no_rawat = r.no_rawat ORDER BY dp.prioritas ASC LIMIT 1) as nm_diag_utama,
           (SELECT pd.noUrut FROM pcare_pendaftaran pd WHERE pd.no_rawat = r.no_rawat LIMIT 1) as no_urut_pcare,
           (SELECT ku.noKunjungan FROM pcare_kunjungan_umum ku WHERE ku.no_rawat = r.no_rawat LIMIT 1) as no_kunjungan_pcare,
           (SELECT rj.noKunjungan FROM pcare_rujuk_subspesialis rj WHERE rj.no_rawat = r.no_rawat LIMIT 1) as no_rujukan_sub,
           (SELECT rk.noKunjungan FROM pcare_rujuk_khusus rk WHERE rk.no_rawat = r.no_rawat LIMIT 1) as no_rujukan_khusus
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
    LEFT JOIN (
        SELECT pr1.* FROM pemeriksaan_ralan pr1
        JOIN (SELECT no_rawat, MAX(CONCAT(tgl_perawatan, ' ', jam_rawat)) as max_time FROM pemeriksaan_ralan GROUP BY no_rawat) pr2
          ON pr1.no_rawat = pr2.no_rawat AND CONCAT(pr1.tgl_perawatan, ' ', pr1.jam_rawat) = pr2.max_time
    ) pr ON r.no_rawat = pr.no_rawat
    WHERE (r.kd_pj = 'BPJ' OR pj.png_jawab LIKE '%BPJS%' OR pj.png_jawab LIKE '%bpjs%')
      AND r.tgl_registrasi = '$today'
    GROUP BY r.no_rawat
    ORDER BY r.jam_reg ASC
    LIMIT $per_page_kunj OFFSET $offset_kunj
");
$visit_list = [];
if ($bpjs_visits) while ($row = $bpjs_visits->fetch_assoc()) $visit_list[] = $row;

// 2. Data Riwayat Surat Rujukan Terkirim
$rujukan_list = [];
$res_rujuk = $conn->query("
    (SELECT r.no_rawat, r.noKunjungan, r.tglDaftar, r.tglEstRujuk, r.no_rkm_medis, r.nm_pasien, r.noKartu,
            r.nmPPK, r.kdPPK, r.nmSubSpesialis as tujuan_rujuk, r.kdDiag1, r.nmDiag1, 'subspesialis' as jenis_rujuk
     FROM pcare_rujuk_subspesialis r)
    UNION
    (SELECT rk.no_rawat, rk.noKunjungan, rk.tglDaftar, rk.tglEstRujuk, rk.no_rkm_medis, rk.nm_pasien, rk.noKartu,
            (SELECT nmPPK FROM pcare_rujuk_subspesialis WHERE kdPPK = rk.kdPPK LIMIT 1) as nmPPK, rk.kdPPK, rk.nmKhusus as tujuan_rujuk, rk.kdDiag1, rk.nmDiag1, 'khusus' as jenis_rujuk
     FROM pcare_rujuk_khusus rk)
    ORDER BY tglDaftar DESC, no_rawat DESC
    LIMIT 25
");
if ($res_rujuk) while ($r = $res_rujuk->fetch_assoc()) $rujukan_list[] = $r;

// 3. Daftar Dokter & Poli untuk Dropdown
$dokter_list = [];
$res_dok = $conn->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1'");
if ($res_dok) while ($d = $res_dok->fetch_assoc()) $dokter_list[] = $d;

$poli_list = [];
$res_pol = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status='1'");
if ($res_pol) while ($p = $res_pol->fetch_assoc()) $poli_list[] = $p;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Bridging PCare BPJS Kesehatan</h1>
    <p class="page-subtitle">Integrasi Faskes Tingkat Pertama (FKTP) &mdash; Validasi Peserta, Kunjungan, &amp; Pembuatan Rujukan RS</p>
  </div>
  <div class="page-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
    <button type="button" class="btn btn-outline" style="border-color:#0284c7;color:#0369a1;background:#f0f9ff;" onclick="sinkronSemuaPcare()">
      <i class="fas fa-rotate" id="iconSyncAllPcare"></i> Tarik Antrean Hari Ini
    </button>
    <button type="button" class="btn btn-primary" onclick="openBridgingMonitorModal()">
      <i class="fas fa-tower-broadcast"></i> Live Network Monitor
    </button>
    <button type="button" class="btn btn-secondary" onclick="testKoneksiPCare(this)">
      <i class="fas fa-plug"></i> Tes Diagnostik PCare
    </button>
    <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="btn btn-outline">
      <i class="fas fa-cog"></i> Kredensial
    </a>
  </div>
</div>

<!-- ─── Status Koneksi Card ──────────────────────────────── -->
<div class="card mb-16" style="border-left:5px solid <?= $is_configured ? 'var(--success)' : 'var(--warning)' ?>;">
  <div class="card-body" style="padding:16px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="width:44px;height:44px;background:<?= $is_configured ? 'var(--success-bg)' : 'var(--warning-bg)' ?>;border-radius:12px;display:flex;align-items:center;justify-content:center;color:<?= $is_configured ? 'var(--success)' : 'var(--warning)' ?>;font-size:20px;">
        <i class="fas <?= $is_configured ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
      </div>
      <div>
        <div style="font-size:15px;font-weight:700;color:var(--gray-900);">
          <?= $is_configured ? 'Koneksi PCare BPJS Siap Digunakan' : 'Kredensial PCare Belum Dikonfigurasi Lengkap' ?>
        </div>
        <div style="font-size:12px;color:var(--gray-500);margin-top:2px;">
          Endpoint: <code><?= htmlspecialchars($url) ?></code> | ConsID: <code><?= htmlspecialchars($consid ?: '(kosong)') ?></code>
        </div>
      </div>
    </div>
    <span class="integration-badge <?= $is_configured ? 'connected' : 'disconnected' ?>" id="badgeKoneksi" style="font-size:12px;padding:6px 14px;">
      <span class="dot"></span><?= $is_configured ? 'Terkoneksi' : 'Offline' ?>
    </span>
  </div>
</div>

<!-- ─── Tab Navigation ───────────────────────────────────── -->
<div style="display:flex;gap:10px;margin-bottom:16px;border-bottom:1px solid var(--gray-200);padding-bottom:10px;">
  <button type="button" class="btn btn-sm btn-primary" id="tabBtnKunjungan" onclick="switchTab('kunjungan')">
    <i class="fas fa-calendar-day"></i> Kunjungan BPJS Hari Ini (<?= count($visit_list) ?>)
  </button>
  <button type="button" class="btn btn-sm btn-outline" id="tabBtnRujukan" onclick="switchTab('rujukan')">
    <i class="fas fa-file-medical"></i> Daftar Surat Rujukan BPJS (<?= count($rujukan_list) ?>)
  </button>
</div>

<div style="display:grid;grid-template-columns:360px 1fr;gap:16px;align-items:start;">

  <!-- ─── Kolom Kiri: Cek Kepesertaan & Ref Diagnosa ──────── -->
  <div style="display:flex;flex-direction:column;gap:16px;">
    <!-- Cek Peserta -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-id-card text-primary"></i> Cek Kepesertaan BPJS</div>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Nomor Kartu BPJS / NIK</label>
          <div style="display:flex;gap:8px;">
            <input type="text" id="inputNoKartu" class="form-control" placeholder="13 digit No. Kartu / 16 digit NIK" maxlength="16">
            <button type="button" class="btn btn-primary" onclick="cekPeserta()">
              <i class="fas fa-search"></i> Cek
            </button>
          </div>
        </div>

        <!-- Result Box -->
        <div id="boxHasilPeserta" style="display:none;background:var(--gray-50);padding:14px;border-radius:8px;border:1px solid var(--gray-200);font-size:12px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <div>
              <strong id="resNama" style="font-size:14px;color:var(--gray-900);display:block;"></strong>
              <small id="resRm" style="color:var(--primary-600);font-weight:600;"></small>
            </div>
            <span class="badge badge-success" id="resStatus">AKTIF</span>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;color:var(--gray-600);">
            <div>No. Kartu: <strong id="resNoKartu" style="color:var(--gray-800);"></strong></div>
            <div>NIK: <strong id="resNik" style="color:var(--gray-800);"></strong></div>
            <div>Tgl Lahir / Umur: <span id="resTglLahir"></span></div>
            <div>Jenis Kelamin: <span id="resJk"></span></div>
            <div>Jenis Peserta: <span id="resJnsPeserta"></span></div>
            <div>Faskes Terdaftar: <span id="resFaskes"></span></div>
            <div>Hak Kelas: <span id="resKelas"></span></div>
            <div>Alamat: <span id="resAlamat"></span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pencarian ICD-10 BPJS -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-book-medical text-primary"></i> Kamus Diagnosa ICD-10 BPJS</div>
      </div>
      <div class="card-body">
        <div class="form-group mb-10">
          <div style="display:flex;gap:8px;">
            <input type="text" id="inputDiagnosa" class="form-control" placeholder="Cari nama diagnosa / kode ICD-10...">
            <button type="button" class="btn btn-secondary" onclick="cariDiagnosa()">
              <i class="fas fa-search"></i>
            </button>
          </div>
        </div>
        <div id="boxHasilDiag" style="max-height:220px;overflow-y:auto;display:none;font-size:12px;">
          <table class="table table-sm" style="margin:0;">
            <thead><tr><th>Kode</th><th>Nama Diagnosa BPJS</th></tr></thead>
            <tbody id="tbodyDiag"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ─── Kolom Kanan: Tab Content ────────────────────────── -->
  <div>

    <!-- TAB 1: Kunjungan BPJS Hari Ini -->
    <div id="tabKunjungan" class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-sync text-primary"></i> Data Kunjungan Peserta BPJS Hari Ini</div>
        <span style="font-size:12px;color:var(--gray-500);"><?= count($visit_list) ?> Pasien BPJS</span>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if (empty($visit_list)): ?>
          <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-hospital-user"></i></div>
            <div class="empty-state-title">Belum ada kunjungan peserta BPJS hari ini</div>
          </div>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>No. Rawat</th>
                  <th>Pasien BPJS</th>
                  <th>No. Kartu / NIK</th>
                  <th>Poli / Dokter</th>
                  <th>Diagnosa &amp; Status</th>
                  <th style="width:210px;text-align:center;">Aksi PCare</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($visit_list as $v): 
                  $has_daftar = !empty($v['no_urut_pcare']);
                  $has_kunj = !empty($v['no_kunjungan_pcare']);
                  $no_rujuk = $v['no_rujukan_sub'] ?: ($v['no_rujukan_khusus'] ?: '');
                ?>
                  <tr>
                    <td>
                      <span style="font-family:monospace;font-size:11px;font-weight:700;color:var(--gray-600);">
                        <?= htmlspecialchars($v['no_rawat']) ?>
                      </span>
                    </td>
                    <td>
                      <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($v['nm_pasien']) ?></div>
                      <div style="font-size:11px;color:var(--gray-400);">RM: <?= $v['no_rkm_medis'] ?></div>
                    </td>
                    <td>
                      <div style="font-family:monospace;font-weight:600;color:var(--primary-600);font-size:12px;">
                        <?= htmlspecialchars($v['no_peserta'] ?: $v['no_ktp'] ?: '-') ?>
                      </div>
                    </td>
                    <td>
                      <div style="font-size:12px;"><?= htmlspecialchars($v['nm_poli']) ?></div>
                      <div style="font-size:11px;color:var(--gray-500);"><?= htmlspecialchars($v['nm_dokter']) ?></div>
                    </td>
                    <td>
                      <?php if (!empty($v['kd_diag_utama'])): ?>
                        <div style="font-size:11.5px;font-weight:600;color:#0369a1;">
                          <span style="font-family:monospace;background:#e0f2fe;padding:1px 4px;border-radius:3px;"><?= htmlspecialchars($v['kd_diag_utama']) ?></span> <?= htmlspecialchars($v['nm_diag_utama'] ?? '') ?>
                        </div>
                      <?php else: ?>
                        <span style="font-size:11px;color:var(--gray-400);">Belum diinput diagnosa</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <div style="display:flex;flex-direction:column;gap:4px;align-items:center;">
                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                          <!-- Step 1: Pendaftaran PCare -->
                          <?php if ($has_daftar): ?>
                            <span style="font-size:10px;font-weight:700;color:#0369a1;background:#e0f2fe;padding:2px 6px;border-radius:4px;" title="Terdaftar di PCare BPJS">
                              <i class="fas fa-id-badge"></i> Urut #<?= htmlspecialchars($v['no_urut_pcare']) ?>
                            </span>
                          <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-info" style="padding:2px 6px;font-size:10.5px;" onclick="daftarPCare('<?= $v['no_rawat'] ?>', this)" title="Step 1: Kirim Pendaftaran ke PCare BPJS">
                              <i class="fas fa-user-plus"></i> 1. Daftar
                            </button>
                          <?php endif; ?>

                          <!-- Step 2: Kunjungan PCare -->
                          <?php if ($has_kunj): ?>
                            <span style="font-size:10px;font-weight:700;color:#047857;background:#d1fae5;padding:2px 6px;border-radius:4px;" title="Kunjungan PCare Terkirim">
                              <i class="fas fa-check-circle"></i> Kunjungan OK
                            </span>
                          <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" style="padding:2px 6px;font-size:10.5px;" onclick="syncPCare('<?= $v['no_rawat'] ?>', this)" title="Step 2: Kirim Kunjungan ke PCare BPJS">
                              <i class="fas fa-cloud-arrow-up"></i> 2. Kirim
                            </button>
                          <?php endif; ?>
                        </div>

                        <!-- Rujukan RS (Opsional) -->
                        <div>
                          <?php if (!empty($no_rujuk)): ?>
                            <button type="button" class="btn btn-sm btn-outline-success" style="padding:2px 6px;font-size:10.5px;" onclick="window.open('<?= BASE_URL ?>modules/pcare/cetak_rujukan.php?no_rawat=<?= urlencode($v['no_rawat']) ?>', '_blank')" title="Cetak Surat Rujukan BPJS">
                              <i class="fas fa-print"></i> Cetak Rujuk
                            </button>
                          <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" style="padding:2px 6px;font-size:10.5px;" onclick='bukaModalRujukan(<?= json_encode($v) ?>)' title="Buat Surat Rujukan ke RS">
                              <i class="fas fa-share-nodes"></i> Rujuk RS
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= render_pagination($pag_kunj) ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- TAB 2: Riwayat Surat Rujukan Terkirim -->
    <div id="tabRujukan" class="card" style="display:none;">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-file-medical text-primary"></i> Riwayat Surat Rujukan BPJS Terkirim</div>
        <span style="font-size:12px;color:var(--gray-500);"><?= count($rujukan_list) ?> Rujukan Diterbitkan</span>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if (empty($rujukan_list)): ?>
          <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-file-circle-check"></i></div>
            <div class="empty-state-title">Belum ada surat rujukan yang dibuat</div>
            <div class="empty-state-desc">Pilih pasien BPJS pada tab Kunjungan lalu klik tombol "Buat Rujukan".</div>
          </div>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>No. Rujukan / PCare</th>
                  <th>Pasien &amp; No. Kartu</th>
                  <th>Tgl Rujuk / Rencana</th>
                  <th>RS Tujuan &amp; Subspesialis</th>
                  <th>Diagnosa Rujukan</th>
                  <th style="width:130px;text-align:center;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rujukan_list as $rj): ?>
                  <tr>
                    <td>
                      <div style="font-family:monospace;font-weight:700;color:#0284c7;font-size:12.5px;">
                        <?= htmlspecialchars($rj['noKunjungan'] ?: '-') ?>
                      </div>
                      <div style="font-size:10.5px;color:var(--gray-400);">Rawat: <?= htmlspecialchars($rj['no_rawat']) ?></div>
                    </td>
                    <td>
                      <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($rj['nm_pasien']) ?></div>
                      <div style="font-family:monospace;font-size:11px;color:var(--primary-600);">RM: <?= $rj['no_rkm_medis'] ?> | <?= $rj['noKartu'] ?></div>
                    </td>
                    <td>
                      <div style="font-size:12px;font-weight:600;color:var(--gray-800);"><?= date('d/m/Y', strtotime($rj['tglEstRujuk'] ?: $rj['tglDaftar'])) ?></div>
                      <span class="badge badge-outline-primary" style="font-size:10px;"><?= ucfirst($rj['jenis_rujuk']) ?></span>
                    </td>
                    <td>
                      <div style="font-weight:700;font-size:12.5px;color:#0f172a;"><?= htmlspecialchars($rj['nmPPK'] ?: $rj['kdPPK']) ?></div>
                      <div style="font-size:11.5px;color:var(--gray-600);"><?= htmlspecialchars($rj['tujuan_rujuk']) ?></div>
                    </td>
                    <td>
                      <div style="font-size:12px;">
                        <span style="font-family:monospace;font-weight:700;color:#0369a1;background:#e0f2fe;padding:1px 4px;border-radius:3px;"><?= htmlspecialchars($rj['kdDiag1']) ?></span>
                        <?= htmlspecialchars($rj['nmDiag1']) ?>
                      </div>
                    </td>
                    <td style="text-align:center;">
                      <a href="<?= BASE_URL ?>modules/pcare/cetak_rujukan.php?no_rawat=<?= urlencode($rj['no_rawat']) ?>" target="_blank" class="btn btn-sm btn-primary">
                        <i class="fas fa-print"></i> Cetak Surat
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>


<!-- ─── MODAL PEMBUATAN RUJUKAN PCARE BPJS ────────────────── -->
<div class="modal" id="modalBuatRujukan" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.65);z-index:2500;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);">
  <div class="modal-content" style="background:#ffffff;border-radius:16px;max-width:980px;width:100%;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 25px 60px -15px rgba(0,0,0,0.35);overflow:hidden;">
    
    <!-- Modal Header -->
    <div style="padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg, #f0f9ff, #e0f2fe);">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:40px;height:40px;border-radius:10px;background:#0284c7;color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-hospital-user"></i>
        </div>
        <div>
          <h3 style="font-size:16px;font-weight:800;color:#0369a1;margin:0;">Form Pembuatan Surat Rujukan BPJS PCare</h3>
          <p style="font-size:12px;color:#0284c7;margin:0;">Bridging Faskes Tingkat Pertama (FKTP) ke Rumah Sakit Rujukan</p>
        </div>
      </div>
      <button type="button" onclick="tutupModalRujukan()" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;padding:4px 8px;border-radius:6px;">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Modal Body -->
    <div style="padding:22px 24px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:18px;background:#f8fafc;">
      
      <form id="formRujukan" onsubmit="event.preventDefault();">
        <input type="hidden" id="rujukNoRawat" name="no_rawat">
        <input type="hidden" id="rujukNoRm" name="no_rkm_medis">
        <input type="hidden" id="rujukKdPPK" name="kd_ppk">
        <input type="hidden" id="rujukNmPPK" name="nm_ppk">
        <input type="hidden" id="rujukNmSubSpesialis" name="nm_subspesialis">
        <input type="hidden" id="rujukNmSarana" name="nm_sarana">
        <input type="hidden" id="rujukNmKhusus" name="nm_khusus">
        <input type="hidden" id="rujukNmTacc" name="nm_tacc">

        <!-- 1. IDENTITAS PASIEN & KUNJUNGAN -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-id-card"></i> 1. Identitas Pasien &amp; Kunjungan
          </div>
          <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:12px;">
            <div class="form-group">
              <label class="form-label">Nama Pasien</label>
              <input type="text" id="rujukNmPasien" name="nm_pasien" class="form-control" readonly style="font-weight:700;background:#f8fafc;">
            </div>
            <div class="form-group">
              <label class="form-label">Nomor Kartu BPJS</label>
              <input type="text" id="rujukNoKartu" name="no_kartu" class="form-control" readonly style="font-family:monospace;font-weight:700;color:#0284c7;background:#f8fafc;">
            </div>
            <div class="form-group">
              <label class="form-label">Tgl. Daftar Kunjungan</label>
              <input type="date" id="rujukTglDaftar" name="tgl_daftar" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
            <div class="form-group">
              <label class="form-label">Poli Klinik</label>
              <select id="rujukKdPoli" name="kd_poli" class="form-control">
                <?php foreach ($poli_list as $pl): ?>
                  <option value="<?= htmlspecialchars($pl['kd_poli']) ?>"><?= htmlspecialchars($pl['nm_poli']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Dokter Pemeriksa</label>
              <select id="rujukKdDokter" name="kd_dokter" class="form-control">
                <?php foreach ($dokter_list as $dl): ?>
                  <option value="<?= htmlspecialchars($dl['kd_dokter']) ?>"><?= htmlspecialchars($dl['nm_dokter']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- 2. PEMERIKSAAN FISIK & DIAGNOSA ICD-10 -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin-top:14px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-stethoscope"></i> 2. Pemeriksaan Klinis &amp; Diagnosa (ICD-10)
          </div>
          
          <div class="form-group mb-10">
            <label class="form-label">Keluhan Utama</label>
            <textarea id="rujukKeluhan" name="keluhan" class="form-control" rows="2" placeholder="Tuliskan keluhan atau indikasi medis rujukan..."></textarea>
          </div>

          <div style="display:grid;grid-template-columns:repeat(6, 1fr);gap:8px;">
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Sistole (mmHg)</label>
              <input type="number" id="rujukSistole" name="sistole" class="form-control" value="120">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Diastole (mmHg)</label>
              <input type="number" id="rujukDiastole" name="diastole" class="form-control" value="80">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Nadi (x/m)</label>
              <input type="number" id="rujukHeartRate" name="heart_rate" class="form-control" value="80">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Resp (x/m)</label>
              <input type="number" id="rujukRespRate" name="resp_rate" class="form-control" value="20">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Tinggi (cm)</label>
              <input type="number" id="rujukTinggi" name="tinggi_badan" class="form-control" value="165">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:11px;">Berat (kg)</label>
              <input type="number" id="rujukBerat" name="berat_badan" class="form-control" value="60">
            </div>
          </div>

          <!-- Inline Autocomplete ICD-10 Search Box -->
          <div style="position:relative;">
            <div style="display:grid;grid-template-columns:1.2fr 2fr;gap:12px;margin-top:10px;">
              <div class="form-group" style="position:relative;">
                <label class="form-label" style="display:flex;justify-content:space-between;">
                  <span>Kode ICD-10 Utama *</span>
                  <span style="font-size:10.5px;color:#0284c7;font-weight:normal;">Ketik kode / nama penyakit</span>
                </label>
                <div style="display:flex;gap:6px;">
                  <input type="text" id="rujukKdDiag1" name="kd_diag1" class="form-control" placeholder="Contoh: I10 / Hipertensi / Diare" required style="font-family:monospace;font-weight:700;text-transform:uppercase;" oninput="onDiagInputLive(this.value)" autocomplete="off">
                  <button type="button" class="btn btn-primary" onclick="toggleDiagDropdown()" title="Cari Diagnosa"><i class="fas fa-search"></i> Cari</button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Nama Diagnosa Utama (BPJS)</label>
                <input type="text" id="rujukNmDiag1" name="nm_diag1" class="form-control" placeholder="Nama penyakit terpilih" style="background:#f8fafc;font-weight:600;color:#0369a1;">
              </div>
            </div>

            <!-- Dropdown Popover Hasil Pencarian Diagnosa -->
            <div id="modalDiagDropdown" style="display:none;position:absolute;top:70px;left:0;right:0;background:#ffffff;border:2px solid #0284c7;border-radius:10px;box-shadow:0 18px 40px rgba(0,0,0,0.3);z-index:9999;max-height:260px;overflow-y:auto;padding:8px;">
              <div style="padding:6px 10px;background:#f0f9ff;border-radius:6px;font-size:11px;font-weight:700;color:#0369a1;display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span><i class="fas fa-book-medical"></i> Hasil Pencarian Diagnosa ICD-10 (Klik untuk memilih):</span>
                <button type="button" onclick="document.getElementById('modalDiagDropdown').style.display='none'" style="background:none;border:none;color:#64748b;font-weight:bold;cursor:pointer;font-size:13px;">&times; Tutup</button>
              </div>
              <div id="modalDiagResults" style="display:flex;flex-direction:column;gap:4px;">
                <div style="text-align:center;color:#64748b;padding:12px;font-size:11.5px;">Ketik minimal 2 huruf kode atau nama penyakit (contoh: K30, I10, Demam, Diare)...</div>
              </div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-top:8px;">
            <div class="form-group">
              <label class="form-label">Kode ICD-10 Sekunder (Opsional)</label>
              <input type="text" id="rujukKdDiag2" name="kd_diag2" class="form-control" placeholder="ICD-10 Tambahan" style="font-family:monospace;text-transform:uppercase;">
            </div>
            <div class="form-group">
              <label class="form-label">Terapi yang Diberikan</label>
              <input type="text" id="rujukTerapi" name="terapi" class="form-control" placeholder="Terapi awal / obat yang telah diberikan...">
            </div>
          </div>
        </div>

        <!-- 3. TUJUAN RUJUKAN & SPESIALIS -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin-top:14px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-hospital"></i> 3. Spesialis &amp; Pencarian Rumah Sakit Rujukan
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-bottom:12px;">
            <div class="form-group">
              <label class="form-label">Jenis Rujukan</label>
              <select id="rujukJenis" name="jenis_rujukan" class="form-control" onchange="toggleJenisRujukan()">
                <option value="subspesialis">Rujuk Vertikal (Subspesialis)</option>
                <option value="khusus">Rujukan Kasus Khusus</option>
              </select>
            </div>
            <div class="form-group" id="boxPilihSpesialis">
              <label class="form-label">Poli Spesialis</label>
              <select id="rujukSpesialis" class="form-control" onchange="onSpesialisChange()">
                <option value="">Memuat Spesialis...</option>
              </select>
            </div>
            <div class="form-group" id="boxPilihSubspesialis">
              <label class="form-label">Subspesialis *</label>
              <select id="rujukSubSpesialis" name="kd_subspesialis" class="form-control">
                <option value="">Pilih Spesialis Dulu</option>
              </select>
            </div>
            <div class="form-group" id="boxPilihKhusus" style="display:none;">
              <label class="form-label">Kategori Kasus Khusus</label>
              <select id="rujukKhusus" name="kd_khusus" class="form-control">
                <option value="">Memuat Khusus...</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Sarana RS</label>
              <select id="rujukSarana" name="kd_sarana" class="form-control">
                <option value="1">Rawat Jalan</option>
                <option value="2">Rawat Inap</option>
                <option value="3">IGD</option>
              </select>
            </div>
          </div>

          <div style="display:flex;align-items:center;gap:12px;background:#f0f9ff;padding:12px 14px;border-radius:10px;border:1px solid #bae6fd;margin-bottom:12px;">
            <div style="flex:1;">
              <label class="form-label" style="color:#0369a1;font-weight:700;">Tanggal Rencana Kunjungan Rujuk *</label>
              <input type="date" id="rujukTglEst" name="tgl_est_rujuk" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div style="padding-top:20px;">
              <button type="button" class="btn btn-primary" onclick="cariFaskesRujukan(this)">
                <i class="fas fa-search-location"></i> Cari Faskes / RS Rujukan Realtime
              </button>
            </div>
          </div>

          <!-- Live Result Cards Faskes Rujukan -->
          <div id="boxHasilFaskes" style="display:none;">
            <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:8px;">
              <i class="fas fa-list"></i> Pilih Rumah Sakit Tujuan:
            </div>
            <div id="listFaskesCards" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:10px;max-height:260px;overflow-y:auto;padding-right:4px;">
              <!-- Cards dynamically inserted -->
            </div>
          </div>

          <!-- RS Terpilih Card -->
          <div id="boxFaskesTerpilih" style="display:none;background:#ecfdf5;border:1.5px solid #10b981;padding:12px 16px;border-radius:10px;margin-top:10px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <div>
                <span style="font-size:10px;font-weight:800;color:#047857;background:#d1fae5;padding:2px 8px;border-radius:12px;text-transform:uppercase;">
                  Faskes Rujukan Terpilih
                </span>
                <div id="selectedRsNama" style="font-size:14px;font-weight:800;color:#065f46;margin-top:4px;"></div>
                <div id="selectedRsInfo" style="font-size:11.5px;color:#047857;"></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-success" onclick="document.getElementById('boxHasilFaskes').style.display='block';">
                <i class="fas fa-pencil"></i> Ganti RS
              </button>
            </div>
          </div>

        </div>

        <!-- 4. TACC & ALASAN RUJUKAN -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;margin-top:14px;">
          <div style="font-size:12.5px;font-weight:800;color:#0369a1;margin-bottom:12px;text-transform:uppercase;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-clipboard-check"></i> 4. Kriteria TACC (Time, Age, Complication, Comorbidity)
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group">
              <label class="form-label">Kategori TACC</label>
              <select id="rujukTacc" name="kd_tacc" class="form-control" onchange="onTaccChange()">
                <option value="0">Tanpa TACC</option>
                <option value="1">Time (Waktu)</option>
                <option value="2">Age (Umur)</option>
                <option value="3">Complication (Komplikasi)</option>
                <option value="4">Comorbidity (Penyakit Penyerta)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Alasan TACC / Catatan Rujukan</label>
              <input type="text" id="rujukAlasanTacc" name="alasan_tacc" class="form-control" placeholder="Contoh: Memerlukan penanganan spesialis lanjutan">
            </div>
          </div>
          <div class="form-group mt-10" id="boxCatatanKhusus" style="display:none;">
            <label class="form-label">Catatan Rujukan Kasus Khusus</label>
            <input type="text" id="rujukCatatanKhusus" name="catatan_khusus" class="form-control" placeholder="Catatan program rujukan khusus...">
          </div>
        </div>

      </form>

    </div>

    <!-- Modal Footer -->
    <div style="padding:14px 24px;border-top:1px solid #e2e8f0;background:#ffffff;display:flex;justify-content:space-between;align-items:center;">
      <button type="button" class="btn btn-secondary" onclick="tutupModalRujukan()">Batal</button>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-outline-primary" id="btnSimpanOnly" onclick="simpanKirimRujukan(false)">
          <i class="fas fa-paper-plane"></i> Simpan &amp; Kirim BPJS
        </button>
        <button type="button" class="btn btn-primary" id="btnSimpanPrint" onclick="simpanKirimRujukan(true)">
          <i class="fas fa-print"></i> Kirim &amp; Cetak Surat Rujukan
        </button>
      </div>
    </div>

  </div>
</div>

<!-- ─── Modal Log Uji Koneksi PCare ────────────────────────── -->
<div class="modal" id="modalLogTest" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);z-index:2000;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px);">
  <div class="modal-content" style="background:#ffffff;border-radius:14px;max-width:760px;width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:34px;height:34px;border-radius:8px;background:#e0f2fe;color:#0284c7;display:flex;align-items:center;justify-content:center;font-size:16px;">
          <i class="fas fa-terminal"></i>
        </div>
        <div>
          <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Log Uji Koneksi Live PCare BPJS</h3>
          <p style="font-size:11.5px;color:#64748b;margin:0;">Hasil komunikasi HTTP cURL &amp; Validasi Signature BPJS v4.0</p>
        </div>
      </div>
      <button type="button" onclick="closeModalLogTest()" style="background:none;border:none;color:#94a3b8;font-size:18px;cursor:pointer;padding:4px 8px;border-radius:6px;">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div style="padding:20px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:14px;background:#f8fafc;">
      <div style="display:flex;align-items:center;gap:8px;background:#ffffff;padding:8px 12px;border-radius:10px;border:1px solid #e2e8f0;flex-wrap:wrap;">
        <span style="font-size:12px;font-weight:700;color:#475569;margin-right:4px;"><i class="fas fa-filter"></i> Pilih Endpoint Tes:</span>
        <button type="button" class="btn btn-sm btn-primary" id="btnTestDiag" onclick="testKoneksiPCare(this, 'diagnosa')">
          <i class="fas fa-book-medical"></i> Diagnosa (/diagnosa/A00/0/1)
        </button>
        <button type="button" class="btn btn-sm btn-secondary" id="btnTestDokter" onclick="testKoneksiPCare(this, 'dokter')">
          <i class="fas fa-user-doctor"></i> Dokter (/dokter/0/1)
        </button>
        <button type="button" class="btn btn-sm btn-secondary" id="btnTestPoli" onclick="testKoneksiPCare(this, 'poli')">
          <i class="fas fa-hospital"></i> Poli (/poli/fktp/0/1)
        </button>
      </div>

      <div id="logLoading" style="display:none;text-align:center;padding:40px 20px;">
        <i class="fas fa-circle-notch fa-spin" style="font-size:36px;color:#0284c7;"></i>
        <div style="margin-top:12px;font-size:14px;font-weight:600;color:#334155;">Menghubungi Server PCare BPJS...</div>
      </div>

      <div id="logResult" style="display:flex;flex-direction:column;gap:12px;">
        <div id="logBanner" style="padding:14px 18px;border-radius:10px;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
          <div style="display:flex;align-items:center;gap:12px;">
            <div id="logStatusIcon" style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;"></div>
            <div>
              <div id="logStatusTitle" style="font-size:14px;font-weight:700;"></div>
              <div id="logStatusSub" style="font-size:12px;margin-top:2px;"></div>
            </div>
          </div>
          <div style="text-align:right;">
            <span id="logHttpCode" style="font-size:12px;font-weight:800;padding:4px 10px;border-radius:6px;"></span>
            <div id="logLatency" style="font-size:11px;color:#64748b;margin-top:4px;"></div>
          </div>
        </div>

        <div>
          <label style="font-size:11.5px;font-weight:700;color:#475569;margin-bottom:4px;display:block;">Request Headers &amp; URL:</label>
          <pre id="logRequestDetails" style="background:#0f172a;color:#38bdf8;padding:12px;border-radius:8px;font-size:11px;font-family:monospace;overflow-x:auto;max-height:120px;margin:0;"></pre>
        </div>

        <div>
          <label style="font-size:11.5px;font-weight:700;color:#475569;margin-bottom:4px;display:block;">Response Payload Body:</label>
          <pre id="logResponseBody" style="background:#0f172a;color:#a7f3d0;padding:12px;border-radius:8px;font-size:11px;font-family:monospace;overflow-x:auto;max-height:180px;margin:0;"></pre>
        </div>
      </div>
    </div>

    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#ffffff;display:flex;justify-content:flex-end;gap:8px;">
      <button type="button" class="btn btn-secondary" onclick="closeModalLogTest()">Tutup</button>
      <button type="button" class="btn btn-primary" onclick="testKoneksiPCare(this, currentTestType)"><i class="fas fa-rotate-right"></i> Tes Ulang</button>
    </div>
  </div>
</div>

<script>
let currentTestType = 'diagnosa';
let cachedSpesialis = [];
let cachedSarana = [];
let cachedKhusus = [];

function switchTab(tab) {
  const tKunj = document.getElementById('tabKunjungan');
  const tRujuk = document.getElementById('tabRujukan');
  const bKunj = document.getElementById('tabBtnKunjungan');
  const bRujuk = document.getElementById('tabBtnRujukan');

  if (tab === 'rujukan') {
    tKunj.style.display = 'none';
    tRujuk.style.display = 'block';
    bKunj.className = 'btn btn-sm btn-outline';
    bRujuk.className = 'btn btn-sm btn-primary';
  } else {
    tKunj.style.display = 'block';
    tRujuk.style.display = 'none';
    bKunj.className = 'btn btn-sm btn-primary';
    bRujuk.className = 'btn btn-sm btn-outline';
  }
}

// ─── Modal Rujukan Logic ──────────────────────────────────────
function bukaModalRujukan(v) {
  const modal = document.getElementById('modalBuatRujukan');
  if (!modal) return;

  // Isi data identitas
  document.getElementById('rujukNoRawat').value     = v.no_rawat || '';
  document.getElementById('rujukNoRm').value        = v.no_rkm_medis || '';
  document.getElementById('rujukNmPasien').value    = v.nm_pasien || '';
  document.getElementById('rujukNoKartu').value     = v.no_peserta || v.no_ktp || '';
  document.getElementById('rujukTglDaftar').value   = v.tgl_registrasi || '<?= date('Y-m-d') ?>';
  document.getElementById('rujukKdPoli').value      = v.kd_poli || '001';
  document.getElementById('rujukKdDokter').value    = v.kd_dokter || '';

  // Pemeriksaan fisik & diagnosa
  document.getElementById('rujukKeluhan').value     = v.keluhan || '';
  document.getElementById('rujukKdDiag1').value     = v.kd_diag_utama || '';
  document.getElementById('rujukNmDiag1').value     = v.nm_diag_utama || '';
  document.getElementById('rujukSistole').value     = v.tensi ? (v.tensi.split('/')[0] || 120) : 120;
  document.getElementById('rujukDiastole').value    = v.tensi ? (v.tensi.split('/')[1] || 80) : 80;
  document.getElementById('rujukHeartRate').value   = v.nadi || 80;
  document.getElementById('rujukRespRate').value    = v.respirasi || 20;
  document.getElementById('rujukTinggi').value      = v.tinggi || 165;
  document.getElementById('rujukBerat').value       = v.berat || 60;

  // Reset RS Terpilih
  document.getElementById('rujukKdPPK').value = '';
  document.getElementById('rujukNmPPK').value = '';
  document.getElementById('boxFaskesTerpilih').style.display = 'none';
  document.getElementById('boxHasilFaskes').style.display = 'none';

  modal.style.display = 'flex';
  loadMasterRujukan();
}

function tutupModalRujukan() {
  const modal = document.getElementById('modalBuatRujukan');
  if (modal) modal.style.display = 'none';
}

function toggleJenisRujukan() {
  const jenis = document.getElementById('rujukJenis').value;
  const boxSp = document.getElementById('boxPilihSpesialis');
  const boxSub = document.getElementById('boxPilihSubspesialis');
  const boxKh = document.getElementById('boxPilihKhusus');
  const boxCatKh = document.getElementById('boxCatatanKhusus');

  if (jenis === 'khusus') {
    boxSp.style.display = 'none';
    boxSub.style.display = 'none';
    boxKh.style.display = 'block';
    boxCatKh.style.display = 'block';
    loadKhususDropdown();
  } else {
    boxSp.style.display = 'block';
    boxSub.style.display = 'block';
    boxKh.style.display = 'none';
    boxCatKh.style.display = 'none';
  }
}

function onTaccChange() {
  const tacc = document.getElementById('rujukTacc');
  const opt = tacc.options[tacc.selectedIndex];
  document.getElementById('rujukNmTacc').value = opt ? opt.text : '';
}

function loadMasterRujukan() {
  // Load Spesialis
  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=get_spesialis`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    cachedSpesialis = list;
    const sel = document.getElementById('rujukSpesialis');
    sel.innerHTML = '';
    list.forEach((s, idx) => {
      const selected = (s.kdSpesialis === 'INT' || idx === 0) ? 'selected' : '';
      sel.innerHTML += `<option value="${s.kdSpesialis}" ${selected}>${s.nmSpesialis}</option>`;
    });

    // Otomatis populate subspesialis awal
    onSpesialisChange();
  })
  .catch(() => {
    onSpesialisChange();
  });
}

function loadKhususDropdown() {
  if (cachedKhusus.length > 0) return;
  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=get_khusus`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    cachedKhusus = list;
    const sel = document.getElementById('rujukKhusus');
    sel.innerHTML = '';
    list.forEach(k => {
      sel.innerHTML += `<option value="${k.kdKhusus}">${k.nmKhusus}</option>`;
    });
  });
}

function onSpesialisChange() {
  const kdSp = document.getElementById('rujukSpesialis').value || 'INT';
  const selSub = document.getElementById('rujukSubSpesialis');
  selSub.innerHTML = '<option value="">Memuat Subspesialis...</option>';

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=get_subspesialis&kd_spesialis=${encodeURIComponent(kdSp)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    selSub.innerHTML = '';
    if (list.length === 0) {
      selSub.innerHTML = `<option value="${kdSp}">${kdSp} (Umum)</option>`;
    } else {
      list.forEach((sub, idx) => {
        selSub.innerHTML += `<option value="${sub.kdSubSpesialis}">${sub.nmSubSpesialis} [${sub.kdSubSpesialis}]</option>`;
      });
    }
  })
  .catch(() => {
    selSub.innerHTML = `<option value="${kdSp}">${kdSp} (Umum)</option>`;
  });
}

// ─── Inline ICD-10 Live Search Autocomplete Inside Modal ───────
let diagDebounceTimer = null;

function onDiagInputLive(val) {
  clearTimeout(diagDebounceTimer);
  const q = val.trim();
  if (q.length < 2) {
    document.getElementById('modalDiagDropdown').style.display = 'none';
    return;
  }

  diagDebounceTimer = setTimeout(() => {
    lakukanPencarianDiagnosaModal(q);
  }, 250);
}

function toggleDiagDropdown() {
  const q = document.getElementById('rujukKdDiag1').value.trim();
  if (!q) {
    showToast('Ketik kode atau nama diagnosa pada kolom input', 'info');
    document.getElementById('rujukKdDiag1').focus();
    return;
  }
  lakukanPencarianDiagnosaModal(q);
}

function lakukanPencarianDiagnosaModal(q) {
  const dropdown = document.getElementById('modalDiagDropdown');
  const container = document.getElementById('modalDiagResults');
  
  dropdown.style.display = 'block';
  container.innerHTML = '<div style="text-align:center;padding:10px;color:#0284c7;"><i class="fas fa-spinner fa-spin"></i> Mencari diagnosa ICD-10 di BPJS &amp; Database...</div>';

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=cari_diagnosa&q=${encodeURIComponent(q)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    container.innerHTML = '';

    if (list.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:12px;color:#94a3b8;font-size:12px;">Diagnosa tidak ditemukan. Coba ketik kode atau nama lainnya.</div>';
      return;
    }

    list.forEach(item => {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:6px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:all 0.15s;';
      row.onmouseenter = () => { row.style.background = '#f0f9ff'; };
      row.onmouseleave = () => { row.style.background = 'transparent'; };
      
      const badgeSource = item.source === 'pcare' ? '<span style="font-size:9px;background:#d1fae5;color:#065f46;padding:1px 5px;border-radius:3px;font-weight:700;">BPJS</span>' : '<span style="font-size:9px;background:#e0f2fe;color:#0369a1;padding:1px 5px;border-radius:3px;font-weight:700;">LOKAL</span>';
      const isTacc = typeof isDiagnosaTACC === 'function' ? isDiagnosaTACC(item.kdDiag) : false;
      const badgeTacc = isTacc ? '<span style="font-size:9.5px;background:#fef3c7;color:#b45309;border:1px solid #fde68a;padding:1px 6px;border-radius:3px;font-weight:800;margin-left:4px;"><i class="fas fa-exclamation-triangle"></i> TACC</span>' : '';

      row.innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-family:monospace;font-weight:800;color:#0284c7;background:#e0f2fe;padding:2px 6px;border-radius:4px;font-size:12px;">${item.kdDiag}</span>
          <span style="font-size:12.5px;font-weight:600;color:#1e293b;">${item.nmDiag}</span>
          ${badgeTacc}
        </div>
        <div>${badgeSource}</div>
      `;

      row.onclick = () => pilihDiag(item.kdDiag, item.nmDiag);
      container.appendChild(row);
    });
  })
  .catch(() => {
    container.innerHTML = '<div style="text-align:center;padding:10px;color:#ef4444;">Gagal memuat hasil pencarian</div>';
  });
}

function pilihDiag(kd, nm) {
  document.getElementById('rujukKdDiag1').value = kd;
  document.getElementById('rujukNmDiag1').value = nm;
  document.getElementById('modalDiagDropdown').style.display = 'none';

  if (typeof isDiagnosaTACC === 'function' && isDiagnosaTACC(kd)) {
    showTaccWarningModal({
      kdDiag: kd,
      nmDiag: nm,
      targetTaccSelectId: 'rujukTacc',
      targetAlasanInputId: 'rujukAlasanTacc'
    });
  } else {
    showToast(`Diagnosa dipilih: [${kd}] ${nm}`, 'info');
  }
}

function cariFaskesRujukan(btn) {
  const jenis = document.getElementById('rujukJenis').value;
  let kdSub = document.getElementById('rujukSubSpesialis').value;
  const kdKh = document.getElementById('rujukKhusus').value;
  const kdSar = document.getElementById('rujukSarana').value || '1';
  const tglRujuk = document.getElementById('rujukTglEst').value || '<?= date('Y-m-d') ?>';

  if (jenis === 'subspesialis' && !kdSub) {
    const kdSp = document.getElementById('rujukSpesialis').value;
    if (kdSp) {
      kdSub = kdSp;
    } else {
      showToast('Pilih Poli Spesialis / Subspesialis terlebih dahulu', 'warning');
      return;
    }
  }
  if (jenis === 'khusus' && !kdKh) {
    showToast('Pilih Kasus Khusus terlebih dahulu', 'warning');
    return;
  }

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mencari RS...';
  }

  const url = `<?= BASE_URL ?>modules/pcare/ajax.php?action=cari_faskes_rujukan&jenis_rujukan=${jenis}&kd_subspesialis=${encodeURIComponent(kdSub || '')}&kd_khusus=${encodeURIComponent(kdKh || '')}&kd_sarana=${encodeURIComponent(kdSar)}&tgl_rujuk=${encodeURIComponent(tglRujuk)}`;

  fetch(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-search-location"></i> Cari Faskes / RS Rujukan Realtime';
    }

    const box = document.getElementById('boxHasilFaskes');
    const container = document.getElementById('listFaskesCards');
    container.innerHTML = '';

    const list = res.list || [];
    if (!res.success || list.length === 0) {
      showToast(res.message || 'Tidak ada faskes rujukan yang tersedia pada tanggal ini.', 'warning');
      box.style.display = 'none';
      return;
    }

    list.forEach(f => {
      const card = document.createElement('div');
      card.style.cssText = 'background:#ffffff;border:1.5px solid #cbd5e1;border-radius:10px;padding:12px;cursor:pointer;transition:all 0.2s;';
      card.onmouseenter = () => { card.style.borderColor = '#0284c7'; card.style.boxShadow = '0 4px 12px rgba(2,132,199,0.15)'; };
      card.onmouseleave = () => { card.style.borderColor = '#cbd5e1'; card.style.boxShadow = 'none'; };
      
      const persentase = f.persentase || '0%';
      const jadwal = f.jadwal || 'Tersedia';

      card.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px;">
          <strong style="font-size:13px;color:#0f172a;display:block;">${f.nmppk}</strong>
          <span style="font-size:10px;font-weight:800;background:#e0f2fe;color:#0284c7;padding:2px 6px;border-radius:4px;">${f.kelas || 'RS'}</span>
        </div>
        <div style="font-size:11px;color:#64748b;margin-bottom:8px;">${f.alamatPpk || '-'} &bull; Telp: ${f.telpPpk || '-'}</div>
        <div style="display:flex;justify-content:space-between;font-size:11px;background:#f8fafc;padding:6px 8px;border-radius:6px;border:1px solid #e2e8f0;">
          <span>Kapasitas: <strong>${f.jmlRujuk || 0}/${f.kapasitas || 0}</strong></span>
          <span style="color:#059669;font-weight:700;">Kuota: ${persentase}</span>
        </div>
        <div style="margin-top:6px;font-size:10.5px;color:#0369a1;">📅 Jadwal: ${jadwal}</div>
      `;

      card.onclick = () => pilihFaskes(f.kdppk, f.nmppk, f.kelas, f.alamatPpk);
      container.appendChild(card);
    });

    box.style.display = 'block';
    showToast(`Ditemukan ${list.length} Faskes Rujukan`, 'success');
  })
  .catch(err => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-search-location"></i> Cari Faskes / RS Rujukan Realtime';
    }
    showToast('Gagal mencari faskes rujukan', 'danger');
  });
}

function pilihFaskes(kdppk, nmppk, kelas, alamat) {
  document.getElementById('rujukKdPPK').value = kdppk;
  document.getElementById('rujukNmPPK').value = nmppk;

  document.getElementById('selectedRsNama').textContent = `${nmppk} (${kdppk})`;
  document.getElementById('selectedRsInfo').textContent = `${kelas || ''} &bull; ${alamat || ''}`;

  document.getElementById('boxFaskesTerpilih').style.display = 'block';
  document.getElementById('boxHasilFaskes').style.display = 'none';

  showToast(`RS Tujuan dipilih: ${nmppk}`, 'info');
}

function simpanKirimRujukan(andPrint = false) {
  const form = document.getElementById('formRujukan');
  const kdPPK = document.getElementById('rujukKdPPK').value;
  const kdDiag1 = document.getElementById('rujukKdDiag1').value.trim();

  if (!kdDiag1) { showToast('Diagnosa utama ICD-10 wajib diisi', 'warning'); return; }
  if (!kdPPK) { showToast('Silakan cari dan pilih Rumah Sakit Rujukan terlebih dahulu', 'warning'); return; }

  // Set selected texts for hidden inputs
  const selSub = document.getElementById('rujukSubSpesialis');
  if (selSub && selSub.selectedIndex >= 0) document.getElementById('rujukNmSubSpesialis').value = selSub.options[selSub.selectedIndex].text;
  
  const selSar = document.getElementById('rujukSarana');
  if (selSar && selSar.selectedIndex >= 0) document.getElementById('rujukNmSarana').value = selSar.options[selSar.selectedIndex].text;

  const selKh = document.getElementById('rujukKhusus');
  if (selKh && selKh.selectedIndex >= 0) document.getElementById('rujukNmKhusus').value = selKh.options[selKh.selectedIndex].text;

  const selTacc = document.getElementById('rujukTacc');
  if (selTacc && selTacc.selectedIndex >= 0) document.getElementById('rujukNmTacc').value = selTacc.options[selTacc.selectedIndex].text;

  const btn1 = document.getElementById('btnSimpanOnly');
  const btn2 = document.getElementById('btnSimpanPrint');
  btn1.disabled = true;
  btn2.disabled = true;
  btn2.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim Rujukan...';

  const formData = new FormData(form);
  formData.append('action', 'simpan_kirim_rujukan');

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php`, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    btn1.disabled = false;
    btn2.disabled = false;
    btn2.innerHTML = '<i class="fas fa-print"></i> Kirim &amp; Cetak Surat Rujukan';

    if (res.success) {
      showToast(res.message, 'success');
      tutupModalRujukan();
      if (andPrint) {
        window.open(`<?= BASE_URL ?>modules/pcare/cetak_rujukan.php?no_rawat=${encodeURIComponent(res.no_rawat)}`, '_blank');
      }
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(res.message || 'Gagal mengirim surat rujukan', 'danger');
    }
  })
  .catch(err => {
    btn1.disabled = false;
    btn2.disabled = false;
    btn2.innerHTML = '<i class="fas fa-print"></i> Kirim &amp; Cetak Surat Rujukan';
    showToast('Terjadi kesalahan saat memproses rujukan', 'danger');
  });
}

// ─── Diagnostik Koneksi PCare ─────────────────────────────────
function closeModalLogTest() {
  const modal = document.getElementById('modalLogTest');
  if (modal) modal.style.display = 'none';
}

function testKoneksiPCare(btn, type = 'diagnosa') {
  currentTestType = type;
  const modal = document.getElementById('modalLogTest');
  const loading = document.getElementById('logLoading');
  const result = document.getElementById('logResult');

  ['btnTestDiag', 'btnTestDokter', 'btnTestPoli'].forEach(id => {
    const b = document.getElementById(id);
    if (b) b.className = 'btn btn-sm btn-secondary';
  });
  if (type === 'dokter') {
    const b = document.getElementById('btnTestDokter');
    if (b) b.className = 'btn btn-sm btn-primary';
  } else if (type === 'poli') {
    const b = document.getElementById('btnTestPoli');
    if (b) b.className = 'btn btn-sm btn-primary';
  } else {
    const b = document.getElementById('btnTestDiag');
    if (b) b.className = 'btn btn-sm btn-primary';
  }

  if (modal) {
    modal.style.display = 'flex';
    loading.style.display = 'block';
    result.style.display = 'none';
  }

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=test_koneksi&type=${encodeURIComponent(type)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    loading.style.display = 'none';
    result.style.display = 'flex';

    const code = res.code || (res.success ? 200 : 500);
    const isSuccess = (code == 200 || res.success);
    const banner = document.getElementById('logBanner');
    const icon = document.getElementById('logStatusIcon');
    const title = document.getElementById('logStatusTitle');
    const sub = document.getElementById('logStatusSub');
    const httpCode = document.getElementById('logHttpCode');
    const latency = document.getElementById('logLatency');
    const reqDetails = document.getElementById('logRequestDetails');
    const resBody = document.getElementById('logResponseBody');

    const badge = document.getElementById('badgeKoneksi');
    if (badge) {
      badge.className = isSuccess ? 'integration-badge connected' : 'integration-badge disconnected';
      badge.innerHTML = `<span class="dot"></span>${isSuccess ? 'Terkoneksi' : 'Gagal / Offline'}`;
    }

    if (isSuccess) {
      banner.style.background = '#ecfdf5';
      banner.style.borderColor = '#a7f3d0';
      icon.style.background = '#10b981';
      icon.style.color = '#ffffff';
      icon.innerHTML = '<i class="fas fa-check"></i>';
      title.style.color = '#065f46';
      title.textContent = 'Koneksi ke PCare BPJS Berhasil!';
      sub.style.color = '#047857';
      sub.textContent = res.message || 'Server BPJS merespon dengan status 200 OK';
      httpCode.style.background = '#d1fae5';
      httpCode.style.color = '#065f46';
      httpCode.textContent = `HTTP ${code} OK`;
    } else {
      banner.style.background = '#fef2f2';
      banner.style.borderColor = '#fecaca';
      icon.style.background = '#ef4444';
      icon.style.color = '#ffffff';
      icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
      title.style.color = '#991b1b';
      title.textContent = `Koneksi PCare Gagal [Code ${code}]`;
      sub.style.color = '#b91c1c';
      sub.textContent = res.message || res.metadata?.message || 'Gagal berkomunikasi dengan server BPJS';
      httpCode.style.background = '#fee2e2';
      httpCode.style.color = '#991b1b';
      httpCode.textContent = `HTTP ${code}`;
    }

    const dur = res.debug?.duration_ms || 0;
    latency.textContent = `⏱️ Latency: ${dur} ms`;

    const headersList = res.debug?.headers || [];
    const urlReq = res.debug?.url || 'https://apijkn.bpjs-kesehatan.go.id/pcare-rest';
    const methodReq = res.debug?.method || 'GET';
    
    let headerStr = `<span style="color:#a5f3fc;"># Request</span>\n<span style="color:#fcd34d;">${methodReq}</span> ${urlReq}\n\n<span style="color:#a5f3fc;"># Headers</span>\n`;
    if (headersList.length > 0) {
      headersList.forEach(h => { headerStr += `${h}\n`; });
    } else {
      headerStr += `ConsID: ${res.debug?.cons_id || '-'}\nUserKey: ${res.debug?.user_key || '-'}\n`;
    }
    reqDetails.innerHTML = headerStr;

    const responsePayload = {
      metadata: res.metadata || { code: code, message: res.message },
      response: res.response || null
    };
    resBody.textContent = JSON.stringify(responsePayload, null, 2);
  })
  .catch(err => {
    loading.style.display = 'none';
    result.style.display = 'flex';
    document.getElementById('logStatusTitle').textContent = 'Error Jaringan Lokal';
    document.getElementById('logStatusSub').textContent = 'Tidak dapat menghubungi server lokal SIMKlinik: ' + err.message;
    document.getElementById('logResponseBody').textContent = 'Network Error / Script Timeout';
  });
}

function cekPeserta() {
  const no = document.getElementById('inputNoKartu').value.trim();
  if (!no) { showToast('Masukkan nomor kartu BPJS atau NIK pasien', 'warning'); return; }

  showToast('Memverifikasi kepesertaan ke database & BPJS...', 'info');

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=cek_peserta&no=${encodeURIComponent(no)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success) {
      showToast(res.message || 'Data tidak ditemukan', 'warning');
      return;
    }
    const d = res.data;
    document.getElementById('boxHasilPeserta').style.display = 'block';
    document.getElementById('resNama').textContent       = d.nama;
    document.getElementById('resRm').textContent         = d.no_rkm_medis ? `No. RM: ${d.no_rkm_medis}` : '';
    document.getElementById('resNoKartu').textContent    = d.no_kartu;
    document.getElementById('resNik').textContent        = d.nik;
    document.getElementById('resTglLahir').textContent   = `${d.tgl_lahir} (${d.umur})`;
    document.getElementById('resJk').textContent         = d.jk;
    document.getElementById('resJnsPeserta').textContent = d.jenis_peserta;
    document.getElementById('resFaskes').textContent     = d.faskes;
    document.getElementById('resKelas').textContent      = d.kelas;
    document.getElementById('resAlamat').textContent     = d.alamat;
    document.getElementById('resStatus').textContent     = d.status_kartu;

    showToast(`Data kepesertaan ${d.nama} terverifikasi Aktif!`, 'success');
  })
  .catch(err => {
    showToast('Terjadi kesalahan saat memverifikasi data', 'danger');
  });
}

function cariDiagnosa() {
  const q = document.getElementById('inputDiagnosa').value.trim();
  if (q.length < 2) { showToast('Ketik minimal 2 huruf untuk mencari diagnosa', 'warning'); return; }

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php?action=cari_diagnosa&q=${encodeURIComponent(q)}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    const list = res.data?.response?.list || [];
    const tbody = document.getElementById('tbodyDiag');
    tbody.innerHTML = '';

    if (list.length === 0) {
      tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;color:var(--gray-400);">Tidak ditemukan</td></tr>';
    } else {
      list.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td style="font-weight:700;color:var(--primary-600);font-family:monospace;cursor:pointer;" onclick="document.getElementById('rujukKdDiag1').value='${item.kdDiag}';document.getElementById('rujukNmDiag1').value='${item.nmDiag}';">${item.kdDiag}</td><td>${item.nmDiag}</td>`;
        tbody.appendChild(tr);
      });
    }
    document.getElementById('boxHasilDiag').style.display = 'block';
  })
  .catch(() => {
    showToast('Gagal mencari referensi diagnosa', 'danger');
  });
function sinkronSemuaPcare() {
  const icon = document.getElementById('iconSyncAllPcare');
  if (icon) icon.classList.add('fa-spin');
  showToast('Menghubungi PCare BPJS untuk menarik data antrean hari ini...', 'info');

  fetch('<?= BASE_URL ?>modules/pcare/ajax.php?action=sinkron_semua_pcare&tgl=<?= urlencode($today) ?>', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(res => {
    if (icon) icon.classList.remove('fa-spin');
    if (res.success) {
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Gagal sinkron antrean PCare', 'danger');
    }
  })
  .catch(err => {
    if (icon) icon.classList.remove('fa-spin');
    showToast('Terjadi kesalahan saat sinkronisasi PCare: ' + err, 'danger');
  });
}

function daftarPCare(no_rawat, btn) {
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  }

  const formData = new FormData();
  formData.append('action', 'kirim_pendaftaran');
  formData.append('no_rawat', no_rawat);

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php`, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (btn) {
      btn.disabled = false;
      if (res.success) {
        btn.className = 'btn btn-sm btn-success';
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => location.reload(), 1000);
      } else {
        btn.innerHTML = '<i class="fas fa-user-plus"></i> 1. Daftar';
      }
    }
    showToast(res.message, res.success ? 'success' : 'warning');
  })
  .catch(err => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-user-plus"></i> 1. Daftar';
    }
    showToast('Gagal mengirim pendaftaran ke PCare BPJS: ' + err, 'danger');
  });
}

function syncPCare(no_rawat, btn) {
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  }

  const formData = new FormData();
  formData.append('action', 'sync_kunjungan');
  formData.append('no_rawat', no_rawat);

  fetch(`<?= BASE_URL ?>modules/pcare/ajax.php`, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    if (btn) {
      btn.disabled = false;
      if (res.success) {
        btn.className = 'btn btn-sm btn-success';
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => location.reload(), 1000);
      } else {
        btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> 2. Kirim';
      }
    }
    showToast(res.message, res.success ? 'success' : 'warning');
  })
  .catch(() => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> 2. Kirim';
    }
    showToast('Gagal mengirim data kunjungan ke PCare BPJS', 'danger');
  });
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
