<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Submenu Editor';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

$map_file = __DIR__ . '/children.json';
$map = file_exists($map_file) ? json_decode(file_get_contents($map_file), true) : [];
$map = is_array($map) ? $map : [];

ob_start();
?>

<h1>📁 Submenu Editor</h1>
<p>Edit media and descriptions of subtopics (child categories).</p>

<div style="display: flex; gap: 1em; flex-wrap: wrap; margin-bottom: 2em;">
  <form method="POST" action="sync.php" style="flex: 1 1 200px;">
    <button type="submit" style="width: 100%">🔄 Sync Submenu from Categories</button>
  </form>

  <form method="POST" action="cleanup.php" style="flex: 1 1 200px;">
    <button type="submit" style="width: 100%">🧹 Clean Up Unused Submenu</button>
  </form>
</div>


<?php if (empty($map)): ?>
  <p>No submenu found. Add child categories to create new submenu.</p>
<?php else: ?>
  <form method="POST" action="save.php">
    <?php foreach ($map as $slug => $data): ?>
      <fieldset style="margin-bottom:2em">
        <legend><?= htmlspecialchars($slug) ?> (Parent: <?= htmlspecialchars($data['parent']) ?>)</legend>

        <label>Jenis Media:
          <select name="map[<?= $slug ?>][type]">
            <?php foreach (['lottie', 'image', 'svg', 'gif'] as $type): ?>
              <option value="<?= $type ?>" <?= ($data['type'] ?? '') === $type ? 'selected' : '' ?>><?= strtoupper($type) ?></option>
            <?php endforeach; ?>
          </select>
        </label><br>

        <label>URL:
          <input type="text" name="map[<?= $slug ?>][url]" value="<?= htmlspecialchars($data['url'] ?? '') ?>" style="width:100%">
        </label><br>

        <label>Deskripsi:
          <input type="text" name="map[<?= $slug ?>][desc]" value="<?= htmlspecialchars($data['desc'] ?? '') ?>" style="width:100%">
        </label><br>

        <label>Active Status:
          <select name="map[<?= $slug ?>][active]">
            <option value="1" <?= !empty($data['active']) ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= empty($data['active']) ? 'selected' : '' ?>>Inactive</option>
          </select>
        </label>
      </fieldset>
    <?php endforeach; ?>
    <button type="submit">💾 Save All</button>
  </form>
<?php endif; ?>

<?php
$content = ob_get_clean();
$pageTitle = 'Submenu Editor';
require_once __DIR__ . '/../../partials/layout.php';
