<?php
// kalau mau pindah dir edit juga partials/program dan admin/categories/add
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Program Editor';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

$map_file = __DIR__ . '/programs.json';
$programs = file_exists($map_file) ? json_decode(file_get_contents($map_file), true) : [];
$programs = is_array($programs) ? $programs : [];

ob_start();
?>

<h1>Program Editor</h1>
<p>Edit media and descriptions of programs displayed on student pages.</p>

<div style="display: flex; gap: 1em; flex-wrap: wrap; margin-bottom: 2em;">
  <form method="POST" action="sync.php" style="flex: 1 1 200px;">
    <button type="submit" style="width: 100%">🔄 Sync Programs from Categories</button>
  </form>

  <form method="POST" action="cleanup.php" style="flex: 1 1 200px;">
    <button type="submit" style="width: 100%">🧹 Clean Up Unused Programs</button>
  </form>
</div>

<?php if (empty($programs)): ?>
  <p>No programs found. Add main categories to create new programs.</p>
<?php else: ?>
  <form method="POST" action="save.php">
    <?php foreach ($programs as $slug => $data): ?>
      <fieldset style="margin-bottom:2em">
        <legend><?= htmlspecialchars($slug) ?></legend>

        <label>Media Type:
          <select name="map[<?= $slug ?>][type]">
            <?php foreach (['lottie', 'image', 'svg', 'gif'] as $type): ?>
              <option value="<?= $type ?>" <?= ($data['type'] ?? '') === $type ? 'selected' : '' ?>><?= strtoupper($type) ?></option>
            <?php endforeach; ?>
          </select>
        </label><br>

        <label>URL:
          <input type="text" name="map[<?= $slug ?>][url]" value="<?= htmlspecialchars($data['url'] ?? '') ?>" style="width:100%">
        </label><br>

        <label>Description:
          <input type="text" name="map[<?= $slug ?>][desc]" value="<?= htmlspecialchars($data['desc'] ?? '') ?>" style="width:100%">
        </label><br>

        <!-- Preview -->
        <div style="margin-top:1em">
          <?php if (($data['type'] ?? '') === 'lottie'): ?>
            <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.8.5/dist/dotlottie-wc.js" type="module"></script>
            <dotlottie-wc src="<?= htmlspecialchars($data['url']) ?>" style="width:200px;height:200px" autoplay loop></dotlottie-wc>
          <?php else: ?>
            <img src="<?= htmlspecialchars($data['url']) ?>" alt="" style="max-width:200px;max-height:200px">
          <?php endif; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>

    <p><em>Programs are automatically added when main categories are created. Use the form above to edit media and descriptions.</em></p>
    <button type="submit">Save All</button>
  </form>
  
  
<?php endif; ?>

<?php
$content = ob_get_clean();
$pageTitle = 'Program Editor';
require_once __DIR__ . '/../../partials/layout.php';
