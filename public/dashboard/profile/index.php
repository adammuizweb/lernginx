<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../includes/bootstrap.php';
date_default_timezone_set('UTC');

// Helper untuk menghindari warning htmlspecialchars(null)
function esc($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$user = get_user_from_session($pdo);
if (!$user) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Profile - Dashboard';
    require_once __DIR__ . '/../partials/layout.php';
    exit;
}

// Pastikan kita ambil data lengkap (fungsi get_user_full_by_id dibuat sebelumnya)
$user = function_exists('get_user_full_by_id') ? get_user_full_by_id($pdo, $user['id']) : get_user_by_id($pdo, $user['id']);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email         = trim($_POST['email'] ?? '');
    $display_name  = trim($_POST['display_name'] ?? '');
    $password      = $_POST['password'] ?? '';

    // Kolom tambahan
    $foto_profil   = trim($_POST['foto_profil'] ?? '');
    $nomor_telpon  = trim($_POST['nomor_telpon'] ?? '');
    $alamat_rumah  = trim($_POST['alamat_rumah'] ?? '');
    $tanggal_lahir = $_POST['tanggal_lahir'] ?: null;
    $asal_sekolah  = trim($_POST['asal_sekolah'] ?? '');
    $tahun_masuk   = $_POST['tahun_masuk'] ?: null;
    $jurusan       = trim($_POST['jurusan'] ?? '');
    $nisn          = trim($_POST['nisn'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        try {
            $updateFields = "
                email = ?, display_name = ?, foto_profil = ?, nomor_telpon = ?, alamat_rumah = ?, 
                tanggal_lahir = ?, asal_sekolah = ?, tahun_masuk = ?, 
                jurusan = ?, nisn = ?, updated_at = NOW()
            ";

            $params = [
                $email, $display_name, $foto_profil, $nomor_telpon, $alamat_rumah,
                $tanggal_lahir, $asal_sekolah, $tahun_masuk,
                $jurusan, $nisn
            ];

            if (!empty($password)) {
                $updateFields .= ", password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            $params[] = $user['id'];

            $stmt = $pdo->prepare("UPDATE users SET $updateFields WHERE id = ?");
            $stmt->execute($params);

            $message = "Profile updated successfully on " . date('d M Y H:i:s');
            // refresh user data lengkap
            $user = function_exists('get_user_full_by_id') ? get_user_full_by_id($pdo, $user['id']) : get_user_by_id($pdo, $user['id']);
        } catch (PDOException $e) {
            $message = "Failed to update profile: " . $e->getMessage();
        }
    }
}

ob_start();
?>

<?php if ($message): ?>
  <div class="alert alert-info"><?= esc($message) ?></div>
<?php endif; ?>

<h2>My Profile</h2>
<form id="profile-main-form" class="formprof-form" method="POST" action="">
  <label>Username</label>
  <input type="text" value="<?= esc($user['username'] ?? '') ?>" disabled>

  <label>Role</label>
  <input type="text" value="<?= esc($user['role'] ?? '') ?>" disabled>

  <label>Email</label>
  <input type="email" name="email" value="<?= esc($user['email'] ?? '') ?>" required>

  <label>New Password (leave empty if you don't want to change)</label>
  <input type="password" name="password">

  <label>Full Name</label>
  <input type="text" name="display_name" value="<?= esc($user['display_name'] ?? '') ?>" placeholder="Example: johndoe">

  <!-- FOTO PROFIL -->
  <div class="formprof-fotowrap">
    <div class="formprof-preview">
      <?php if (!empty($user['foto_profil'])): ?>
        <img id="fotprof-preview-img" src="<?= esc($user['foto_profil']) ?>" alt="Profile Photo">
      <?php else: ?>
        <div id="fotprof-preview-placeholder">
          <svg width="100" height="100" viewBox="0 0 24 24" fill="none">
            <rect width="24" height="24" rx="4" fill="#f3f3f3"/>
            <path d="M12 12a3 3 0 100-6 3 3 0 000 6z" fill="#d0d0d0"/>
            <path d="M4 20a8 8 0 0116 0H4z" fill="#e6e6e6"/>
          </svg>
          <div>No photo yet</div>
        </div>
      <?php endif; ?>
    </div>

    <div class="formprof-side">
      <label for="fotprof-url">Photo URL Input</label>
      <input id="fotprof-url" type="text" name="foto_profil" value="<?= esc($user['foto_profil'] ?? '') ?>" placeholder="URL foto profil...">

      <div style="display:flex;gap:8px;align-items:center;">
        <input id="fotprof-file" type="file" accept=".png,.jpg,.jpeg,.webp" style="display: none;">
<label for="fotprof-file" class="custom-upload-btn">Choose Photo</label>
        <button type="button" id="fotprof-upload-btn" class="formprof-upload-btn" disabled>Upload Photo</button>
        <div id="fotprof-status" class="formprof-status"></div>
      </div>

      <div style="font-size:13px;">
        Requirements: PNG, JPG, JPEG, WebP — max 300 KB.
      </div>

      <div id="fotprof-notify" class="fotprof-notify"></div>
    </div>
  </div>

  <label>Phone Number (for password reset via WhatsApp)</label>
  <input type="text" name="nomor_telpon" value="<?= esc($user['nomor_telpon'] ?? '') ?>" placeholder="Example: 6281234567890">

  <label>Home Address</label>
  <textarea name="alamat_rumah"><?= esc($user['alamat_rumah'] ?? '') ?></textarea>

  <label>Date of Birth</label>
  <input type="date" name="tanggal_lahir" value="<?= esc($user['tanggal_lahir'] ?? '') ?>">

  <label>School</label>
  <input type="text" name="asal_sekolah" value="<?= esc($user['asal_sekolah'] ?? '') ?>">

  <label>Enrollment Year</label>
  <select name="tahun_masuk">
    <option value="">-- Select Year --</option>
    <?php
      $tahun_sekarang = date('Y');
      for ($tahun = 2020; $tahun <= $tahun_sekarang; $tahun++) {
          $selected = ((string)($user['tahun_masuk'] ?? '') === (string)$tahun) ? 'selected' : '';
          echo "<option value=\"$tahun\" $selected>$tahun</option>";
      }
    ?>
  </select>

  <label>Major</label>
  <input type="text" name="jurusan" value="<?= esc($user['jurusan'] ?? '') ?>">

  <label>NISN</label>
  <input type="text" name="nisn" value="<?= esc($user['nisn'] ?? '') ?>">

  <button type="submit">Save Changes</button>
</form>

<script>
(function(){
  const fileInput = document.getElementById('fotprof-file');
  const uploadBtn = document.getElementById('fotprof-upload-btn');
  const statusEl = document.getElementById('fotprof-status');
  const notifyEl = document.getElementById('fotprof-notify');
  const previewImg = document.getElementById('fotprof-preview-img');
  const previewPlaceholder = document.getElementById('fotprof-preview-placeholder');
  const urlInput = document.getElementById('fotprof-url');

  const userid = <?= json_encode((int)$user['id']) ?>;
  const uploadEndpoint = 'upload_photo.php';

  function showNotify(type, text) {
    notifyEl.innerHTML = '<div class="fotprof-notify-' + type + '">' + text + '</div>';
    setTimeout(()=> { notifyEl.innerHTML = ''; }, 5000);
  }

  uploadBtn.addEventListener('click', function(){
    const file = fileInput.files[0];
    if (!file) {
      showNotify('error', 'Please select an image file first.');
      return;
    }
    // client-side quick checks before upload
    const allowed = ['image/png','image/jpeg','image/webp'];
    if (!allowed.includes(file.type)) {
      showNotify('error', 'File format not allowed. Use PNG, JPG, JPEG, or WebP.');
      return;
    }
    if (file.size > 300 * 1024) {
      showNotify('error', 'File too large. Maximum 300 KB.');
      return;
    }

    const fd = new FormData();
    fd.append('foto', file);

    statusEl.textContent = 'Uploading...';
    uploadBtn.disabled = true;

    fetch(uploadEndpoint, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      cache: 'no-cache'
    })
    .then(async r => {
      const text = await r.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (err) {
        uploadBtn.disabled = false;
        statusEl.textContent = '';
        // tampilkan cuplikan response untuk debugging (server mungkin memberi HTML/error)
        showNotify('error', 'Server did not return JSON. Check console for details.');
        console.error('Upload response (not JSON):', text.substring(0, 200));
        return;
      }
      uploadBtn.disabled = false;
      statusEl.textContent = '';
      if (data.success) {
        if (previewPlaceholder) previewPlaceholder.style.display = 'none';
        previewImg.src = data.url;
        previewImg.style.display = 'block';
        urlInput.value = data.url;
        showNotify('success', 'Upload successful.');
      } else {
        showNotify('error', data.message || 'Upload failed (unknown).');
      }
    })
    .catch(err => {
      uploadBtn.disabled = false;
      statusEl.textContent = '';
      showNotify('error', 'Upload failed: ' + (err.message || 'network error'));
    });
  });

  // Jika user paste URL manual, update preview
  urlInput.addEventListener('change', function(){
    const val = urlInput.value.trim();
    if (!val) {
      if (previewPlaceholder) previewPlaceholder.style.display = 'block';
      previewImg.style.display = 'none';
      previewImg.src = '';
      return;
    }
    previewImg.src = val;
    previewImg.style.display = 'block';
    if (previewPlaceholder) previewPlaceholder.style.display = 'none';
  });
})();
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Profile - Dashboard';
require_once __DIR__ . '/../partials/layout.php';
