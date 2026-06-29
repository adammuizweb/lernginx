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
<h1>⚙️ Admin Panel</h1>
<p>Manage content and learning materials below.</p>

<div class="admin-grid">
  <a href="/dashboard/admin/materi.php" class="admin-box">
    <div class="admin-box-icon">✏️</div>
    <div class="admin-box-title">Write Material</div>
    <div class="admin-box-desc">Create and manage learning materials.</div>
  </a>
  <!-- Kotak baru: Assign -->
  <a href="/dashboard/admin/assign/" class="admin-box">
    <div class="admin-box-icon">🔓</div>
    <div class="admin-box-title">Assign Module</div>
    <div class="admin-box-desc">Register students to modules; manage enrollment status manually.</div>
  </a>

  <!-- Kotak baru: Monitoring -->
  <a href="/dashboard/admin/monitoring/" class="admin-box">
    <div class="admin-box-icon">📊</div>
    <div class="admin-box-title">Monitoring</div>
    <div class="admin-box-desc">View student module registrations and their status (Active / Pending).</div>
  </a>
  
  <?php if ($user['role'] === 'admin'): ?>
  <a href="/dashboard/admin/user-setting/" class="admin-box">
    <div class="admin-box-icon">👥</div>
    <div class="admin-box-title">User Settings</div>
    <div class="admin-box-desc">
      Kelola akun pengguna: ubah email, role, dan reset password.
    </div>
  </a>
<?php endif; ?>

  <?php if ($user['role'] === 'admin'): ?>
  <a href="/dashboard/admin/media/" class="admin-box">
    <div class="admin-box-icon">📷</div>
    <div class="admin-box-title"><span style="color: red;">Media</span> ⚠️</div>
    <div class="admin-box-desc">
      <span style="color: red;">⚠️ Media Management 🙇 Still in Development 🥲</span>
    </div>
  </a>
<?php endif; ?>

  <a href="/dashboard/admin/program.php" class="admin-box">
    <div class="admin-box-icon">🖼️</div>
    <div class="admin-box-title">Edit Program Display</div>
    <div class="admin-box-desc">Edit media and descriptions of programs displayed to students.</div>
  </a>

<a href="/dashboard/admin/publik.php" class="admin-box">
  <div class="admin-box-icon">🔊️</div>
  <div class="admin-box-title">Public Articles</div>
  <div class="admin-box-desc">Create articles for the public front page.</div>
</a>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Admin - Dashboard';
require_once __DIR__ . '/../partials/layout.php';
