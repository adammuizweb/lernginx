// dashboard/partials/first-login.js
(function (global) {
  'use strict';

  function showNotify(notifyEl, text, type='info'){
    if (!notifyEl) return;
    notifyEl.innerHTML = '<span style="color:'+(type==='error'?'var(--danger)':'var(--muted)')+'">'+text+'</span>';
    setTimeout(()=>{ if (notifyEl) notifyEl.innerHTML=''; }, 4000);
  }

  function bindWithin(root){
    const overlay = root.getElementById('firstlog-overlay');
    if (!overlay) return null;

    const closeBtn = overlay.querySelector('.firstlog-close');
    const skipBtn = overlay.querySelector('.firstlog-skip');
    const submitBtn = overlay.querySelector('.firstlog-submit');
    const modalForm = overlay.querySelector('#firstlog-form');

    const fileInput = overlay.querySelector('#firstlog-fotprof-file');
    const uploadBtn = overlay.querySelector('#firstlog-fotprof-upload-btn');
    const statusEl = overlay.querySelector('#firstlog-fotprof-status');
    const notifyEl = overlay.querySelector('#firstlog-fotprof-notify');
    const previewImg = overlay.querySelector('#firstlog-fotprof-preview-img');
    const previewPlaceholder = overlay.querySelector('#firstlog-fotprof-preview-placeholder');
    const urlInput = overlay.querySelector('#firstlog-fotprof-url');

    const uploadEndpoint = (global.APP && global.APP.uploadEndpoint) ? global.APP.uploadEndpoint : '/dashboard/profile/upload_photo.php';
    const updateEndpoint = (global.APP && global.APP.updateProfileEndpoint) ? global.APP.updateProfileEndpoint : '/dashboard/profile/update_profile_ajax.php';

    // snapshot to detect dirty state (unsaved changes)
    var initialFormSnapshot = null;

    function serializeForm(form){
      var out = {};
      if (!form) return out;
      var els = form.querySelectorAll('input, textarea, select');
      for (var i=0;i<els.length;i++){
        var el = els[i];
        var name = el.name;
        if (!name) continue;
        if (el.type === 'file') {
          out[name] = (el.files && el.files.length) ? 'file:1' : 'file:0';
        } else if (el.type === 'checkbox' || el.type === 'radio') {
          // collect all checked values for that name
          if (!out.hasOwnProperty(name)) out[name] = [];
          if (el.checked) out[name].push(el.value || 'on');
        } else {
          out[name] = el.value || '';
        }
      }
      return out;
    }

    function isFormDirty(){
      if (!modalForm) return false;
      if (!initialFormSnapshot) return false;
      var cur = serializeForm(modalForm);
      var keys = new Set(Object.keys(initialFormSnapshot));
      Object.keys(cur).forEach(function(k){ keys.add(k); });
      for (var k of keys) {
        var a = initialFormSnapshot[k];
        var b = cur[k];
        var as = Array.isArray(a) ? a.join('|') : (a===undefined ? '' : String(a));
        var bs = Array.isArray(b) ? b.join('|') : (b===undefined ? '' : String(b));
        if (as !== bs) return true;
      }
      return false;
    }

    // --- small enhancement: enable upload button on file select + show local preview ---
    if (fileInput && uploadBtn && !fileInput._previewBound) {
      fileInput._previewBound = true;
      fileInput.addEventListener('change', function () {
        const f = fileInput.files && fileInput.files[0];
        uploadBtn.disabled = !f;
        if (f && previewImg) {
          try {
            const url = URL.createObjectURL(f);
            if (previewPlaceholder) previewPlaceholder.style.display = 'none';
            previewImg.src = url;
            previewImg.style.display = 'block';
            previewImg.onload = function(){ URL.revokeObjectURL(url); previewImg.onload = null; };
          } catch (e) { /* ignore preview errors */ }
        } else {
          if (previewPlaceholder) previewPlaceholder.style.display = 'block';
          if (previewImg) { previewImg.style.display = 'none'; previewImg.src = ''; }
        }
      });
    }

    // helper: determine if required fields missing in current modal form
    function hasMissingRequired() {
      if (!modalForm) return false;
      var req = ['display_name','nomor_telpon'];
      for (var i=0;i<req.length;i++){
        var el = modalForm.querySelector('[name="'+req[i]+'"]');
        if (!el || !(el.value || '').trim()) return true;
      }
      return false;
    }

    // yakqin popup creation / lookup (id prefix yakqin-)
    var yakRoot = document.getElementById('yakqin-popup');
    if (!yakRoot) {
      yakRoot = document.createElement('div');
      yakRoot.id = 'yakqin-popup';
      yakRoot.className = 'yakqin-overlay';
      yakRoot.style.display = 'none';
      yakRoot.innerHTML = '\
        <div class="yakqin-box" role="dialog" aria-modal="true" aria-labelledby="yakqin-title">\
          <p id="yakqin-title" class="yakqin-message">You have not completed your data. Are you sure you want to exit?</p>\
          <div class="yakqin-buttons">\
            <button id="yakqin-confirm" class="yakqin-btn yakqin-btn-yes" type="button">Yes, Exit</button>\
            <button id="yakqin-cancel"  class="yakqin-btn yakqin-btn-no"  type="button">Cancel</button>\
          </div>\
        </div>';
      document.body.appendChild(yakRoot);
    }
    var yakConfirm = yakRoot.querySelector('#yakqin-confirm');
    var yakCancel  = yakRoot.querySelector('#yakqin-cancel');

    function showYakqin() { yakRoot.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    function hideYakqin() { yakRoot.style.display = 'none'; document.body.style.overflow = ''; }

    // show/hide modal helpers
    function showFirstLog(){
      // snapshot when modal opens -> baseline for detecting unsaved changes
      initialFormSnapshot = serializeForm(modalForm);
      overlay.setAttribute('aria-hidden','false');
      overlay.style.display='block';
      document.body.style.overflow='hidden';
      setTimeout(()=> overlay.querySelector('[name="display_name"]')?.focus(), 250);
    }
    function hideFirstLog(){
      try {
        const active = document.activeElement;
        if (overlay.contains(active) && typeof active.blur === 'function') {
          active.blur();
        }
      } catch(e){ /* ignore */ }

      overlay.setAttribute('aria-hidden','true');
      overlay.style.display='none';
      document.body.style.overflow='';
      // clear snapshot when modal is fully closed
      initialFormSnapshot = null;
    }

    // upload binding
    if (uploadBtn && !uploadBtn._firstlogBound) {
      uploadBtn._firstlogBound = true;
      uploadBtn.addEventListener('click', function(){
        const file = fileInput.files && fileInput.files[0];
        if (!file){ showNotify(notifyEl, 'Please select an image file first.', 'error'); return; }
        const allowed = ['image/png','image/jpeg','image/webp'];
        if (!allowed.includes(file.type)){ showNotify(notifyEl, 'Format not allowed.', 'error'); return; }
        if (file.size > 300*1024){ showNotify(notifyEl, 'File too large (max 300KB).', 'error'); return; }

        const fd = new FormData();
        fd.append('foto', file);
        statusEl.textContent = 'Uploading...';
        uploadBtn.disabled = true;

        fetch(uploadEndpoint, { method:'POST', body: fd, credentials:'same-origin' })
          .then(async r => {
            uploadBtn.disabled = false; statusEl.textContent = '';
            const txt = await r.text();
            let data;
            try { data = JSON.parse(txt); } catch(e){ console.error('Upload not JSON', txt); showNotify(notifyEl, 'Server error (see console)','error'); return; }
            if (data.success) {
              if (previewPlaceholder) previewPlaceholder.style.display = 'none';
              if (previewImg){ previewImg.src = data.url; previewImg.style.display = 'block'; }
              if (urlInput) urlInput.value = data.url;
              document.querySelectorAll('input[name="foto_profil"]').forEach(i => i.value = data.url);
              showNotify(notifyEl, 'Upload successful.');
            } else {
              showNotify(notifyEl, data.message || 'Upload failed.', 'error');
            }
          }).catch(err => {
            uploadBtn.disabled = false; statusEl.textContent = '';
            showNotify(notifyEl, 'Upload failed: '+(err.message||'network'),'error');
          });
      });
    }

    if (urlInput && !urlInput._firstlogBound) {
      urlInput._firstlogBound = true;
      urlInput.addEventListener('change', function(){
        const v = (urlInput.value || '').trim();
        if (!v){ if (previewPlaceholder) previewPlaceholder.style.display='block'; if (previewImg){ previewImg.style.display='none'; previewImg.src=''; } return; }
        if (previewPlaceholder) previewPlaceholder.style.display='none';
        if (previewImg){ previewImg.src = v; previewImg.style.display='block'; }
        document.querySelectorAll('input[name="foto_profil"]').forEach(i => i.value = v);
      });
    }

    // Intercept skip/close/outside click to show yakqin when required missing OR there are unsaved changes
    if (skipBtn && !skipBtn._yakBound) {
      skipBtn._yakBound = true;
      skipBtn.addEventListener('click', function(ev){
        if (hasMissingRequired() || isFormDirty()) {
          ev && ev.preventDefault && ev.preventDefault();
          showYakqin();
        } else {
          // allow default flow (other listeners may hide)
        }
      }, false);
    }

    if (closeBtn && !closeBtn._yakBound) {
      closeBtn._yakBound = true;
      closeBtn.addEventListener('click', function(ev){
        if (hasMissingRequired() || isFormDirty()) {
          ev && ev.preventDefault && ev.preventDefault();
          showYakqin();
        } else {
          // allow default flow
        }
      }, false);
    }

    // clicking overlay background -> show confirmation if missing OR dirty
    if (!overlay._yakOutsideBound) {
      overlay._yakOutsideBound = true;
      overlay.addEventListener('click', function(e){
        if (e.target === overlay && (hasMissingRequired() || isFormDirty())) {
          e.preventDefault();
          showYakqin();
        }
      }, false);
    }

    // yakqin button behaviors
    if (yakCancel && !yakCancel._yakBound) {
      yakCancel._yakBound = true;
      yakCancel.addEventListener('click', function(){ hideYakqin(); }, false);
    }
    if (yakConfirm && !yakConfirm._yakBound) {
      yakConfirm._yakBound = true;
      yakConfirm.addEventListener('click', function(){
        hideYakqin();
        // close modal after confirm
        try { hideFirstLog(); } catch(e){
          overlay.setAttribute('aria-hidden','true'); overlay.style.display='none'; document.body.style.overflow='';
        }
      }, false);
    }

    // Close handlers (existing behavior) - keep after yak bindings
    // IMPORTANT: only close the main modal if required fields are NOT missing AND no unsaved changes.
    if (closeBtn && !closeBtn._firstlogBound) {
      closeBtn._firstlogBound = true;
      closeBtn.addEventListener('click', function(ev){
        if (hasMissingRequired() || isFormDirty()) {
          // do NOT hide the main modal here — yakqin handles confirmation
          ev && ev.preventDefault && ev.preventDefault();
          return;
        }
        hideFirstLog();
      });
    }
    if (skipBtn && !skipBtn._firstlogBound) {
      skipBtn._firstlogBound = true;
      skipBtn.addEventListener('click', function(ev){
        if (hasMissingRequired() || isFormDirty()) {
          ev && ev.preventDefault && ev.preventDefault();
          return;
        }
        hideFirstLog();
      });
    }

    // click outside modal closes (but we already intercept earlier for yakqin)
    if (!overlay._firstlogBound) {
      overlay._firstlogBound = true;
      overlay.addEventListener('click', function(e){
        if (e.target === overlay) {
          if (hasMissingRequired() || isFormDirty()) {
            // yakqin handler already manages this — do not close main modal here
            return;
          }
          hideFirstLog();
        }
      });
    }

    // ESC key closes (or shows confirmation when missing required or dirty)
    if (!document._firstlogEscBound) {
      document._firstlogEscBound = true;
      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' || e.key === 'Esc') {
          if (overlay.getAttribute('aria-hidden') === 'false') {
            if (hasMissingRequired() || isFormDirty()) {
              showYakqin();
            } else {
              hideFirstLog();
            }
          }
        }
      });
    }

    // submit (AJAX)
    if (submitBtn && !submitBtn._firstlogBound) {
      submitBtn._firstlogBound = true;
      submitBtn.addEventListener('click', function(){
        const fd = new FormData(modalForm);
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        if (global.APP && global.APP.csrfToken) fd.append('csrf_token', global.APP.csrfToken);

        fetch(updateEndpoint, { method:'POST', body: fd, credentials:'same-origin' })
          .then(async r => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save & Continue';
            const txt = await r.text();
            let data;
            try { data = JSON.parse(txt); } catch(e){
              console.error('Update not JSON', txt);
              showNotify(notifyEl, 'Server error (see console)','error');
              return;
            }
            if (data.success) {
              try { window.dispatchEvent(new CustomEvent('firstlog:updated',{detail:data.user})); } catch(e){}
              hideFirstLog();
              window.location.href = '/dashboard/';
            } else {
              showNotify(notifyEl, data.message || 'Failed to save.', 'error');
            }
          }).catch(err => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save & Continue';
            showNotify(notifyEl, 'Failed to save: '+(err.message||'network'),'error');
          });
      });
    }

    return { overlay, showFirstLog, hideFirstLog };
  } // bindWithin

  function init() {
    const excludedPaths = [
      '/dashboard/student/',
      '/dashboard/profile/',
      '/dashboard/settings/',
      '/dashboard/help/'
    ];

    const currentPath = window.location.pathname;

    // Check if current path matches any exclusion
    if (excludedPaths.some(path => currentPath.startsWith(path))) return;

    const bound = bindWithin(document);
    if (!bound) return;

    const needs = (global.APP && global.APP.needsFirstLogin) ? true : false;
    if (needs) {
      setTimeout(() => bound.showFirstLog(), 300);
    }
  }

  // auto-init on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // expose for manual init or tests
  global.FirstLogin = global.FirstLogin || {};
  global.FirstLogin.init = init;
})(window);
