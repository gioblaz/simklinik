<?php
/**
 * SIMKlinik — Modul Antrean Online BPJS Kesehatan (Mobile JKN & 7 Task Pelayanan)
 * Dilengkapi:
 * 1. Monitor Antrean & 7 Task Tracker
 * 2. Katalog URL & Dokumen Bridging BPJS
 * 3. API Tester / Postman Simulator Terpadu
 */

$page_title    = 'Antrean Online BPJS';
$active_module = 'antrean_bpjs';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_module_access('antrean_bpjs');
require_once dirname(__DIR__, 2) . '/includes/bpjs_antrean.php';

// ─── AJAX Actions ─────────────────────────────────────────────
if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
    $ajax_action = sanitize($_GET['ajax'] ?? $_POST['ajax'] ?? '');
    header('Content-Type: application/json');

    // 1. Update Task ID Manual / Otomatis
    if ($ajax_action === 'update_task') {
        $kodebooking = $conn->real_escape_string($_POST['kodebooking'] ?? '');
        $taskid      = (int)($_POST['taskid'] ?? 1);

        $res = BpjsAntreanService::updateTaskId($kodebooking, $taskid);
        echo json_encode(['success' => true, 'response' => $res, 'message' => "Task $taskid berhasil dikirim ke BPJS."]);
        exit;
    }

    // 2. Simulasi Ambil Antrean Mobile JKN
    if ($ajax_action === 'simulasi_booking') {
        $no_rm   = $conn->real_escape_string($_POST['no_rkm_medis'] ?? '');
        $kd_poli = $conn->real_escape_string($_POST['kd_poli'] ?? 'U0001');
        $kd_dok  = $conn->real_escape_string($_POST['kd_dokter'] ?? 'DR001');
        $tgl     = $conn->real_escape_string($_POST['tgl_periksa'] ?? date('Y-m-d'));

        // Ambil info pasien
        $p = $conn->query("SELECT no_rkm_medis, nm_pasien, no_peserta, no_ktp, no_tlp FROM pasien WHERE no_rkm_medis = '$no_rm' LIMIT 1")->fetch_assoc();
        if (!$p) {
            echo json_encode(['success' => false, 'message' => 'Pasien tidak ditemukan']);
            exit;
        }

        // Tembak webhook internal
        $payload = [
            'nomorkartu'     => $p['no_peserta'] ?: '0001234567890',
            'nik'            => $p['no_ktp'] ?: '3201000000000001',
            'nohp'           => $p['no_tlp'] ?: '081234567890',
            'kodepoli'       => $kd_poli,
            'norm'           => $p['no_rkm_medis'],
            'tanggalperiksa' => $tgl,
            'kodedokter'     => $kd_dok,
            'jampraktek'     => '08:00-14:00',
            'jeniskunjungan' => 1,
            'nomorreferensi' => 'REF-' . rand(10000, 99999)
        ];

        $ch = curl_init(BASE_URL . 'api/bpjs/antrean.php?action=ambilantrean');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($res, true);
        echo json_encode(['success' => true, 'data' => $json]);
        exit;
    }

    // 3. Batalkan Booking Mobile JKN
    if ($ajax_action === 'batal_booking') {
        $kodebooking = $conn->real_escape_string($_POST['kodebooking'] ?? '');
        $res = BpjsAntreanService::batalAntrean($kodebooking, 'Dibatalkan dari Dashboard SIMKlinik');
        $conn->query("UPDATE mlite_antrian_referensi SET status_kirim = 'Batal' WHERE kodebooking = '$kodebooking'");
        echo json_encode(['success' => true, 'message' => 'Antrean berhasil dibatalkan.']);
        exit;
    }

    // 4. API Tester / Postman Runner
    if ($ajax_action === 'run_postman_test') {
        $url     = trim($_POST['url'] ?? '');
        $method  = strtoupper(trim($_POST['method'] ?? 'GET'));
        $body    = trim($_POST['body'] ?? '');
        $headers_input = trim($_POST['headers'] ?? '');

        if (empty($url)) {
            echo json_encode(['success' => false, 'message' => 'URL endpoint tidak boleh kosong']);
            exit;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $http_headers = ['Content-Type: application/json'];
        if (!empty($headers_input)) {
            $custom_lines = explode("\n", str_replace("\r", "", $headers_input));
            foreach ($custom_lines as $line) {
                if (trim($line)) $http_headers[] = trim($line);
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $start_time  = microtime(true);
        $response    = curl_exec($ch);
        $elapsed_ms  = round((microtime(true) - $start_time) * 1000);
        $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $err         = curl_error($ch);
        curl_close($ch);

        if ($err) {
            echo json_encode([
                'success'    => false,
                'http_code'  => 500,
                'elapsed_ms' => $elapsed_ms,
                'message'    => 'CURL Error: ' . $err
            ]);
            exit;
        }

        $res_headers = substr($response, 0, $header_size);
        $res_body    = substr($response, $header_size);
        $size_bytes  = strlen($res_body);

        echo json_encode([
            'success'    => true,
            'http_code'  => $http_code,
            'elapsed_ms' => $elapsed_ms,
            'size_bytes' => $size_bytes,
            'headers'    => $res_headers,
            'body'       => $res_body
        ]);
        exit;
    }
}

// ─── Filter & Data Antrean Hari Ini ───────────────────────────
$today = date('Y-m-d');
$tgl   = sanitize($_GET['tgl'] ?? $today);

$antrian_res = $conn->query("
    SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg, r.kd_dokter, r.kd_poli, r.stts, r.kd_pj,
           p.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir, p.no_peserta, p.alamat,
           pol.nm_poli, d.nm_dokter, pj.png_jawab as nm_penjab,
           COALESCE(ar.kodebooking, CONCAT('BK', DATE_FORMAT(r.tgl_registrasi, '%Y%m%d'), LPAD(r.no_reg, 4, '0'))) as kodebooking,
           COALESCE(ar.status_kirim, 'Aktif') as status_kirim
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
    LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
    LEFT JOIN mlite_antrian_referensi ar ON (r.no_rkm_medis = ar.no_rkm_medis AND r.tgl_registrasi = ar.tanggal_periksa)
    WHERE r.tgl_registrasi = '$tgl'
    ORDER BY CAST(r.no_reg AS UNSIGNED) ASC, r.jam_reg ASC
");

$antrian_list = [];
if ($antrian_res) {
    while ($row = $antrian_res->fetch_assoc()) {
        $kb = $conn->real_escape_string($row['kodebooking']);
        $tasks = [];
        $t_res = $conn->query("SELECT taskid, status, waktu FROM mlite_antrian_referensi_taskid WHERE nomor_referensi = '$kb' ORDER BY taskid ASC");
        if ($t_res) {
            while ($tr = $t_res->fetch_assoc()) {
                $tasks[(int)$tr['taskid']] = $tr;
            }
        }
        $row['tasks'] = $tasks;
        $antrian_list[] = $row;
    }
}

// Stats
$total_booking = count($antrian_list);
$total_checkin = 0;
$total_selesai = 0;
$total_batal   = 0;

foreach ($antrian_list as $a) {
    if ($a['status_kirim'] === 'Batal') $total_batal++;
    if (isset($a['tasks'][1])) $total_checkin++;
    if (isset($a['tasks'][7])) $total_selesai++;
}

// Master Poli & Pasien untuk Modal Simulasi
$poli_list = [];
$rp = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1'");
if ($rp) while ($r = $rp->fetch_assoc()) $poli_list[] = $r;

$dokter_list = [];
$rd = $conn->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status = '1'");
if ($rd) while ($r = $rd->fetch_assoc()) $dokter_list[] = $r;

$bpjs_cfg = BpjsAntreanService::getConfig();

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
  <div>
    <h1 class="page-title" style="font-size:18px;margin-bottom:2px;">Bridging Antrean Online BPJS (Mobile JKN)</h1>
    <p class="page-subtitle" style="font-size:12px;margin:0;">Integrasi pendaftaran pasien Mobile JKN, 7 Task Waktu Tunggu & Postman API Tester</p>
  </div>
  <div class="page-actions" style="display:flex;align-items:center;gap:8px;">
    <button type="button" class="btn btn-secondary" onclick="openModalSimulator()" style="font-size:12.5px;padding:7px 14px;">
      <i class="fas fa-mobile-alt"></i> Simulasi Booking Mobile JKN
    </button>
    <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="btn btn-primary" style="font-size:12.5px;padding:7px 16px;font-weight:700;">
      <i class="fas fa-sliders-h"></i> Pengaturan Kredensial
    </a>
  </div>
</div>

<!-- ─── Navigation Tabs Antrean vs Katalog URL vs API Tester ── -->
<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:6px 10px;margin-bottom:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
  <button type="button" class="btn btn-sm btn-primary tab-nav-btn" id="btnTabAntrean" onclick="switchAntreanTab('antrean')" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;padding:6px 14px;">
    <i class="fas fa-list-ol"></i> 1. Monitor Antrean & 7 Task Tracker
  </button>
  <button type="button" class="btn btn-sm btn-secondary tab-nav-btn" id="btnTabKatalog" onclick="switchAntreanTab('katalog')" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;padding:6px 14px;">
    <i class="fas fa-book-open" style="color:#0284c7;"></i> 2. Katalog URL Webhook & Dokumen BPJS
  </button>
  <button type="button" class="btn btn-sm btn-secondary tab-nav-btn" id="btnTabTester" onclick="switchAntreanTab('tester')" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;padding:6px 14px;background:#fdf2f8;color:#be185d;border-color:#fbcfe8;">
    <i class="fas fa-terminal"></i> 3. API Tester (Postman Simulator)
  </button>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TAB PANE 1: MONITOR ANTREAN & 7 TASK TRACKER                -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div id="paneAntrean" style="display:block;">

  <!-- KPI Stats Summary Cards -->
  <div class="stats-grid mb-14" style="grid-template-columns:repeat(4, 1fr);gap:12px;">
    
    <div class="stat-card" style="padding:12px 16px;border-left:4px solid #3b82f6;">
      <div class="stat-header">
        <span class="stat-title" style="font-size:11.5px;">Total Booking Mobile JKN</span>
        <div class="stat-icon" style="background:#eff6ff;color:#3b82f6;width:34px;height:34px;"><i class="fas fa-ticket-alt"></i></div>
      </div>
      <div class="stat-value" style="font-size:22px;margin-top:4px;"><?= $total_booking ?></div>
      <div class="stat-desc" style="font-size:11px;color:#64748b;">Kunjungan <?= tgl_indo($tgl) ?></div>
    </div>

    <div class="stat-card" style="padding:12px 16px;border-left:4px solid #8b5cf6;">
      <div class="stat-header">
        <span class="stat-title" style="font-size:11.5px;">Sudah Check-In (Task 1)</span>
        <div class="stat-icon" style="background:#f5f3ff;color:#8b5cf6;width:34px;height:34px;"><i class="fas fa-user-check"></i></div>
      </div>
      <div class="stat-value" style="font-size:22px;margin-top:4px;"><?= $total_checkin ?></div>
      <div class="stat-desc" style="font-size:11px;color:#64748b;">Hadir di Klinik</div>
    </div>

    <div class="stat-card" style="padding:12px 16px;border-left:4px solid #10b981;">
      <div class="stat-header">
        <span class="stat-title" style="font-size:11.5px;">Selesai Pelayanan (Task 7)</span>
        <div class="stat-icon" style="background:#ecfdf5;color:#10b981;width:34px;height:34px;"><i class="fas fa-check-circle"></i></div>
      </div>
      <div class="stat-value" style="font-size:22px;margin-top:4px;"><?= $total_selesai ?></div>
      <div class="stat-desc" style="font-size:11px;color:#64748b;">Obat Diserahkan</div>
    </div>

    <div class="stat-card" style="padding:12px 16px;border-left:4px solid #ef4444;">
      <div class="stat-header">
        <span class="stat-title" style="font-size:11.5px;">Dibatalkan</span>
        <div class="stat-icon" style="background:#fef2f2;color:#ef4444;width:34px;height:34px;"><i class="fas fa-times-circle"></i></div>
      </div>
      <div class="stat-value" style="font-size:22px;margin-top:4px;"><?= $total_batal ?></div>
      <div class="stat-desc" style="font-size:11px;color:#64748b;">Batal / Kadaluarsa</div>
    </div>

  </div>

  <!-- Webhook Info Bar -->
  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:9px 16px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <div style="font-size:12px;color:#334155;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-link text-primary"></i>
      <span>URL Webhook Faskes:</span>
      <code style="font-weight:700;color:#0369a1;background:#e0f2fe;padding:2px 8px;border-radius:6px;"><?= BASE_URL ?>api/bpjs/antrean.php</code>
    </div>
    <div style="display:flex;gap:6px;">
      <button type="button" class="btn btn-sm btn-secondary" onclick="switchAntreanTab('katalog')" style="padding:3px 10px;font-size:11.5px;">
        <i class="fas fa-book-open"></i> Katalog URL
      </button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="switchAntreanTab('tester')" style="padding:3px 10px;font-size:11.5px;color:#db2777;">
        <i class="fas fa-terminal"></i> Buka API Tester
      </button>
    </div>
  </div>

  <!-- Tabel Daftar Antrean Mobile JKN & 7 Task Tracker -->
  <div class="card" style="margin-bottom:14px;">
    <div class="card-header" style="padding:10px 16px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <div class="card-title" style="font-size:13px;display:flex;align-items:center;gap:6px;margin:0;">
        <i class="fas fa-list-ol text-primary"></i>
        <span>Daftar Pasien Booking Mobile JKN & Status 7 Task ID</span>
      </div>
      <form method="GET" style="display:flex;align-items:center;gap:6px;margin:0;">
        <label style="font-size:12px;color:#64748b;font-weight:600;">Tanggal:</label>
        <input type="date" name="tgl" class="form-control" style="width:140px;height:30px;font-size:12px;padding:2px 8px;" value="<?= $tgl ?>" onchange="this.form.submit()">
      </form>
    </div>

    <div class="card-body" style="padding:0;">
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:12.5px;">
          <thead>
            <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
              <th style="width:110px;padding:8px 12px;">No. Booking</th>
              <th style="padding:8px 12px;">Pasien & No. RM</th>
              <th style="padding:8px 12px;">Poli / Dokter</th>
              <th style="width:90px;text-align:center;padding:8px 8px;">Antrean</th>
              <th style="width:260px;padding:8px 10px;">Progress 7 Task ID Pelayanan</th>
              <th style="width:90px;text-align:center;padding:8px 8px;">Status</th>
              <th style="width:120px;text-align:right;padding:8px 14px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($antrian_list)): ?>
              <tr>
                <td colspan="7" style="text-align:center;padding:40px;color:#94a3b8;">
                  <i class="fas fa-calendar-times" style="font-size:32px;color:#cbd5e1;display:block;margin-bottom:8px;"></i>
                  Belum ada pendaftaran pasien via Mobile JKN pada tanggal ini.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($antrian_list as $a): ?>
                <tr>
                  <td style="padding:8px 12px;">
                    <code style="font-weight:700;color:#0f172a;background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11.5px;">
                      <?= htmlspecialchars($a['kodebooking']) ?>
                    </code>
                  </td>
                  <td style="padding:8px 12px;">
                    <div style="font-weight:700;color:#0f172a;"><?= htmlspecialchars($a['nm_pasien']) ?></div>
                    <div style="font-size:11px;color:#64748b;margin-top:2px;">
                      RM: <b><?= $a['no_rkm_medis'] ?></b> &bull; <?= $a['no_peserta'] ? htmlspecialchars($a['no_peserta']) : 'Non-BPJS' ?>
                    </div>
                    <?php if (!empty($a['alamat'])): ?>
                      <div style="font-size:11px;color:#475569;margin-top:2px;display:flex;align-items:center;gap:3px;">
                        <i class="fas fa-map-marker-alt" style="color:#0891b2;font-size:9.5px;flex-shrink:0;"></i>
                        <span style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($a['alamat']) ?>"><?= htmlspecialchars($a['alamat']) ?></span>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td style="padding:8px 12px;">
                    <div style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($a['nm_poli'] ?: 'Poli Umum') ?></div>
                    <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($a['nm_dokter'] ?: '-') ?></div>
                  </td>
                  <td style="text-align:center;font-weight:700;color:var(--primary-700);font-size:13px;padding:8px 8px;">
                    <?= htmlspecialchars($a['no_reg'] ?: 'A-001') ?>
                  </td>
                  <td style="padding:8px 10px;">
                    <!-- Visual 7 Task Pills -->
                    <div style="display:flex;align-items:center;gap:4px;">
                      <?php for ($t = 1; $t <= 7; $t++): 
                        $is_done = isset($a['tasks'][$t]);
                      ?>
                        <button type="button" 
                                onclick="kirimTask('<?= $a['kodebooking'] ?>', <?= $t ?>)"
                                class="btn btn-sm"
                                title="Task <?= $t ?>: <?= getTaskName($t) ?> (<?= $is_done ? 'Sudah Terkirim' : 'Klik untuk Kirim' ?>)"
                                style="width:26px;height:26px;padding:0;border-radius:50%;font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;
                                       background:<?= $is_done ? '#10b981' : '#e2e8f0' ?>;color:<?= $is_done ? '#fff' : '#64748b' ?>;border:none;">
                          <?= $t ?>
                        </button>
                      <?php endfor; ?>
                    </div>
                    <div style="font-size:10px;color:#64748b;margin-top:4px;">
                      <?php
                      $latest_task = 0;
                      for ($t = 7; $t >= 1; $t--) {
                          if (isset($a['tasks'][$t])) { $latest_task = $t; break; }
                      }
                      echo $latest_task > 0 ? "Posisi: <b>Task $latest_task (" . getTaskName($latest_task) . ")</b>" : "Menunggu Pasien Hadir (Task 1)";
                      ?>
                    </div>
                  </td>
                  <td style="text-align:center;padding:8px 8px;">
                    <span class="badge badge-<?= $a['status_kirim']==='Batal' ? 'danger' : ($latest_task===7 ? 'success' : 'primary') ?>" style="font-size:10px;">
                      <?= $a['status_kirim']==='Batal' ? 'Batal' : ($latest_task===7 ? 'Selesai' : 'Aktif') ?>
                    </span>
                  </td>
                  <td style="text-align:right;padding:8px 14px;">
                    <div style="display:inline-flex;gap:4px;">
                      <?php if ($a['status_kirim'] !== 'Batal' && $latest_task < 7): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" style="padding:3px 7px;font-size:11px;" title="Batalkan Antrean" onclick="batalkanAntrean('<?= $a['kodebooking'] ?>')">
                          <i class="fas fa-ban"></i>
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
  </div>

</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TAB PANE 2: KATALOG RESMI URL & DOKUMEN BRIDGING BPJS       -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div id="paneKatalog" style="display:none;">

  <!-- Action Bar Print / Copy All -->
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
    <div>
      <h2 style="font-size:15px;font-weight:700;margin:0;color:#0f172a;">Katalog Endpoint Webservice Antrean Online BPJS</h2>
      <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Dokumen teknis resmi endpoint webhook untuk diserahkan ke Tim IT BPJS Kesehatan / Kantor Cabang</p>
    </div>
    <div style="display:flex;gap:8px;">
      <button type="button" class="btn btn-secondary" onclick="window.print()" style="font-size:12px;padding:6px 14px;">
        <i class="fas fa-print"></i> Cetak Dokumen Katalog
      </button>
      <button type="button" class="btn btn-primary" onclick="salinSemuaKatalog()" style="font-size:12px;padding:6px 16px;font-weight:700;">
        <i class="fas fa-copy"></i> Salin Seluruh Katalog URL
      </button>
    </div>
  </div>

  <!-- Identitas Faskes Card -->
  <div class="card mb-16" style="border-top:4px solid var(--primary-600);">
    <div class="card-header" style="background:#f8fafc;padding:10px 18px;">
      <div class="card-title" style="font-size:13px;"><i class="fas fa-hospital text-primary"></i> I. Profil Faskes & Kredensial Bridging</div>
    </div>
    <div class="card-body" style="padding:16px 20px;">
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:14px;font-size:12.5px;">
        
        <div style="background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;">
          <span style="font-size:11px;color:#64748b;display:block;">Nama Fasilitas Kesehatan (Klinik):</span>
          <strong style="color:#0f172a;font-size:13px;"><?= INSTANSI_NAMA ?></strong>
        </div>

        <div style="background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;">
          <span style="font-size:11px;color:#64748b;display:block;">Kode PPK / Faskes (BPJS):</span>
          <strong style="color:#0f172a;font-size:13px;font-family:monospace;"><?= htmlspecialchars($bpjs_cfg['kode_ppk']) ?></strong>
        </div>

        <div style="background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;">
          <span style="font-size:11px;color:#64748b;display:block;">Cons ID Antrean:</span>
          <strong style="color:#0f172a;font-size:13px;font-family:monospace;"><?= htmlspecialchars($bpjs_cfg['cons_id'] ?: 'Belum diisi') ?></strong>
        </div>

        <div style="background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;">
          <span style="font-size:11px;color:#64748b;display:block;">User Key Antrean:</span>
          <strong style="color:#0f172a;font-size:13px;font-family:monospace;"><?= htmlspecialchars(substr($bpjs_cfg['user_key'], 0, 10)) ?>...</strong>
        </div>

        <div style="grid-column: 1 / -1;background:#eff6ff;padding:10px 14px;border-radius:8px;border:1px solid #bfdbfe;display:flex;justify-content:space-between;align-items:center;">
          <div>
            <span style="font-size:11px;color:#1e40af;display:block;font-weight:700;">Base URL Webservice Faskes:</span>
            <code style="color:#1e3a8a;font-weight:700;font-size:13px;"><?= BASE_URL ?>api/bpjs/antrean.php</code>
          </div>
          <button type="button" class="btn btn-sm btn-primary" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>api/bpjs/antrean.php');showToast('Base URL disalin!','success');" style="padding:4px 10px;font-size:11.5px;">
            <i class="fas fa-copy"></i> Salin Base URL
          </button>
        </div>

      </div>
    </div>
  </div>

  <!-- Katalog 6 Endpoints Grid -->
  <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:16px;">
    
    <!-- 1. Status Antrean -->
    <div class="card" style="margin-bottom:0;">
      <div class="card-header" style="background:#f8fafc;padding:10px 18px;display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span class="badge badge-primary" style="font-size:11px;padding:3px 8px;font-weight:700;">GET</span>
          <strong style="font-size:13px;color:#0f172a;">1. Status Antrean & Sisa Kuota Poli</strong>
        </div>
        <div style="display:flex;gap:6px;">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTesterPreset('statusantrean')" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-play"></i> Test di Postman Tester
          </button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>api/bpjs/antrean.php?action=statusantrean&kodepoli=U0001&tanggal=<?= date('Y-m-d') ?>');showToast('URL disalin!','success');" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-copy"></i> Salin Endpoint
          </button>
        </div>
      </div>
      <div class="card-body" style="padding:14px 18px;font-size:12px;">
        <div style="margin-bottom:8px;">
          <span style="color:#64748b;">Endpoint:</span>
          <code style="font-weight:700;color:#0369a1;background:#f1f5f9;padding:2px 8px;border-radius:4px;display:inline-block;margin-top:2px;">
            <?= BASE_URL ?>api/bpjs/antrean.php?action=statusantrean&kodepoli={kodepoli}&tanggal={yyyy-mm-dd}
          </code>
        </div>
        <p style="color:#475569;margin:0 0 8px;"><strong>Deskripsi:</strong> Dipanggil oleh server Mobile JKN untuk menampilkan kuota tersisa dan nomor antrean yang sedang dipanggil pada poliklinik tujuan.</p>
      </div>
    </div>

    <!-- 2. Ambil Antrean Mobile JKN -->
    <div class="card" style="margin-bottom:0;">
      <div class="card-header" style="background:#f8fafc;padding:10px 18px;display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span class="badge badge-success" style="font-size:11px;padding:3px 8px;font-weight:700;background:#059669;">POST</span>
          <strong style="font-size:13px;color:#0f172a;">2. Ambil Antrean Baru (Booking Pasien Mobile JKN)</strong>
        </div>
        <div style="display:flex;gap:6px;">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTesterPreset('ambilantrean')" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-play"></i> Test di Postman Tester
          </button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>api/bpjs/antrean.php?action=ambilantrean');showToast('URL disalin!','success');" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-copy"></i> Salin Endpoint
          </button>
        </div>
      </div>
      <div class="card-body" style="padding:14px 18px;font-size:12px;">
        <div style="margin-bottom:8px;">
          <span style="color:#64748b;">Endpoint:</span>
          <code style="font-weight:700;color:#0369a1;background:#f1f5f9;padding:2px 8px;border-radius:4px;display:inline-block;margin-top:2px;">
            <?= BASE_URL ?>api/bpjs/antrean.php?action=ambilantrean
          </code>
        </div>
        <p style="color:#475569;margin:0;"><strong>Deskripsi:</strong> Menerima data pendaftaran saat pasien menekan tombol Ambil Antrean di aplikasi Mobile JKN dari rumah.</p>
      </div>
    </div>

    <!-- 3. Batal Antrean -->
    <div class="card" style="margin-bottom:0;">
      <div class="card-header" style="background:#f8fafc;padding:10px 18px;display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span class="badge badge-danger" style="font-size:11px;padding:3px 8px;font-weight:700;background:#ef4444;">POST</span>
          <strong style="font-size:13px;color:#0f172a;">3. Pembatalan Antrean Pasien</strong>
        </div>
        <div style="display:flex;gap:6px;">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTesterPreset('batalantrean')" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-play"></i> Test di Postman Tester
          </button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>api/bpjs/antrean.php?action=batalantrean');showToast('URL disalin!','success');" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-copy"></i> Salin Endpoint
          </button>
        </div>
      </div>
      <div class="card-body" style="padding:14px 18px;font-size:12px;">
        <div style="margin-bottom:8px;">
          <span style="color:#64748b;">Endpoint:</span>
          <code style="font-weight:700;color:#0369a1;background:#f1f5f9;padding:2px 8px;border-radius:4px;display:inline-block;margin-top:2px;">
            <?= BASE_URL ?>api/bpjs/antrean.php?action=batalantrean
          </code>
        </div>
        <p style="color:#475569;margin:0;"><strong>Deskripsi:</strong> Dipanggil ketika pasien membatalkan janji kunjungan melalui aplikasi Mobile JKN.</p>
      </div>
    </div>

    <!-- 4. Check-in Kehadiran (Task 1) -->
    <div class="card" style="margin-bottom:0;">
      <div class="card-header" style="background:#f8fafc;padding:10px 18px;display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span class="badge badge-success" style="font-size:11px;padding:3px 8px;font-weight:700;background:#059669;">POST</span>
          <strong style="font-size:13px;color:#0f172a;">4. Check-In Kedatangan Pasien di Faskes (Task 1)</strong>
        </div>
        <div style="display:flex;gap:6px;">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTesterPreset('checkin')" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-play"></i> Test di Postman Tester
          </button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>api/bpjs/antrean.php?action=checkin');showToast('URL disalin!','success');" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-copy"></i> Salin Endpoint
          </button>
        </div>
      </div>
      <div class="card-body" style="padding:14px 18px;font-size:12px;">
        <div style="margin-bottom:8px;">
          <span style="color:#64748b;">Endpoint:</span>
          <code style="font-weight:700;color:#0369a1;background:#f1f5f9;padding:2px 8px;border-radius:4px;display:inline-block;margin-top:2px;">
            <?= BASE_URL ?>api/bpjs/antrean.php?action=checkin
          </code>
        </div>
        <p style="color:#475569;margin:0;"><strong>Deskripsi:</strong> Konfirmasi kedatangan pasien saat tiba di klinik (scan QR di loket faskes / kiosk pendaftaran).</p>
      </div>
    </div>

    <!-- 5. Jadwal Dokter -->
    <div class="card" style="margin-bottom:0;">
      <div class="card-header" style="background:#f8fafc;padding:10px 18px;display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span class="badge badge-primary" style="font-size:11px;padding:3px 8px;font-weight:700;">GET</span>
          <strong style="font-size:13px;color:#0f172a;">5. Jadwal Praktek Dokter Poliklinik</strong>
        </div>
        <div style="display:flex;gap:6px;">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTesterPreset('jadwaldokter')" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-play"></i> Test di Postman Tester
          </button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>api/bpjs/antrean.php?action=jadwaldokter');showToast('URL disalin!','success');" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-copy"></i> Salin Endpoint
          </button>
        </div>
      </div>
      <div class="card-body" style="padding:14px 18px;font-size:12px;">
        <div style="margin-bottom:8px;">
          <span style="color:#64748b;">Endpoint:</span>
          <code style="font-weight:700;color:#0369a1;background:#f1f5f9;padding:2px 8px;border-radius:4px;display:inline-block;margin-top:2px;">
            <?= BASE_URL ?>api/bpjs/antrean.php?action=jadwaldokter&kodepoli={kodepoli}&tanggal={yyyy-mm-dd}
          </code>
        </div>
        <p style="color:#475569;margin:0;"><strong>Deskripsi:</strong> Mengirimkan daftar dokter aktif, jam buka praktek dan kapasitas layanan klinik ke Mobile JKN.</p>
      </div>
    </div>

    <!-- 6. Validasi Pasien Baru -->
    <div class="card" style="margin-bottom:0;">
      <div class="card-header" style="background:#f8fafc;padding:10px 18px;display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span class="badge badge-primary" style="font-size:11px;padding:3px 8px;font-weight:700;">GET</span>
          <strong style="font-size:13px;color:#0f172a;">6. Validasi Pasien Baru (Cek NIK)</strong>
        </div>
        <div style="display:flex;gap:6px;">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTesterPreset('pasienbaru')" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-play"></i> Test di Postman Tester
          </button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>api/bpjs/antrean.php?action=pasienbaru&nik=3201000000000001');showToast('URL disalin!','success');" style="padding:3px 8px;font-size:11px;">
            <i class="fas fa-copy"></i> Salin Endpoint
          </button>
        </div>
      </div>
      <div class="card-body" style="padding:14px 18px;font-size:12px;">
        <div style="margin-bottom:8px;">
          <span style="color:#64748b;">Endpoint:</span>
          <code style="font-weight:700;color:#0369a1;background:#f1f5f9;padding:2px 8px;border-radius:4px;display:inline-block;margin-top:2px;">
            <?= BASE_URL ?>api/bpjs/antrean.php?action=pasienbaru&nik={nik_pasien}
          </code>
        </div>
        <p style="color:#475569;margin:0;"><strong>Deskripsi:</strong> Mengecek apakah NIK pasien yang mendaftar dari Mobile JKN sudah terdaftar di rekam medis SIMKlinik.</p>
      </div>
    </div>

  </div>

</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TAB PANE 3: API TESTER / POSTMAN SIMULATOR TERPADU         -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div id="paneTester" style="display:none;">

  <div class="card" style="border-top:4px solid #db2777;box-shadow:0 4px 16px rgba(0,0,0,0.06);margin-bottom:14px;">
    
    <div class="card-header" style="background:#f8fafc;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;">
      <div class="card-title" style="font-size:13.5px;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-terminal" style="color:#db2777;"></i>
        <span>Postman API Tester — Endpoint Webhook Antrean BPJS</span>
      </div>
      
      <!-- Preset Selector -->
      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:11.5px;color:#64748b;font-weight:600;">Preset Endpoint:</label>
        <select id="selectTesterPreset" class="form-control" style="width:240px;height:30px;font-size:12px;padding:2px 8px;" onchange="loadTesterPreset(this.value)">
          <option value="statusantrean">1. [GET] Status Antrean Poli</option>
          <option value="ambilantrean">2. [POST] Ambil Antrean Mobile JKN</option>
          <option value="checkin">3. [POST] Check-In Kedatangan (Task 1)</option>
          <option value="batalantrean">4. [POST] Batal Antrean</option>
          <option value="jadwaldokter">5. [GET] Jadwal Dokter</option>
          <option value="pasienbaru">6. [GET] Validasi Pasien Baru</option>
          <option value="custom">-- Custom Endpoint URL --</option>
        </select>
      </div>
    </div>

    <div class="card-body" style="padding:18px 20px;">
      
      <!-- Postman URL Bar -->
      <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;">
        <select id="testerMethod" class="form-control" style="width:110px;height:38px;font-size:13px;font-weight:700;background:#f8fafc;border-color:#cbd5e1;" onchange="onMethodChange(this.value)">
          <option value="GET">GET</option>
          <option value="POST">POST</option>
          <option value="PUT">PUT</option>
          <option value="DELETE">DELETE</option>
        </select>
        
        <input type="text" id="testerUrl" class="form-control" style="height:38px;font-size:13px;font-family:monospace;flex:1;"
               value="<?= BASE_URL ?>api/bpjs/antrean.php?action=statusantrean&kodepoli=U0001&tanggal=<?= date('Y-m-d') ?>" placeholder="https://...">

        <button type="button" id="btnSendTester" class="btn btn-primary" onclick="sendPostmanRequest()"
                style="height:38px;padding:0 24px;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:6px;background:#db2777;border-color:#be185d;">
          <i class="fas fa-paper-plane" id="sendIcon"></i> <span id="sendText">Send</span>
        </button>
      </div>

      <!-- Request Body & Headers Section -->
      <div style="margin-bottom:16px;">
        
        <div style="display:flex;gap:8px;border-bottom:1px solid #e2e8f0;margin-bottom:10px;">
          <button type="button" class="btn btn-sm btn-primary tester-req-tab" id="tabReqBody" onclick="switchTesterReqTab('body')" style="padding:4px 12px;font-size:12px;border-radius:6px 6px 0 0;">
            Body (Raw JSON)
          </button>
          <button type="button" class="btn btn-sm btn-secondary tester-req-tab" id="tabReqHeaders" onclick="switchTesterReqTab('headers')" style="padding:4px 12px;font-size:12px;border-radius:6px 6px 0 0;">
            Headers
          </button>
        </div>

        <!-- Tab Body JSON -->
        <div id="paneReqBody" style="display:block;">
          <textarea id="testerBody" class="form-control" rows="8" style="font-family:monospace;font-size:12px;background:#0f172a;color:#a7f3d0;border-radius:8px;padding:12px;line-height:1.4;" placeholder="{}"></textarea>
        </div>

        <!-- Tab Headers -->
        <div id="paneReqHeaders" style="display:none;">
          <textarea id="testerHeaders" class="form-control" rows="4" style="font-family:monospace;font-size:12px;background:#f8fafc;border-radius:8px;padding:10px;" placeholder="Content-Type: application/json"></textarea>
        </div>

      </div>

      <!-- Response Section (Postman Style) -->
      <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#ffffff;">
        
        <div style="padding:8px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
          
          <div style="display:flex;align-items:center;gap:12px;font-size:12px;">
            <strong style="color:#0f172a;"><i class="fas fa-reply text-primary"></i> Response:</strong>
            <span id="respStatusBadge" class="badge badge-secondary" style="font-size:11px;font-weight:700;">Status: -</span>
            <span id="respTimeBadge" style="color:#64748b;font-size:11.5px;font-family:monospace;">Time: - ms</span>
            <span id="respSizeBadge" style="color:#64748b;font-size:11.5px;font-family:monospace;">Size: - B</span>
          </div>

          <div style="display:flex;gap:6px;">
            <button type="button" class="btn btn-sm btn-secondary" onclick="formatResponseJson()" style="padding:3px 8px;font-size:11px;">
              <i class="fas fa-indent"></i> Format JSON
            </button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="copyTesterResponse()" style="padding:3px 8px;font-size:11px;">
              <i class="fas fa-copy"></i> Salin Response
            </button>
          </div>

        </div>

        <div style="padding:12px 14px;background:#0b1120;">
          <pre id="testerResponseBody" style="margin:0;font-family:monospace;font-size:12px;color:#38bdf8;max-height:350px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;">Klik "Send" untuk menjalankan request endpoint...</pre>
        </div>

      </div>

    </div>

  </div>

</div>

<!-- ─── Modal Simulasi Booking Mobile JKN ─────────────────── -->
<div id="modalSimulator" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:9999;padding:16px;">
  <div class="modal-content" style="background:#fff;border-radius:12px;max-width:520px;width:100%;box-shadow:0 10px 25px rgba(0,0,0,0.2);overflow:hidden;">
    
    <div class="modal-header" style="padding:14px 18px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
      <h3 style="margin:0;font-size:14px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-mobile-alt text-primary"></i> Simulator Booking Pasien Mobile JKN
      </h3>
      <button type="button" style="background:none;border:none;font-size:16px;cursor:pointer;color:#64748b;" onclick="closeModalSimulator()">&times;</button>
    </div>

    <form onsubmit="submitSimulasiBooking(event)">
      <div class="modal-body" style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;">
        
        <div class="form-group">
          <label class="form-label">Pilih Pasien Terdaftar <span style="color:#ef4444;">*</span></label>
          <input type="text" id="simSearchPasien" class="form-control" placeholder="Ketik No. RM atau Nama Pasien..." oninput="onSimSearchPasien(this.value)">
          <input type="hidden" id="simNoRm" required>
          <div id="simPasienSelected" style="display:none;margin-top:6px;font-size:12px;color:#0369a1;background:#e0f2fe;padding:6px 10px;border-radius:6px;"></div>
          <div id="simPasienResults" style="display:none;border:1px solid #cbd5e1;border-radius:6px;max-height:160px;overflow-y:auto;margin-top:4px;"></div>
        </div>

        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label">Poliklinik Tujuan</label>
            <select id="simKdPoli" class="form-control">
              <?php foreach ($poli_list as $pl): ?>
                <option value="<?= $pl['kd_poli'] ?>"><?= htmlspecialchars($pl['nm_poli']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Dokter Pemeriksa</label>
            <select id="simKdDokter" class="form-control">
              <?php foreach ($dokter_list as $dl): ?>
                <option value="<?= $dl['kd_dokter'] ?>"><?= htmlspecialchars($dl['nm_dokter']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Tanggal Rencana Kunjungan</label>
          <input type="date" id="simTglPeriksa" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>

      </div>

      <div class="modal-footer" style="padding:12px 18px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeModalSimulator()">Batal</button>
        <button type="submit" class="btn btn-primary" style="font-weight:700;"><i class="fas fa-paper-plane"></i> Kirim Booking Mobile JKN</button>
      </div>
    </form>

  </div>
</div>

<script>
// ─── Sub-Tab Switcher ─────────────────────────────────────────
function switchAntreanTab(tab) {
  const pAntrean = document.getElementById('paneAntrean');
  const pKatalog = document.getElementById('paneKatalog');
  const pTester  = document.getElementById('paneTester');
  const bAntrean = document.getElementById('btnTabAntrean');
  const bKatalog = document.getElementById('btnTabKatalog');
  const bTester  = document.getElementById('btnTabTester');

  pAntrean.style.display = 'none';
  pKatalog.style.display = 'none';
  pTester.style.display  = 'none';

  bAntrean.className = 'btn btn-sm btn-secondary tab-nav-btn';
  bKatalog.className = 'btn btn-sm btn-secondary tab-nav-btn';
  bTester.className  = 'btn btn-sm btn-secondary tab-nav-btn';

  if (tab === 'katalog') {
    pKatalog.style.display = 'block';
    bKatalog.className = 'btn btn-sm btn-primary tab-nav-btn';
  } else if (tab === 'tester') {
    pTester.style.display = 'block';
    bTester.className = 'btn btn-sm btn-primary tab-nav-btn';
  } else {
    pAntrean.style.display = 'block';
    bAntrean.className = 'btn btn-sm btn-primary tab-nav-btn';
  }
}

// ─── Postman Tester Logic ─────────────────────────────────────
const baseUrl = '<?= BASE_URL ?>api/bpjs/antrean.php';

const testerPresets = {
  statusantrean: {
    method: 'GET',
    url: `${baseUrl}?action=statusantrean&kodepoli=U0001&tanggal=<?= date('Y-m-d') ?>`,
    body: ''
  },
  ambilantrean: {
    method: 'POST',
    url: `${baseUrl}?action=ambilantrean`,
    body: JSON.stringify({
      nomorkartu: "0001234567890",
      nik: "3201234567890001",
      nohp: "081234567890",
      kodepoli: "U0001",
      norm: "000001",
      tanggalperiksa: "<?= date('Y-m-d') ?>",
      kodedokter: "DR001",
      jampraktek: "08:00-14:00",
      jeniskunjungan: 1,
      nomorreferensi: "REF-" + Math.floor(10000 + Math.random() * 90000)
    }, null, 2)
  },
  checkin: {
    method: 'POST',
    url: `${baseUrl}?action=checkin`,
    body: JSON.stringify({
      kodebooking: "<?= date('Ymd') ?>0001",
      waktu: Date.now()
    }, null, 2)
  },
  batalantrean: {
    method: 'POST',
    url: `${baseUrl}?action=batalantrean`,
    body: JSON.stringify({
      kodebooking: "<?= date('Ymd') ?>0001",
      keterangan: "Pasien berhalangan hadir"
    }, null, 2)
  },
  jadwaldokter: {
    method: 'GET',
    url: `${baseUrl}?action=jadwaldokter&kodepoli=U0001&tanggal=<?= date('Y-m-d') ?>`,
    body: ''
  },
  pasienbaru: {
    method: 'GET',
    url: `${baseUrl}?action=pasienbaru&nik=3201000000000001`,
    body: ''
  },
  custom: {
    method: 'GET',
    url: `${baseUrl}?action=statusantrean`,
    body: ''
  }
};

function loadTesterPreset(key) {
  switchAntreanTab('tester');
  document.getElementById('selectTesterPreset').value = key;
  const p = testerPresets[key] || testerPresets.custom;
  document.getElementById('testerMethod').value = p.method;
  document.getElementById('testerUrl').value = p.url;
  document.getElementById('testerBody').value = p.body;
  onMethodChange(p.method);
}

function onMethodChange(method) {
  const reqBodyTab = document.getElementById('tabReqBody');
  if (method === 'GET' || method === 'DELETE') {
    document.getElementById('testerBody').placeholder = '// Method ' + method + ' tidak memerlukan Request Body';
  } else {
    document.getElementById('testerBody').placeholder = '{\n  "key": "value"\n}';
  }
}

function switchTesterReqTab(tab) {
  const pBody = document.getElementById('paneReqBody');
  const pHead = document.getElementById('paneReqHeaders');
  const tBody = document.getElementById('tabReqBody');
  const tHead = document.getElementById('tabReqHeaders');

  if (tab === 'headers') {
    pBody.style.display = 'none';
    pHead.style.display = 'block';
    tBody.className = 'btn btn-sm btn-secondary tester-req-tab';
    tHead.className = 'btn btn-sm btn-primary tester-req-tab';
  } else {
    pBody.style.display = 'block';
    pHead.style.display = 'none';
    tHead.className = 'btn btn-sm btn-secondary tester-req-tab';
    tBody.className = 'btn btn-sm btn-primary tester-req-tab';
  }
}

function sendPostmanRequest() {
  const url    = document.getElementById('testerUrl').value.trim();
  const method = document.getElementById('testerMethod').value;
  const body   = document.getElementById('testerBody').value.trim();
  const head   = document.getElementById('testerHeaders').value.trim();

  const sendBtn = document.getElementById('btnSendTester');
  const sendText = document.getElementById('sendText');
  const sendIcon = document.getElementById('sendIcon');
  const respEl  = document.getElementById('testerResponseBody');

  sendBtn.disabled = true;
  sendText.innerText = 'Sending...';
  sendIcon.className = 'fas fa-spinner fa-spin';
  respEl.innerText = 'Mengirim request ke ' + url + '...';

  const fd = new FormData();
  fd.append('ajax', 'run_postman_test');
  fd.append('url', url);
  fd.append('method', method);
  fd.append('body', body);
  fd.append('headers', head);

  fetch('<?= BASE_URL ?>modules/antrean_bpjs/index.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    sendBtn.disabled = false;
    sendText.innerText = 'Send';
    sendIcon.className = 'fas fa-paper-plane';

    const statusBadge = document.getElementById('respStatusBadge');
    const timeBadge   = document.getElementById('respTimeBadge');
    const sizeBadge   = document.getElementById('respSizeBadge');

    const code = res.http_code || 200;
    statusBadge.className = 'badge badge-' + (code >= 200 && code < 300 ? 'success' : (code >= 400 ? 'danger' : 'warning'));
    statusBadge.innerText = 'Status: ' + code + ' ' + (code === 200 ? 'OK' : (code === 201 ? 'Created' : 'Response'));
    timeBadge.innerText   = 'Time: ' + (res.elapsed_ms || 0) + ' ms';
    sizeBadge.innerText   = 'Size: ' + (res.size_bytes || 0) + ' B';

    try {
      const jsonObj = JSON.parse(res.body);
      respEl.innerText = JSON.stringify(jsonObj, null, 2);
    } catch (e) {
      respEl.innerText = res.body || res.message || '(Empty response)';
    }
  })
  .catch(err => {
    sendBtn.disabled = false;
    sendText.innerText = 'Send';
    sendIcon.className = 'fas fa-paper-plane';
    respEl.innerText = 'Error: ' + err;
  });
}

function formatResponseJson() {
  const el = document.getElementById('testerResponseBody');
  try {
    const obj = JSON.parse(el.innerText);
    el.innerText = JSON.stringify(obj, null, 2);
    showToast('JSON berhasil diformat rapi', 'info');
  } catch (e) {
    showToast('Teks bukan format JSON valid', 'warning');
  }
}

function copyTesterResponse() {
  const text = document.getElementById('testerResponseBody').innerText;
  navigator.clipboard.writeText(text);
  showToast('Response berhasil disalin ke clipboard!', 'success');
}

// ─── Document Copy Helper ─────────────────────────────────────
function salinSemuaKatalog() {
  const text = `KATALOG WEBSERVICE ANTREAN ONLINE BPJS (MOBILE JKN)
Faskes: <?= INSTANSI_NAMA ?> (Kode PPK: <?= $bpjs_cfg['kode_ppk'] ?>)
Base URL: <?= BASE_URL ?>api/bpjs/antrean.php

1. Status Antrean:
   GET <?= BASE_URL ?>api/bpjs/antrean.php?action=statusantrean&kodepoli={kodepoli}&tanggal={yyyy-mm-dd}

2. Ambil Antrean (Mobile JKN):
   POST <?= BASE_URL ?>api/bpjs/antrean.php?action=ambilantrean

3. Batal Antrean:
   POST <?= BASE_URL ?>api/bpjs/antrean.php?action=batalantrean

4. Check-in Kehadiran (Task 1):
   POST <?= BASE_URL ?>api/bpjs/antrean.php?action=checkin

5. Jadwal Praktek Dokter:
   GET <?= BASE_URL ?>api/bpjs/antrean.php?action=jadwaldokter&kodepoli={kodepoli}&tanggal={yyyy-mm-dd}

6. Validasi Pasien Baru:
   GET <?= BASE_URL ?>api/bpjs/antrean.php?action=pasienbaru&nik={nik}`;

  navigator.clipboard.writeText(text);
  showToast('Seluruh dokumen katalog URL berhasil disalin!', 'success');
}

function kirimTask(kodebooking, taskid) {
  const fd = new FormData();
  fd.append('ajax', 'update_task');
  fd.append('kodebooking', kodebooking);
  fd.append('taskid', taskid);

  fetch('<?= BASE_URL ?>modules/antrean_bpjs/index.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    showToast(res.message || 'Task berhasil diupdate', 'success');
    setTimeout(() => location.reload(), 400);
  });
}

function batalkanAntrean(kodebooking) {
  if (!confirm('Yakin ingin membatalkan antrean ini?')) return;
  const fd = new FormData();
  fd.append('ajax', 'batal_booking');
  fd.append('kodebooking', kodebooking);

  fetch('<?= BASE_URL ?>modules/antrean_bpjs/index.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    showToast('Antrean berhasil dibatalkan', 'info');
    setTimeout(() => location.reload(), 400);
  });
}

function openModalSimulator() {
  document.getElementById('modalSimulator').style.display = 'flex';
}

function closeModalSimulator() {
  document.getElementById('modalSimulator').style.display = 'none';
}

let simTimer = null;
function onSimSearchPasien(q) {
  clearTimeout(simTimer);
  const resBox = document.getElementById('simPasienResults');
  if (!q || q.length < 1) {
    resBox.style.display = 'none';
    return;
  }
  simTimer = setTimeout(() => {
    fetch(`<?= BASE_URL ?>modules/pasien/ajax.php?action=cari&q=${encodeURIComponent(q)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.data && res.data.length > 0) {
        resBox.innerHTML = res.data.map(p => `
          <div style="padding:6px 10px;border-bottom:1px solid #f1f5f9;cursor:pointer;"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'"
               onclick='selectSimPasien(${JSON.stringify(p)})'>
            <b>${p.nm_pasien}</b> (RM: ${p.no_rkm_medis}) - ${p.no_peserta||'Non-BPJS'}
            ${p.alamat ? `<div style="font-size:11px;color:#64748b;margin-top:1px;"><i class="fas fa-map-marker-alt text-primary" style="font-size:10px;"></i> ${p.alamat}</div>` : ''}
          </div>
        `).join('');
        resBox.style.display = 'block';
      }
    });
  }, 250);
}

function selectSimPasien(p) {
  document.getElementById('simNoRm').value = p.no_rkm_medis;
  document.getElementById('simSearchPasien').style.display = 'none';
  document.getElementById('simPasienResults').style.display = 'none';
  const sel = document.getElementById('simPasienSelected');
  sel.innerHTML = `✓ Pasien: <b>${p.nm_pasien}</b> (RM: ${p.no_rkm_medis}) &bull; No Kartu: ${p.no_peserta||'-'}`;
  sel.style.display = 'block';
}

function submitSimulasiBooking(e) {
  e.preventDefault();
  const no_rm = document.getElementById('simNoRm').value;
  if (!no_rm) {
    alert('Pilih pasien terlebih dahulu');
    return;
  }

  const fd = new FormData();
  fd.append('ajax', 'simulasi_booking');
  fd.append('no_rkm_medis', no_rm);
  fd.append('kd_poli', document.getElementById('simKdPoli').value);
  fd.append('kd_dokter', document.getElementById('simKdDokter').value);
  fd.append('tgl_periksa', document.getElementById('simTglPeriksa').value);

  fetch('<?= BASE_URL ?>modules/antrean_bpjs/index.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      showToast('Booking Mobile JKN berhasil disimulasikan!', 'success');
      closeModalSimulator();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(res.message || 'Gagal simulasi booking');
    }
  });
}
</script>

<?php
function getTaskName(int $t): string {
    $names = [
        1 => 'Check-in Admisi',
        2 => 'Selesai Admisi',
        3 => 'Tunggu Poliklinik',
        4 => 'Mulai Periksa Dokter',
        5 => 'Selesai Periksa Dokter',
        6 => 'Mulai Racik Farmasi',
        7 => 'Selesai Penyerahan Obat'
    ];
    return $names[$t] ?? 'Task ' . $t;
}
include dirname(__DIR__, 2) . '/includes/footer.php';
?>
