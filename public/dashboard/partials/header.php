<?php
if (!defined('DASHBOARD_CONTEXT')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

$user = get_user_from_session($pdo);
?>
<header class="dashboard-header" role="banner">
  <h1>lernginx Dashboard</h1>

  <button id="menu-toggle" class="btn btn-icon menu-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="dashboard-nav" type="button">
    <span class="hamburger" aria-hidden="true">☰</span>
  </button>

  <nav id="dashboard-nav" class="dashboard-nav" role="navigation" aria-hidden="false">
    <a href="/dashboard/">🏠 Home</a>
    <a href="/dashboard/profile/">👤 Profile</a>
<?php if ( $user && in_array($user['role'], ['teacher', 'admin']) ) : ?>
  <a href="/dashboard/admin/">⚙️ Admin</a>
<?php endif; ?>
    <?php if ($user && $user['role'] === 'student') : ?>
      <a href="/dashboard/student/">📚 Modules</a>
    <?php endif; ?>
    <a href="/logout/">🚪 Logout</a>

    <button id="theme-toggle" class="btn btn-icon" type="button" aria-pressed="false" aria-label="Toggle theme" title="Toggle theme (t)">🌓</button>
  </nav>
</header>
