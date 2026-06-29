<?php
// dashboard/admin/pages/add.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

// debug Pastikan $pdo sudah ada sebelum set attribute
if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
}

// auth
$user = get_user_from_session($pdo);
if (! $user || ! in_array($user['role'], ['teacher','admin'])) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Add Page';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// ambil daftar tag yang tersedia (dipakai di form)
try {
    $allTagsStmt = $pdo->query("SELECT id, name FROM tags ORDER BY name ASC");
    $allTags = $allTagsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allTags = [];
}

// ---------------- POST ----------------
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ambil input
        $title = trim($_POST['title'] ?? '');
        $slugInput = trim($_POST['slug'] ?? '');
        // decode konten (btoa...)
        $contentInput = '';
        if (!empty($_POST['content'])) {
            $decoded = base64_decode($_POST['content']);
            if ($decoded !== false) {
                $contentInput = html_entity_decode(urldecode($decoded));
            } else {
                $contentInput = '';
            }
        }
        $excerpt = trim($_POST['excerpt'] ?? '');
        $thumbnail = trim($_POST['thumbnail'] ?? '');
        $status = in_array($_POST['status'] ?? 'draft', ['draft','published','private'], true) ? $_POST['status'] : 'draft';

        // tags: datang sebagai array of ids
        $raw_tag_ids = $_POST['tag_ids'] ?? [];
        if (!is_array($raw_tag_ids)) $raw_tag_ids = [$raw_tag_ids];

        // minimal validation
        if ($title === '') {
            $error = 'Title wajib diisi.';
        }

        if ($error !== '') {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }

        // sanitize tag ids -> only positive ints
        $tag_ids = array_values(array_unique(array_map('intval', array_filter($raw_tag_ids, function($v){ return $v !== '' && $v !== null; })) ));
        $tag_ids = array_filter($tag_ids, function($v){ return $v > 0; });

        // validate against DB using named placeholders (safer when number of ids varies)
        $tag_ids_valid = [];
        if (!empty($tag_ids)) {
            $named = [];
            $params = [];
            foreach ($tag_ids as $i => $tid) {
                $key = ":id{$i}";
                $named[] = $key;
                $params[$key] = (int)$tid;
            }
            $sql = "SELECT id FROM tags WHERE id IN (" . implode(',', $named) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $found = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($found)) {
                foreach ($found as $r) $tag_ids_valid[] = (int)$r['id'];
            }
        }

        $data = [
            'title' => $title,
            'slug' => $slugInput !== '' ? $slugInput : null,
            'content' => $contentInput,
            'excerpt' => $excerpt !== '' ? $excerpt : null,
            'thumbnail' => $thumbnail !== '' ? $thumbnail : null,
            'status' => $status,
            'created_by' => (int)$user['id'],
            'tags' => $tag_ids_valid,
        ];

        $res = page_create($pdo, $data); // helper yang sudah kamu perbaiki
        if ($res['success']) {
            header('Content-Type: application/json', true, 200);
            echo json_encode(['success' => true, 'id' => $res['id']]);
            exit;
        } else {
            // log supaya kita tahu kenapa gagal
            error_log('page_create failed: ' . ($res['error'] ?? 'unknown'));
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => $res['error'] ?? 'Failed to create page.']);
            exit;
        }
    } catch (Throwable $e) {
        // Tangkap semua exception agar tidak "bocor" ke handler yang mungkin mengembalikan 403
        error_log('Exception in pages/add.php POST: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        // (opsional) log sebagian payload untuk debugging — hati-hati di production
        // error_log('POST payload: ' . substr(var_export($_POST, true), 0, 2000));
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => 'Server error saat menyimpan halaman. Cek error log.']);
        exit;
    }
}


// --- Render Form (GET) ---
ob_start();
?>
  <h1>Add New Page</h1>

<form id="pageForm" method="POST" enctype="multipart/form-data">
    <?php if (!isset($allTags)) echo '<p style="color:red;">⚠️ $allTags belum diinisialisasi!</p>'; ?>

  <p>Title: <input type="text" id="title" name="title" required></p>

  <p>
    Slug:
    <input type="text" id="slug" name="slug" readonly style="width:80%;">
    <button type="button" id="edit-slug" style="width:18%;">Edit</button>
  </p>

<p>Tag / Kategori:</p>
<div class="tags-list" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem;">
  <?php if (!empty($allTags) && is_array($allTags)): ?>
    <?php foreach ($allTags as $t): ?>
      <label style="display:flex; align-items:center; gap:6px;">
        <input 
          type="checkbox" 
          name="tag_ids[]" 
          value="<?= $t['id'] ?>" 
          <?= in_array($t['id'], $selectedTags ?? []) ? 'checked' : '' ?>>
        <?= htmlspecialchars($t['name']) ?>
      </label>
    <?php endforeach; ?>
  <?php else: ?>
    <p style="color:#999;">Belum ada tag atau kategori yang tersedia.</p>
  <?php endif; ?>
</div>



  <p>Ringkasan: <textarea name="excerpt" rows="2"></textarea></p>

  <div class="thumbnail-upload-wrapper">
    <label>Thumbnail</label>
    <div class="firstlog-photo-controls">
      <div class="firstlog-photo-preview">
        <div id="thumbnail-preview-placeholder" class="firstlog-photo-placeholder" aria-hidden="true">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="4" fill="#f3f3f3"/>
            <path d="M12 12a3 3 0 100-6 3 3 0 000 6z" fill="#d0d0d0"/>
          </svg>
        </div>
        <img id="thumbnail-preview-img" src="" alt="Preview Thumbnail" style="display:none;">
      </div>

      <div class="firstlog-photo-actions">
        <input id="thumbnail-url" name="thumbnail" type="text" class="firstlog-input" placeholder="URL thumbnail (akan terisi setelah upload)">
        <div class="firstlog-file-row" style="margin-top:8px;">
          <input id="thumbnail-file" type="file" accept=".png,.jpg,.jpeg,.webp" style="display:none;">
          <label for="thumbnail-file" class="custom-upload-btn">Choose Thumbnail</label>
          <button type="button" id="thumbnail-upload-btn" disabled>Upload Thumbnail</button>
          <div id="thumbnail-status" class="firstlog-note">≤ 300 KB</div>
        </div>
      </div>
    </div>
  </div>

  <p>Link YouTube (opsional): <input type="url" name="youtube_url" id="youtube_url" placeholder="https://..."></p>

  <p>Isi Konten:</p>
  <div id="editor-container" style="height: 300px;"></div>
  <input type="hidden" name="content" id="content-input">

  <p>
    Status:
    <select name="status">
      <option value="draft">Draft</option>
      <option value="published">Published</option>
      <option value="private">Private</option>
    </select>
  </p>

  <div id="errorBox" style="margin-bottom:1em; color:red;"></div>
  <button type="submit" id="submitBtn">Save</button>
  <a href="index.php">Back</a>
</form>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="/dashboard/profile/profile-upload.js"></script>

<script>
// Thumbnail small UI (same logic seperti referensimu)
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

// slug client shorthand (kebermanfaatan UX)
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

/* --- Slug / title binding --- */
(function(){
  const title = document.getElementById('title');
  const slug = document.getElementById('slug');
  const editBtn = document.getElementById('edit-slug');
  if (!title || !slug) return;

  const status = document.createElement('div');
  slug.parentNode.appendChild(status);
  let userEdited = false;

  title.addEventListener('input', function(){
    if (userEdited) return;
    const t = this.value || '';
    if (!t.trim()) { status.textContent = 'Silahkan tuliskan judul'; status.style.color = '#888'; slug.value = ''; return; }
    slug.value = slugifyClient(t);
    checkSlug(slug.value);
  });

  slug.addEventListener('input', function(){ userEdited = true; checkSlug(this.value); });

  if (editBtn) {
    editBtn.addEventListener('click', function(){
      if (slug.hasAttribute('readonly')) {
        slug.removeAttribute('readonly');
        slug.focus();
        this.textContent = 'Lock';
      } else {
        slug.setAttribute('readonly','readonly');
        this.textContent = 'Edit';
        slug.value = slugifyClient(slug.value);
        checkSlug(slug.value);
      }
    });
  }

  function checkSlug(value){
    const s = slugifyClient(value || '');
    if (!s) { status.textContent = 'Silahkan tuliskan judul'; status.style.color = '#888'; return; }
    // endpoint check-slug.php perlu kamu siapkan; fallback: ignore error
    fetch('/dashboard/admin/pages/check-slug.php?slug=' + encodeURIComponent(s))
      .then(r => r.json())
      .then(j => {
        if (!j.ok) { status.textContent = 'Error'; status.style.color = 'red'; return; }
        if (j.available) { status.textContent = 'Tersedia'; status.style.color = 'green'; }
        else { status.textContent = 'Sudah ada, saran: ' + (j.suggested || ''); status.style.color = 'orange'; }
      })
      .catch(()=>{ status.textContent = 'Cek gagal'; status.style.color = 'red'; });
  }
})();

/* Quill setup (sama seperti referensi) */
const quill = new Quill('#editor-container', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ 'header': [1, 2, 3, false] }],
      ['bold','italic','underline'],
      ['image','link'],
      [{ 'list': 'ordered'},{ 'list': 'bullet' }],
      ['clean']
    ]
  }
});

// image handler for quill (upload to upload_assets_img.php) - keep size limit 500KB
quill.getModule('toolbar').addHandler('image', () => {
  const input = document.createElement('input');
  input.setAttribute('type','file');
  input.setAttribute('accept','image/*');
  input.click();
  input.onchange = () => {
    const file = input.files && input.files[0];
    if (!file) return;
    const MAX_BYTES = 500 * 1024;
    const errorBox = document.querySelector('#errorBox');
    if (file.size > MAX_BYTES) {
      if (errorBox) errorBox.textContent = 'Ukuran maksimal 500KB.'; return;
    }
    const formData = new FormData();
    formData.append('file', file);
    if (errorBox) { errorBox.style.color = 'black'; errorBox.textContent = 'Uploading images...'; }
    fetch('/dashboard/admin/upload_assets_img.php', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(data => {
        if (!data.success) throw new Error(data.message || 'Upload failed');
        const range = quill.getSelection() || { index: quill.getLength() };
        quill.insertEmbed(range.index, 'image', data.url);
        if (errorBox) errorBox.textContent = '';
      })
      .catch(err => { if (errorBox) errorBox.textContent = err.message || 'An error occurred during upload.'; });
  };
});

// sync & ajax submit
function syncQuillToInput() {
  const contentInput = document.querySelector('#content-input');
  if (contentInput) contentInput.value = btoa(unescape(encodeURIComponent(quill.root.innerHTML)));
}

(function(){
  const form = document.getElementById('pageForm');
  if (!form) return;
  const submitBtn = document.getElementById('submitBtn');
  const errorBox = document.getElementById('errorBox');

  form.addEventListener('submit', function(e){
    e.preventDefault();
    syncQuillToInput();
    const fd = new FormData(this);
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }
    if (errorBox) { errorBox.style.color = 'black'; errorBox.textContent = 'Saving...'; }

    fetch('/dashboard/admin/pages/add.php', { method: 'POST', body: fd })
      .then(res => {
        if (!res.ok) return res.text().then(txt => { throw new Error(txt || 'Connection failed'); });
        return res.json();
      })
      .then(json => {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save'; }
        if (errorBox) {
          if (errorBox) { errorBox.style.color = 'green'; errorBox.textContent = '✅ Page saved successfully.'; }
        } else {
          if (errorBox) { errorBox.style.color = 'red'; errorBox.textContent = json.message || 'Failed to save.'; }
        }
      }).catch(err => {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save'; }
        if (errorBox) { errorBox.style.color = 'red'; errorBox.textContent = err.message || 'A connection error occurred.'; }
      });
  });
})();
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Add Page';
require_once __DIR__ . '/../../partials/layout.php';
