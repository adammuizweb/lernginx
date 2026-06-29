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
<h1>⚙️ Public Articles</h1>
<p>Manage content and learning materials below.</p>

<div class="admin-grid">
  <a href="/dashboard/admin/pages/" class="admin-box">
    <div class="admin-box-icon">🗣️</div>
    <div class="admin-box-title">Create Article</div>
    <div class="admin-box-desc">Write articles for the public front page.</div>
  </a>
  <!-- Kotak baru: Assign -->
  <a href="/dashboard/admin/tags/" class="admin-box">
    <div class="admin-box-icon">🏷️</div>
    <div class="admin-box-title">Create Tags</div>
    <div class="admin-box-desc">Create article groupings based on tags.</div>
  </a>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Admin - Dashboard';
require_once __DIR__ . '/../partials/layout.php';
