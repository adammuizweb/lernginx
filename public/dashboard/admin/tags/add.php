<?php
// dashboard/admin/tags/add.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

// auth
$user = get_user_from_session($pdo);
if (! $user || ! in_array($user['role'], ['teacher','admin'])) {
    $content = '<p>Access denied.</p>';
$pageTitle = 'Add Tag';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['success' => false, 'message' => 'Tag name is required.']);
        exit;
    }

    // gunakan helper untuk find-or-create
    try {
        $tag = tag_find_or_create_by_name($pdo, $name);
        if ($tag && isset($tag['id'])) {
            // optionally update slug/description if provided and different
            $updateFields = [];
            $params = [];
            if ($slugInput !== '' && ($tag['slug'] ?? '') !== $slugInput) {
                $updateFields[] = 'slug = :slug';
                $params[':slug'] = $slugInput;
            }
            if ($description !== '' && ($tag['description'] ?? '') !== $description) {
                $updateFields[] = 'description = :desc';
                $params[':desc'] = $description;
            }
            if (!empty($updateFields)) {
                $params[':id'] = (int)$tag['id'];
                $sql = 'UPDATE tags SET ' . implode(', ', $updateFields) . ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'id' => (int)$tag['id']]);
            exit;
        } else {
            throw new Exception('Failed to create tag.');
        }
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// GET: render simple form
ob_start();
?>
<h1>Add New Tag</h1>

<form id="tagForm" method="POST">
  <p>Name: <input type="text" name="name" required></p>
  <p>Slug (optional): <input type="text" name="slug"></p>
  <p>Description:<br><textarea name="description" rows="4"></textarea></p>

  <div id="errorBox" style="color:red;margin-bottom:1em;"></div>
  <button type="submit" id="submitBtn">Save</button>
  <a href="index.php">Back</a>
</form>

<script>
(function(){
  const form = document.getElementById('tagForm');
  const btn = document.getElementById('submitBtn');
  const errorBox = document.getElementById('errorBox');
  form.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
    if (errorBox) { errorBox.style.color = 'black'; errorBox.textContent = 'Saving...'; }

    fetch('add.php', { method: 'POST', body: fd })
      .then(res => {
        if (!res.ok) return res.text().then(txt => { throw new Error(txt || 'Failed'); });
        return res.json();
      })
      .then(json => {
    if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
    if (json.success) {
      if (errorBox) { errorBox.style.color = 'green'; errorBox.textContent = '✅ Tag saved successfully.'; }
          setTimeout(() => window.location.href = 'index.php', 600);
        } else {
          if (errorBox) { errorBox.style.color = 'red'; errorBox.textContent = json.message || 'Failed to save.'; }
        }
      })
      .catch(err => {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
        if (errorBox) { errorBox.style.color = 'red'; errorBox.textContent = err.message || 'Connection error.'; }
      });
  });
})();
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Add Tag';
require_once __DIR__ . '/../../partials/layout.php';
