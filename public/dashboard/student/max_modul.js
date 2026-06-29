/*
Patch: auto-check descendants dan perbaiki getTopParent

File ini berisi blok <script> yang dapat kamu pastekan ke
dashboard/student/index.php menggantikan script lama yang
membangun categoryParentMap dan event listeners.

Instruksi singkat:
- Letakkan di bawah tempat server mengekspor window.categoryParentMap
  (yang sudah ada di file kamu).
- Script ini membaca window.categoryParentMap dan window.MAX_PARENT_LIMIT
  bila tersedia.
*/

(function () {
  'use strict';

  // Baca parentMap yang diekspor oleh server (childId => parentId)
  const parentMap = window.categoryParentMap || {};

  // Bangun childMap untuk traversal descendant cepat (parentId => [childId,...])
  const childMap = {};
  Object.entries(parentMap).forEach(([childStr, parent]) => {
    const child = parseInt(childStr, 10);
    const pid = parent === null ? 0 : parseInt(parent, 10);
    if (!childMap[pid]) childMap[pid] = [];
    childMap[pid].push(child);
  });

  // Ambil limit dari window atau fallback 2
  const MAX_PARENT_LIMIT = (window.MAX_PARENT_LIMIT !== undefined) ? parseInt(window.MAX_PARENT_LIMIT, 10) : 2;

  // Elemen UI
  const currentCountEl = document.getElementById('currentParentCount');
  const maxLimitEl = document.getElementById('maxParentLimit');
  if (maxLimitEl) maxLimitEl.textContent = MAX_PARENT_LIMIT;

  // Helper: dapatkan semua descendant (DFS)
  function getAllDescendants(startId) {
    const out = [];
    const stack = [startId];
    while (stack.length) {
      const cur = stack.pop();
      const children = childMap[cur] || [];
      for (const c of children) {
        out.push(c);
        stack.push(c);
      }
    }
    return out;
  }

  // Perbaiki fungsi getTopParent: naik sampai parent === 0/null atau tidak ada
  function getTopParent(cid) {
    cid = parseInt(cid, 10);
    if (!parentMap.hasOwnProperty(cid)) return cid;

    let cur = cid;
    while (parentMap.hasOwnProperty(cur) && parentMap[cur] !== 0 && parentMap[cur] !== null) {
      cur = parentMap[cur];
    }
    return cur;
  }

  // Simple toast
  function showWarningbhn(message, type = 'error') {
    let container = document.getElementById('warningbhn-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'warningbhn-container';
      container.style.position = 'fixed';
      container.style.right = '12px';
      container.style.top = '12px';
      container.style.zIndex = 9999;
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `warningbhn-toast ${type}`;
    toast.textContent = message;
    toast.style.marginTop = '8px';
    toast.style.padding = '10px 14px';
    toast.style.borderRadius = '6px';
    toast.style.boxShadow = '0 2px 8px rgba(0,0,0,0.12)';
    toast.style.background = (type === 'error') ? '#ffdede' : '#fff5d6';
    toast.style.color = '#222';
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity .25s';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }

  // Hitung top-level parents dari checkbox tercentang
  function updateParentCount() {
    const checked = Array.from(document.querySelectorAll('.module-checkboxes input[type="checkbox"]:checked'))
      .map(cb => parseInt(cb.value, 10));

    const topParents = new Set();
    checked.forEach(id => topParents.add(getTopParent(id)));

    if (currentCountEl) currentCountEl.textContent = topParents.size;
    return topParents.size;
  }

  // Terapkan toggle ke descendants
  function onParentToggle(parentId, checked) {
    const descendants = getAllDescendants(parentId);
    for (const d of descendants) {
      const cb = document.querySelector(`.module-checkboxes input[type="checkbox"][value="${d}"]`);
      if (!cb) continue;
      cb.checked = checked;
    }
  }

  // Inisialisasi dan attach listener
  document.addEventListener('DOMContentLoaded', () => {
    const checkboxes = Array.from(document.querySelectorAll('.module-checkboxes input[type="checkbox"]'));
    if (!checkboxes.length) return;

    // Jika parent sudah checked saat load, sinkronkan descendants
    checkboxes.forEach(cb => {
      const id = parseInt(cb.value, 10);
      if ((childMap[id] || []).length > 0 && cb.checked) {
        onParentToggle(id, true);
      }
    });

    // Attach listeners
    checkboxes.forEach(cb => {
      cb.addEventListener('change', function () {
        if (this.disabled) return;
        const id = parseInt(this.value, 10);

        // Jika node ini parent
        if ((childMap[id] || []).length > 0) {
          if (this.checked) {
            // Simulasikan checked set
            const checkedNow = Array.from(document.querySelectorAll('.module-checkboxes input[type="checkbox"]:checked'))
              .map(x => parseInt(x.value, 10));
            if (!checkedNow.includes(id)) checkedNow.push(id);

            const tpSet = new Set();
            checkedNow.forEach(cid => tpSet.add(getTopParent(cid)));

            if (tpSet.size > MAX_PARENT_LIMIT) {
              this.checked = false;
              showWarningbhn(`Batas maksimum modul utama tercapai. Anda hanya dapat mendaftar maksimal ${MAX_PARENT_LIMIT} modul utama.`, 'error');
              updateParentCount();
              return;
            }

            onParentToggle(id, true);
          } else {
            onParentToggle(id, false);
          }
        } else {
          // Node child-only
          if (this.checked) {
            const checkedNow = Array.from(document.querySelectorAll('.module-checkboxes input[type="checkbox"]:checked'))
              .map(x => parseInt(x.value, 10));
            if (!checkedNow.includes(id)) checkedNow.push(id);

            const tpSet = new Set();
            checkedNow.forEach(cid => tpSet.add(getTopParent(cid)));

            if (tpSet.size > MAX_PARENT_LIMIT) {
              this.checked = false;
              showWarningbhn(`Batas maksimum modul utama tercapai. Anda hanya dapat mendaftar maksimal ${MAX_PARENT_LIMIT} modul utama.`, 'error');
              updateParentCount();
              return;
            }
          }
        }

        updateParentCount();
      });
    });

    // initial update
    updateParentCount();
  });
})();
