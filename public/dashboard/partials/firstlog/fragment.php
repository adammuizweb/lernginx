<?php
// dashboard/partials/firstlog/fragment.php
if (!defined('DASHBOARD_CONTEXT')) {
    // safety: this file intended to be included only from dashboard context
    return;
}
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES); }
$dashboardBase = $dashboardBase ?? '/dashboard';
?>
<!-- FIRST-LOGIN FRAGMENT (markup only) -->
<!-- NOTE: CSS is separate (add to dashboard.css) -->
<div id="firstlog-overlay" class="firstlog-overlay" aria-hidden="true">
  <div class="firstlog-backdrop" aria-hidden="true"></div>

  <div class="firstlog-modal" role="dialog" aria-modal="true" aria-labelledby="firstlog-title" tabindex="-1">
    <button class="firstlog-close" type="button" aria-label="Close">&times;</button>

    <!-- HEADER -->
    <header class="firstlog-header">
      <div class="firstlog-svg" aria-hidden="true">
        <svg width="48" height="48" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="var(--accent)"/><stop offset="1" stop-color="var(--accent-2)"/></linearGradient></defs>
          <circle cx="60" cy="60" r="44" fill="url(#g)" opacity="0.12"/>
          <circle cx="60" cy="60" r="30" fill="none" stroke="url(#g)" stroke-width="4"></circle>
        </svg>
      </div>
      <div class="firstlog-headtext">
        <h3 id="firstlog-title">Complete Your Profile</h3>
        <p class="firstlog-sub">Fill in a few details to complete your profile.</p>
      </div>
    </header>

    <!-- FORM -->
    <form id="firstlog-form" class="firstlog-form" onsubmit="return false;">
      <input type="hidden" name="email" value="<?= esc($user['email'] ?? '') ?>">
<div class="firstlog-grid">
      <div class="firstlog-row">
        <label class="firstlog-label">Full Name</label>
        <input type="text" name="display_name" class="firstlog-input" value="<?= esc($user['display_name'] ?? '') ?>" autocomplete="name" placeholder="Example: johndoe" required>
      </div>
            <div class="firstlog-row">
        <label class="firstlog-label">Phone Number (for password reset via WhatsApp)</label>
        <input type="text" name="nomor_telpon" class="firstlog-input" value="<?= esc($user['nomor_telpon'] ?? '') ?>" placeholder="Example: 6281234567890" required>
      </div>
</div>
      <div id="firstlog-fotprof-notify" class="firstlog-note" aria-live="polite"></div>
      <div class="firstlog-row firstlog-photo-row">
        <label class="firstlog-label">Profile Photo</label>
        <div class="firstlog-photo-controls">
          <div class="firstlog-photo-preview">
            <?php if (!empty($user['foto_profil'])): ?>
              <img id="firstlog-fotprof-preview-img" src="<?= esc($user['foto_profil']) ?>" alt="Preview Photo">
            <?php else: ?>
              <div id="firstlog-fotprof-preview-placeholder" class="firstlog-photo-placeholder" aria-hidden="true">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="24" rx="4" fill="#f3f3f3"/><path d="M12 12a3 3 0 100-6 3 3 0 000 6z" fill="#d0d0d0"/></svg>
              </div>
              <img id="firstlog-fotprof-preview-img" src="" alt="Preview Photo" style="display:none;">
            <?php endif; ?>
          </div>

          <div class="firstlog-photo-actions">
            <input id="firstlog-fotprof-url" name="foto_profil" type="text" class="firstlog-input" value="<?= esc($user['foto_profil'] ?? '') ?>" placeholder="Profile photo URL (auto-filled after upload)">
            <div class="firstlog-file-row">
              <input id="firstlog-fotprof-file" type="file" accept=".png,.jpg,.jpeg,.webp" style="display: none;">
              <label for="firstlog-fotprof-file" class="custom-upload-btn">Choose Photo</label>
              <button type="button" id="firstlog-fotprof-upload-btn" class="formprof-upload-btn" disabled>Upload Photo</button>
              <div id="firstlog-fotprof-status" class="firstlog-note">≤ 300 KB</div>
            </div>
          </div>
        </div>
      </div>

      <div class="firstlog-row">
        <label class="firstlog-label">Home Address</label>
        <textarea name="alamat_rumah" class="firstlog-input" rows="3"><?= esc($user['alamat_rumah'] ?? '') ?></textarea>
      </div>

      <div class="firstlog-grid">
        <div class="firstlog-row">
          <label class="firstlog-label">Date of Birth</label>
          <input type="date" name="tanggal_lahir" class="firstlog-input" value="<?= esc($user['tanggal_lahir'] ?? '') ?>">
        </div>

        <div class="firstlog-row">
          <label class="firstlog-label">School</label>
          <input type="text" name="asal_sekolah" class="firstlog-input" value="<?= esc($user['asal_sekolah'] ?? '') ?>">
        </div>
      </div>

      <div class="firstlog-grid">
        <div class="firstlog-row">
          <label class="firstlog-label">Enrollment Year</label>
          <select name="tahun_masuk" class="firstlog-input">
            <option value="">-- Select Year --</option>
            <?php $tahun_sekarang = date('Y'); for ($t=2020;$t<=$tahun_sekarang;$t++): $sel = ((string)($user['tahun_masuk'] ?? '') === (string)$t) ? 'selected' : ''; ?>
              <option value="<?= $t ?>" <?= $sel ?>><?= $t ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="firstlog-row">
          <label class="firstlog-label">Major</label>
          <input type="text" name="jurusan" class="firstlog-input" value="<?= esc($user['jurusan'] ?? '') ?>">
        </div>
      </div>

      <div class="firstlog-row">
        <label class="firstlog-label">NISN</label>
        <input type="text" name="nisn" class="firstlog-input" value="<?= esc($user['nisn'] ?? '') ?>">
      </div>
      <div class="firstlog-actions">
        <button type="button" class="firstlog-btn firstlog-btn-secondary firstlog-skip">Later</button>
        <button type="button" class="firstlog-btn firstlog-btn-primary firstlog-submit">Save & Continue</button>
      </div>
    </form>
  </div>
</div>
<!-- ========== YAKQIN POPUP ========== -->
<div id="yakqin-popup" class="yakqin-overlay" style="display:none;">
  <div class="yakqin-box">
    <p class="yakqin-message">You have not completed your data. Are you sure you want to exit?</p>
    <div class="yakqin-buttons">
      <button id="yakqin-confirm" class="yakqin-btn yakqin-btn-yes">Yes, Exit</button>
      <button id="yakqin-cancel" class="yakqin-btn yakqin-btn-no">Cancel</button>
    </div>
  </div>
</div>

<!-- fallback (hidden) main profile form yang dipakai JS fallback jika perlu -->
<form id="profile-main-form" method="POST" action="<?= htmlspecialchars($dashboardBase . '/profile/', ENT_QUOTES) ?>" enctype="multipart/form-data" style="display:none;">
  <input type="hidden" name="email" value="<?= esc($user['email'] ?? '') ?>">
  <input type="hidden" name="foto_profil" value="<?= esc($user['foto_profil'] ?? '') ?>">
  <input type="hidden" name="nomor_telpon" value="<?= esc($user['nomor_telpon'] ?? '') ?>">
  <input type="hidden" name="alamat_rumah" value="<?= esc($user['alamat_rumah'] ?? '') ?>">
  <input type="hidden" name="tanggal_lahir" value="<?= esc($user['tanggal_lahir'] ?? '') ?>">
  <input type="hidden" name="asal_sekolah" value="<?= esc($user['asal_sekolah'] ?? '') ?>">
  <input type="hidden" name="tahun_masuk" value="<?= esc($user['tahun_masuk'] ?? '') ?>">
  <input type="hidden" name="jurusan" value="<?= esc($user['jurusan'] ?? '') ?>">
  <input type="hidden" name="nisn" value="<?= esc($user['nisn'] ?? '') ?>">
  <input type="hidden" name="password" value="">
  <input type="text" name="display_name" value="" autocomplete="off">
</form>
