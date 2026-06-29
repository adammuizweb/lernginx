(function(){
  try {
    var theme = null;
    // check cookie first (server-side sync), fallback ke localStorage
    var cookieMatch = document.cookie.match(/(?:^|;\s*)lernginx_theme=(dark|light)(?:;|$)/);
    if (cookieMatch) {
      theme = cookieMatch[1];
    } else {
      theme = localStorage.getItem('lernginx:theme');
    }
    if (!theme) {
      // fallback to system preference
      try {
        theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      } catch(e) { theme = 'light'; }
    }
    if (theme === 'dark') {
      document.documentElement.classList.add('theme-dark');
      // set a quick inline bg so we don't see white before CSS loads
      document.documentElement.style.background = '#071022';
      document.documentElement.classList.add('theme-locked');
    } else if (theme === 'light') {
      document.documentElement.classList.remove('theme-dark');
      document.documentElement.style.background = '#ffffff';
      document.documentElement.classList.add('theme-locked');
    } else {
      // no locked theme
      document.documentElement.classList.remove('theme-locked');
    }
  } catch(e) { /* silent */ }
})();
// hide nav
(function () {
  const menuToggle = document.getElementById('menu-toggle');
  const nav = document.getElementById('dashboard-nav');

  if (!menuToggle || !nav) return;

  menuToggle.addEventListener('click', () => {
    const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';

    // Jika sedang menutup menu dan fokus ada di dalam nav, pindahkan fokus keluar
    if (isExpanded && nav.contains(document.activeElement)) {
      menuToggle.focus(); // atau document.body.focus();
    }

    // Toggle aria-expanded dan aria-hidden
    menuToggle.setAttribute('aria-expanded', String(!isExpanded));
    nav.setAttribute('aria-hidden', String(isExpanded));
  });
})();
