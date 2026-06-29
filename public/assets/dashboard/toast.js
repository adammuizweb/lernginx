/* ---------------- TOASTS ---------------- */
let toastContainer = document.getElementById('toast-container');
if (!toastContainer) {
  toastContainer = document.createElement('div');
  toastContainer.id = 'toast-container';
  toastContainer.className = 'toast-container';
  document.body.appendChild(toastContainer);
}

window.showToast = function(message, options = {}) {
  const el = document.createElement('div');
  el.className = 'toast card';
  el.tabIndex = 0;
  el.innerHTML = '<strong>' + (options.title || 'Notifikasi') + '</strong><div class="small">' + message + '</div>';
  toastContainer.appendChild(el);

  const timeout = options.timeout || 3500;
  let dismissed = false;
  const timer = setTimeout(() => {
    if (!dismissed) hideToast(el);
  }, timeout);

  el.addEventListener('mouseenter', () => clearTimeout(timer));
  el.addEventListener('click', () => {
    clearTimeout(timer);
    hideToast(el);
    dismissed = true;
  });
};

function hideToast(el) {
  if (!el) return;
  el.style.opacity = '0';
  setTimeout(() => {
    try { el.remove(); } catch (e) {}
  }, 260);
}

// Welcome toast (opsional)
try {
  if (!sessionStorage.getItem('lernginx:welcome_shown')) {
    sessionStorage.setItem('lernginx:welcome_shown', '1');
    setTimeout(() => showToast('Welcome to lernginx Dashboard — theme applied.', { timeout: 1600 }), 500);
  }
} catch (e) {}
