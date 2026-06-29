<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'admin') {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Add Category';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// Ambil semua kategori untuk dropdown parent (ikutkan parent_id agar kita bisa tahu mana child)
$allCats = $pdo->query("SELECT id, name, parent_id FROM categories ORDER BY name ASC")->fetchAll();

// buat list id yang merupakan child (punya parent_id)
$childIds = [];
foreach ($allCats as $c) {
    if (!empty($c['parent_id'])) {
        $childIds[] = (int)$c['id'];
    }
}


$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $description = trim($_POST['description'] ?? '');
$info_raw = '';
if (!empty($_POST['info'])) {
  // jika client mengirim base64 seperti content/add.php
  $maybe = $_POST['info'];
  $decoded = @base64_decode($maybe, true);
  $info_raw = $decoded !== false ? $decoded : $maybe;
}

    $candidate = $slugInput !== '' ? slugify($slugInput) : slugify($name);
    $slug = ensure_unique_slug($pdo, $candidate);

    if ($name === '' || $slug === '') {
        $error = '<p style="color:red;">Nama kategori wajib diisi dan slug tidak boleh kosong.</p>';
    }

    if (!$error) {
            // Sanitasi konten Quill sebelum simpan
    $info_clean = sanitize_quill($info_raw);
        
        // Simpan ke database versi clean
    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id, description, info, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([ $name, $slug, $parent_id, $description, $info_clean ]);

        $safe_desc = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (empty($parent_id)) {
            // ✅ Kategori utama → programs.json
            $map_file = __DIR__ . '/../menu/programs.json';
            $map = file_exists($map_file) ? json_decode(file_get_contents($map_file), true) : [];

            if (!isset($map[$slug])) {
                $map[$slug] = [
                    'type' => 'image',
                    'url' => '',
                    'desc' => $safe_desc
                ];
                file_put_contents($map_file, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } else {
            // ✅ Kategori anak → children.json
            $parent_slug = get_category_by_id($pdo, $parent_id)['slug'] ?? null;
            if ($parent_slug) {
                $child_file = __DIR__ . '/../submenu/children.json';
                $child_map = file_exists($child_file) ? json_decode(file_get_contents($child_file), true) : [];

                if (!isset($child_map[$slug])) {
                    $child_map[$slug] = [
                        'parent' => $parent_slug,
                        'type' => 'image',
                        'url' => '',
                        'desc' => $safe_desc,
                        'active' => true
                    ];
                    file_put_contents($child_file, json_encode($child_map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        $_SESSION['flash'] = 'Category added successfully.';
        header('Location: ./');
        exit;
    }
}

// Tangkap output form sebagai $content
ob_start();
?>
<h1>Add Category</h1>
<?= $error ?>

<form method="POST" id="categoryForm">
    <p>Name:<br>
        <input type="text" id="cat-name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
    </p>

    <p>
      Slug:<br>
      <input type="text" id="cat-slug" name="slug" readonly style="width:80%;" value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>">
      <button type="button" id="edit-cat-slug" style="width:18%;">Edit</button>
      <div id="cat-slug-status" style="margin-top:6px;color:#666;">Please write the category name</div>
    </p>

    <p>Parent (optional):<br>
        <select name="parent_id">
            <option value="">-- None --</option>
            <?php foreach ($allCats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (isset($_POST['parent_id']) && $_POST['parent_id'] == $c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>Description:<br>
        <textarea name="description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </p>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<div>
  <label for="info-editor">Additional Info</label>
  <div id="info-editor" style="height:200px;"><?= /* kosongkan atau isi nanti via JS */ '' ?></div>
  <input type="hidden" name="info" id="info-input">
</div>

    <p>
        <div id="errorBox" style="margin-bottom:1em; color:red;"></div>
        <button type="submit">Save</button>
        <a href="./">Cancel</a>
    </p>
</form>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
  // Ambil nilai awal dari PHP (decode base64 jika POST ulang)
  const initialInfo = <?= json_encode(
    (isset($category) ? ($category['info'] ?? '') : '') ?: 
    (isset($_POST['info']) ? (base64_decode($_POST['info']) ?: '') : '')
  ) ?>;

  // Inisialisasi Quill
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
              const errorBox = document.querySelector('#errorBox');
              if (file.size > MAX_BYTES) {
                if (errorBox) {
                  errorBox.style.color = 'red';
                  errorBox.innerHTML = 'Ukuran maksimal 500KB.';
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
              .then(res => res.json())
              .then(data => {
                if (data.success && data.url) {
                  const range = infoQuill.getSelection() || { index: infoQuill.getLength() };
                  infoQuill.insertEmbed(range.index, 'image', data.url);
                  if (errorBox) errorBox.innerHTML = '';
                } else {
                  if (errorBox) {
                    errorBox.style.color = 'red';
                    errorBox.innerHTML = data.message || 'Failed to upload image.';
                  }
                }
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

  // Isi awal dari server
  if (initialInfo) {
    try {
      infoQuill.root.innerHTML = initialInfo;
    } catch (e) {
      infoQuill.root.textContent = initialInfo;
    }
  }

  // Sinkronisasi ke hidden input sebelum submit
  (function(){
    const form = document.querySelector('#categoryForm') || document.querySelector('form');
    if (!form) return;

    const hiddenInput = document.getElementById('info-input');
    if (!hiddenInput) return;

    form.addEventListener('submit', function() {
      const rawHTML = infoQuill.root.innerHTML || '';
      try {
        hiddenInput.value = btoa(unescape(encodeURIComponent(rawHTML)));
      } catch (err) {
        hiddenInput.value = rawHTML;
      }
    });
  })();
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

  var nameEl = document.getElementById('cat-name');
  var slugEl = document.getElementById('cat-slug');
  var editBtn = document.getElementById('edit-cat-slug');
  var status = document.getElementById('cat-slug-status');

  var userEdited = false;

  function checkSlugRemote(value){
    var s = slugify(value);
    if (!s) {
      status.textContent = 'Silahkan tuliskan nama kategori';
      status.style.color = '#666';
      return;
    }
    fetch('/dashboard/admin/content/check-slug.php?slug=' + encodeURIComponent(s))
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

  // inisialisasi status jika ada nilai awal
  (function init(){
    var initial = slugEl.value || nameEl.value || '';
    if (initial) {
      checkSlugRemote(initial);
    }
  })();
})();
</script>

<script>
(function(){
  // daftar id kategori yang adalah child (dari server)
  const childIds = <?= json_encode($childIds) ?> || [];

  const select = document.querySelector('select[name="parent_id"]');
  if (!select) return;

  // Non-aktifkan option yang merupakan child dan beri tanda
  for (let i = 0; i < select.options.length; i++) {
    const opt = select.options[i];
    if (!opt.value) continue; // skip pilihan kosong
    const id = Number(opt.value);
    if (childIds.indexOf(id) !== -1) {
      opt.disabled = true;
      // tambahkan keterangan pada teks option agar jelas
      try {
        opt.text = opt.text + ' — (kategori anak, tidak bisa dipilih sebagai induk)';
      } catch (e) {
        // ignore
      }
    }
  }

  // Validasi saat submit (jaga-jaga jika browser mengizinkan memilih option disabled)
  const form = document.getElementById('categoryForm') || document.querySelector('form');
  const errorBox = document.getElementById('errorBox');
  if (!form) return;

  form.addEventListener('submit', function(e){
    const val = select.value;
    if (val && childIds.indexOf(Number(val)) !== -1) {
      e.preventDefault();
      if (errorBox) {
        errorBox.style.color = 'red';
        errorBox.textContent = 'Pilihan Induk tidak valid — hanya kategori utama (tanpa parent) yang boleh dipilih sebagai induk.';
      } else {
        alert('Pilihan Induk tidak valid — hanya kategori utama (tanpa parent) yang boleh dipilih sebagai induk.');
      }
      return false;
    }
  });
})();
</script>


<?php
$content = ob_get_clean();
$pageTitle = 'Add Category';
require_once __DIR__ . '/../../partials/layout.php';
