<?php
if (!defined('DASHBOARD_CONTEXT')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$user = get_user_from_session($pdo);
$programs = get_parent_categories($pdo);
$is_siswa = ($user['role'] ?? '') === 'student';
?>

<aside class="dashboard-sidebar">
  <ul>
    <li><a href="/dashboard/" class="<?= ($currentPath === '/dashboard' || $currentPath === '/dashboard/') ? 'active' : '' ?>">Dashboard</a></li>
    <?php if ($user && in_array($user['role'], ['teacher', 'admin'])): ?>
      <li><a href="/dashboard/admin/content/" class="<?= str_contains($currentPath, '/admin/content') ? 'active' : '' ?>">Post</a></li>
      <?php if ($user['role'] === 'admin'): ?>
        <li><a href="/dashboard/admin/categories/" class="<?= str_contains($currentPath, '/admin/categories') ? 'active' : '' ?>">Category</a></li>
      <?php endif; ?>
    <?php endif; ?>
  </ul>

  <hr>

  <h3>Programs</h3>
  <ul class="sidebar-programs">
    <li><a href="/dashboard/?modul=programs" class="<?= ($_GET['modul'] ?? '') === 'programs' ? 'active' : '' ?>">▸ All Programs</a></li>

<?php
foreach ($programs as $prog):
  $parent_id = $prog['id'];
  $parent_name = htmlspecialchars($prog['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  $parent_slug = htmlspecialchars($prog['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  $parent_url = "/dashboard/?modul=topic&slug={$parent_slug}";
  $cur = rtrim($currentPath, '/');
  $u = rtrim($parent_url, '/');
  $active = (str_starts_with($cur, $u)) ? 'active' : '';

  $children = get_child_categories($pdo, $parent_id);
  $has_parent_access = userHasDirectModule($pdo, $user['id'], $parent_id);
?>
  <li>
<?php if (!$is_siswa || $has_parent_access): ?>
  <a href="<?= $parent_url ?>" class="<?= $active ?>"><?= $parent_name ?></a>
<?php else: ?>
  <span class="disabled"><?= $parent_name ?></span>
<?php endif; ?>
  </li>

  <?php if (!empty($children)): ?>
    <ul class="sidebar-subprograms">
      <?php foreach ($children as $child):
        $child_name = htmlspecialchars($child['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $child_slug = htmlspecialchars($child['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $child_url = "/dashboard/?modul=topic&slug={$child_slug}";
        $child_active = (str_starts_with($cur, rtrim($child_url, '/'))) ? 'active' : '';
        $has_child_access = userHasDirectModule($pdo, $user['id'], $child['id']);
      ?>
        <li>
<?php if (!$is_siswa || $has_child_access): ?>
  <a href="<?= $child_url ?>" class="<?= $child_active ?>">↳ <?= $child_name ?></a>
<?php else: ?>
  <span class="disabled">↳ <?= $child_name ?></span>
<?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endforeach; ?>

  </ul>
</aside>
