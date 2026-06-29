/* dashboard.js — bersih, DOM-safe, mobile-aware */
(function () {
  'use strict';

  const docEl = document.documentElement;
  const body = document.body;
  const THEME_KEY = 'lernginx:theme';

  function safeGet(id){ return document.getElementById(id) || null; }
  function isMobileMQ(){ return window.matchMedia('(max-width: 768px)'); }

  /* THEME MODULE */
  (function () {
    const themeToggle = safeGet('theme-toggle');

    function applyTheme(theme){
      if (theme === 'dark') {
        docEl.classList.add('theme-dark', 'theme-locked');
      } else if (theme === 'light') {
        docEl.classList.remove('theme-dark');
        docEl.classList.add('theme-locked');
      } else {
        docEl.classList.remove('theme-locked');
      }

      if (!themeToggle) return;
      if (theme === 'dark') { themeToggle.textContent = '🌙'; themeToggle.setAttribute('aria-pressed','true'); }
      else if (theme === 'light') { themeToggle.textContent = '🔆'; themeToggle.setAttribute('aria-pressed','false'); }
      else { themeToggle.textContent = '🌓'; themeToggle.removeAttribute('aria-pressed'); }
    }

    function detectSystemPref(){
      try { return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
      catch(e){ return 'light'; }
    }

    function loadTheme(){
      try {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved === 'dark' || saved === 'light') { applyTheme(saved); return; }
      } catch(e){}
      applyTheme(detectSystemPref());
    }

    function toggleTheme(){
      const isDark = docEl.classList.contains('theme-dark');
      const newTheme = isDark ? 'light' : 'dark';
      applyTheme(newTheme);
      try{ localStorage.setItem(THEME_KEY, newTheme); }catch(e){}
      try{ document.cookie = 'lernginx_theme=' + encodeURIComponent(newTheme) + ';path=/;max-age=' + (60*60*24*365) + ';SameSite=Lax'; }catch(e){}
      if (typeof showToast === 'function') showToast('Tema diubah ke ' + newTheme, { timeout: 1200 });
    }

    if (themeToggle) themeToggle.addEventListener('click', toggleTheme);
    loadTheme();
  })();

  /* NAV MODULE (mobile-aware) */
  (function () {
    const menuToggle = safeGet('menu-toggle');
    const nav = safeGet('dashboard-nav');
    const header = document.querySelector('.dashboard-header');
    if (!nav || !header) return;

    const mq = isMobileMQ();
    let globalsAttached = false;

    function setMobileState(mobile){
      if (mobile) {
        // hidden and inert until opened
        nav.setAttribute('aria-hidden','true');
        nav.setAttribute('inert','');
        if (menuToggle) menuToggle.setAttribute('aria-expanded','false');
      } else {
        // desktop: fully interactive
        nav.removeAttribute('aria-hidden');
        nav.removeAttribute('inert');
        if (menuToggle) menuToggle.setAttribute('aria-expanded','true');
        header.classList.remove('nav-open');
        body.classList.remove('nav-lock');
      }
    }

    function attachGlobals(){
      if (globalsAttached) return;
      document.addEventListener('click', onDocClick);
      document.addEventListener('keydown', onKeyDown);
      globalsAttached = true;
    }
    function detachGlobals(){
      if (!globalsAttached) return;
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onKeyDown);
      globalsAttached = false;
    }

    function openMenu(){
      header.classList.add('nav-open');
      nav.removeAttribute('inert');
      nav.setAttribute('aria-hidden','false');
      if (menuToggle) menuToggle.setAttribute('aria-expanded','true');
      body.classList.add('nav-lock');
      const first = nav.querySelector('a,button,[tabindex]:not([tabindex="-1"])');
      if (first && typeof first.focus === 'function') first.focus();
      attachGlobals();
    }

    function closeMenu(){
      header.classList.remove('nav-open');
      nav.setAttribute('aria-hidden','true');
      nav.setAttribute('inert','');
      if (menuToggle) menuToggle.setAttribute('aria-expanded','false');
      body.classList.remove('nav-lock');
      if (menuToggle && typeof menuToggle.focus === 'function') menuToggle.focus();
      detachGlobals();
    }

    function toggleMenu(e){
      if (!mq.matches) return; // hanya untuk mobile
      e.stopPropagation();
      if (header.classList.contains('nav-open')) closeMenu();
      else openMenu();
    }

    function onDocClick(e){
      if (!header.contains(e.target)) closeMenu();
    }

    function onKeyDown(e){
      if (e.key === 'Escape') { closeMenu(); return; }
      if (e.key === 'Tab' && mq.matches) {
        const focusables = Array.from(nav.querySelectorAll('a,button,[tabindex]:not([tabindex="-1"])')).filter(Boolean);
        if (focusables.length === 0) return;
        const first = focusables[0], last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    }

    // initial and responsive handling
    setMobileState(mq.matches);
    mq.addEventListener('change', (ev) => setMobileState(ev.matches));

    if (menuToggle) menuToggle.addEventListener('click', toggleMenu);

    // close on nav link click (mobile UX)
    nav.addEventListener('click', function(e){
      const a = e.target.closest('a');
      if (a && a.getAttribute('href') && mq.matches) closeMenu();
    });
  })();

})();
