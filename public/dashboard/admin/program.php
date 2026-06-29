<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if ( ! $user || !in_array($user['role'], ['teacher', 'admin']) ) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Admin - Dashboard';
    require_once __DIR__ . '/../partials/layout.php';
    exit;
}

// Tangkap output HTML sebagai $content
ob_start();
?>
<h1>⚙️ Kelola Tampilan Program</h1>
<p>Kelola konten dan kategori materi di bawah ini.</p>

<div class="admin-grid">
 <?php if ($user['role'] === 'admin'): ?>
<a href="/dashboard/admin/menu/" class="admin-box">
  <div class="admin-box-icon">🎨</div>
  <div class="admin-box-title">Program Editor</div>
  <div class="admin-box-desc">Edit media and descriptions of programs displayed to students.</div>
</a>
<?php endif; ?>

 <?php if ($user['role'] === 'admin'): ?>
<a href="/dashboard/admin/submenu/" class="admin-box">
  <div class="admin-box-icon">👶</div>
  <div class="admin-box-title">Subprogram Editor</div>
  <div class="admin-box-desc">Edit media and descriptions of subprograms displayed to students.</div>
</a>
<?php endif; ?>
<p>Still stored in JSON; may move to database in the future if category data grows too large.</p>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Admin - Dashboard';
require_once __DIR__ . '/../partials/layout.php';
