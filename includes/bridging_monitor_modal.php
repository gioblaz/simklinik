<?php
/**
 * SIMKlinik — Realtime PCare BPJS Latency & Connection Monitor Modal (Delightful Mood UI)
 * Spesifikasi TrustMark BPJS Kesehatan (Master Data & Services)
 */
?>
<!-- ─── Realtime PCare BPJS Monitor Modal ───────────────────── -->
<div class="modal-overlay bm-modal-overlay" id="modalBridgingMonitor" style="z-index:9999;">
  <div class="modal-dialog bm-modal-dialog" style="max-width:1120px;width:95%;border-radius:18px;overflow:hidden;box-shadow:0 25px 60px -15px rgba(0,0,0,0.45);border:1px solid #334155;">

    <!-- Modal Header -->
    <div class="bm-modal-header">
      <div class="bm-header-left">
        <div class="bm-radar-wrapper" style="background:linear-gradient(135deg, rgba(2, 132, 199, 0.25), rgba(30, 58, 138, 0.45));border-color:rgba(56, 189, 248, 0.4);color:#38bdf8;">
          <div class="bm-radar-ping" style="border-color:rgba(56, 189, 248, 0.7);"></div>
          <i class="fas fa-hospital-user bm-radar-icon"></i>
        </div>
        <div>
          <div style="display:flex;align-items:center;gap:10px;">
            <h3 class="bm-modal-title">Live PCare BPJS Connection & Latency Monitor</h3>
            <span class="bm-live-badge"><i class="fas fa-circle"></i> TRUSTMARK v4.0</span>
          </div>
          <div class="bm-modal-sub">Pemantauan latensi interaktif dengan indikator ekspresi &amp; osiloskop realtime</div>
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

        <!-- Ping All Action -->
        <button type="button" class="bm-btn-ping-all" id="btnPingAllBridging" title="Kirim paket ping ke seluruh endpoint TrustMark PCare">
          <i class="fas fa-bolt"></i> Ping Semua Endpoint PCare
        </button>

        <!-- Close Modal -->
        <button type="button" class="bm-btn-close" onclick="closeBridgingMonitorModal()" title="Tutup">
          <i class="fas fa-xmark"></i>
        </button>
      </div>
    </div>

    <!-- Modal Body -->
    <div class="bm-modal-body">

      <!-- ─── 1. DELIGHTFUL HERO MASCOT MOOD BANNER ─── -->
      <div class="bm-hero-mood-card mood-good" id="bmHeroMoodCard">
        <div class="bm-hero-mood-left">
          <!-- Animated 3D Emoji Avatar -->
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

      <!-- ─── 2. Top Stats & Oscilloscope Waveform Visualizer ─── -->
      <div class="bm-waveform-card">
        <div class="bm-waveform-header">
          <div class="bm-wave-title">
            <i class="fas fa-heart-pulse" style="color:#10b981;"></i>
            <span>PCare Gateway Oscilloscope & Latency Waveform</span>
          </div>
          <div class="bm-status-chips">
            <span class="bm-badge bm-badge-success" id="bmHeaderStatusBadge">
              <i class="fas fa-spinner fa-spin"></i> Menguji koneksi PCare...
            </span>
          </div>
        </div>

        <!-- Canvas ECG Oscilloscope -->
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

      <!-- ─── 3. 6 TrustMark Endpoint Service Grid Cards with Emotional Tags ─── -->
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
            <!-- Dynamic Mood Tag -->
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
              <button type="button" class="bm-btn-action" data-inspect-channel="diagnosa" title="Lihat Payload Data">
                <i class="fas fa-code"></i> Data
              </button>
              <button type="button" class="bm-btn-action" data-ping-channel="diagnosa" title="Ping Endpoint">
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
              <button type="button" class="bm-btn-action" data-inspect-channel="dokter" title="Lihat Payload Data">
                <i class="fas fa-code"></i> Data
              </button>
              <button type="button" class="bm-btn-action" data-ping-channel="dokter" title="Ping Endpoint">
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
              <button type="button" class="bm-btn-action" data-inspect-channel="poli" title="Lihat Payload Data">
                <i class="fas fa-code"></i> Data
              </button>
              <button type="button" class="bm-btn-action" data-ping-channel="poli" title="Ping Endpoint">
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
              <button type="button" class="bm-btn-action" data-inspect-channel="kesadaran" title="Lihat Payload Data">
                <i class="fas fa-code"></i> Data
              </button>
              <button type="button" class="bm-btn-action" data-ping-channel="kesadaran" title="Ping Endpoint">
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
              <button type="button" class="bm-btn-action" data-inspect-channel="statuspulang" title="Lihat Payload Data">
                <i class="fas fa-code"></i> Data
              </button>
              <button type="button" class="bm-btn-action" data-ping-channel="statuspulang" title="Ping Endpoint">
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
              <button type="button" class="bm-btn-action" data-inspect-channel="spesialis" title="Lihat Payload Data">
                <i class="fas fa-code"></i> Data
              </button>
              <button type="button" class="bm-btn-action" data-ping-channel="spesialis" title="Ping Endpoint">
                <i class="fas fa-rotate"></i> Ping
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- ─── 4. Activity Diagnostic Console Terminal ─── -->
      <div class="bm-terminal-card">
        <div class="bm-terminal-header">
          <div class="bm-term-dots">
            <span class="dot-red"></span>
            <span class="dot-yellow"></span>
            <span class="dot-green"></span>
            <span class="term-title"><i class="fas fa-terminal" style="margin-right:6px;"></i> Live PCare BPJS Diagnostic Stream &amp; Decrypted Events</span>
          </div>
          <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" class="bm-btn-term" id="btnClearBridgingLogs" title="Bersihkan log aktivitas">
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

    </div><!-- /.modal-body -->

  </div><!-- /.modal-dialog -->
</div><!-- /#modalBridgingMonitor -->

<!-- ─── Sub Modal: Payload Inspector ────────────────────────── -->
<div class="modal-overlay" id="modalInspectPayload" style="z-index:10000;">
  <div class="modal-dialog" style="max-width:700px;width:90%;border-radius:14px;background:#0f172a;color:#f8fafc;border:1px solid #334155;">
    <div class="modal-header" style="background:#1e293b;border-bottom:1px solid #334155;padding:14px 20px;">
      <h3 class="modal-title" id="inspectPayloadTitle" style="color:#f8fafc;font-size:15px;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-code" style="color:#38bdf8;"></i> Detail Respon Terdekripsi PCare BPJS
      </h3>
      <button type="button" class="modal-close" onclick="closeModal('modalInspectPayload')" style="color:#94a3b8;"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="padding:16px 20px;">
      <pre id="inspectPayloadContent" style="background:#090d16;padding:16px;border-radius:8px;font-family:monospace;font-size:12px;color:#38bdf8;max-height:380px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;border:1px solid #1e293b;"></pre>
    </div>
    <div class="modal-footer" style="background:#1e293b;border-top:1px solid #334155;padding:10px 20px;display:flex;justify-content:flex-end;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modalInspectPayload')" style="background:#334155;color:#f8fafc;border:none;">
        Tutup
      </button>
    </div>
  </div>
</div>
