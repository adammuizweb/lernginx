<?php
// dashboard/admin/tags/edit.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

$user = get_user_from_session($pdo);
if ( ! $user || ! in_array($user['role'], ['teacher','admin']) ) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Edit Tag';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (! $id) {
    $content = '<p>Tag not found.</p>';
    $pageTitle = 'Edit Tag';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// ambil tag
$stmt = $pdo->prepare("SELECT * FROM tags WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$tag = $stmt->fetch(PDO::FETCH_ASSOC);
if (! $tag) {
    $content = '<p>Tag not found.</p>';
    $pageTitle = 'Edit Tag';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// POST: proses update (AJAX JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['success' => false, 'message' => 'Tag name is required.']);
        exit;
    }

    // slugify: prefer existing global slugify if present, else pages_slugify, else simple fallback
    if ($slugInput === '') {
        // generate from name
        if (function_exists('slugify')) $candidate = slugify($name);
        elseif (function_exists('pages_slugify')) $candidate = pages_slugify($name);
        else {
            $candidate = preg_replace('~[^\pL\d]+~u', '-', $name);
            $candidate = iconv('utf-8', 'us-ascii//TRANSLIT', $candidate) ?: $candidate;
            $candidate = preg_replace('~[^-\w]+~', '', $candidate);
            $candidate = preg_replace('~-+~', '-', $candidate);
            $candidate = strtolower(trim($candidate, '-'));
            if ($candidate === '') $candidate = 'tag';
        }
    } else {
        if (function_exists('slugify')) $candidate = slugify($slugInput);
        elseif (function_exists('pages_slugify')) $candidate = pages_slugify($slugInput);
        else {
            $candidate = preg_replace('~[^\pL\d]+~u', '-', $slugInput);
            $candidate = iconv('utf-8', 'us-ascii//TRANSLIT', $candidate) ?: $candidate;
            $candidate = preg_replace('~[^-\w]+~', '', $candidate);
            $candidate = preg_replace('~-+~', '-', $candidate);
            $candidate = strtolower(trim($candidate, '-'));
        }
    }

    // ensure unique slug for tags (exclude current id)
    $base = $candidate;
    $i = 0;
    while (true) {
        $candidateTry = $i === 0 ? $base : ($base . '-' . $i);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tags WHERE slug = ? AND id != ?");
        $stmt->execute([$candidateTry, $id]);
        $cnt = (int)$stmt->fetchColumn();
        if ($cnt === 0) { $finalSlug = $candidateTry; break; }
        $i++;
    }

    // update
    $stmt = $pdo->prepare("UPDATE tags SET name = :name, slug = :slug, description = :desc, updated_at = NOW() WHERE id = :id");
    $stmt->execute([
        ':name' => $name,
        ':slug' => $finalSlug,
        ':desc' => $description !== '' ? $description : null,
        ':id' => $id,
    ]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Tag updated successfully.']);
    exit;
}

// GET: render form
ob_start();
?>
<h1>Edit Tag</h1>

<form id="tag-edit-form" method="POST">
  <p>Name: <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? $tag['name'] ?? '') ?>"></p>
  <p>Slug: <input type="text" name="slug" id="slug-input" value="<?= htmlspecialchars($_POST['slug'] ?? $tag['slug'] ?? '') ?>"></p>
  <p>Description:<br><textarea name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? $tag['description'] ?? '') ?></textarea></p>

  <div id="errorBox" style="color:red;margin-bottom:1em;"></div>
  <button type="submit" id="submitBtn">Save</button>
  <a href="index.php">← Back</a>
</form>

<script>
// optional client slug helper for UX
function slugifyClient(s) {
  s = String(s || '').trim().toLowerCase();
  s = s.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
  s = s.replace(/[^a-z0-9\s-]/g, '');
  s = s.replace(/\s+/g, '-');
  s = s.replace(/-+/g, '-');
  s = s.replace(/^-+|-+$/g, '');
  return s;
}

(function(){
  const form = document.getElementById('tag-edit-form');
  const btn = document.getElementById('submitBtn');
  const errorBox = document.getElementById('errorBox');
  const nameInput = form.querySelector('[name="name"]');
  const slugInput = document.getElementById('slug-input');

  // auto fill slug when name changes and slug empty
  nameInput.addEventListener('input', () => {
    if (slugInput.value.trim() === '') {
      slugInput.value = slugifyClient(nameInput.value);
    }
  });

  form.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
    if (errorBox) { errorBox.style.color = 'black'; errorBox.textContent = 'Saving...'; }

    fetch('edit.php?id=<?= (int)$id ?>', { method: 'POST', body: fd })
      .then(res => {
        if (!res.ok) return res.text().then(txt => { throw new Error(txt || 'Connection failed'); });
        return res.json();
      })
      .then(json => {
    if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
    if (json.success) {
      if (errorBox) { errorBox.style.color = 'green'; errorBox.textContent = '✅ Tag saved successfully.'; }
          setTimeout(()=> window.location.href = 'index.php', 700);
        } else {
          if (errorBox) { errorBox.style.color = 'red'; errorBox.textContent = json.message || 'Failed to save.'; }
        }
      })
      .catch(err => {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
        if (errorBox) { errorBox.style.color = 'red'; errorBox.textContent = err.message || 'Connection error.'; } else console.error(err);
      });
  });
})();
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Edit Tag';
require_once __DIR__ . '/../../partials/layout.php';
