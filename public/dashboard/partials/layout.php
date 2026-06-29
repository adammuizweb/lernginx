<?php
// dasboard/partials/layout.php
if (!defined('DASHBOARD_CONTEXT')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

$user = get_user_from_session($pdo);
if (!$user) {
    header('Location: /login/');
    exit;
}

// $dashboardBase = '/lernginx/dashboard';
$dashboardBase = '/dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <script src="/assets/dashboard/main.js" defer></script>
  <link rel="stylesheet" href="/assets/dashboard/dashboard.css">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32x32.png">
  <link rel="stylesheet" href="/assets/animation/animation.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
  <?php include_once __DIR__ . '/header.php'; ?>

<?php
// ambil path tanpa query
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// normalisasi: hapus trailing slash
$uri = rtrim($uri, '/');

$dashboardRoots = [
    $dashboardBase,
    $dashboardBase . '/index.php',
];

if (!defined('FIRSTLOG_PARTIALS_INCLUDED')) {
    if (in_array($uri, $dashboardRoots, true)) {
        define('FIRSTLOG_PARTIALS_INCLUDED', true);
        if (file_exists(__DIR__ . '/first-login.php')) {
            require_once __DIR__ . '/first-login.php';
        }
        // congratulations.php removed on purpose
    }
}
?>
<div id="warningbhn-container"></div>
  <div class="dashboard-layout">
    <main class="content-area">
      <?= $content ?>
    </main>
    <?php include_once __DIR__ . '/sidebar.php'; ?>
  </div>

<?php include_once __DIR__ . '/footer.php'; ?>

<script src="/assets/dashboard/toast.js" defer></script>
<script src="/assets/dashboard/dashboard.js" defer></script>
<script src="/assets/animation/animation.js"></script>
<script src="/dashboard/profile/profile-upload.js" defer></script>
</body>
</html>
