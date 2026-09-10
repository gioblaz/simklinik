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

// ─── CSS Animations for Toast ─────────────────────────────────
const style = document.createElement('style');
style.textContent = `
  @keyframes slideInRight  { from { opacity:0; transform: translateX(40px); } to { opacity:1; transform: translateX(0); } }
  @keyframes slideOutRight { from { opacity:1; transform: translateX(0); } to { opacity:0; transform: translateX(40px); } }
`;
document.head.appendChild(style);
