// dashboard/admin/assign/assign.js
// Gabungan: policy toggle + modal edit siswa (fetch fallback)
// Pastikan file ini diload dengan `defer` atau setelah window._isAdmin didefinisikan

(function () {
  'use strict';

  // ----- POLICY TOGGLE (awal file) -----
  function initPolicyToggle() {
    const toggle = document.getElementById('policy-toggle-input');
    const status = document.getElementById('policy-toggle-status');
    // pastikan ada fallback jika window._isAdmin belum didefinisikan
    window._isAdmin = typeof window._isAdmin !== 'undefined' ? window._isAdmin : false;
    const isAdmin = !!window._isAdmin;

    if (!isAdmin && toggle) {
      toggle.disabled = true;
      toggle.style.cursor = 'not-allowed';
    }

    function updateLabel(checked) {
      const statusText = checked ? 'Pending Activation' : 'No Moderation';
      const computed = getComputedStyle(document.documentElement);
      const statusColor = computed.getPropertyValue(checked ? '--status-color-pending' : '--status-color-active').trim();
      if (status) {
        status.textContent = statusText;
        status.style.color = statusColor;
      }
    }

    updateLabel(toggle?.checked);

    toggle?.addEventListener('change', function () {
      if (!isAdmin) return;
      const newValue = this.checked ? '1' : '0';
      updateLabel(this.checked);

      fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'update_policy=1&toggle_policy=' + encodeURIComponent(newValue)
      })
      .then(r => r.ok ? r.json() : Promise.reject())
      .catch(() => alert('Failed to save policy.'));
    });
  }

  // ----- ASSIGN / USER EDIT MODAL -----
  function initAssignUserEdit() {
    const isAdmin = !!window._isAdmin;

    function q(sel, ctx) { return (ctx || document).querySelector(sel); }
    function qa(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

function formatToJakarta(iso) {
  if (!iso) return '—';
  try {
    const d = new Date(iso); // JS sudah bisa parse ISO dengan offset/Z
    return d.toLocaleString('id-ID', {
      timeZone: 'Asia/Jakarta',
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    }) + ' WIB';
  } catch (e) {
    console.warn('formatToJakarta parse failed', e, iso);
    return iso;
  }
}

// perbaikan toggleModal: set aria-hidden sebelum/selanjutnya manage focus
function toggleModal(show){
  const m = q('#user-edit-modal');
  if(!m) return;

  if (show) {
    // make visible to assistive tech first
    m.setAttribute('aria-hidden','false');
    m.style.display = 'block';
    // focus setelah diterapkan
    setTimeout(()=> {
      const el = q('#ue_display_name') || q('#ue_username') || q('#user-edit-close');
      if(el) el.focus();
    }, 120);
  } else {
    // before hide, remove focus from inside modal to avoid aria-hidden error
    try {
      const active = document.activeElement;
      if (m.contains(active) && typeof active.blur === 'function') active.blur();
    } catch(e){ /* ignore */ }
    m.setAttribute('aria-hidden','true');
    m.style.display = 'none';
    // return focus to a safe element (e.g. body or first .btn-edit-user)
    const firstEdit = document.querySelector('.btn-edit-user');
    if (firstEdit) firstEdit.focus();
    else document.body.focus();
  }
}

function fillForm(user){
  q('#ue_id').value = user.id || '';
  q('#ue_username').value = user.username || '';
  q('#ue_display_name').value = user.display_name || '';
  q('#ue_email').value = user.email || '';
  q('#ue_nomor_telpon').value = user.nomor_telpon || '';
  q('#ue_alamat_rumah').value = user.alamat_rumah || '';
  q('#ue_tanggal_lahir').value = user.tanggal_lahir || '';
  q('#ue_asal_sekolah').value = user.asal_sekolah || '';
  q('#ue_tahun_masuk').value = user.tahun_masuk || '';
  q('#ue_jurusan').value = user.jurusan || '';
  q('#ue_nisn').value = user.nisn || '';
  q('#ue_fotprof_url').value = user.foto_profil || '';
  updatePreview(user.foto_profil || '');

  // isi created_at / updated_at (view-only area)
// isi created_at / updated_at (view-only area)
try {
  const ca = user.created_at ? formatToJakarta(user.created_at) : '—';
  const ua = user.updated_at ? formatToJakarta(user.updated_at) : '—';
  const elCa = q('#ue_created_at'); const elUa = q('#ue_updated_at');
  if (elCa) elCa.textContent = ca;
  if (elUa) elUa.textContent = ua;
} catch (e) {
  console.warn('fillForm: created/updated parsing failed', e);
}
}

    function updatePreview(url) {
      const img = q('#ue_fotprof_preview_img');
      const pl = q('#ue_preview_placeholder');
      if (!img || !pl) return;
      if (!url) {
        img.style.display = 'none';
        pl.style.display = 'block';
        return;
      }
      img.src = url;
      img.style.display = 'block';
      pl.style.display = 'none';
    }

    function setReadonly(readonly) {
      const inputs = qa('#user-edit-form input, #user-edit-form textarea, #user-edit-form select');
      inputs.forEach(i => {
        if (i.name === 'username') { i.readOnly = true; i.disabled = false; return; }
        if (readonly) { i.setAttribute('readonly', 'readonly'); i.setAttribute('disabled', 'disabled'); }
        else { i.removeAttribute('readonly'); i.removeAttribute('disabled'); }
      });
      const saveBtn = q('#ue_save_btn'); if (saveBtn) saveBtn.style.display = readonly ? 'none' : 'inline-block';
      const uploadBtn = q('#ue_fotprof_upload_btn'); const fileInput = q('#ue_fotprof_file');
      if (uploadBtn) uploadBtn.disabled = readonly; if (fileInput) fileInput.disabled = readonly;
    }

    // upload handlers (reuse profile upload endpoint)
    const uploadEndpoint = '/dashboard/profile/upload_photo.php';
    function initUploadHandlers() {
      const fileInput = q('#ue_fotprof_file');
      const uploadBtn = q('#ue_fotprof_upload_btn');
      const statusEl = q('#ue_fotprof_status');
      const notifyEl = q('#ue_fotprof_notify');
      const urlInput = q('#ue_fotprof_url');

      function showNotify(text, kind='info') { if (!notifyEl) return; notifyEl.innerHTML = '<div class="fotprof-notify-'+kind+'">'+text+'</div>'; setTimeout(()=>{ if (notifyEl) notifyEl.innerHTML=''; }, 5000); }

      if (fileInput) {
        fileInput.addEventListener('change', function () { if (uploadBtn) uploadBtn.disabled = !fileInput.files || !fileInput.files.length; });
      }

      if (uploadBtn) {
        uploadBtn.addEventListener('click', function () {
          const f = fileInput && fileInput.files ? fileInput.files[0] : null;
          if (!f) { showNotify('Please select an image file first','error'); return; }
          const allowed = ['image/png','image/jpeg','image/webp'];
          if (!allowed.includes(f.type)) { showNotify('File format not allowed','error'); return; }
          if (f.size > 300 * 1024) { showNotify('File too large. Max 300 KB','error'); return; }

          const fd = new FormData(); fd.append('foto', f);
          if (statusEl) statusEl.textContent = 'Uploading...';
          uploadBtn.disabled = true;

          fetch(uploadEndpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.text())
            .then(text => {
              uploadBtn.disabled = false; if (statusEl) statusEl.textContent = '';
              let data;
              try { data = JSON.parse(text); } catch (e) { console.error('Upload non-JSON', text); showNotify('Server upload tidak mengembalikan JSON. Cek console.','error'); return; }
              if (data.success) { if (urlInput) urlInput.value = data.url; updatePreview(data.url); showNotify('Upload successful','success'); }
              else showNotify(data.message || 'Upload failed','error');
            })
            .catch(err => { uploadBtn.disabled = false; if (statusEl) statusEl.textContent = ''; console.error(err); showNotify('Upload failed: '+(err.message||'network error'),'error'); });
        });
      }

      if (q('#ue_fotprof_url')) q('#ue_fotprof_url').addEventListener('change', e => updatePreview(e.target.value.trim()));
    }

    // helper: resolve user from button (data-user JSON) or fetch by id
    async function resolveUserFromButton(btn) {
      const raw = btn.getAttribute('data-user');
      if (raw) {
        try { return JSON.parse(raw); } catch (e) { console.warn('data-user JSON parse failed', e); }
      }
      const uid = btn.getAttribute('data-user-id');
      if (uid) {
        const resp = await fetch('/dashboard/admin/assign/get_user.php?id=' + encodeURIComponent(uid), { credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'} });
        if (!resp.ok) {
          const txt = await resp.text();
          console.error('get_user.php non-200', resp.status, txt);
          throw new Error('Network error: ' + resp.status);
        }
        const data = await resp.json();
        if (!data || !data.success) { console.error('get_user.php returned error', data); throw new Error(data && data.message ? data.message : 'Failed to load user data'); }
        return data.user;
      }
      throw new Error('Edit button has no data-user or data-user-id attribute');
    }

    function init() {
      // attach handlers to buttons (supports both data-user & data-user-id)
      qa('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', async (ev) => {
          const msgEl = q('#ue_message'); if (msgEl) msgEl.textContent = 'Loading...';
          toggleModal(true);
          try {
            const user = await resolveUserFromButton(btn);
            fillForm(user || {});
            const readonly = !isAdmin;
            setReadonly(readonly);
            if (msgEl) msgEl.textContent = '';
          } catch (err) {
            console.error('Failed to load user data for modal', err);
            if (msgEl) msgEl.textContent = 'Failed to load user data: ' + (err.message || 'unknown');
            fillForm({});
            setReadonly(true);
          }
        });
      });

      const closeX = q('#user-edit-close'); const closeBtn = q('#ue_close_btn');
      if (closeX) closeX.addEventListener('click', () => toggleModal(false));
      if (closeBtn) closeBtn.addEventListener('click', () => toggleModal(false));

      initUploadHandlers();

      const saveBtn = q('#ue_save_btn');
      if (saveBtn) {
        saveBtn.addEventListener('click', async function () {
          const form = q('#user-edit-form');
          const fd = new FormData(form);
          const msgEl = q('#ue_message'); if (msgEl) msgEl.textContent = 'Saving...';
          try {
            const resp = await fetch('/dashboard/admin/assign/save_user.php', { method:'POST', body:fd, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'} });
            const data = await resp.json();
if (data.success) {
  if (msgEl) msgEl.textContent = 'Saved.';

  // data.user diharapkan dikembalikan dari server (see save_user.php)
  const serverUser = data.user || {};

  // update created/updated in modal (format ke GMT+7)
  const caEl = q('#ue_created_at');
  const uaEl = q('#ue_updated_at');
  if (caEl) caEl.textContent = formatToJakarta(serverUser.created_at);
  if (uaEl) uaEl.textContent = formatToJakarta(serverUser.updated_at);

  // update table row cells and data-user attribute
  const uid = fd.get('id');
  const row = document.querySelector('tr[data-user-id="'+uid+'"]');
  if (row) {
    const dn = fd.get('display_name') || '';
    const em = fd.get('email') || '';
    const tds = row.querySelectorAll('td');
    if (tds[1]) tds[1].textContent = dn;
    if (tds[2]) tds[2].textContent = em;

    const btnEdit = row.querySelector('.btn-edit-user');
    if (btnEdit) {
      // parse existing safely
      let userObj = {};
      const raw = btnEdit.getAttribute('data-user');
      if (raw) {
        try {
          const parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object') userObj = parsed;
        } catch (e) {
          userObj = {};
        }
      }

      // ensure id/username preserved
      if (!userObj.id) userObj.id = uid;
      if (!userObj.username) {
        const usernameTd = row.querySelector('td');
        if (usernameTd) userObj.username = usernameTd.textContent.trim();
      }

      // update fields with form values (FormData.get returns null if absent)
      const fields = ['display_name','email','nomor_telpon','alamat_rumah','tanggal_lahir','asal_sekolah','tahun_masuk','jurusan','nisn','foto_profil'];
      fields.forEach(k => {
        const v = fd.get(k);
        if (v !== null) userObj[k] = v || '';
      });

      // update timestamps from server (ISO)
      if (serverUser.created_at) userObj.created_at = serverUser.created_at;
      if (serverUser.updated_at) userObj.updated_at = serverUser.updated_at;

      try {
        btnEdit.setAttribute('data-user', JSON.stringify(userObj));
      } catch (e) {
        console.error('Failed to stringify updated userObj', e, userObj);
      }
    }
  }

  // keep modal open briefly then close (UX)
  setTimeout(() => toggleModal(false), 600);
}
 else {
              if (msgEl) msgEl.textContent = data.message || 'Failed to save.';
            }
          } catch (err) {
            console.error(err);
            if (q('#ue_message')) q('#ue_message').textContent = 'Network error.';
          }
        });
      }
    }

    // run init on DOM ready
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
  }

  // Initialize both modules on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initPolicyToggle(); initAssignUserEdit(); });
  } else {
    initPolicyToggle(); initAssignUserEdit();
  }

})();
