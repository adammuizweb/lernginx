(function () {
  'use strict';

  // ---- helpers ----
  function q(sel, ctx) { return (ctx || document).querySelector(sel); }
  function qa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function escapeHtml(s){
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function(m){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
    });
  }
  function statusText(n){
    return n === 0 ? '✅ Active' : (n === 1 ? '⏳ Pending' : (n === 2 ? '❌ Inactive' : ('Status:' + n)));
  }

  var API = 'index.php'; // gunakan index.php relatif (page berada di /dashboard/assign/index.php)
  var userSelect = q('#user_id');
  var assignArea = q('#assign-area');

  if (!userSelect || !assignArea) return;

  userSelect.addEventListener('change', function () {
    var uid = this.value;
    if (!uid) {
      assignArea.innerHTML = '<em>Select a student to view and manage their module status.</em>';
      return;
    }
    loadUserModules(uid);
  });

  if (userSelect.value) loadUserModules(userSelect.value);

  function loadUserModules(userId) {
    assignArea.innerHTML = '<em>Loading module status…</em>';
    fetch(API + '?ajax=get_user_modules&user_id=' + encodeURIComponent(userId), { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('Network'); return r.json(); })
      .then(function (rows) {
        renderAssignArea(userId, rows || []);
      })
      .catch(function (err) {
        console.error(err);
        assignArea.innerHTML = '<div class="alert">Failed to load module data.</div>';
      });
  }

  function renderAssignArea(userId, registeredModules) {
    var regByCat = {};
    var regByModuleId = {};
    registeredModules.forEach(function (r) {
      regByCat[String(r.category_id)] = r;
      regByModuleId[String(r.module_id)] = r;
    });
    window._regByModuleId = regByModuleId;

    var allCategories = Array.isArray(window._availableCategories) ? window._availableCategories : [];

    var html = '';

    // Registered modules
    html += '<div class="registered-modules">';
    html += '<h3>Modul Terdaftar</h3>';
    if (!registeredModules.length) {
      html += '<div>No modules registered for this student.</div>';
    } else {
      html += '<table class="module-status-table" style="width:100%;border-collapse:collapse;">';
      html += '<thead><tr><th style="text-align:left">Modul</th><th style="width:160px">Status</th><th style="width:160px">Aksi</th></tr></thead><tbody>';
      registeredModules.forEach(function (m) {
        var mid = String(m.module_id);
        var reviewedHtml = (parseInt(m.is_reviewed,10) === 1) ? ' <span style="font-size:0.85em;color:#888;">(direview)</span>' : '';
        html += '<tr data-module-id="' + mid + '">';
        html += '<td>' + escapeHtml(m.name) + '</td>';
        html += '<td class="status-cell">' + escapeHtml(statusText(Number(m.status))) + reviewedHtml + '</td>';
        html += '<td>';
        html += '<select class="module-status-select" data-module-id="' + mid + '">';
        html += '<option value="0"' + (Number(m.status) === 0 ? ' selected' : '') + '>Active</option>';
        html += '<option value="1"' + (Number(m.status) === 1 ? ' selected' : '') + '>Pending</option>';
        html += '<option value="2"' + (Number(m.status) === 2 ? ' selected' : '') + '>Inactive</option>';
        html += '</select> ';
        html += '<button type="button" class="module-status-save" data-module-id="' + mid + '">Save</button>';
        html += '</td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
    }
    html += '</div>';

    // Build add-new-modules form using injected window._availableCategories
    if (allCategories.length) {
      var toAdd = allCategories.filter(function (c) { return !regByCat[String(c.id)]; });
      if (toAdd.length) {
        html += '<form id="add-modules-form" method="POST" action="index.php" style="margin-top:12px;">';
        html += '<input type="hidden" name="user_id" value="' + encodeURIComponent(userId) + '">';
        html += '<h3>Add New Module</h3>';
        toAdd.forEach(function (c) {
          html += '<label style="display:block;"><input type="checkbox" name="modules[]" value="' + encodeURIComponent(c.id) + '"> ' + escapeHtml(c.name) + '</label>';
        });
        html += '<div style="margin-top:6px;"><label>Status for new registration <select name="status"><option value="0">Active</option><option value="1">Pending</option></select></label></div>';
        html += '<div style="margin-top:8px;"><button type="submit">Register Module</button></div>';
        html += '</form>';
      } else {
        html += '<div style="margin-top:12px;"><em>Tidak ada modul baru untuk ditambahkan.</em></div>';
      }
    } else {
      html += '<div style="margin-top:12px;"><em>Module list not loaded. Use fallback form if needed.</em></div>';
    }

    assignArea.innerHTML = html;

    // Attach event listeners for saving status
    qa('.module-status-save', assignArea).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var moduleId = this.getAttribute('data-module-id');
        var sel = q('.module-status-select[data-module-id="' + moduleId + '"]', assignArea);
        if (!sel) return;
        var newStatus = sel.value;
        btn.disabled = true;

        var categoryId = (regByModuleId[moduleId] && regByModuleId[moduleId].category_id) ? regByModuleId[moduleId].category_id : null;
        // If we don't have categoryId, cannot proceed
        if (!categoryId) {
          alert('Module category not found. Reload the page and try again.');
          btn.disabled = false;
          return;
        }

        var payload = 'ajax_action=update_module_status'
                    + '&status=' + encodeURIComponent(newStatus)
                    + '&user_id=' + encodeURIComponent(userId)
                    + '&category_id=' + encodeURIComponent(categoryId);

        fetch(API, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: payload
        })
        .then(function (r) {
          if (!r.ok) throw new Error('Network');
          return r.json();
        })
        .then(function (j) {
          if (j && j.ok) {
            var row = q('tr[data-module-id="' + moduleId + '"]', assignArea);
            if (row) {
              // update cell with proper reviewed markup preserved if server set it
              var reviewedHtml = (regByModuleId[moduleId] && parseInt(regByModuleId[moduleId].is_reviewed,10) === 1) ? ' <span style="font-size:0.85em;color:#888;">(direview)</span>' : '';
              q('.status-cell', row).innerHTML = escapeHtml(statusText(Number(newStatus))) + reviewedHtml;
            }
          } else {
            alert('Failed to save status: ' + (j && j.error ? j.error : 'server error'));
          }
        })
        .catch(function (err) {
          console.error(err);
          alert('Request error.');
        })
        .finally(function () {
          btn.disabled = false;
        });
      });
    });

    // no client-side JS for add form; it posts to index.php and server handles
  }

})();
