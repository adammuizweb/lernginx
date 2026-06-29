// Section 5 JS — accessible tabs with click, keyboard, and hash support
(function () {
  const tabs = Array.from(document.querySelectorAll('.program-tabs .tab'));
  const panels = Array.from(document.querySelectorAll('.program-tabs .tab-panel'));

  if (!tabs.length) return;

  function activateTab(targetTab, setFocus = true, pushHash = true) {
    const target = targetTab.getAttribute('data-target');
    tabs.forEach(t => {
      const selected = t === targetTab;
      t.setAttribute('aria-selected', selected ? 'true' : 'false');
      t.tabIndex = selected ? 0 : -1;
    });

    panels.forEach(p => {
      if (p.id === 'panel-' + target) {
        p.removeAttribute('hidden');
        p.style.opacity = 0;
        p.style.transform = 'translateY(8px)';
        // animate in
        requestAnimationFrame(() => {
          p.style.transition = 'opacity .28s ease, transform .28s ease';
          p.style.opacity = 1;
          p.style.transform = 'translateY(0)';
        });
      } else {
        p.setAttribute('hidden', '');
      }
    });

    if (setFocus) targetTab.focus();
    if (pushHash) {
      // update URL hash without scrolling
      const hash = 'program-' + target;
      if (history.replaceState) {
        history.replaceState(null, '', '#' + hash);
      } else {
        location.hash = hash;
      }
    }
  }

  // click handler
  tabs.forEach(t => {
    t.addEventListener('click', (e) => {
      activateTab(t, true, true);
    });

    t.addEventListener('keydown', (e) => {
      const idx = tabs.indexOf(t);
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        e.preventDefault();
        const dir = e.key === 'ArrowRight' ? 1 : -1;
        const nextIdx = (idx + dir + tabs.length) % tabs.length;
        activateTab(tabs[nextIdx], true, true);
      } else if (e.key === 'Home') {
        e.preventDefault();
        activateTab(tabs[0], true, true);
      } else if (e.key === 'End') {
        e.preventDefault();
        activateTab(tabs[tabs.length - 1], true, true);
      } else if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        activateTab(t, true, true);
      }
    });
  });

  // on load: if hash exists, open corresponding tab
  function openFromHash() {
    const hash = location.hash.replace('#', '');
    if (!hash) return;
    // expecting hash "program-glow" or "program-sciv" etc.
    if (!hash.startsWith('program-')) return;
    const key = hash.replace('program-', '');
    const tabToOpen = tabs.find(t => t.dataset.target === key);
    if (tabToOpen) {
      activateTab(tabToOpen, false, false);
      // scroll into view slightly (don't force if user scrolled)
      if (typeof window !== 'undefined' && window.scrollY < 100) {
        document.getElementById('programs')?.scrollIntoView({ behavior: 'smooth' });
      }
    }
  }
  openFromHash();

  // Ensure first tab selected by default
  const initiallySelected = tabs.find(t => t.getAttribute('aria-selected') === 'true') || tabs[0];
  activateTab(initiallySelected, false, false);
})();
