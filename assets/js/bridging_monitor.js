/**
 * SIMKlinik — Realtime Bridging Network Monitor & ECG Animation Engine
 * v2.0 - Multi-Channel Live Polling, Oscilloscope Canvas & Diagnostics
 */

(function() {
  'use strict';

  // State Management
  const state = {
    isPolling: true,
    pollIntervalSec: 15,
    countdownSec: 15,
    timerId: null,
    countdownId: null,
    channels: {},
    summary: {},
    logs: [],
    maxLogs: 50,
    isPinging: false,
    waveAnimationId: null,
    canvasCtx: null,
    canvasEl: null,
    wavePhase: 0,
    waveSpikes: []
  };

  // Helper Elements
  function $(id) { return document.getElementById(id); }

  // ─── Initialize Engine ─────────────────────────────────────────
  function init() {
    setupEventListeners();
    setupCanvas();
    startCountdown();
    // Initial fetch
    pingAllChannels(true);
  }

  // ─── Setup Event Listeners ─────────────────────────────────────
  function setupEventListeners() {
    // Open Modal Triggers
    document.querySelectorAll('[data-open-bridging-monitor]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        openBridgingMonitorModal();
      });
    });

    // Ping All Button
    const btnPingAll = $('btnPingAllBridging');
    if (btnPingAll) {
      btnPingAll.addEventListener('click', () => pingAllChannels(false));
    }

    // Toggle Auto Refresh
    const toggleAuto = $('toggleAutoBridging');
    if (toggleAuto) {
      toggleAuto.addEventListener('change', (e) => {
        state.isPolling = e.target.checked;
        if (state.isPolling) {
          state.countdownSec = state.pollIntervalSec;
          startCountdown();
          showToast('Auto-refresh monitor diaktifkan (15 detik)', 'info');
        } else {
          stopCountdown();
          showToast('Auto-refresh monitor dijeda', 'warning');
        }
      });
    }

    // Clear Logs Button
    const btnClearLogs = $('btnClearBridgingLogs');
    if (btnClearLogs) {
      btnClearLogs.addEventListener('click', () => {
        state.logs = [];
        renderLogs();
      });
    }

    // Individual Ping Buttons (Delegation)
    document.addEventListener('click', (e) => {
      const pingBtn = e.target.closest('[data-ping-channel]');
      if (pingBtn) {
        const chKey = pingBtn.dataset.pingChannel;
        pingSingleChannel(chKey, pingBtn);
      }

      const inspectBtn = e.target.closest('[data-inspect-channel]');
      if (inspectBtn) {
        const chKey = inspectBtn.dataset.inspectChannel;
        inspectChannelPayload(chKey);
      }
    });
  }

  // ─── Modal Open / Close ────────────────────────────────────────
  window.openBridgingMonitorModal = function() {
    const modal = $('modalBridgingMonitor');
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
      // Restart canvas if needed
      setTimeout(setupCanvas, 150);
    }
  };

  window.closeBridgingMonitorModal = function() {
    const modal = $('modalBridgingMonitor');
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }
  };

  // ─── Countdown & Auto Refresh ──────────────────────────────────
  function startCountdown() {
    stopCountdown();
    state.countdownSec = state.pollIntervalSec;
    updateCountdownUI();

    state.countdownId = setInterval(() => {
      if (!state.isPolling) return;
      state.countdownSec--;
      updateCountdownUI();

      if (state.countdownSec <= 0) {
        state.countdownSec = state.pollIntervalSec;
        pingAllChannels(true);
      }
    }, 1000);
  }

  function stopCountdown() {
    if (state.countdownId) {
      clearInterval(state.countdownId);
      state.countdownId = null;
    }
  }

  function updateCountdownUI() {
    const el = $('bridgingCountdownSec');
    if (el) el.textContent = `${state.countdownSec}s`;

    const circle = $('bridgingCountdownCircle');
    if (circle) {
      const totalDash = 2 * Math.PI * 14; // r=14 -> ~88
      const offset = totalDash - (state.countdownSec / state.pollIntervalSec) * totalDash;
      circle.style.strokeDasharray = `${totalDash}`;
      circle.style.strokeDashoffset = `${offset}`;
    }
  }

  // ─── Ping All Channels ─────────────────────────────────────────
  async function pingAllChannels(isBackground = false) {
    if (state.isPinging) return;
    state.isPinging = true;

    // Trigger visual pulse
    triggerSpikePulse();

    const btnPing = $('btnPingAllBridging');
    if (btnPing && !isBackground) {
      btnPing.classList.add('is-loading');
      const icon = btnPing.querySelector('i');
      if (icon) icon.className = 'fas fa-spinner fa-spin';
    }

    try {
      const url = (window.SIMKLINIK_BASE_URL || '/') + 'api/monitor_bridging.php?channel=all&t=' + Date.now();
      const res = await fetch(url, { cache: 'no-store' });
      const data = await res.json();

      if (data && data.success) {
        state.summary = data.summary || {};
        state.channels = data.channels || {};
        renderDashboard(data);
        addLogEntry('SYSTEM', 'Multi-channel health scan selesai: ' + (data.summary.status_text || 'OK'), 'info');
      } else {
        throw new Error('Respon API tidak valid');
      }
    } catch (err) {
      console.error('Bridging Monitor Error:', err);
      addLogEntry('ERROR', 'Gagal memanggil API monitor: ' + err.message, 'danger');
    } finally {
      state.isPinging = false;
      if (btnPing && !isBackground) {
        btnPing.classList.remove('is-loading');
        const icon = btnPing.querySelector('i');
        if (icon) icon.className = 'fas fa-bolt';
      }
    }
  }

  // ─── Ping Single Channel ───────────────────────────────────────
  async function pingSingleChannel(chKey, btnEl) {
    if (btnEl) {
      btnEl.disabled = true;
      btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ping...';
    }

    triggerSpikePulse();

    try {
      const url = (window.SIMKLINIK_BASE_URL || '/') + `api/monitor_bridging.php?channel=${chKey}&t=` + Date.now();
      const res = await fetch(url, { cache: 'no-store' });
      const data = await res.json();

      if (data && data.success && data.channels && data.channels[chKey]) {
        const chData = data.channels[chKey];
        state.channels[chKey] = chData;
        renderChannelCard(chKey, chData);
        updateTopbarWidget();

        const statusLabel = chData.online ? `ONLINE (${chData.duration_ms}ms)` : `OFFLINE (HTTP ${chData.http_code})`;
        addLogEntry(chKey.toUpperCase(), `Manual Ping ${chData.name} -> ${statusLabel}`, chData.online ? 'success' : 'danger');
      }
    } catch (err) {
      addLogEntry(chKey.toUpperCase(), `Gagal ping channel: ` + err.message, 'danger');
    } finally {
      if (btnEl) {
        btnEl.disabled = false;
        btnEl.innerHTML = '<i class="fas fa-rotate"></i> Ping';
      }
    }
  }

  // ─── Render Dashboard UI ───────────────────────────────────────
  function renderDashboard(data) {
    // 1. Update Topbar Widget
    updateTopbarWidget();

    // 2. Update Modal Summary Badges
    const sum = data.summary || {};
    const totalEl = $('bmSummaryTotal');
    const onlineEl = $('bmSummaryOnline');
    const latencyEl = $('bmSummaryLatency');
    const healthEl = $('bmSummaryHealth');

    if (totalEl) totalEl.textContent = `${sum.online || 0}/${sum.total || 6}`;
    if (onlineEl) onlineEl.textContent = sum.status_text || 'PCare Responsif';
    if (latencyEl) latencyEl.textContent = `${sum.avg_latency_ms || 0} ms`;
    if (healthEl) healthEl.textContent = `${sum.health_score || 0}%`;

    // 3. Update Hero Mascot Mood Banner
    updateHeroMood(sum);

    // 4. Render Channel Cards
    if (data.channels) {
      Object.keys(data.channels).forEach(key => {
        renderChannelCard(key, data.channels[key]);
      });
    }

    // 5. Update Header Badge
    const headerBadge = $('bmHeaderStatusBadge');
    if (headerBadge) {
      if (sum.online === sum.total && sum.total > 0) {
        headerBadge.className = 'bm-badge bm-badge-success';
        headerBadge.innerHTML = `<i class="fas fa-check-circle"></i> Semua Endpoint PCare Online (${sum.avg_latency_ms} ms)`;
      } else if (sum.online > 0) {
        headerBadge.className = 'bm-badge bm-badge-warning';
        headerBadge.innerHTML = `<i class="fas fa-triangle-exclamation"></i> ${sum.online}/${sum.total} Endpoint Aktif (${sum.avg_latency_ms} ms)`;
      } else {
        headerBadge.className = 'bm-badge bm-badge-danger';
        headerBadge.innerHTML = '<i class="fas fa-circle-xmark"></i> Server PCare Tidak Merespon';
      }
    }
  }

  // ─── Update Hero Mascot Mood Banner ────────────────────────────
  function updateHeroMood(sum) {
    const heroCard = $('bmHeroMoodCard');
    const avatar = $('bmMascotAvatar');
    const badge = $('bmHeroMoodBadge');
    const title = $('bmHeroMoodTitle');
    const desc = $('bmHeroMoodDesc');
    const avgLatEl = $('bmHeroAvgLat');
    const uptimeEl = $('bmHeroUptime');

    if (!heroCard) return;

    const isOnline = sum.online > 0;
    const avgLat = sum.avg_latency_ms || 0;
    const isAllOnline = sum.online === sum.total && sum.total > 0;

    if (avgLatEl) avgLatEl.textContent = `${avgLat} ms`;
    if (uptimeEl) uptimeEl.textContent = `${sum.health_score || 0}%`;

    if (isAllOnline && avgLat < 500) {
      // 🟢 Bagus -> Emot Tersenyum / Bahagia
      heroCard.className = 'bm-hero-mood-card mood-good';
      if (avatar) {
        avatar.textContent = '😄';
        avatar.className = 'bm-mascot-avatar anim-bounce';
      }
      if (badge) {
        badge.className = 'bm-mood-badge mood-good';
        badge.innerHTML = '😄 KONEKSI BAGUS';
      }
      if (title) title.textContent = 'Server PCare BPJS Berjalan Sangat Lancar!';
      if (desc) desc.innerHTML = `Semua endpoint master data merespons secepat kilat (Rata-rata <strong>${avgLat} ms</strong>).`;
    } else if (isOnline && (avgLat >= 500 || sum.online < sum.total)) {
      // 🟡 Lambat -> Emot Sedikit Cemberut
      heroCard.className = 'bm-hero-mood-card mood-warn';
      if (avatar) {
        avatar.textContent = '🫤';
        avatar.className = 'bm-mascot-avatar anim-wobble';
      }
      if (badge) {
        badge.className = 'bm-mood-badge mood-warn';
        badge.innerHTML = '🫤 SEDIKIT CEMBERUT';
      }
      if (title) title.textContent = 'Server PCare Sedikit Lambat Merespon';
      if (desc) desc.innerHTML = `Waktu respon server agak lambat (Rata-rata <strong>${avgLat} ms</strong>). Transaksi tetap dapat diproses.`;
    } else {
      // 🔴 Putus / Error -> Emot Marah
      heroCard.className = 'bm-hero-mood-card mood-danger';
      if (avatar) {
        avatar.textContent = '😡';
        avatar.className = 'bm-mascot-avatar anim-shake';
      }
      if (badge) {
        badge.className = 'bm-mood-badge mood-danger';
        badge.innerHTML = '😡 KONEKSI TERPUTUS!';
      }
      if (title) title.textContent = 'Server PCare BPJS Marah & Terputus!';
      if (desc) desc.innerHTML = 'Koneksi ke endpoint BPJS gagal atau timeout. Periksa internet atau kredensial.';
    }
  }

  // ─── Render Individual Channel Card ────────────────────────────
  function renderChannelCard(key, ch) {
    const card = $(`bmCard_${key}`);
    if (!card) return;

    const isOnline = !!ch.online;
    const lat = ch.duration_ms || 0;

    // Mood Classification
    let moodClass = 'mood-good';
    let moodEmoji = '😊';
    let moodText = 'Koneksi Bagus';
    let speedTagHtml = '<span class="bm-speed-tag speed-fast">⚡ Cepat</span>';

    if (!isOnline) {
      moodClass = 'mood-danger';
      moodEmoji = '😡';
      moodText = 'Koneksi Putus';
      speedTagHtml = '<span class="bm-speed-tag speed-slow">❌ Putus</span>';
      card.className = 'bm-channel-card card-mood-danger';
    } else if (lat >= 500) {
      moodClass = 'mood-warn';
      moodEmoji = '🫤';
      moodText = 'Sedikit Lambat';
      speedTagHtml = '<span class="bm-speed-tag speed-medium">🐢 Lambat</span>';
      card.className = 'bm-channel-card card-mood-warn';
    } else {
      card.className = 'bm-channel-card card-mood-good';
    }

    // Dynamic Mood Tag
    const moodTag = card.querySelector('.bm-card-mood-tag');
    if (moodTag) {
      moodTag.className = `bm-card-mood-tag ${moodClass}`;
      moodTag.innerHTML = `<span class="bm-emoji">${moodEmoji}</span> <span class="bm-mood-text">${moodText}</span>`;
    }

    // Status Dot
    const dot = card.querySelector('.bm-radar-dot');
    if (dot) {
      dot.className = `bm-radar-dot ${isOnline ? 'is-online' : 'is-offline'}`;
    }

    // Status Badge
    const badge = card.querySelector('.bm-status-pill');
    if (badge) {
      badge.className = `bm-status-pill ${isOnline ? 'pill-success' : 'pill-danger'}`;
      badge.innerHTML = isOnline 
        ? `<i class="fas fa-circle-check"></i> Terkoneksi` 
        : `<i class="fas fa-circle-xmark"></i> Gagal`;
    }

    // Latency Gauge & Bar
    const latNum = card.querySelector('.bm-lat-num');
    if (latNum) {
      latNum.innerHTML = isOnline ? `${lat} ms ${speedTagHtml}` : `— ${speedTagHtml}`;
      latNum.style.color = isOnline ? (lat < 500 ? '#34d399' : '#fbbf24') : '#f87171';
    }

    const latBar = card.querySelector('.bm-lat-bar-fill');
    if (latBar) {
      const pct = Math.min(100, Math.max(12, Math.round((lat / 1500) * 100)));
      latBar.style.width = isOnline ? `${pct}%` : '0%';
      latBar.style.backgroundColor = isOnline ? (lat < 500 ? '#10b981' : '#f59e0b') : '#ef4444';
    }

    // Friendly Explanation Message
    const msgEl = card.querySelector('.bm-msg-text');
    if (msgEl) {
      if (isOnline && lat < 500) {
        msgEl.innerHTML = `<span style="color:#34d399;">😄 ${moodText}</span> &mdash; Respon super cepat &amp; terenkripsi normal`;
      } else if (isOnline && lat >= 500) {
        msgEl.innerHTML = `<span style="color:#fbbf24;">🫤 ${moodText}</span> &mdash; Respon membutuhkan ${lat} ms, harap bersabar`;
      } else {
        msgEl.innerHTML = `<span style="color:#f87171;">😡 ${moodText}</span> &mdash; Gagal terhubung ke endpoint BPJS`;
      }
    }

    const timeEl = card.querySelector('.bm-time-text');
    if (timeEl) {
      timeEl.textContent = ch.timestamp ? `Ping terakhir: ${ch.timestamp}` : '';
    }

    // Code Badge
    const codeBadge = card.querySelector('.bm-http-code');
    if (codeBadge) {
      codeBadge.textContent = ch.http_code ? `HTTP ${ch.http_code}` : 'TIMEOUT';
      codeBadge.className = `bm-http-code ${ch.http_code === 200 || ch.http_code === 208 ? 'code-200' : 'code-err'}`;
    }
  }


  // ─── Update Topbar Widget ──────────────────────────────────────
  function updateTopbarWidget() {
    const pill = $('topbarBridgingPill');
    if (!pill) return;

    const sum = state.summary || {};
    const isOnline = sum.online > 0;
    const avgLat = sum.avg_latency_ms || 0;

    const dot = pill.querySelector('.topbar-radar-dot');
    const label = pill.querySelector('.topbar-bridging-text');
    const latTag = pill.querySelector('.topbar-bridging-lat');

    if (dot) {
      dot.className = `topbar-radar-dot ${isOnline ? 'dot-online' : 'dot-warn'}`;
    }

    if (label) {
      label.textContent = isOnline ? 'PCare Online' : 'PCare Terputus';
    }

    if (latTag) {
      if (avgLat > 0) {
        latTag.textContent = `${avgLat}ms`;
        latTag.style.display = 'inline-block';
      } else {
        latTag.style.display = 'none';
      }
    }
  }


  // ─── Activity Log Stream ───────────────────────────────────────
  function addLogEntry(channel, message, type = 'info') {
    const time = new Date().toLocaleTimeString('id-ID');
    const entry = { time, channel, message, type };
    state.logs.unshift(entry);
    if (state.logs.length > state.maxLogs) state.logs.pop();
    renderLogs();
  }

  function renderLogs() {
    const container = $('bmLogStreamContainer');
    if (!container) return;

    if (state.logs.length === 0) {
      container.innerHTML = `<div class="bm-log-empty"><i class="fas fa-terminal"></i> Menunggu aktivitas monitor...</div>`;
      return;
    }

    let html = '';
    state.logs.forEach(log => {
      const typeClass = log.type === 'success' ? 'log-success' : (log.type === 'danger' ? 'log-danger' : (log.type === 'warning' ? 'log-warning' : 'log-info'));
      html += `
        <div class="bm-log-row ${typeClass}">
          <span class="bm-log-time">${log.time}</span>
          <span class="bm-log-channel">[${log.channel}]</span>
          <span class="bm-log-msg">${escapeHtml(log.message)}</span>
        </div>
      `;
    });

    container.innerHTML = html;
  }

  function escapeHtml(str) {
    return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  // ─── Inspect Channel Payload ───────────────────────────────────
  function inspectChannelPayload(chKey) {
    const ch = state.channels[chKey];
    if (!ch) return;

    const modal = $('modalInspectPayload');
    if (!modal) return;

    const title = $('inspectPayloadTitle');
    const content = $('inspectPayloadContent');

    if (title) title.textContent = `Detail Respon: ${ch.name}`;
    if (content) {
      const displayObj = {
        channel: ch.name,
        endpoint_url: ch.url + (ch.endpoint || ''),
        status_koneksi: ch.online ? 'TERHUBUNG (ONLINE)' : 'TERPUTUS (OFFLINE)',
        http_code: ch.http_code,
        latency_ms: ch.duration_ms,
        last_ping: ch.timestamp,
        pesan_sistem: ch.message,
        payload_data: ch.detail || '(Tidak ada data response tambahan)'
      };
      content.textContent = JSON.stringify(displayObj, null, 2);
    }

    modal.classList.add('active');
  }

  // ─── Animated ECG Waveform / Oscilloscope Canvas ───────────────
  function setupCanvas() {
    const canvas = $('bridgingWaveCanvas');
    if (!canvas) return;

    state.canvasEl = canvas;
    const ctx = canvas.getContext('2d');
    state.canvasCtx = ctx;

    // Resize Canvas for high DPI
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    canvas.width = (rect.width || 600) * dpr;
    canvas.height = (rect.height || 100) * dpr;
    ctx.scale(dpr, dpr);

    if (!state.waveAnimationId) {
      animateWave();
    }
  }

  function triggerSpikePulse() {
    // Generate an ECG P-Q-R-S-T spike pattern
    state.waveSpikes.push({
      x: 0,
      intensity: 1.0
    });
  }

  function animateWave() {
    const canvas = state.canvasEl;
    const ctx = state.canvasCtx;

    if (!canvas || !ctx) {
      state.waveAnimationId = requestAnimationFrame(animateWave);
      return;
    }

    const rect = canvas.getBoundingClientRect();
    const width = rect.width || 600;
    const height = rect.height || 100;
    const midY = height / 2;

    // Clear background with translucent dark slate for persistence motion trail
    ctx.fillStyle = 'rgba(15, 23, 42, 0.28)';
    ctx.fillRect(0, 0, width, height);

    // Draw Subtle Grid Lines
    ctx.strokeStyle = 'rgba(51, 65, 85, 0.35)';
    ctx.lineWidth = 1;
    const gridSize = 24;

    ctx.beginPath();
    for (let x = 0; x < width; x += gridSize) {
      ctx.moveTo(x, 0);
      ctx.lineTo(x, height);
    }
    for (let y = 0; y < height; y += gridSize) {
      ctx.moveTo(0, y);
      ctx.lineTo(width, y);
    }
    ctx.stroke();

    // ECG Waveform Path
    ctx.beginPath();
    ctx.lineWidth = 2.4;
    ctx.shadowBlur = 12;
    ctx.shadowColor = '#10b981';
    ctx.strokeStyle = '#10b981';

    state.wavePhase += 0.05;

    for (let x = 0; x < width; x += 2) {
      let y = midY;

      // Small background heartbeat vibration
      y += Math.sin((x * 0.04) - state.wavePhase) * 2;

      // Check Spikes
      state.waveSpikes.forEach(spike => {
        const dist = Math.abs(x - spike.x);
        if (dist < 40) {
          // ECG Signature: Q-R-S Spike
          const normDist = (x - spike.x) / 40;
          if (normDist > -0.6 && normDist < -0.2) {
            y += 6 * spike.intensity; // Q dip
          } else if (normDist >= -0.2 && normDist <= 0.2) {
            y -= 34 * Math.cos(normDist * Math.PI * 2.5) * spike.intensity; // R Peak!
          } else if (normDist > 0.2 && normDist < 0.6) {
            y += 12 * spike.intensity; // S dip
          } else if (normDist >= 0.6 && normDist <= 1.0) {
            y -= 10 * Math.sin(normDist * Math.PI) * spike.intensity; // T wave
          }
        }
      });

      if (x === 0) {
        ctx.moveTo(x, y);
      } else {
        ctx.lineTo(x, y);
      }
    }
    ctx.stroke();

    // Advance Spikes across screen
    state.waveSpikes.forEach(spike => {
      spike.x += 4.5;
    });

    // Auto generate gentle periodic heartbeats every few seconds
    if (Math.random() < 0.015 && state.waveSpikes.length < 3) {
      triggerSpikePulse();
    }

    // Clean up finished spikes
    state.waveSpikes = state.waveSpikes.filter(s => s.x < width + 60);

    // Glowing Scanner Head Point
    const scanX = (state.wavePhase * 55) % width;
    ctx.beginPath();
    ctx.arc(scanX, midY, 4, 0, Math.PI * 2);
    ctx.fillStyle = '#34d399';
    ctx.shadowBlur = 16;
    ctx.shadowColor = '#34d399';
    ctx.fill();

    // Reset shadow
    ctx.shadowBlur = 0;

    state.waveAnimationId = requestAnimationFrame(animateWave);
  }

  // Handle Resize
  window.addEventListener('resize', () => {
    if (state.canvasEl) setupCanvas();
  });

  // Start on DOM Ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
