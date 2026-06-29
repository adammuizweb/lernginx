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
<h1>⚙️ Create Module Material</h1>
<p>Manage content and learning materials below.</p>

<div class="admin-grid">
  <a href="/dashboard/admin/content/" class="admin-box">
    <div class="admin-box-icon">📝</div>
    <div class="admin-box-title">Posts</div>
    <div class="admin-box-desc">Manage all published materials and articles.</div>
  </a>
    
    <?php if ($user['role'] === 'admin'): ?>
  <a href="/dashboard/admin/categories/" class="admin-box">
    <div class="admin-box-icon">📁</div>
    <div class="admin-box-title">Categories</div>
    <div class="admin-box-desc">Manage category structure and subtopics for learning programs.</div>
  </a>
 <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Admin - Dashboard';
require_once __DIR__ . '/../partials/layout.php';
