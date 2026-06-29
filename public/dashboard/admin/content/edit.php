<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

$user = get_user_from_session($pdo);
if ( ! $user || !in_array($user['role'], ['teacher', 'admin']) ) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Edit Post';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ( ! $id ) {
    $content = '<p>Post tidak ditemukan.</p>';
    $pageTitle = 'Edit Post';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND is_deleted = 0");
$stmt->execute([$id]);
$post = $stmt->fetch();

if ( ! $post ) {
    $content = '<p>Post tidak ditemukan.</p>';
    $pageTitle = 'Edit Post';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// Validasi tambahan untuk guru: hanya boleh edit post miliknya sendiri
if ($user['role'] === 'teacher' && $post['author_id'] !== $user['id']) {
    $_SESSION['flash'] = 'Guru hanya dapat mengedit post yang mereka buat sendiri.';
    header('Location: ./');
    exit;
}


$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // terima request AJAX dari form (mengembalikan JSON)
    // ambil original slug dari DB
    $originalSlug = $post['slug'];

    $title = trim($_POST['title'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    // decode konten yang dikirim client (sama dengan add.php)
    $contentInput = html_entity_decode(urldecode(base64_decode($_POST['content'] ?? '')));
    $excerpt = $_POST['excerpt'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $status = $_POST['status'] ?? 'draft';
    $youtube_url = trim($_POST['youtube_url'] ?? '');
    $thumbnail = trim($_POST['thumbnail'] ?? '');

    // validasi youtube
    if ($youtube_url !== '' && !preg_match('#^(https?://)?(www\.)?(youtube\.com/watch\?v=|youtu\.be/)#i', $youtube_url)) {
        $error = 'Link YouTube tidak valid.';
    }

    // Slug logic: jika user tidak mengubah slug, tetap pakai original
    if ($slugInput === '' || $slugInput === $originalSlug) {
        $slug = $originalSlug;
    } else {
        $candidate = slugify($slugInput);
        if ($candidate === '') {
            $error = 'Slug tidak boleh kosong.';
        } else {
            // Pastikan unik (fungsi ensure_unique_slug harus ada di includes/bootstrap.php)
            $slug = ensure_unique_slug($pdo, $candidate);
        }
    }

    // is_auto_slug handling
    $is_auto_slug = ($post['is_auto_slug'] ? 1 : 0);
    if ($post['is_auto_slug'] && ($slugInput !== '' || $title !== $post['title'])) {
        $is_auto_slug = 0;
    }

    // validasi minimal
    if ($error === '') {
        if (! $slug || ! $category_id || ! is_numeric($category_id)) {
            $error = 'Data incomplete or invalid.';
        }
    }

    if ($error === '') {
        $stmt = $pdo->prepare("UPDATE posts SET title=?, slug=?, content=?, excerpt=?, thumbnail=?, category_id=?, status=?, youtube_url=?, is_auto_slug=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([
            $title,
            $slug,
            $contentInput,
            $excerpt,
            $thumbnail,
            $category_id,
            $status,
            $youtube_url,
            $is_auto_slug,
            $id
        ]);

        // beri response JSON untuk AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Changes saved successfully.']);
        exit;
    }

    // error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $error ?: 'Data incomplete or invalid.']);
    exit;
}

/* --- Render form (GET) --- */
ob_start();
?>

<?php if ($post['is_auto_slug'] && $post['status'] === 'draft'): ?>
  <p style="color:red;">Slug masih otomatis. Sebaiknya ubah sebelum dipublikasikan.</p>
<?php endif; ?>

<h1>Edit Post</h1>

<form method="POST" id="post-edit-form" enctype="multipart/form-data">
    <p>Title: <input type="text" id="post-title" name="title" value="<?= htmlspecialchars($_POST['title'] ?? $post['title']) ?>"></p>

    <p>
      Slug:
      <input type="text" id="post-slug" name="slug" value="<?= htmlspecialchars($_POST['slug'] ?? $post['slug']) ?>" readonly style="width:70%;">
      <button type="button" id="edit-slug" style="width:28%;">Edit</button>
      <div id="post-slug-status" style="margin-top:6px;color:#666;">Silahkan tuliskan judul</div>
    </p>

    <p>Kategori:
        <select name="category_id" required>
            <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id'] == ($post['category_id'] ?? null) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>Excerpt: <textarea rows="2" name="excerpt"><?= htmlspecialchars($_POST['excerpt'] ?? $post['excerpt']) ?></textarea></p>

    <!-- Thumbnail upload UI (sama seperti add.php) -->
    <div class="thumbnail-upload-wrapper">
      <label class="firstlog-label">Thumbnail Post</label>
      <div class="firstlog-photo-controls">
        <div class="firstlog-photo-preview">
          <?php if (!empty($_POST['thumbnail'] ?? $post['thumbnail'])): ?>
            <img id="thumbnail-preview-img" src="<?= htmlspecialchars($_POST['thumbnail'] ?? $post['thumbnail']) ?>" alt="Preview Thumbnail">
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
          <input id="thumbnail-url" name="thumbnail" type="text" class="firstlog-input" value="<?= htmlspecialchars($_POST['thumbnail'] ?? $post['thumbnail']) ?>" placeholder="URL thumbnail (otomatis terisi setelah upload)">
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
             placeholder="https://www.youtube.com/watch?v=B2D95G0zSAo"
             value="<?= htmlspecialchars((string)($_POST['youtube_url'] ?? $post['youtube_url']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </p>

    <p>Isi Konten:</p>
    <div id="editor-container" style="height: 300px;"></div>
    <input type="hidden" name="content" id="content-input">

    <p>
      Status:
      <select name="status">
        <option value="draft" <?= (($_POST['status'] ?? $post['status']) === 'draft') ? 'selected' : '' ?>>Draft</option>
        <option value="published" <?= (($_POST['status'] ?? $post['status']) === 'published') ? 'selected' : '' ?>>Published</option>
      </select>
    </p>

    <div id="errorBox" style="margin-bottom:1em; color:red;"></div>
    <button type="submit" id="submitBtn">Save Changes</button>
    <a href="./">← Kembali</a>
</form>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<!-- reuse profile upload helper jika ada -->
<script src="/dashboard/profile/profile-upload.js"></script>

<script>
// ---------- Thumbnail upload (mirip add.php) ----------
const thumbFileInput = document.getElementById('thumbnail-file');
const thumbUploadBtn = document.getElementById('thumbnail-upload-btn');
const thumbPreviewImg = document.getElementById('thumbnail-preview-img');
const thumbPlaceholder = document.getElementById('thumbnail-preview-placeholder');
const thumbUrlInput = document.getElementById('thumbnail-url');
const thumbStatusBox = document.getElementById('thumbnail-status');

thumbFileInput && thumbFileInput.addEventListener('change', () => {
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

thumbUploadBtn && thumbUploadBtn.addEventListener('click', () => {
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

// ---------- Slug utilities and remote check (edit-aware) ----------
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

(function(){
  const title = document.getElementById('post-title');
  const slug = document.getElementById('post-slug');
  const editBtn = document.getElementById('edit-slug');
  const status = document.getElementById('post-slug-status');
  if (!title || !slug) return;

  const currentId = <?= (int)$post['id'] ?>;
  const originalSlug = <?= json_encode($post['slug']) ?>;
  // kalau post dibuat dengan is_auto_slug==1 maka biarkan auto-update saat title berubah
  // jika is_auto_slug==0 maka jangan auto-update slug saat title berubah
  let userEdited = <?= $post['is_auto_slug'] ? 'false' : 'true' ?>;

  const remoteCheck = (val) => {
    const s = slugify(val || '');
    if (!s) {
      status.textContent = 'Silahkan tuliskan judul';
      status.style.color = '#888';
      return;
    }

    // kirim type=post supaya endpoint meng-exclude row post yg sama
    const url = '/dashboard/admin/content/check-slug-edit.php?slug=' + encodeURIComponent(s)
              + '&type=post&exclude_id=' + encodeURIComponent(currentId);

    fetch(url)
      .then(r => {
        if (!r.ok) throw new Error('Cek slug gagal');
        return r.json();
      })
      .then(j => {
        if (!j.ok) { status.textContent = 'Error saat cek slug'; status.style.color = 'red'; return; }

        // jika yang dicek sama dengan originalSlug, tetap tampil tersedia
        if (s === originalSlug) {
          status.textContent = 'Ini slug asli post (tidak perlu diubah)';
          status.style.color = 'green';
          return;
        }

        if (j.available) {
          status.textContent = 'Tersedia';
          status.style.color = 'green';
        } else {
          status.textContent = 'Sudah ada, saran: ' + (j.suggested || '');
          status.style.color = 'orange';
        }
      })
      .catch(()=>{ status.textContent = 'Cek gagal'; status.style.color = 'red'; });
  };

  // title input: hanya update slug bila user belum manually edit AND post awalnya auto-slug
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
    remoteCheck(slug.value);
  });

  slug.addEventListener('input', function(){
    userEdited = true;
    remoteCheck(this.value);
  });

  if (editBtn) {
    editBtn.addEventListener('click', function(){
      if (slug.hasAttribute('readonly')) {
        // unlock -> user will edit slug manually
        slug.removeAttribute('readonly');
        slug.focus();
        userEdited = true;
        this.textContent = 'Lock';
      } else {
        // lock -> keep whatever slug user left; prevent auto-update afterwards
        slug.setAttribute('readonly','readonly');
        this.textContent = 'Edit';
        slug.value = slugify(slug.value);
        userEdited = true; // tetap true supaya perubahan judul tidak lagi mengubah slug
        remoteCheck(slug.value);
      }
    });
  }

  // init: cek slug hanya bila ada nilai; remoteCheck akan memanggil endpoint dengan exclude_id sehingga original tidak ditandai sebagai conflict
  (function init(){
    const initial = slug.value || title.value || '';
    if (initial) remoteCheck(initial);
  })();

})();

// ---------- Quill setup + image uploader (500KB limit like add.php) ----------
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

      const MAX_BYTES = 500 * 1024; // 500KB
      const allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];
      const errorBox = document.querySelector('#errorBox');

      if (file.size > MAX_BYTES) {
        if (errorBox) {
          errorBox.style.color = 'red';
          errorBox.innerHTML = 'Ukuran maksimal 500KB.';
        }
        return;
      }

      if (!allowedTypes.includes(file.type)) {
        if (errorBox) {
          errorBox.style.color = 'red';
          errorBox.innerHTML = 'File format not allowed.';
        }
        return;
      }

      const formData = new FormData();
      formData.append('file', file);

      if (errorBox) {
        errorBox.style.color = 'black';
        errorBox.innerHTML = 'Uploading images...';
      }

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
        const range = quill.getSelection() || { index: quill.getLength() };
        quill.insertEmbed(range.index, 'image', data.url);
        if (errorBox) errorBox.innerHTML = '';
      })
      .catch(err => {
        if (errorBox) {
          errorBox.style.color = 'red';
          errorBox.innerHTML = err.message || 'An error occurred during upload.';
        }
      });
    };
  }
}
    }
  }
});

// set initial content (ambil dari DB)
(function(){
  const initial = <?= json_encode($_POST['content'] ?? $post['content']) ?>;
  if (initial) {
    // safe assign HTML
    quill.root.innerHTML = initial;
  }
})();

// sync quill to hidden input (encoded)
function syncQuillToInput() {
  const contentInput = document.querySelector('#content-input');
  if (contentInput) {
    const rawHTML = quill.root.innerHTML;
    // encode sama seperti add.php agar aman (ModSecurity)
    contentInput.value = btoa(unescape(encodeURIComponent(rawHTML)));
  }
}

/* --- AJAX submit form --- */
(function(){
  const form = document.querySelector('#post-edit-form');
  if (!form) return;

  const submitBtn = document.querySelector('#submitBtn');
  const errorBox = document.querySelector('#errorBox');

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    syncQuillToInput();

    const fd = new FormData(this);

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';
    }
    if (errorBox) {
      errorBox.style.color = 'black';
      errorBox.innerHTML = 'Saving...';
    }

    // kirim ke edit.php (akan diproses sebagai JSON)
    // sertakan id di query agar server tahu resource; namun server sudah tahu id dari GET saat render
    fetch('edit.php?id=<?= (int)$post['id'] ?>', {
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
        submitBtn.textContent = 'Save Changes';
      }

      if (!errorBox) return;

      if (data.success) {
        errorBox.style.color = 'green';
        errorBox.innerHTML = '✅ Changes saved successfully.';
        setTimeout(() => window.location.href = './', 800);
      } else {
        errorBox.style.color = 'red';
        errorBox.innerHTML = data.message || 'Data incomplete or invalid.';
      }
    })
    .catch(err => {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Changes';
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
$pageTitle = 'Edit Post';
require_once __DIR__ . '/../../partials/layout.php';
