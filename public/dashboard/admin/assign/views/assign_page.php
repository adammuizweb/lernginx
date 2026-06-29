<?php if ($message): ?>
  <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" action="index.php" style="margin-bottom:20px;">
  <div class="policy-toggle-wrapper">
    <label class="policy-toggle-switch">
      <input type="checkbox" id="policy-toggle-input" <?= $currentPolicy == '1' ? 'checked' : '' ?> <?= $user['role'] !== 'admin' ? 'disabled' : '' ?>>
      <span class="policy-toggle-slider"></span>
    </label>
    <span class="policy-toggle-label">
      Mode Pendaftaran: <strong id="policy-toggle-status"><?= $currentPolicy == '1' ? 'Pending Diaktifkan' : 'Tanpa Moderasi' ?></strong>
    </span>
  </div>
  <div class="policy-toggle-meta" style="font-size: 13px; color: var(--muted); margin-top: 4px;">
    Terakhir diubah oleh: <strong><?= htmlspecialchars($policyRow['updated_by'] ?? '—') ?></strong>
    pada <em><?= $policyRow['updated_at'] ? date('d M Y H:i', strtotime($policyRow['updated_at'])) : '—' ?></em>
  </div>
</form>

<h2>🧑‍🏫 Assign Module to Student</h2>

<form method="get" action="<?= htmlspecialchars($_SERVER['SCRIPT_NAME']) ?>" class="casisw-filter-form">
  <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search username, name, or email" style="min-width:220px;" />
  
  <label for="category">Category:</label>
  <select name="category" id="category">
    <option value="0">All Categories</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= ($filter_category === (int)$c['id']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="status">Module Status:</label>
  <select name="status" id="status">
    <option value="">All Status</option>
    <option value="0" <?= ($filter_status === 0) ? 'selected' : '' ?>>✅ Active</option>
    <option value="1" <?= ($filter_status === 1) ? 'selected' : '' ?>>⏳ Pending</option>
    <option value="2" <?= ($filter_status === 2) ? 'selected' : '' ?>>❌ Inactive</option>
  </select>

  <label for="per_page">Tampilkan:</label>
  <select name="per_page" id="per_page">
    <?php foreach ($allowedPerPage as $pp): ?>
      <option value="<?= $pp ?>" <?= ($perPage == $pp) ? 'selected' : '' ?>><?= $pp ?> siswa</option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Terapkan</button>
</form>

<?php if (empty($students)): ?>
  <p>Tidak ada siswa yang sesuai.</p>
<?php else: ?>

<table id="students-table" style="width:100%; border-collapse:collapse; margin-top:8px;">
  <thead>
    <tr>
      <th style="text-align:left; padding:8px; border-bottom:1px solid var(--table-border);">Username</th>
      <th style="text-align:left; padding:8px; border-bottom:1px solid var(--table-border);">Display Name</th>
      <th style="text-align:left; padding:8px; border-bottom:1px solid var(--table-border);">Email</th>
      <th style="text-align:left; padding:8px; border-bottom:1px solid var(--table-border);">Program</th>
      <th style="text-align:right; padding:8px; border-bottom:1px solid var(--table-border);">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($students as $s):
        $uid = (int)$s['id'];
        $mods = $modulesMap[$uid] ?? [];
    ?>
    <tr data-user-id="<?= $uid ?>">
      <td style="padding:8px; border-bottom:1px solid var(--table-border);"><?= htmlspecialchars($s['username']) ?></td>
      <td style="padding:8px; border-bottom:1px solid var(--table-border);"><?= htmlspecialchars($s['display_name'] ?? '') ?></td>
      <td style="padding:8px; border-bottom:1px solid var(--table-border);"><?= htmlspecialchars($s['email'] ?? '') ?></td>
      <td style="padding:8px; border-bottom:1px solid var(--table-border);">
        <?php if (empty($mods)): ?>
          <small style="color:var(--text-muted);">—</small>
        <?php else: ?>
          <?php foreach ($mods as $m):
              $label = ((int)$m['status'] === 1) ? 'Pending' : (((int)$m['status'] === 2) ? 'Inactive' : 'Active');
              $color = ((int)$m['status'] === 1) ? 'var(--status-color-pending)' : (((int)$m['status'] === 2) ? 'var(--danger)' : 'var(--status-color-active)');
          ?>
            <div style="display:inline-block; margin-right:6px; padding:6px 10px; border-radius:6px; background:var(--badge-bg); border:1px solid var(--badge-border);">
              <div style="font-weight:600;"><?= htmlspecialchars($m['category_name']) ?></div>
              <div style="font-size:11px; color:<?= $color ?>;"><?= $label ?><?= ((int)$m['is_reviewed'] === 1 ? ' • reviewed' : '') ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </td>
<td style="padding:8px; border-bottom:1px solid var(--table-border); text-align:right;">
  <div style="display:flex; gap:6px; justify-content:flex-end;">
    <button class="btn-open-modules" data-user-id="<?= $uid ?>">Modul</button>
    <button class="btn-edit-user btn-open-modules-yellow" type="button" data-user-id="<?= $uid ?>">Edit</button>
  </div>
</td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($totalPages > 1): ?>
<nav class="pagination" aria-label="Pagination" style="margin-top:12px;">
  <?php if ($page > 1): ?>
    <a href="<?= page_url($page-1, $q, $filter_category, $filter_status, $perPage) ?>">‹ Prev</a>
  <?php endif; ?>

  <?php
    $start = max(1, $page - 3);
    $end = min($totalPages, $page + 3);
    for ($p = $start; $p <= $end; $p++):
  ?>
    <?php if ($p === $page): ?>
      <strong><?= $p ?></strong>
    <?php else: ?>
      <a href="<?= page_url($p, $q, $filter_category, $filter_status, $perPage) ?>"><?= $p ?></a>
    <?php endif; ?>
  <?php endfor; ?>

  <?php if ($page < $totalPages): ?>
    <a href="<?= page_url($page+1, $q, $filter_category, $filter_status, $perPage) ?>">Next ›</a>
  <?php endif; ?>

  <span style="margin-left:12px;color:var(--text-muted);">Hal <?= $page ?> dari <?= $totalPages ?> (<?= number_format($total) ?> siswa)</span>
</nav>
<?php endif; ?>

<?php endif; ?>

<div id="assign-area" style="margin-top:12px; width: auto;">
  <em>Select a student untuk melihat dan mengelola status modulnya. Area ini akan memuat status modul terdaftar dan form pendaftaran modul baru.</em>
</div>

<div id="student-modules-modal" class="modal-overlay">
  <div class="modal-content">
    <button id="modal-close" class="modal-close-btn">×</button>
    <h3 id="modal-title">Student Modules</h3>
    <div id="modal-user-info" class="modal-user-info"></div>

    <form id="modal-assign-form">
      <input type="hidden" name="user_id" id="modal_user_id" value="">
      <div id="modal-categories-list" class="modal-categories-list"></div>
      <div class="modal-actions">
        <button type="button" id="modal-save-btn">Save</button>
        <button type="button" id="modal-cancel-btn" style="background:#c00;color:#fff;">Cancel</button>
      </div>
    </form>
    <div id="modal-message" class="modal-message"></div>
  </div>
</div>

<!-- USER EDIT / VIEW MODAL -->
<div id="user-edit-modal" class="modal-overlay" style="display:none; position: fixed; justify-items: center; align-content: center;" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="user-edit-title" tabindex="-1">
    <button id="user-edit-close" class="modal-close-btn" aria-label="Close">×</button>


<div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;">
  <h3 id="user-edit-title">User Profile</h3>
  <div class="user-meta" style="text-align:right; font-size:12px; color:var(--text-muted);">
    <div>Created: <span id="ue_created_at">—</span></div>
    <div>Updated: <span id="ue_updated_at">—</span></div>
  </div>
</div>



    <form id="user-edit-form" method="POST" onsubmit="return false;">
      <input type="hidden" name="id" id="ue_id" value="">

      <div class="form-row">
        <label>Username</label>
        <input type="text" name="username" id="ue_username" readonly>
      </div>

      <div class="form-row">
        <label>Full Name</label>
        <input type="text" name="display_name" id="ue_display_name">
      </div>

      <div class="form-row">
        <label>Email</label>
        <input type="email" name="email" id="ue_email">
      </div>

      <div class="form-row">
        <label>Phone Number</label>
        <input type="text" name="nomor_telpon" id="ue_nomor_telpon">
      </div>

      <div class="form-row">
        <label>Home Address</label>
        <textarea name="alamat_rumah" id="ue_alamat_rumah" rows="3"></textarea>
      </div>

      <div class="form-row">
        <label>Date of Birth</label>
        <input type="date" name="tanggal_lahir" id="ue_tanggal_lahir">
      </div>

      <div class="form-row">
        <label>School</label>
        <input type="text" name="asal_sekolah" id="ue_asal_sekolah">
      </div>

      <div class="form-row">
        <label>Enrollment Year</label>
        <select name="tahun_masuk" id="ue_tahun_masuk">
          <option value="">-- Select Year --</option>
          <?php $y = date('Y'); for ($t=2020; $t<=$y; $t++): ?>
            <option value="<?= $t ?>"><?= $t ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="form-row">
        <label>Major</label>
        <input type="text" name="jurusan" id="ue_jurusan">
      </div>

      <div class="form-row">
        <label>NISN</label>
        <input type="text" name="nisn" id="ue_nisn">
      </div>

      <!-- Foto profil: reuse mekanik upload (URL + file input + upload button) -->
      <div class="form-row formprof-fotowrap" style="display:flex;gap:12px;align-items:flex-start;">
        <div class="formprof-preview" style="min-width:120px;">
          <div id="ue_preview_wrapper">
            <img id="ue_fotprof_preview_img" src="" alt="Foto" style="max-width:120px;display:none;border-radius:6px;">
            <div id="ue_preview_placeholder" style="display:none;">
              <svg width="80" height="80" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="4" fill="#f3f3f3"/><path d="M12 12a3 3 0 100-6 3 3 0 000 6z" fill="#d0d0d0"/></svg>
              <div style="font-size:12px;color:var(--text-muted);">No photo yet</div>
            </div>
          </div>
        </div>

        <div style="flex:1;">
          <label for="ue_fotprof_url">Photo URL Input</label>
          <input id="ue_fotprof_url" type="text" name="foto_profil" placeholder="URL foto profil...">

          <div style="display:flex;gap:8px;align-items:center;margin-top:6px;">
            <input id="ue_fotprof_file" type="file" accept=".png,.jpg,.jpeg,.webp" style="display:none;">
            <label for="ue_fotprof_file" class="custom-upload-btn" style="cursor:pointer;">Choose Photo</label>
            <button type="button" id="ue_fotprof_upload_btn" class="formprof-upload-btn" disabled>Upload Photo</button>
            <div id="ue_fotprof_status" class="formprof-status" style="font-size:13px;"></div>
          </div>

          <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">Persyaratan: png, jpg, jpeg, webp — maksimal 300 KB.</div>
          <div id="ue_fotprof_notify" class="fotprof-notify" style="margin-top:6px;"></div>
        </div>
      </div>

      <div class="modal-actions" style="margin-top:10px;">
        <button type="button" id="ue_save_btn" style="display:none;">Save</button>
        <button type="button" id="ue_close_btn" style="background:#c00;color:#fff;">Close</button>
      </div>

      <div id="ue_message" class="modal-message" style="margin-top:8px;"></div>
    </form>
  </div>
</div>

<script>
  window._isAdmin = <?= json_encode($user['role'] === 'admin') ?>; // sudah ada di filemu; biar aman
</script>


<script>
  window._availableCategories = <?= json_encode($categories) ?>;
  window._isAdmin = <?= json_encode($user['role'] === 'admin') ?>;
</script>

<script src="<?= BASE_URL ?>/assets/dashboard/assign_modal.js" defer></script>
<script src="<?= BASE_URL ?>/assets/dashboard/modul.js" defer></script>
<script src="<?= BASE_URL ?>/dashboard/admin/assign/assign.js" defer></script>