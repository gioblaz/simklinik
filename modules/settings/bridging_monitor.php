<?php
/**
 * SIMKlinik — Monitoring Koneksi & Latensi PCare BPJS Realtime (Delightful Mood Dashboard)
 */

$page_title    = 'Monitoring Koneksi PCare BPJS';
$active_module = 'bridging_monitor';
$sub_setting   = 'bridging_monitor';

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ─── Page Header ──────────────────────────────────────── -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
  <div>
    <div style="display:flex;align-items:center;gap:10px;">
      <h1 class="page-title">Monitoring Koneksi &amp; Latensi PCare BPJS</h1>
      <span class="bm-live-badge"><i class="fas fa-circle"></i> TRUSTMARK v4.0</span>
    </div>
    <p class="page-subtitle">Pantau kesehatan koneksi, latensi, dan sinkronisasi data master Web Service PCare FKTP BPJS Kesehatan secara realtime</p>
  </div>
  <div class="page-actions" style="display:flex;align-items:center;gap:8px;">
    <a href="<?= BASE_URL ?>modules/pcare/index.php" class="btn btn-secondary">
      <i class="fas fa-hospital-user"></i> Menu PCare
    </a>
    <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="btn btn-outline">
      <i class="fas fa-sliders"></i> Pengaturan Kredensial
    </a>
    <button type="button" class="btn btn-primary" onclick="openBridgingMonitorModal()">
      <i class="fas fa-expand"></i> Buka Mode Overlay
    </button>
  </div>
</div>

<!-- ─── Bridging Dashboard Body (NOC View) ────────────────── -->
<div class="bm-modal-dialog" style="max-width:100%;width:100%;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.15);border:1px solid #334155;margin-bottom:30px;">

  <!-- Header -->
  <div class="bm-modal-header">
    <div class="bm-header-left">
      <div class="bm-radar-wrapper" style="background:linear-gradient(135deg, rgba(2, 132, 199, 0.25), rgba(30, 58, 138, 0.45));border-color:rgba(56, 189, 248, 0.4);color:#38bdf8;">
        <div class="bm-radar-ping" style="border-color:rgba(56, 189, 248, 0.7);"></div>
        <i class="fas fa-hospital-user bm-radar-icon"></i>
      </div>
      <div>
        <div style="font-size:16px;font-weight:700;color:#f8fafc;">PCare Live Latency &amp; Mood Health Operations</div>
        <div class="bm-modal-sub">Pemindaian otomatis setiap 15 detik dengan visualisasi ekspresi emosional &amp; gelombang osiloskop</div>
      </div>
    </div>

    <div class="bm-header-actions">
      <!-- Auto Refresh Controller -->
      <div class="bm-auto-refresh-box">
        <div class="bm-countdown-wrapper" title="Interval Auto Refresh">
          <svg class="bm-countdown-svg" width="34" height="34" viewBox="0 0 34 34">
            <circle class="bm-countdown-bg" cx="17" cy="17" r="14"></circle>
            <circle class="bm-countdown-circle" id="bridgingCountdownCircle" cx="17" cy="17" r="14"></circle>
          </svg>
          <span class="bm-countdown-text" id="bridgingCountdownSec">15s</span>
        </div>

        <label class="bm-toggle-switch" title="Aktif/Jeda Auto Scan">
          <input type="checkbox" id="toggleAutoBridging" checked>
          <span class="bm-toggle-slider"></span>
        </label>
      </div>

      <button type="button" class="bm-btn-ping-all" id="btnPingAllBridging">
        <i class="fas fa-bolt"></i> Ping Semua Endpoint PCare
      </button>
    </div>
  </div>

  <!-- Body -->
  <div class="bm-modal-body">

    <!-- 1. HERO MASCOT MOOD BANNER -->
    <div class="bm-hero-mood-card mood-good" id="bmHeroMoodCard">
      <div class="bm-hero-mood-left">
        <div class="bm-mascot-avatar anim-bounce" id="bmMascotAvatar">😄</div>
        <div class="bm-hero-mood-texts">
          <span class="bm-mood-badge mood-good" id="bmHeroMoodBadge">😄 KONEKSI BAGUS</span>
          <div class="bm-hero-mood-title" id="bmHeroMoodTitle">Server PCare BPJS Berjalan Sangat Lancar!</div>
          <div class="bm-hero-mood-desc" id="bmHeroMoodDesc">Semua endpoint master data merespons secepat kilat (Rata-rata <strong>-- ms</strong>).</div>
        </div>
      </div>

      <div class="bm-hero-mood-stats">
        <div class="bm-hero-stat-pill">
          <span class="num" id="bmHeroAvgLat" style="color:#38bdf8;">-- ms</span>
          <span class="lbl">Rata-rata Latensi</span>
        </div>
        <div class="bm-hero-stat-pill">
          <span class="num" id="bmHeroUptime" style="color:#34d399;">--%</span>
          <span class="lbl">Kesehatan Jaringan</span>
        </div>
      </div>
    </div>

    <!-- 2. Oscilloscope Visualizer -->
    <div class="bm-waveform-card">
      <div class="bm-waveform-header">
        <div class="bm-wave-title">
          <i class="fas fa-heart-pulse" style="color:#10b981;"></i>
          <span>PCare Gateway Oscilloscope & Latency Waveform</span>
        </div>
        <div class="bm-status-chips">
          <span class="bm-badge bm-badge-success" id="bmHeaderStatusBadge">
            <i class="fas fa-spinner fa-spin"></i> Menghubungkan ke PCare...
          </span>
        </div>
      </div>

      <div class="bm-canvas-container">
        <canvas id="bridgingWaveCanvas" height="100"></canvas>
        <div class="bm-canvas-overlay-stats">
          <div class="bm-mini-stat">
            <span class="lbl">Koneksi PCare</span>
            <span class="val" id="bmSummaryHealth" style="color:#10b981;">--%</span>
          </div>
          <div class="bm-mini-stat">
            <span class="lbl">Rata-rata Latensi</span>
            <span class="val" id="bmSummaryLatency" style="color:#38bdf8;">-- ms</span>
          </div>
          <div class="bm-mini-stat">
            <span class="lbl">Endpoint Aktif</span>
            <span class="val" id="bmSummaryTotal" style="color:#facc15;">--/6</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 3. 6 TrustMark Endpoint Service Grid Cards -->
    <div class="bm-channels-grid">

      <!-- 1. Kamus ICD-10 (/diagnosa) -->
      <div class="bm-channel-card card-mood-good" id="bmCard_diagnosa">
        <div class="bm-card-head">
          <div class="bm-card-brand">
            <div class="bm-channel-icon" style="background:rgba(2, 132, 199, 0.18);color:#38bdf8;border:1px solid rgba(2, 132, 199, 0.35);">
              <i class="fas fa-book-medical"></i>
            </div>
            <div>
              <div class="bm-card-name">Kamus Diagnosa ICD-10</div>
              <div class="bm-card-sub">GET /diagnosa/{keyword}/{start}/{limit}</div>
            </div>
          </div>
          <div class="bm-card-mood-tag mood-good">
            <span class="bm-emoji">😊</span>
            <span class="bm-mood-text">Koneksi Bagus</span>
          </div>
        </div>

        <div class="bm-card-metric">
          <div>
            <div class="bm-metric-lbl">Response Time</div>
            <div class="bm-lat-num">-- ms <span class="bm-speed-tag speed-fast">⚡ Cepat</span></div>
          </div>
          <span class="bm-http-code code-200">HTTP --</span>
        </div>

        <div class="bm-lat-bar-track">
          <div class="bm-lat-bar-fill" style="width:0%;"></div>
        </div>

        <div class="bm-card-details">
          <div class="bm-msg-text">Menghubungkan...</div>
          <div class="bm-time-text"></div>
        </div>

        <div class="bm-card-foot">
          <span class="bm-status-pill pill-neutral"><i class="fas fa-circle-notch fa-spin"></i> Checking</span>
          <div style="display:flex;gap:6px;">
            <button type="button" class="bm-btn-action" data-inspect-channel="diagnosa">
              <i class="fas fa-code"></i> Data
            </button>
            <button type="button" class="bm-btn-action" data-ping-channel="diagnosa">
              <i class="fas fa-rotate"></i> Ping
            </button>
          </div>
        </div>
      </div>

      <!-- 2. Dokter Faskes (/dokter) -->
      <div class="bm-channel-card card-mood-good" id="bmCard_dokter">
        <div class="bm-card-head">
          <div class="bm-card-brand">
            <div class="bm-channel-icon" style="background:rgba(124, 58, 237, 0.18);color:#c084fc;border:1px solid rgba(124, 58, 237, 0.35);">
              <i class="fas fa-user-doctor"></i>
            </div>
            <div>
              <div class="bm-card-name">Referensi Dokter Faskes</div>
              <div class="bm-card-sub">GET /dokter/{start}/{limit}</div>
            </div>
          </div>
          <div class="bm-card-mood-tag mood-good">
            <span class="bm-emoji">😊</span>
            <span class="bm-mood-text">Koneksi Bagus</span>
          </div>
        </div>

        <div class="bm-card-metric">
          <div>
            <div class="bm-metric-lbl">Response Time</div>
            <div class="bm-lat-num">-- ms <span class="bm-speed-tag speed-fast">⚡ Cepat</span></div>
          </div>
          <span class="bm-http-code code-200">HTTP --</span>
        </div>

        <div class="bm-lat-bar-track">
          <div class="bm-lat-bar-fill" style="width:0%;"></div>
        </div>

        <div class="bm-card-details">
          <div class="bm-msg-text">Menghubungkan...</div>
          <div class="bm-time-text"></div>
        </div>

        <div class="bm-card-foot">
          <span class="bm-status-pill pill-neutral"><i class="fas fa-circle-notch fa-spin"></i> Checking</span>
          <div style="display:flex;gap:6px;">
            <button type="button" class="bm-btn-action" data-inspect-channel="dokter">
              <i class="fas fa-code"></i> Data
            </button>
            <button type="button" class="bm-btn-action" data-ping-channel="dokter">
              <i class="fas fa-rotate"></i> Ping
            </button>
          </div>
        </div>
      </div>

      <!-- 3. Poli FKTP (/poli/fktp) -->
      <div class="bm-channel-card card-mood-good" id="bmCard_poli">
        <div class="bm-card-head">
          <div class="bm-card-brand">
            <div class="bm-channel-icon" style="background:rgba(5, 150, 105, 0.18);color:#34d399;border:1px solid rgba(5, 150, 105, 0.35);">
              <i class="fas fa-hospital"></i>
            </div>
            <div>
              <div class="bm-card-name">Referensi Poli FKTP</div>
              <div class="bm-card-sub">GET /poli/fktp/{start}/{limit}</div>
            </div>
          </div>
          <div class="bm-card-mood-tag mood-good">
            <span class="bm-emoji">😊</span>
            <span class="bm-mood-text">Koneksi Bagus</span>
          </div>
        </div>

        <div class="bm-card-metric">
          <div>
            <div class="bm-metric-lbl">Response Time</div>
            <div class="bm-lat-num">-- ms <span class="bm-speed-tag speed-fast">⚡ Cepat</span></div>
          </div>
          <span class="bm-http-code code-200">HTTP --</span>
        </div>

        <div class="bm-lat-bar-track">
          <div class="bm-lat-bar-fill" style="width:0%;"></div>
        </div>

        <div class="bm-card-details">
          <div class="bm-msg-text">Menghubungkan...</div>
          <div class="bm-time-text"></div>
        </div>

        <div class="bm-card-foot">
          <span class="bm-status-pill pill-neutral"><i class="fas fa-circle-notch fa-spin"></i> Checking</span>
          <div style="display:flex;gap:6px;">
            <button type="button" class="bm-btn-action" data-inspect-channel="poli">
              <i class="fas fa-code"></i> Data
            </button>
            <button type="button" class="bm-btn-action" data-ping-channel="poli">
              <i class="fas fa-rotate"></i> Ping
            </button>
          </div>
        </div>
      </div>

      <!-- 4. Status Kesadaran (/kesadaran) -->
      <div class="bm-channel-card card-mood-good" id="bmCard_kesadaran">
        <div class="bm-card-head">
          <div class="bm-card-brand">
            <div class="bm-channel-icon" style="background:rgba(234, 88, 12, 0.18);color:#fb923c;border:1px solid rgba(234, 88, 12, 0.35);">
              <i class="fas fa-brain"></i>
            </div>
            <div>
              <div class="bm-card-name">Status Kesadaran Pasien</div>
              <div class="bm-card-sub">GET /kesadaran</div>
            </div>
          </div>
          <div class="bm-card-mood-tag mood-good">
            <span class="bm-emoji">😊</span>
            <span class="bm-mood-text">Koneksi Bagus</span>
          </div>
        </div>

        <div class="bm-card-metric">
          <div>
            <div class="bm-metric-lbl">Response Time</div>
            <div class="bm-lat-num">-- ms <span class="bm-speed-tag speed-fast">⚡ Cepat</span></div>
          </div>
          <span class="bm-http-code code-200">HTTP --</span>
        </div>

        <div class="bm-lat-bar-track">
          <div class="bm-lat-bar-fill" style="width:0%;"></div>
        </div>

        <div class="bm-card-details">
          <div class="bm-msg-text">Menghubungkan...</div>
          <div class="bm-time-text"></div>
        </div>

        <div class="bm-card-foot">
          <span class="bm-status-pill pill-neutral"><i class="fas fa-circle-notch fa-spin"></i> Checking</span>
          <div style="display:flex;gap:6px;">
            <button type="button" class="bm-btn-action" data-inspect-channel="kesadaran">
              <i class="fas fa-code"></i> Data
            </button>
            <button type="button" class="bm-btn-action" data-ping-channel="kesadaran">
              <i class="fas fa-rotate"></i> Ping
            </button>
          </div>
        </div>
      </div>

      <!-- 5. Status Pulang Pasien (/statuspulang) -->
      <div class="bm-channel-card card-mood-good" id="bmCard_statuspulang">
        <div class="bm-card-head">
          <div class="bm-card-brand">
            <div class="bm-channel-icon" style="background:rgba(13, 148, 136, 0.18);color:#2dd4bf;border:1px solid rgba(13, 148, 136, 0.35);">
              <i class="fas fa-person-walking-arrow-right"></i>
            </div>
            <div>
              <div class="bm-card-name">Status Pulang Pasien</div>
              <div class="bm-card-sub">GET /statuspulang/rawatInap/{0|1}</div>
            </div>
          </div>
          <div class="bm-card-mood-tag mood-good">
            <span class="bm-emoji">😊</span>
            <span class="bm-mood-text">Koneksi Bagus</span>
          </div>
        </div>

        <div class="bm-card-metric">
          <div>
            <div class="bm-metric-lbl">Response Time</div>
            <div class="bm-lat-num">-- ms <span class="bm-speed-tag speed-fast">⚡ Cepat</span></div>
          </div>
          <span class="bm-http-code code-200">HTTP --</span>
        </div>

        <div class="bm-lat-bar-track">
          <div class="bm-lat-bar-fill" style="width:0%;"></div>
        </div>

        <div class="bm-card-details">
          <div class="bm-msg-text">Menghubungkan...</div>
          <div class="bm-time-text"></div>
        </div>

        <div class="bm-card-foot">
          <span class="bm-status-pill pill-neutral"><i class="fas fa-circle-notch fa-spin"></i> Checking</span>
          <div style="display:flex;gap:6px;">
            <button type="button" class="bm-btn-action" data-inspect-channel="statuspulang">
              <i class="fas fa-code"></i> Data
            </button>
            <button type="button" class="bm-btn-action" data-ping-channel="statuspulang">
              <i class="fas fa-rotate"></i> Ping
            </button>
          </div>
        </div>
      </div>

      <!-- 6. Spesialis Rujukan (/spesialis) -->
      <div class="bm-channel-card card-mood-good" id="bmCard_spesialis">
        <div class="bm-card-head">
          <div class="bm-card-brand">
            <div class="bm-channel-icon" style="background:rgba(225, 29, 72, 0.18);color:#fb7185;border:1px solid rgba(225, 29, 72, 0.35);">
              <i class="fas fa-stethoscope"></i>
            </div>
            <div>
              <div class="bm-card-name">Spesialis & Sarana Rujukan</div>
              <div class="bm-card-sub">GET /spesialis &amp; /spesialis/sarana</div>
            </div>
          </div>
          <div class="bm-card-mood-tag mood-good">
            <span class="bm-emoji">😊</span>
            <span class="bm-mood-text">Koneksi Bagus</span>
          </div>
        </div>

        <div class="bm-card-metric">
          <div>
            <div class="bm-metric-lbl">Response Time</div>
            <div class="bm-lat-num">-- ms <span class="bm-speed-tag speed-fast">⚡ Cepat</span></div>
          </div>
          <span class="bm-http-code code-200">HTTP --</span>
        </div>

        <div class="bm-lat-bar-track">
          <div class="bm-lat-bar-fill" style="width:0%;"></div>
        </div>

        <div class="bm-card-details">
          <div class="bm-msg-text">Menghubungkan...</div>
          <div class="bm-time-text"></div>
        </div>

        <div class="bm-card-foot">
          <span class="bm-status-pill pill-neutral"><i class="fas fa-circle-notch fa-spin"></i> Checking</span>
          <div style="display:flex;gap:6px;">
            <button type="button" class="bm-btn-action" data-inspect-channel="spesialis">
              <i class="fas fa-code"></i> Data
            </button>
            <button type="button" class="bm-btn-action" data-ping-channel="spesialis">
              <i class="fas fa-rotate"></i> Ping
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Terminal -->
    <div class="bm-terminal-card">
      <div class="bm-terminal-header">
        <div class="bm-term-dots">
          <span class="dot-red"></span>
          <span class="dot-yellow"></span>
          <span class="dot-green"></span>
          <span class="term-title"><i class="fas fa-terminal" style="margin-right:6px;"></i> Live PCare BPJS Diagnostic Stream &amp; Decrypted Events</span>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <button type="button" class="bm-btn-term" id="btnClearBridgingLogs">
            <i class="fas fa-trash-can"></i> Bersihkan Log
          </button>
          <a href="<?= BASE_URL ?>modules/settings/bridging.php" class="bm-btn-term" style="text-decoration:none;color:#94a3b8;">
            <i class="fas fa-sliders"></i> Kredensial PCare
          </a>
        </div>
      </div>

      <div class="bm-terminal-body" id="bmLogStreamContainer">
        <div class="bm-log-empty"><i class="fas fa-spinner fa-spin"></i> Memulai sistem diagnostik PCare...</div>
      </div>
    </div>

  </div>

</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
