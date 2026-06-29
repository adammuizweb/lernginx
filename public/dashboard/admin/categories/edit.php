<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'admin') {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Edit Category';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    $content = '<p>ID tidak valid.</p>';
    $pageTitle = 'Edit Category';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// Ambil kategori
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();
if (!$category) {
    $content = '<p>Kategori tidak ditemukan.</p>';
    $pageTitle = 'Edit Category';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// Ambil semua kategori lain untuk dropdown parent
$allCatsStmt = $pdo->prepare("SELECT id, name, parent_id FROM categories WHERE id != ? ORDER BY name ASC");
$allCatsStmt->execute([$id]);
$allCats = $allCatsStmt->fetchAll();

// buat list id yang merupakan child (punya parent_id)
$childIds = [];
foreach ($allCats as $c) {
    if (!empty($c['parent_id'])) {
        $childIds[] = (int)$c['id'];
    }
}


$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ambil slug asli dari DB sebelum POST
    $originalSlug = $category['slug'];

    // ambil input
    $name = trim($_POST['name'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $description = trim($_POST['description'] ?? '');
$info_raw = '';
if (!empty($_POST['info'])) {
  $maybe = $_POST['info'];
  $decoded = @base64_decode($maybe, true);
  $info_raw = $decoded !== false ? $decoded : $maybe;
}
$info_clean = sanitize_quill($info_raw);
    if ($slugInput === '' || $slugInput === $originalSlug) {
        $slug = $originalSlug;
    } else {
        $candidate = slugify($slugInput);
        if ($candidate === '') {
            $error = '<p style="color:red;">Slug tidak boleh kosong.</p>';
        } else {
            $slug = ensure_unique_slug($pdo, $candidate);
        }
    }

if ($error === '') {
  $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, parent_id = ?, description = ?, info = ?, updated_at = NOW() WHERE id = ?");
  $stmt->execute([
    $name,
    $slug,
    $parent_id,
    $description,
    $info_clean, // ← gunakan versi clean
    $category['id']
  ]);
        $_SESSION['flash'] = '✅ Category changes saved successfully.';
        header('Location: ./');
        exit;
    }
}

// Tangkap output form sebagai $content
ob_start();
?>

<h1>Edit Category</h1>

<?php if ($error): ?>
  <?= $error ?>
<?php endif; ?>

<form method="POST" id="category-edit-form">
    <p>Name:<br>
        <input type="text" id="cat-name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? $category['name']) ?>">
    </p>

    <p>
      Slug:<br>
      <input type="text" id="cat-slug" name="slug" readonly style="width:80%;" value="<?= htmlspecialchars($_POST['slug'] ?? $category['slug']) ?>">
      <button type="button" id="edit-cat-slug" style="width:18%;">Edit</button>
      <div id="cat-slug-status" style="margin-top:6px;color:#666;">Please write the category name</div>
    </p>

    <p>Parent:<br>
        <select name="parent_id">
            <option value="">-- None --</option>
            <?php foreach ($allCats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ((isset($_POST['parent_id']) ? $_POST['parent_id'] : $category['parent_id']) == $c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>Description:<br>
        <textarea name="description"><?= htmlspecialchars($_POST['description'] ?? $category['description']) ?></textarea>
    </p>

<label for="info-editor">Additional Info</label>
<div id="info-editor" style="height:200px;"><?= $category['info'] ?? '' ?></div>
<input type="hidden" name="info" id="info-input">
<div id="errorBox" style="margin-top:10px; color:red;"></div>
    <p>
        
        <button type="submit">Save</button>
        <a href="./">Cancel</a>
    </p>
</form>
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<script>
  const initialInfo = <?= json_encode(
    isset($_POST['info']) ? base64_decode($_POST['info']) : ($category['info'] ?? '')
  ) ?>;

  const infoQuill = new Quill('#info-editor', {
    theme: 'snow',
    placeholder: 'Tulis info tambahan...',
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
            input.type = 'file';
            input.accept = 'image/*';
            input.click();

            input.onchange = () => {
              const file = input.files && input.files[0];
              if (!file) return;

              const MAX_BYTES = 500 * 1024;
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
                const range = infoQuill.getSelection() || { index: infoQuill.getLength() };
                infoQuill.insertEmbed(range.index, 'image', data.url);
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

  if (initialInfo) {
    try {
      infoQuill.root.innerHTML = initialInfo;
    } catch (e) {
      infoQuill.root.textContent = initialInfo;
    }
  }

  document.querySelector('#category-edit-form').addEventListener('submit', function() {
    const rawHTML = infoQuill.root.innerHTML || '';
    try {
      document.getElementById('info-input').value = btoa(unescape(encodeURIComponent(rawHTML)));
    } catch (err) {
      document.getElementById('info-input').value = rawHTML;
    }
  });
</script>
<script>
(function(){
  function slugify(str){
    str = String(str || '').trim().toLowerCase();
    str = str.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
    str = str.replace(/[^a-z0-9\s-]/g, '');
    str = str.replace(/\s+/g, '-');
    str = str.replace(/-+/g, '-');
    str = str.replace(/^-+|-+$/g, '');
    return str;
  }

  // current id tersedia dari server
  var currentId = <?= (int)$category['id'] ?>;
  var nameEl = document.getElementById('cat-name');
  var slugEl = document.getElementById('cat-slug');
  var editBtn = document.getElementById('edit-cat-slug');
  var status = document.getElementById('cat-slug-status');
  var userEdited = false;

  function checkSlugRemote(value){
    var s = slugify(value);
    if (!s) {
      status.textContent = 'Please write the category name';
      status.style.color = '#666';
      return;
    }
    fetch('/dashboard/admin/content/check-slug-edit.php?slug=' + encodeURIComponent(s)
          + '&type=category&exclude_id=' + encodeURIComponent(currentId))
      .then(r => r.json())
      .then(j => {
        if (!j.ok) { status.textContent = 'Error saat cek slug'; status.style.color = 'red'; return; }
        if (j.available) {
          status.textContent = 'Slug tersedia';
          status.style.color = 'green';
        } else {
          status.textContent = 'Sudah ada, saran: ' + j.suggested;
          status.style.color = 'orange';
        }
      })
      .catch(function(){
        status.textContent = 'Cek gagal';
        status.style.color = 'red';
      });
  }

  nameEl && nameEl.addEventListener('input', function(){
    if (userEdited) return;
    var v = this.value || '';
    var s = slugify(v);
    // Hati-hati: jangan overwrite slug jika pengguna sebelumnya mengedit slug manual
    slugEl.value = s;
    checkSlugRemote(s);
  });

  slugEl && slugEl.addEventListener('input', function(){
    userEdited = true;
    checkSlugRemote(this.value);
  });

  editBtn && editBtn.addEventListener('click', function(){
    if (slugEl.hasAttribute('readonly')) {
      slugEl.removeAttribute('readonly');
      slugEl.focus();
      this.textContent = 'Lock';
    } else {
      slugEl.setAttribute('readonly','readonly');
      this.textContent = 'Edit';
      slugEl.value = slugify(slugEl.value);
      checkSlugRemote(slugEl.value);
    }
  });

  // inisialisasi status dengan nilai awal (pakai check-slug-edit agar mengabaikan current row)
  (function init(){
    var initial = slugEl.value || nameEl.value || '';
    if (initial) checkSlugRemote(initial);
  })();
})();
</script>
<script>
(function(){
  // daftar id kategori yang adalah child (dari server)
  const childIds = <?= json_encode($childIds ?? []) ?> || [];

  const select = document.querySelector('select[name="parent_id"]');
  if (!select) return;

  // Non-aktifkan option yang merupakan child dan beri tanda
  for (let i = 0; i < select.options.length; i++) {
    const opt = select.options[i];
    if (!opt.value) continue; // skip pilihan kosong
    const id = Number(opt.value);
    if (childIds.indexOf(id) !== -1) {
      opt.disabled = true;
      try {
        opt.text = opt.text + ' — (kategori anak, tidak bisa dipilih sebagai induk)';
      } catch (e) { /* ignore */ }
    }
  }

  // Validasi saat submit (cadangan jika browser mengizinkan memilih option disabled)
  const form = document.getElementById('category-edit-form');
  const errorBox = document.getElementById('errorBox');
  if (!form) return;

  form.addEventListener('submit', function(e){
    const val = select.value;
    if (val && childIds.indexOf(Number(val)) !== -1) {
      e.preventDefault();
      if (errorBox) {
        errorBox.style.color = 'red';
        errorBox.textContent = 'Invalid parent selection — only main categories (without parent) can be selected as parent.';
      } else {
        alert('Invalid parent selection — only main categories (without parent) can be selected as parent.');
      }
      return false;
    }
  });
})();
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Edit Category';
require_once __DIR__ . '/../../partials/layout.php';
