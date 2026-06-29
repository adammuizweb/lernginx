document.addEventListener('DOMContentLoaded', function () {
  const API = 'index.php'; // endpoint relatif
  const openButtons = document.querySelectorAll('.btn-open-modules');
  const modal = document.getElementById('student-modules-modal');
  const modalTitle = document.getElementById('modal-title');
  const modalUserInfo = document.getElementById('modal-user-info');
  const modalUserId = document.getElementById('modal_user_id');
  const modalList = document.getElementById('modal-categories-list');
  const saveBtn = document.getElementById('modal-save-btn');
  const closeBtn = document.getElementById('modal-cancel-btn');
  const closeX = document.getElementById('modal-close');
  const msg = document.getElementById('modal-message');

  function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

function renderCategories(categories) {
  modalList.innerHTML = '';
  categories.forEach(c => {
    const cid = parseInt(c.id, 10);
    const wrapper = document.createElement('div');
    wrapper.style.padding = '6px';
    wrapper.style.borderBottom = '1px solid #f5f5f5';

    // NOTE: placeholder option dibuat disabled
    wrapper.innerHTML =
      '<div style="display:flex; justify-content:space-between; align-items:center;">' +
      '<div style="flex:1;">' +
      '<div style="font-weight:600;">' + escapeHtml(c.name) + '</div>' +
      '</div>' +
      '<div style="flex:0 0 220px; text-align:right;">' +
      '<select name="modules[' + cid + ']" style="min-width:180px;">' +
      '<option value="" disabled selected>—</option>' + 
      '<option value="0">Active</option>' +
      '<option value="1">Pending</option>' +
      '<option value="2">Inactive</option>' +
      '</select>' +
      '</div>' +
      '</div>';
    modalList.appendChild(wrapper);
  });
}


  function openModalFor(userId, username, displayName, email) {
    modal.style.display = 'flex';
    modalTitle.textContent = 'Modul untuk ' + username;
    modalUserInfo.innerHTML =
      '<strong>' + escapeHtml(username) + '</strong>' +
      (displayName ? ' — ' + escapeHtml(displayName) : '') +
      (email ? ' · ' + escapeHtml(email) : '');
    modalUserId.value = userId;
    msg.textContent = '';

    // Render kategori dari injected data
    renderCategories(window._availableCategories || []);

    // Fetch student modules from API (via index.php)
    fetch(API + '?ajax=get_user_modules&user_id=' + encodeURIComponent(userId), { credentials: 'same-origin' })
      .then(r => r.ok ? r.json() : Promise.reject('network'))
      .then(data => {
        // data bisa berisi beberapa baris; kita pilih reviewed row jika ada
        const byCategory = {};
        (data || []).forEach(d => {
          const cid = String(d.category_id);
          if (!byCategory[cid]) byCategory[cid] = { anyRow: d, reviewedRow: null };
          if (parseInt(d.is_reviewed, 10) === 1) {
            byCategory[cid].reviewedRow = d;
          }
        });

        Object.keys(byCategory).forEach(cid => {
          const rec = byCategory[cid];
          const sel = modalList.querySelector('select[name="modules[' + cid + ']"]');
          if (!sel) return;
          if (rec.reviewedRow) sel.value = String(rec.reviewedRow.status);
          else if (rec.anyRow) sel.value = String(rec.anyRow.status);
        });
      })
      .catch(err => {
        console.error(err);
        msg.textContent = 'Failed to load student modules.';
      });
  }

  function closeModal() {
    modal.style.display = 'none';
    modalUserId.value = '';
    modalList.innerHTML = '';
    msg.textContent = '';
  }

  openButtons.forEach(btn => {
    btn.addEventListener('click', function () {
      const uid = this.getAttribute('data-user-id');
      const tr = document.querySelector('tr[data-user-id="' + uid + '"]');
      const username = tr ? tr.querySelector('td').textContent.trim() : ('User ' + uid);
      const displayName = tr ? tr.querySelectorAll('td')[1].textContent.trim() : '';
      const email = tr ? tr.querySelectorAll('td')[2].textContent.trim() : '';
      openModalFor(uid, username, displayName, email);
    });
  });

  closeBtn.addEventListener('click', closeModal);
  closeX.addEventListener('click', closeModal);

  saveBtn.addEventListener('click', function () {
    const userId = modalUserId.value;
    if (!userId) return;

    const params = new URLSearchParams();
    params.append('ajax_action', 'assign_modules');
    params.append('user_id', userId);

    modalList.querySelectorAll('select[name^="modules["]').forEach(sel => {
      const name = sel.getAttribute('name');
      const match = name.match(/^modules\[(\d+)\]$/);
      if (!match) return;
      const cid = match[1];
      const val = sel.value;
      // if empty string -> send empty (means "dash"/no decision)
      params.append('modules[' + cid + ']', val === '' ? '' : val);
    });

    saveBtn.disabled = true;
    msg.textContent = 'Saving...';

    fetch(API, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params.toString()
    })
      .then(r => r.ok ? r.json() : r.text().then(t => Promise.reject(t)))
      .then(json => {
        if (json.ok) {
          msg.textContent = 'Changes saved.';
          setTimeout(function () { closeModal(); location.reload(); }, 700);
        } else {
          msg.textContent = 'Failed: ' + (json.error || 'server error');
        }
      })
      .catch(err => {
        console.error(err);
        msg.textContent = 'Failed to save: ' + (typeof err === 'string' ? err : '');
      })
      .finally(() => { saveBtn.disabled = false; });
  });
});
