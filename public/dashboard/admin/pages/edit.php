<?php
// dashboard/admin/pages/edit.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

// --- Auth ---
$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Edit Page';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// --- Ambil data halaman ---
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    $content = '<p>Page not found.</p>';
    $pageTitle = 'Edit Page';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

$page = page_get($pdo, 'id', $id, false);
if (!$page) {
    $content = '<p>Page not found.</p>';
    $pageTitle = 'Edit Page';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// Guru hanya boleh edit miliknya sendiri
if ($user['role'] === 'teacher' && isset($page['created_by']) && (int)$page['created_by'] !== (int)$user['id']) {
    $_SESSION['flash'] = 'Guru hanya dapat mengedit halaman yang mereka buat sendiri.';
    header('Location: ./');
    exit;
}

// --- Ambil semua tag untuk daftar checkbox ---
try {
    $stmt = $pdo->query("SELECT id, name FROM tags ORDER BY name ASC");
    $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $allTags = [];
}

// --- POST: proses update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        $originalSlug = $page['slug'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $slugInput = trim($_POST['slug'] ?? '');

        // decode konten
        $contentInput = '';
        if (!empty($_POST['content'])) {
            $decoded = base64_decode($_POST['content']);
            if ($decoded !== false) {
                $contentInput = html_entity_decode(urldecode($decoded));
            }
        }

        $excerpt = trim($_POST['excerpt'] ?? '');
        $thumbnail = trim($_POST['thumbnail'] ?? '');
        $status = in_array($_POST['status'] ?? 'draft', ['draft','published','private'], true)
            ? $_POST['status']
            : ($page['status'] ?? 'draft');

        if ($title === '') {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => 'Title wajib diisi.']);
            exit;
        }

        // validasi thumbnail
        if (!empty($thumbnail)) {
            $isUrl = filter_var($thumbnail, FILTER_VALIDATE_URL);
            $isRelative = (strpos($thumbnail, '/') === 0 || strpos($thumbnail, './') === 0);
            if (!$isUrl && !$isRelative) $thumbnail = null;
        } else {
            $thumbnail = null;
        }

        // ---- Tag Handling (baru) ----
        $finalTags = [];

        // 1) dari checkbox (IDs)
        $raw_tag_ids = $_POST['tag_ids'] ?? [];
        if (!is_array($raw_tag_ids)) $raw_tag_ids = [$raw_tag_ids];
        $tag_ids = array_values(array_unique(array_map('intval', array_filter($raw_tag_ids, fn($v) => $v !== '' && $v !== null))));

        if (!empty($tag_ids)) {
            $named = []; $params = [];
            foreach ($tag_ids as $i => $tid) {
                $key = ":id{$i}";
                $named[] = $key;
                $params[$key] = $tid;
            }
            $sql = "SELECT id FROM tags WHERE id IN (" . implode(',', $named) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $found = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            $found = array_map('intval', $found);
            if (!empty($found)) {
                $finalTags = $found;
            }
        }

        // 2) tag baru (comma-separated names)
        $tags_new = trim($_POST['tags_new'] ?? '');
        if ($tags_new !== '') {
            $parts = array_map('trim', explode(',', $tags_new));
            $parts = array_filter($parts, fn($v) => $v !== '');
            if (!empty($parts)) $finalTags = array_merge($finalTags, $parts);
        }

        $finalTags = array_values(array_unique($finalTags, SORT_REGULAR));

        // ---- Slug ----
        $slugToSend = null;
        if ($slugInput !== '' && $slugInput !== $originalSlug) {
            $slugToSend = $slugInput;
        }

        // ---- Sanitasi konten ringan ----
        if ($contentInput !== '') {
            $allowed = '<p><a><ul><ol><li><strong><b><em><i><br><img><h1><h2><h3><h4><blockquote><code>';
            $contentInput = strip_tags($contentInput, $allowed);
        }

        // ---- Build data ----
        $data = [
            'title' => $title,
            'content' => $contentInput,
            'excerpt' => $excerpt !== '' ? $excerpt : null,
            'thumbnail' => $thumbnail,
            'status' => $status,
            'tags' => $finalTags,
        ];
        if ($slugToSend !== null) $data['slug'] = $slugToSend;

        // ---- Update ----
        $res = page_update($pdo, $id, $data);
        if ($res['success']) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Changes saved successfully.']);
            exit;
        } else {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $res['error'] ?? 'Failed to save changes.']);
            exit;
        }
    } catch (Throwable $e) {
        error_log("Exception in edit.php (page id=$id): " . $e->getMessage());
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => 'Server error saat menyimpan perubahan.']);
        exit;
    }
}

// --- Render form (GET) ---
$selectedTagIds = array_map('intval', array_column($page['tags'] ?? [], 'id'));
ob_start();
?>
<?php if (!empty($page['is_auto_slug'] ?? null) && ($page['status'] ?? '') === 'draft'): ?>
  <p style="color:red;">Slug masih otomatis. Sebaiknya ubah sebelum dipublikasikan.</p>
<?php endif; ?>

<h1>Edit Page</h1>

<form method="POST" id="page-edit-form" enctype="multipart/form-data">
    <p>Title: <input type="text" id="title" name="title" value="<?= htmlspecialchars($_POST['title'] ?? $page['title'] ?? '') ?>" required></p>

    <p>
      Slug:
      <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($_POST['slug'] ?? $page['slug'] ?? '') ?>" readonly style="width:70%;">
      <button type="button" id="edit-slug" style="width:28%;">Edit</button>
      <div id="slug-status" style="margin-top:6px;color:#666;">Silahkan tuliskan judul</div>
    </p>

  <!-- Tag Section -->
  <p>Tag / Kategori:</p>
  <div class="tags-list" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem;">
    <?php if (!empty($allTags) && is_array($allTags)): ?>
      <?php foreach ($allTags as $t): ?>
        <label style="display:flex; align-items:center; gap:6px;">
          <input
            type="checkbox"
            name="tag_ids[]"
            value="<?= (int)$t['id'] ?>"
            <?= in_array((int)$t['id'], $selectedTagIds) ? 'checked' : '' ?>>
          <?= htmlspecialchars($t['name']) ?>
        </label>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="color:#999;">Belum ada tag atau kategori yang tersedia.</p>
    <?php endif; ?>
  </div>

    <p>Ringkasan: <textarea name="excerpt" rows="2"><?= htmlspecialchars($_POST['excerpt'] ?? $page['excerpt'] ?? '') ?></textarea></p>

    <!-- Thumbnail upload UI -->
    <div class="thumbnail-upload-wrapper">
      <label>Thumbnail</label>
      <div class="firstlog-photo-controls">
        <div class="firstlog-photo-preview">
          <?php $thumbVal = $_POST['thumbnail'] ?? ($page['thumbnail'] ?? ''); ?>
          <?php if (!empty($thumbVal)): ?>
            <img id="thumbnail-preview-img" src="<?= htmlspecialchars($thumbVal) ?>" alt="Preview Thumbnail">
          <?php else: ?>
            <div id="thumbnail-preview-placeholder" class="firstlog-photo-placeholder" aria-hidden="true">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="24" height="24" rx="4" fill="#f3f3f3"/>
                <path d="M12 12a3 3 0 100-6 3 3 0 000 6z" fill="#d0d0d0"/>
              </svg>
            </div>
            <img id="thumbnail-preview-img" src="" alt="Preview Thumbnail" style="display:none;">
          <?php endif; ?>
        </div>

        <div class="firstlog-photo-actions">
          <input id="thumbnail-url" name="thumbnail" type="text" class="firstlog-input" value="<?= htmlspecialchars($thumbVal) ?>" placeholder="URL thumbnail (otomatis terisi setelah upload)">
          <div class="firstlog-file-row" style="margin-top:8px;">
            <input id="thumbnail-file" type="file" accept=".png,.jpg,.jpeg,.webp" style="display:none;">
            <label for="thumbnail-file" class="custom-upload-btn">Choose Thumbnail</label>
            <button type="button" id="thumbnail-upload-btn" disabled>Upload Thumbnail</button>
            <div id="thumbnail-status" class="firstlog-note">≤ 300 KB</div>
          </div>
        </div>
      </div>
    </div>

    <p>Isi Konten:</p>
    <div id="editor-container" style="height: 300px;"></div>
    <input type="hidden" name="content" id="content-input">

    <p>
      Status:
      <select name="status">
        <option value="draft" <?= (($_POST['status'] ?? $page['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
        <option value="published" <?= (($_POST['status'] ?? $page['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published</option>
        <option value="private" <?= (($_POST['status'] ?? $page['status'] ?? '') === 'private') ? 'selected' : '' ?>>Private</option>
      </select>
    </p>

    <div id="errorBox" style="margin-bottom:1em; color:red;"></div>
    <button type="submit" id="submitBtn">Save Changes</button>
    <a href="./">← Kembali</a>
</form>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="/dashboard/profile/profile-upload.js"></script>

<script>
// Thumbnail UI (mirip add.php)
const thumbFileInput = document.getElementById('thumbnail-file');
const thumbUploadBtn = document.getElementById('thumbnail-upload-btn');
const thumbPreviewImg = document.getElementById('thumbnail-preview-img');
const thumbPlaceholder = document.getElementById('thumbnail-preview-placeholder');
const thumbUrlInput = document.getElementById('thumbnail-url');
const thumbStatusBox = document.getElementById('thumbnail-status');

if (thumbFileInput) {
  thumbFileInput.addEventListener('change', () => {
    const file = thumbFileInput.files[0];
    if (!file) return;
    if (file.size > 300 * 1024) {
      thumbStatusBox.textContent = 'Ukuran terlalu besar (maks 300KB)';
      thumbStatusBox.style.color = 'red';
      thumbUploadBtn.disabled = true;
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      thumbPreviewImg.src = e.target.result;
      thumbPreviewImg.style.display = 'block';
      if (thumbPlaceholder) thumbPlaceholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
    thumbStatusBox.textContent = 'Siap diupload';
    thumbStatusBox.style.color = 'green';
    thumbUploadBtn.disabled = false;
  });
}

if (thumbUploadBtn) {
  thumbUploadBtn.addEventListener('click', () => {
    const file = thumbFileInput.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    thumbStatusBox.textContent = 'Uploading...';
    thumbStatusBox.style.color = 'black';
    thumbUploadBtn.disabled = true;

    fetch('/dashboard/admin/upload_assets_img.php', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.url) {
          thumbUrlInput.value = data.url;
          thumbPreviewImg.src = data.url;
          thumbPreviewImg.style.display = 'block';
          if (thumbPlaceholder) thumbPlaceholder.style.display = 'none';
          thumbStatusBox.textContent = '✅ Thumbnail uploaded successfully';
          thumbStatusBox.style.color = 'green';
        } else {
          thumbStatusBox.textContent = data.message || 'Upload failed';
          thumbStatusBox.style.color = 'red';
        }
      })
      .catch(err => {
        thumbStatusBox.textContent = err.message || 'Connection error';
        thumbStatusBox.style.color = 'red';
      });
  });
}

// slug utilities + remote check
function slugifyClient(str){
  str = String(str || '').trim().toLowerCase();
  if (!str) return '';
  str = str.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
  str = str.replace(/[^a-z0-9\s-]/g, '');
  str = str.replace(/\s+/g, '-');
  str = str.replace(/-+/g, '-');
  str = str.replace(/^-+|-+$/g, '');
  return str;
}

(function(){
  const titleEl = document.getElementById('title');
  const slugEl = document.getElementById('slug');
  const editBtn = document.getElementById('edit-slug');
  const status = document.getElementById('slug-status');
  if (!titleEl || !slugEl) return;

  const currentId = <?= (int)$page['id'] ?>;
  const originalSlug = <?= json_encode($page['slug'] ?? '') ?>;
  // if DB signals is_auto_slug, allow auto-update; otherwise default to not auto
  let userEdited = <?= (!empty($page['is_auto_slug']) ? 'false' : 'true') ?>;

  function remoteCheck(val) {
    const s = slugifyClient(val || '');
    if (!s) { status.textContent = 'Silahkan tuliskan judul'; status.style.color = '#888'; return; }
    const url = '/dashboard/admin/pages/check-slug-edit.php?slug=' + encodeURIComponent(s) + '&exclude_id=' + encodeURIComponent(currentId);
    fetch(url)
      .then(r => r.json())
      .then(j => {
        if (!j.ok) { status.textContent = 'Error saat cek slug'; status.style.color = 'red'; return; }
        if (s === originalSlug) {
          status.textContent = 'Ini slug asli halaman (tidak perlu diubah)';
          status.style.color = 'green';
          return;
        }
        if (j.available) { status.textContent = 'Tersedia'; status.style.color = 'green'; }
        else { status.textContent = 'Sudah ada, saran: ' + (j.suggested || ''); status.style.color = 'orange'; }
      })
      .catch(()=>{ status.textContent = 'Cek gagal'; status.style.color = 'red'; });
  }

  titleEl.addEventListener('input', function(){
    if (userEdited) return;
    const t = this.value || '';
    if (!t.trim()) { status.textContent = 'Silahkan tuliskan judul'; status.style.color = '#888'; slugEl.value = ''; return; }
    slugEl.value = slugifyClient(t);
    remoteCheck(slugEl.value);
  });

  slugEl.addEventListener('input', function(){ userEdited = true; remoteCheck(this.value); });

  if (editBtn) {
    editBtn.addEventListener('click', function(){
      if (slugEl.hasAttribute('readonly')) {
        slugEl.removeAttribute('readonly');
        slugEl.focus();
        userEdited = true;
        this.textContent = 'Lock';
      } else {
        slugEl.setAttribute('readonly','readonly');
        this.textContent = 'Edit';
        slugEl.value = slugifyClient(slugEl.value);
        userEdited = true;
        remoteCheck(slugEl.value);
      }
    });
  }

  // init remote check for current slug
  (function init(){ const initial = slugEl.value || titleEl.value || ''; if (initial) remoteCheck(initial); })();
})();

// Quill setup (same as add.php)
const quill = new Quill('#editor-container', {
  theme: 'snow',
  modules: { toolbar: { container: [
    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
    ['bold', 'italic', 'underline'],
    [{ 'align': [] }],
    [{ 'font': [] }],
    [{ 'size': ['small', false, 'large', 'huge'] }],
    [{ 'color': [] }, { 'background': [] }],
    [{ 'script': 'sub'}, { 'script': 'super' }],
    ['image', 'link'],
    [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'list': 'check' }],
    ['clean']
  ], handlers: {
    image: function() {
      const input = document.createElement('input');
      input.setAttribute('type','file');
      input.setAttribute('accept','image/*');
      input.click();
      input.onchange = () => {
        const file = input.files && input.files[0];
        if (!file) return;
        const MAX_BYTES = 500 * 1024;
        const errorBox = document.querySelector('#errorBox');
        if (file.size > MAX_BYTES) { if (errorBox) errorBox.innerHTML = 'Ukuran maksimal 500KB.'; return; }
        const formData = new FormData();
        formData.append('file', file);
        if (errorBox) { errorBox.style.color = 'black'; errorBox.innerHTML = 'Uploading images...'; }
        fetch('/dashboard/admin/upload_assets_img.php', { method: 'POST', body: formData })
          .then(async res => {
            const text = await res.text();
            let data;
            try { data = JSON.parse(text); } catch { throw new Error('Respons server tidak valid.'); }
            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to upload image.');
            return data;
          })
          .then(data => {
            const range = quill.getSelection() || { index: quill.getLength() };
            quill.insertEmbed(range.index, 'image', data.url);
            if (errorBox) errorBox.innerHTML = '';
          })
          .catch(err => { if (errorBox) errorBox.innerHTML = err.message || 'An error occurred during upload.'; });
      };
    }
  } } }
});

// set initial content from DB
(function(){
  const initial = <?= json_encode($_POST['content'] ?? $page['content'] ?? '') ?>;
  if (initial) quill.root.innerHTML = initial;
})();

function syncQuillToInput() {
  const contentInput = document.getElementById('content-input');
  if (contentInput) contentInput.value = btoa(unescape(encodeURIComponent(quill.root.innerHTML)));
}

// AJAX submit
(function(){
  const form = document.getElementById('page-edit-form');
  if (!form) return;
  const submitBtn = document.getElementById('submitBtn');
  const errorBox = document.getElementById('errorBox');

  form.addEventListener('submit', function(e){
    e.preventDefault();
    syncQuillToInput();
    const fd = new FormData(this);
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }
    if (errorBox) { errorBox.style.color = 'black'; errorBox.textContent = 'Saving...'; }

    fetch('edit.php?id=<?= (int)$page['id'] ?>', { method: 'POST', body: fd })
      .then(res => {
        if (!res.ok) return res.text().then(txt => { throw new Error(txt || 'Connection to server failed.'); });
        return res.json();
      })
      .then(data => {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save Changes'; }
        if (!errorBox) return;
        if (data.success) { errorBox.style.color = 'green'; errorBox.innerHTML = '✅ Changes saved successfully.'; setTimeout(()=> window.location.href = './', 800); }
        else { errorBox.style.color = 'red'; errorBox.innerHTML = data.message || 'Data incomplete or invalid.'; }
      })
      .catch(err => {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save Changes'; }
        if (errorBox) { errorBox.style.color = 'red'; errorBox.innerHTML = err.message || 'A connection error occurred.'; } else console.error(err);
      });
  });
})();
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Edit Page';
require_once __DIR__ . '/../../partials/layout.php';
