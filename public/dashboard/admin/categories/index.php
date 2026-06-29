<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if ( ! $user || !in_array($user['role'], ['teacher', 'admin']) ) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Categories - Dashboard';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// Ambil semua kategori
$stmt = $pdo->query("
    SELECT c.*, p.name AS parent_name
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    ORDER BY p.name ASC, c.name ASC
");
$categories = $stmt->fetchAll();

// Tangkap output HTML sebagai $content
ob_start();
?>
<?php if ($_GET['delete'] ?? null === 'success'): ?>
    <div class="alert alert-success">✅ Category deleted successfully.</div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div style="padding:10px;background:#e0ffe0;border:1px solid #b2d8b2;color:#2d662d;margin-bottom:16px;">
    <?= htmlspecialchars($_SESSION['flash']) ?>
  </div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
<h1>📁 Category List</h1>
<p><a href="add.php" class="btn add">+ Add Category</a></p>
<table class="content-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Parent</th>
            <th>Created</th>
            <th>Last Updated</th>
            <th>On/Off</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($categories)): ?>
        <tr><td colspan="6" style="text-align:center;">No categories yet.</td></tr>
    <?php else: ?>
        <?php foreach ($categories as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['name']) ?></td>
            <td><?= htmlspecialchars($c['slug']) ?></td>
            <td><?= htmlspecialchars($c['parent_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($c['created_at']) ?></td>
            <td><?= htmlspecialchars($c['updated_at'] ?? '-') ?></td>
            <td>
  <form method="post" action="toggle_show_posts.php" style="display:inline;">
    <input type="hidden" name="id" value="<?= $c['id'] ?>">
    <input type="hidden" name="redirect" value="index.php">
    <input type="checkbox" name="show_posts" value="1"
           onchange="this.form.submit()" <?= $c['show_posts'] ? 'checked' : '' ?>>
  </form>
</td>
            <td>
                <a href="edit.php?id=<?= $c['id'] ?>" class="btn edit">Edit</a>
                <a href="delete.php?id=<?= $c['id'] ?>" class="btn delete"
                   onclick="return confirm('Delete this category? Subcategories will not be affected.')">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
$pageTitle = 'Categories - Dashboard';
require_once __DIR__ . '/../../partials/layout.php';
