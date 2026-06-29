<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if ( ! $user || !in_array($user['role'], ['teacher', 'admin']) ) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Add Post';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// Ambil kategori
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $contentInput = html_entity_decode(urldecode(base64_decode($_POST['content'] ?? '')));
    $excerpt = $_POST['excerpt'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $status = $_POST['status'] ?? 'draft';
    $youtube_url = trim($_POST['youtube_url'] ?? '');
    $thumbnail = trim($_POST['thumbnail'] ?? '');

    if ($title === '') {
        $base = 'postingan';
        $i = 1;
        while (true) {
            $candidate = slugify($base . '-' . $i++);
            $unique = ensure_unique_slug($pdo, $candidate);
            if ($unique === $candidate) {
                $slug = $candidate;
                break;
            }
        }
        $is_auto_slug = 1;
    } else {
        $slugCandidate = $slugInput !== '' ? slugify($slugInput) : slugify($title);
        $slug = ensure_unique_slug($pdo, $slugCandidate);
        $is_auto_slug = 0;
    }

    if ($youtube_url !== '') {
        if (!preg_match('#^(https?://)?(www\.)?(youtube\.com/watch\?v=|youtu\.be/)#i', $youtube_url)) {
            $error = '<p style="color:red;">Link YouTube tidak valid.</p>';
        }
    }

    // Validasi minimal
    if (!$error && $slug && $category_id && is_numeric($category_id)) {
        $slug = ensure_unique_slug($pdo, $slug);

        $stmt = $pdo->prepare("INSERT INTO posts 
            (title, slug, content, excerpt, thumbnail, category_id, author_id, status, youtube_url, is_auto_slug, is_deleted, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");

        $stmt->execute([
            $title,
            $slug,
            $contentInput,
            $excerpt,
            $thumbnail,
            $category_id,
            $user['id'],
            $status,
            $youtube_url,
            $is_auto_slug
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    // Gagal validasi
    echo json_encode(['success' => false, 'message' => $error ?: 'Data incomplete or invalid.']);
    exit;
}

ob_start();
?>

<h1>Add Post Baru</h1>
<?= $error ?>
<form id="postForm" method="POST" enctype="multipart/form-data">
<p>Title: <input type="text" id="title" name="title"></p>

<p>
  Slug:
  <input type="text" id="slug" name="slug" readonly style="width:80%;">
  <button type="button" id="edit-slug" title="Edit slug" style="width:18%;">Edit</button>
</p>

<p>Kategori:
  <select name="category_id" required>
    <option value="">-- -- Select Category -- --</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
</p>

<p>Excerpt: <textarea name="excerpt" rows="2"></textarea></p>


<div class="thumbnail-upload-wrapper">
  <label class="firstlog-label">Thumbnail Post</label>
  <div class="firstlog-photo-controls">
    <div class="firstlog-photo-preview">
      <?php if (!empty($_POST['thumbnail'])): ?>
        <img id="thumbnail-preview-img" src="<?= htmlspecialchars($_POST['thumbnail']) ?>" alt="Preview Thumbnail">
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
      <input id="thumbnail-url" name="thumbnail" type="text" class="firstlog-input" value="<?= htmlspecialchars($_POST['thumbnail'] ?? '') ?>" placeholder="URL thumbnail (otomatis terisi setelah upload)">
      <div class="firstlog-file-row">
        <input id="thumbnail-file" type="file" accept=".png,.jpg,.jpeg,.webp" style="display: none;">
        <label for="thumbnail-file" class="custom-upload-btn">Choose Thumbnail</label>
        <button type="button" id="thumbnail-upload-btn" class="formprof-upload-btn" disabled>Upload Thumbnail</button>
        <div id="thumbnail-status" class="firstlog-note">≤ 300 KB</div>
      </div>
    </div>
  </div>
</div>


<p>Link YouTube:
  <input type="url" name="youtube_url" id="youtube_url"
         placeholder="https://www.youtube.com/watch?v=B2D95G0zSAo">
</p>

<p>Isi Konten:</p>
<div id="editor-container" style="height: 300px;"></div>
<input type="hidden" name="content" id="content-input">

<p>
  Status:
  <select name="status">
    <option value="draft">Draft</option>
    <option value="published">Published</option>
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
// pinjam dari foto
const thumbFileInput = document.getElementById('thumbnail-file');
const thumbUploadBtn = document.getElementById('thumbnail-upload-btn');
const thumbPreviewImg = document.getElementById('thumbnail-preview-img');
const thumbPlaceholder = document.getElementById('thumbnail-preview-placeholder');
const thumbUrlInput = document.getElementById('thumbnail-url');
const thumbStatusBox = document.getElementById('thumbnail-status');

thumbFileInput.addEventListener('change', () => {
  const file = thumbFileInput.files[0];
  if (!file) return;

  if (file.size > 300 * 1024) {
    thumbStatusBox.textContent = 'Ukuran terlalu besar (maks 300KB)';
    thumbStatusBox.style.color = 'red';
    thumbUploadBtn.disabled = true;
    return;
  }

  // review langsung dari file lokal
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


thumbUploadBtn.addEventListener('click', () => {
  const file = thumbFileInput.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);

  thumbStatusBox.textContent = 'Uploading...';
  thumbStatusBox.style.color = 'black';
  thumbUploadBtn.disabled = true;

  fetch('/dashboard/admin/upload_assets_img.php', {
    method: 'POST',
    body: formData
  })
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
</script>
<script>
/* Utility slugify */
function slugify(str){
  str = String(str || '').trim().toLowerCase();
  if (!str) return '';
  str = str.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
  str = str.replace(/[^a-z0-9\s-]/g, '');
  str = str.replace(/\s+/g, '-');
  str = str.replace(/-+/g, '-');
  str = str.replace(/^-+|-+$/g, '');
  return str;
}

/* --- Slug checking / title binding --- */
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
    if (!t.trim()) {
      status.textContent = 'Silahkan tuliskan judul';
      status.style.color = '#888';
      slug.value = '';
      return;
    }
    slug.value = slugify(t);
    checkSlug(slug.value);
  });

  slug.addEventListener('input', function(){
    userEdited = true;
    checkSlug(this.value);
  });

  if (editBtn) {
    editBtn.addEventListener('click', function(){
      if (slug.hasAttribute('readonly')) {
        slug.removeAttribute('readonly');
        slug.focus();
        this.textContent = 'Lock';
      } else {
        slug.setAttribute('readonly','readonly');
        this.textContent = 'Edit';
        slug.value = slugify(slug.value);
        checkSlug(slug.value);
      }
    });
  }

  function checkSlug(value){
    const s = slugify(value || '');
    if (!s) {
      status.textContent = 'Silahkan tuliskan judul';
      status.style.color = '#888';
      return;
    }

    fetch('/dashboard/admin/content/check-slug.php?slug=' + encodeURIComponent(s))
      .then(r => {
        if (!r.ok) throw new Error('Cek slug gagal');
        return r.json();
      })
      .then(j => {
        if (!j.ok) { status.textContent = 'Error'; status.style.color = 'red'; return; }
        if (j.available) {
          status.textContent = 'Tersedia';
          status.style.color = 'green';
        } else {
          status.textContent = 'Sudah ada, saran: ' + (j.suggested || '');
          status.style.color = 'orange';
        }
      })
      .catch(()=>{ status.textContent = 'Cek gagal'; status.style.color = 'red'; });
  }
})();

/* --- Quill setup + image uploader with client-side size check --- */
const quill = new Quill('#editor-container', {
  theme: 'snow',
  modules: {
    toolbar: {
      container: [
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
      ],
      handlers: {
        image: function() {
          const input = document.createElement('input');
          input.setAttribute('type', 'file');
          input.setAttribute('accept', 'image/*');
          input.click();

          input.onchange = () => {
            const file = input.files && input.files[0];
            if (!file) return;

            // client-side size limit
            const MAX_BYTES = 500 * 1024; // 500KB
            const errorBox = document.querySelector('#errorBox');
            if (file.size > MAX_BYTES) {
              if (errorBox) errorBox.innerHTML = 'Ukuran maksimal 500KB.';
              return;
            }

            const formData = new FormData();
            formData.append('file', file);

            // optional: show temporary UI feedback
            if (errorBox) { errorBox.style.color = 'black'; errorBox.innerHTML = 'Uploading images...'; }

fetch('/dashboard/admin/upload_assets_img.php', {
  method: 'POST',
  body: formData
})
.then(async res => {
  const text = await res.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error('Respons server tidak valid.');
  }

  if (!res.ok || !data.success) {
    throw new Error(data.message || 'Failed to upload image.');
  }

  return data;
})
            .then(data => {
              if (data && data.success && data.url) {
                const range = quill.getSelection() || { index: quill.getLength() };
                quill.insertEmbed(range.index, 'image', data.url);
                if (errorBox) { errorBox.innerHTML = ''; }
              } else {
                if (errorBox) errorBox.innerHTML = (data && data.message) ? data.message : 'Failed to upload image.';
              }
            })
            .catch(err => {
              if (errorBox) errorBox.innerHTML = err.message || 'An error occurred during upload.';
            });
          };
        }
      }
    }
  }
});

/* Pastikan konten Quill tersalin ke hidden input sebelum proses submit (dipanggil oleh handler submit AJAX di bawah) */
function syncQuillToInput() {
  const contentInput = document.querySelector('#content-input');
  if (contentInput) contentInput.value = quill.root.innerHTML;
}

/* --- AJAX submit form (ke add.php) dengan UI loading dan errorBox --- */
(function(){
  const form = document.querySelector('#postForm') || document.querySelector('form');
  if (!form) return;

  const submitBtn = document.querySelector('#submitBtn');
  const errorBox = document.querySelector('#errorBox');

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    // ⬇️ Encode konten Quill agar aman dari ModSecurity
    const contentInput = document.querySelector('#content-input');
    if (contentInput) {
      const rawHTML = quill.root.innerHTML;
      contentInput.value = btoa(unescape(encodeURIComponent(rawHTML)));
    }

    const fd = new FormData(this);

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';
    }
    if (errorBox) {
      errorBox.style.color = 'black';
      errorBox.innerHTML = 'Saving...';
    }

    fetch('add.php', {
      method: 'POST',
      body: fd
    })
    .then(res => {
      if (!res.ok) return res.text().then(txt => { throw new Error(txt || 'Connection to server failed.'); });
      return res.json();
    })
    .then(data => {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save';
      }

      if (!errorBox) return;

      if (data.success) {
        errorBox.style.color = 'green';
        errorBox.innerHTML = '✅ Post saved successfully.';
        setTimeout(() => window.location.href = 'index.php', 800);
      } else {
        errorBox.style.color = 'red';
        errorBox.innerHTML = data.message || 'Data incomplete or invalid.';
      }
    })
    .catch(err => {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save';
      }
      if (errorBox) {
        errorBox.style.color = 'red';
        errorBox.innerHTML = err.message || 'A connection error occurred.';
      } else {
        console.error(err);
      }
    });
  });
})();

</script>
<?php
$content = ob_get_clean();
$pageTitle = 'Add Post';
require_once __DIR__ . '/../../partials/layout.php';
