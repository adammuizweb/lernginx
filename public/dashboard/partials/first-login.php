<?php
// dashboard/partials/first-login.php
// Entry lightweight: include fragment yang berisi markup + fallback form.
// Requirements: $pdo and get_user_from_session() available from bootstrap included in layout.
if (!function_exists('get_user_from_session')) return;
$user = get_user_from_session($pdo);
if (!$user) return;

// Determine if first-login is needed (server-side)
// Consider first-login if display_name is empty OR nomor_telpon is empty
$needs_firstlogin = (trim($user['display_name'] ?? '') === '' || trim($user['nomor_telpon'] ?? '') === '');
$dashboardBase = $dashboardBase ?? '/dashboard'; // jika layout sudah set, pakai itu

// include modal markup & fallback form (fragment has markup + hidden form)
$fragmentPath = __DIR__ . '/firstlog/fragment.php';
if (file_exists($fragmentPath)) {
    require_once $fragmentPath;
} else {
    // If fragment missing, do nothing here; caller asked not to assume unknown files.
    // You can provide fragment.php jika ingin saya update juga.
}

// inject lightweight config for client-side
?>
<script>
window.APP = window.APP || {};
window.APP.needsFirstLogin = <?= json_encode($needs_firstlogin ? true : false) ?>;
window.APP.uploadEndpoint = <?= json_encode($dashboardBase . '/profile/upload_photo.php') ?>;
window.APP.updateProfileEndpoint = <?= json_encode($dashboardBase . '/profile/update_profile_ajax.php') ?>;
<?php if (!empty($_SESSION['csrf_token'])): ?>
window.APP.csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
<?php endif; ?>
</script>

<!-- load JS behavior (defer so it doesn't block) -->
<script src="/dashboard/partials/first-login.js" defer></script>

<!-- Augmentation script:
     - enforce rule: if user filled any other field but display_name / nomor_telpon empty,
       then block Save & show warning (class warprof-warning, id prefix warprof-).
     - when user clicks "Nanti saja" or close and display_name/nomor_telpon empty,
       show a non-blocking warning with id warprof-<uniq> and allow closing.
-->
<script>
(function(){
  'use strict';

  // helper: create unique id with prefix warprof-
  function makeWarprofId(){
    return 'warprof-' + Date.now() + '-' + Math.floor(Math.random()*1000);
  }

  function createWarprofWarning(container, message, ttlMs) {
    ttlMs = typeof ttlMs === 'number' ? ttlMs : 4000;
    var id = makeWarprofId();
    var el = document.createElement('div');
    el.id = id;
    el.className = 'warprof-warning';
    // minimal inline styling so it's visible even if CSS missing; user can override with .warprof-warning
    el.style.cssText = 'padding:10px;border-radius:6px;background:#fff3cd;border:1px solid #ffeeba;color:#856404;margin-bottom:12px;box-shadow:0 2px 6px rgba(0,0,0,0.06);';
    el.setAttribute('role','alert');
    el.innerText = message || 'Profile incomplete — please fill in Full Name and Phone Number.';
    // insert at top of modal (if container found), otherwise body
    if (container && container.insertBefore) {
      container.insertBefore(el, container.firstChild);
    } else {
      document.body.appendChild(el);
    }
    // auto-remove after ttlMs
    setTimeout(function(){
      try { el.parentNode && el.parentNode.removeChild(el); } catch(e){}
    }, ttlMs);
    return el;
  }

  function anyOtherFieldFilled(form, requiredNames) {
    if (!form) return false;
    var els = form.querySelectorAll('input, textarea, select');
    for (var i=0;i<els.length;i++){
      var el = els[i];
      var name = el.name || '';
      if (!name) continue;
      // skip required names, email, foto_profil hidden etc.
      if (requiredNames.indexOf(name) !== -1) continue;
      if (name === 'email' || name === 'password') continue;
      // skip hidden inputs used as fallback
      if (el.type === 'hidden') continue;
      // file input: check if has file
      if (el.type === 'file') {
        if (el.files && el.files.length > 0) return true;
        continue;
      }
      var v = (el.value || '').trim();
      if (v !== '') return true;
    }
    return false;
  }

  function missingRequired(form, requiredNames) {
    var missing = [];
    if (!form) return missing;
    requiredNames.forEach(function(n){
      var el = form.querySelector('[name="'+n+'"]');
      var val = el ? (el.value || '').trim() : '';
      if (!val) missing.push(n);
    });
    return missing;
  }

  // attach after DOM ready
  function initAugment() {
    var overlay = document.getElementById('firstlog-overlay');
    if (!overlay) return;
    var form = overlay.querySelector('#firstlog-form');
    if (!form) return;

    var submitBtn = overlay.querySelector('.firstlog-submit');
    var skipBtn = overlay.querySelector('.firstlog-skip');
    var closeBtn = overlay.querySelector('.firstlog-close');

    var requiredNames = ['display_name','nomor_telpon'];

    // intercept submit click: if any other field filled AND required missing => block + show warprof warning
    if (submitBtn && !submitBtn._warprofBound) {
      submitBtn._warprofBound = true;
      submitBtn.addEventListener('click', function(evt){
        try {
          var otherFilled = anyOtherFieldFilled(form, requiredNames);
          var miss = missingRequired(form, requiredNames);
          if (otherFilled && miss.length > 0) {
            // prevent normal save; show warning and focus first missing field
            evt && evt.preventDefault && evt.preventDefault();
            createWarprofWarning(
              overlay.querySelector('.firstlog-modal') || overlay,
              'You filled in some other information, but Full Name and Phone Number are still empty. Please fill in Name and Phone Number first.',
              5000
            );
            // focus first missing
            var firstMissing = form.querySelector('[name="'+miss[0]+'"]');
            if (firstMissing && typeof firstMissing.focus === 'function') {
              firstMissing.focus();
            }
            return false;
          }
          // else allow submit — existing first-login.js will handle AJAX submit
        } catch(e){
          console.error('warprof submit check error', e);
        }
      }, false);
    }

    // intercept skip/close: if required missing -> show non-blocking warning (with warprof- prefix) then allow close
    var skipHandler = function(evt){
      try {
        var miss = missingRequired(form, requiredNames);
        if (miss.length > 0) {
          // show warning but do not block the close
          createWarprofWarning(
            overlay.querySelector('.firstlog-modal') || overlay,
             'Profile incomplete. You can close now and fill it later in Profile. (Recommended: fill Name & Phone Number.)',
            4500
          );
          // allow existing close logic (first-login.js) to run afterwards
        }
      } catch(e){
        console.error('warprof skip handler error', e);
      }
    };

    if (skipBtn && !skipBtn._warprofBound) {
      skipBtn._warprofBound = true;
      skipBtn.addEventListener('click', skipHandler, false);
    }
    if (closeBtn && !closeBtn._warprofBound) {
      closeBtn._warprofBound = true;
      closeBtn.addEventListener('click', skipHandler, false);
    }

    // for safety: if user clicks outside overlay to close (overlay click), also show warning if missing required
    if (!overlay._warprofBound) {
      overlay._warprofBound = true;
      overlay.addEventListener('click', function(e){
        if (e.target === overlay) {
          try {
            var miss = missingRequired(form, requiredNames);
            if (miss.length > 0) {
              createWarprofWarning(
                overlay.querySelector('.firstlog-modal') || overlay,
                'Profile incomplete. You can close now and fill it later in Profile. (Name & Phone Number empty.)',
                4500
              );
            }
          } catch(e){}
        }
      }, false);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAugment);
  } else {
    initAugment();
  }
})();
</script>

<!-- Fields and behavior are managed in firstlog/fragment.php -->
