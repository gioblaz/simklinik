/**
 * SIMKlinik — App JavaScript
 * Global UI interactions
 */

// ─── Sidebar Toggle ──────────────────────────────────────────
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const toggleBtn = document.getElementById('sidebarToggle');
const mobileOverlay = document.getElementById('mobileOverlay');

function isMobile() { return window.innerWidth <= 768; }

if (toggleBtn) {
  toggleBtn.addEventListener('click', () => {
    if (isMobile()) {
      sidebar?.classList.toggle('open');
      mobileOverlay?.classList.toggle('active');
    } else {
      sidebar?.classList.toggle('collapsed');
      mainContent?.classList.toggle('expanded');
      localStorage.setItem('sidebarCollapsed', sidebar?.classList.contains('collapsed') ? '1' : '0');
    }
  });
}

if (mobileOverlay) {
  mobileOverlay.addEventListener('click', () => {
    sidebar?.classList.remove('open');
    mobileOverlay.classList.remove('active');
  });
}

// Restore sidebar state
window.addEventListener('DOMContentLoaded', () => {
  if (!isMobile() && localStorage.getItem('sidebarCollapsed') === '1') {
    sidebar?.classList.add('collapsed');
    mainContent?.classList.add('expanded');
  }
});

// ─── Dropdown Menus (Robust Event Delegation) ─────────────────
document.addEventListener('click', (e) => {
  const trigger = e.target.closest('[data-dropdown]');
  if (trigger) {
    e.preventDefault();
    e.stopPropagation();
    const dropdownId = trigger.getAttribute('data-dropdown');
    const target = document.getElementById(dropdownId);
    const isOpen = target?.classList.contains('open');

    // Close all open dropdowns first
    document.querySelectorAll('.dropdown-menu.open').forEach(m => {
      if (m !== target) m.classList.remove('open');
    });

    // Toggle current
    if (target) {
      target.classList.toggle('open', !isOpen);
    }
    return;
  }

  // Clicked outside dropdown menu
  if (!e.target.closest('.dropdown-menu')) {
    document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
  }
});

// ─── Auto-dismiss Flash Alerts ───────────────────────────────
document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
  const delay = parseInt(alert.dataset.autoDismiss) || 4000;
  setTimeout(() => {
    alert.style.transition = 'opacity 0.4s ease';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 400);
  }, delay);
});

// Close alert manually
document.querySelectorAll('.alert-close').forEach(btn => {
  btn.addEventListener('click', () => {
    const alert = btn.closest('.alert');
    alert.style.transition = 'opacity 0.3s ease';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 300);
  });
});

// ─── Modals ───────────────────────────────────────────────────
function openModal(id) {
  const overlay = document.getElementById(id);
  if (overlay) {
    overlay.classList.add('active');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(id) {
  const overlay = document.getElementById(id);
  if (overlay) {
    overlay.classList.remove('active');
    overlay.style.display = 'none';
    document.body.style.overflow = '';
  }
}

document.querySelectorAll('[data-open-modal]').forEach(btn => {
  btn.addEventListener('click', () => openModal(btn.dataset.openModal));
});

document.querySelectorAll('[data-close-modal]').forEach(btn => {
  btn.addEventListener('click', () => closeModal(btn.dataset.closeModal));
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal(overlay.id);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(o => {
      closeModal(o.id);
    });
  }
});

// ─── Confirm Delete ───────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', (e) => {
    const msg = el.dataset.confirm || 'Apakah Anda yakin?';
    if (!confirm(msg)) e.preventDefault();
  });
});

// ─── Form Validation ──────────────────────────────────────────
function validateForm(formId) {
  const form = document.getElementById(formId);
  if (!form) return true;
  let valid = true;
  form.querySelectorAll('[required]').forEach(field => {
    if (!field.value.trim()) {
      field.classList.add('is-invalid');
      valid = false;
    } else {
      field.classList.remove('is-invalid');
    }
  });
  return valid;
}

// ─── AJAX Helper ──────────────────────────────────────────────
async function fetchData(url, options = {}) {
  try {
    const response = await fetch(url, {
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      ...options
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return await response.json();
  } catch (err) {
    console.error('fetchData error:', err);
    return null;
  }
}

// ─── Format Rupiah (JS) ───────────────────────────────────────
function formatRupiah(num) {
  return 'Rp ' + Math.round(num).toLocaleString('id-ID');
}

// ─── Toast Notification ───────────────────────────────────────
function showToast(message, type = 'info', duration = 3000) {
  const icons = { success: 'fa-check-circle', danger: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
  const colors = { success: '#16a34a', danger: '#dc2626', warning: '#d97706', info: '#0891b2' };

  const toast = document.createElement('div');
  toast.style.cssText = `
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    background: #fff; border-left: 4px solid ${colors[type] || colors.info};
    border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.12);
    padding: 14px 18px; display: flex; align-items: center; gap: 12px;
    font-family: Inter, sans-serif; font-size: 13px; color: #1e293b;
    max-width: 340px; animation: slideInRight 0.3s ease;
  `;

  toast.innerHTML = `
    <i class="fas ${icons[type] || icons.info}" style="color:${colors[type]};font-size:16px;flex-shrink:0;"></i>
    <span style="flex:1;">${message}</span>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:16px;padding:0;">×</button>
  `;

  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = 'slideOutRight 0.3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// ─── Current Time Display (WIB) ───────────────────────────────
function updateClock() {
  const el = document.getElementById('currentTime');
  if (el) {
    const now = new Date();
    el.textContent = now.toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
  }
}

setInterval(updateClock, 1000);
updateClock();

// ─── DataTable-like Search ────────────────────────────────────
const searchInputs = document.querySelectorAll('[data-search-table]');
searchInputs.forEach(input => {
  input.addEventListener('input', () => {
    const tableId = input.dataset.searchTable;
    const query = input.value.toLowerCase();
    const table = document.getElementById(tableId);
    if (!table) return;
    table.querySelectorAll('tbody tr').forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(query) ? '' : 'none';
    });
  });
});

// ─── Sidebar active link ──────────────────────────────────────
document.querySelectorAll('.nav-link').forEach(link => {
  if (link.href && window.location.href.includes(link.getAttribute('href'))) {
    link.classList.add('active');
  }
});

// ─── Top Nav Tabs Mousewheel Scroll (only if overflowed) ────────
const navTabsWrapper = document.querySelector('.top-nav-tabs-wrapper');
if (navTabsWrapper) {
  navTabsWrapper.addEventListener('wheel', (e) => {
    if (navTabsWrapper.scrollWidth > navTabsWrapper.clientWidth && e.deltaY !== 0) {
      e.preventDefault();
      navTabsWrapper.scrollLeft += e.deltaY;
    }
  }, { passive: false });
}

// ─── CSS Animations for Toast & Modals ────────────────────────
const style = document.createElement('style');
style.textContent = `
  @keyframes slideInRight  { from { opacity:0; transform: translateX(40px); } to { opacity:1; transform: translateX(0); } }
  @keyframes slideOutRight { from { opacity:1; transform: translateX(0); } to { opacity:0; transform: translateX(40px); } }
  @keyframes taccModalPop  { from { opacity:0; transform: scale(0.92) translateY(-20px); } to { opacity:1; transform: scale(1) translateY(0); } }
`;
document.head.appendChild(style);

// ═══════════════════════════════════════════════════════════════════════════
// ─── MODUL 144 DIAGNOSA NON-SPESIALISTIK (TACC) BPJS KESEHATAN / FKTP ─────
// ═══════════════════════════════════════════════════════════════════════════

const TACC_ICD_PREFIXES = [
  'A01', 'A03', 'A06', 'A09', 'A15', 'A16', 'A27', 'A35', 'A37', 'A46', 
  'A51', 'A54', 'A59', 'A63', 'A74', 'A82', 'A90', 'A91', 
  'B00', 'B01', 'B02', 'B05', 'B07', 'B08', 'B15', 'B20', 'B35', 'B36', 'B37', 'B50', 'B51', 'B52', 'B53', 'B54', 'B65', 'B68', 'B74', 'B76', 'B77', 'B79', 'B80', 'B85', 'B86',
  'E11', 'E14', 'E16', 'E46', 'E50', 'E56', 'E66', 'E78', 'E79',
  'F41', 'F45',
  'G43', 'G44', 'G45', 'G47', 'G51',
  'H00', 'H01', 'H02', 'H04', 'H10', 'H11', 'H15', 'H25', 'H52', 'H60', 'H61', 'H66',
  'I10', 'I46', 'I84',
  'J00', 'J01', 'J02', 'J03', 'J04', 'J10', 'J11', 'J18', 'J20', 'J30', 'J45',
  'K12', 'K21', 'K29', 'K30', 'K35', 'K64', 'K81', 'K90', 'K92',
  'L01', 'L02', 'L03', 'L08', 'L20', 'L21', 'L23', 'L24', 'L42', 'L50', 'L70', 'L73', 'L74',
  'M10', 'M19',
  'N39', 'N47', 'N61', 'N70', 'N72', 'N76', 'N89',
  'O21', 'O42', 'O70', 'O72', 'O80', 'O92', 'O99',
  'P55',
  'R04', 'R56',
  'T14', 'T15', 'T16', 'T17', 'T20', 'T21', 'T22', 'T23', 'T24', 'T25', 'T30', 'T31', 'T32', 'T62', 'T63', 'T75', 'T78',
  'Z34'
];

/**
 * Cek apakah kode ICD-10 termasuk dalam 144 Diagnosa Non-Spesialistik (TACC)
 */
function isDiagnosaTACC(kdDiag) {
  if (!kdDiag) return false;
  const clean = kdDiag.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
  if (clean.length < 3) return false;
  const prefix3 = clean.substring(0, 3);
  return TACC_ICD_PREFIXES.includes(prefix3);
}

/**
 * Tampilkan Modal Warning TACC dengan Desain Interaktif & Ramah Pengguna
 */
function showTaccWarningModal(options = {}) {
  const {
    kdDiag = '',
    nmDiag = '',
    targetTaccSelectId = 'rujukTacc',
    targetAlasanInputId = 'rujukAlasanTacc',
    onConfirm = null
  } = options;

  let modalEl = document.getElementById('modalWarningTaccGlobal');
  if (!modalEl) {
    modalEl = document.createElement('div');
    modalEl.id = 'modalWarningTaccGlobal';
    modalEl.style.cssText = `
      position:fixed; top:0; left:0; width:100vw; height:100vh;
      background:rgba(15, 23, 42, 0.7); backdrop-filter:blur(4px);
      display:none; align-items:center; justify-content:center;
      z-index:999999; padding:16px;
    `;
    modalEl.innerHTML = `
      <div style="background:#ffffff;border-radius:16px;max-width:560px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);border:1.5px solid #fde047;overflow:hidden;animation:taccModalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Header -->
        <div style="background:linear-gradient(135deg, #d97706 0%, #b45309 100%);color:#fff;padding:18px 22px;display:flex;align-items:center;gap:12px;">
          <div style="width:42px;height:42px;border-radius:10px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <div>
            <h3 style="margin:0;font-size:16.5px;font-weight:800;letter-spacing:-0.2px;line-height:1.2;">Peringatan: Diagnosa Masuk TACC!</h3>
            <p style="margin:3px 0 0 0;font-size:12px;color:#fef3c7;">Termasuk 144 Diagnosa Non-Spesialistik (FKTP)</p>
          </div>
        </div>

        <!-- Body -->
        <div style="padding:22px;font-size:13px;color:#334155;line-height:1.55;">
          
          <div style="background:#fefce8;border:1.5px solid #fef08a;border-radius:10px;padding:12px 14px;margin-bottom:14px;display:flex;align-items:center;gap:10px;">
            <span style="font-family:monospace;font-weight:800;color:#92400e;background:#fde047;padding:3px 8px;border-radius:6px;font-size:13px;" id="taccModalKdDiag"></span>
            <span style="font-weight:700;color:#78350f;font-size:13.5px;" id="taccModalNmDiag"></span>
          </div>

          <p style="margin-bottom:10px;">
            Diagnosa di atas merupakan <strong>Kompetensi Faskes Tingkat Pertama (FKTP)</strong> yang seharusnya dituntaskan di klinik/puskesmas.
          </p>

          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
            <div style="font-size:11.5px;font-weight:800;color:#0369a1;text-transform:uppercase;margin-bottom:6px;display:flex;align-items:center;gap:5px;">
              <i class="fas fa-clipboard-check"></i> Ketentuan Rujukan BPJS (Kriteria TACC):
            </div>
            <ul style="margin:0;padding-left:18px;font-size:12px;color:#475569;display:flex;flex-direction:column;gap:3px;">
              <li><strong>T (Time):</strong> Telah diterapi optimal kurun waktu tertentu tanpa perbaikan.</li>
              <li><strong>A (Age):</strong> Usia pasien berisiko tinggi (bayi/geriatri).</li>
              <li><strong>C (Complication):</strong> Terdapat komplikasi penyakit.</li>
              <li><strong>C (Comorbidity):</strong> Terdapat penyakit penyerta yang memperberat kondisi.</li>
            </ul>
          </div>

          <p style="margin:0;color:#b91c1c;font-weight:600;font-size:12px;">
            <i class="fas fa-info-circle"></i> Jika pasien tetap dirujuk ke Rumah Sakit, Anda <strong>wajib mengisi Kriteria TACC dan Alasan Medis</strong> agar bridging rujukan tidak ditolak BPJS.
          </p>
        </div>

        <!-- Footer -->
        <div style="background:#f8fafc;padding:14px 22px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;">
          <button type="button" id="btnTaccModalClose" class="btn btn-primary" style="background:#d97706;border-color:#d97706;font-weight:700;padding:8px 18px;border-radius:8px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-check"></i> Saya Mengerti, Lengkapi TACC
          </button>
        </div>

      </div>
    `;
    document.body.appendChild(modalEl);
  }

  document.getElementById('taccModalKdDiag').textContent = kdDiag;
  document.getElementById('taccModalNmDiag').textContent = nmDiag;
  modalEl.style.display = 'flex';

  const btnClose = document.getElementById('btnTaccModalClose');
  btnClose.onclick = function() {
    modalEl.style.display = 'none';

    // Auto highlight target elements
    const selTacc = document.getElementById(targetTaccSelectId);
    const txtAlasan = document.getElementById(targetAlasanInputId);

    if (selTacc) {
      selTacc.style.border = '2px solid #d97706';
      selTacc.style.background = '#fef3c7';
      if (selTacc.value === '0') {
        selTacc.value = '1'; // Default ke Time (Waktu)
        if (typeof onTaccChange === 'function') onTaccChange();
      }
      selTacc.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (txtAlasan) {
      txtAlasan.style.border = '2px solid #d97706';
      txtAlasan.focus();
    }

    if (typeof onConfirm === 'function') {
      onConfirm();
    }
  };
}
