<?php
require_once __DIR__ . '/../includes/bootstrap.php';
define('DASHBOARD_CONTEXT', true);

$user = get_user_from_session($pdo);
if (!$user) {
  header('Location: /login/');
  exit;
}

$pageTitle = "Dashboard lernginx";

$modul = $_GET['modul'] ?? null;
$slug = $_GET['slug'] ?? null;
$cat_path = $_GET['cat_path'] ?? null;

// 🔍 Helper: jumlah modul aktif siswa
function get_active_module_count($pdo, $user_id) {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE user_id = ? AND status = 0");
  $stmt->execute([$user_id]);
  return (int) $stmt->fetchColumn();
}

// 🧭 Routing berdasarkan modul eksplisit
if ($modul === 'topic' && $slug) {
  $data = require __DIR__ . '/../modul/topic/index.php';
  render_dashboard($data['template'], $data['vars'], $data['title']);
}
elseif ($modul === 'post' && $slug) {
  $data = require __DIR__ . '/../modul/post/detail.php';
  render_dashboard($data['template'], $data['vars'], $data['title']);
}
elseif ($modul === 'programs' && !$slug) {
  $data = require __DIR__ . '/../modul/programs/index.php';
  render_dashboard($data['template'], $data['vars'], $data['title']);
}
elseif ($modul === 'programs' && $slug) {
  $data = require __DIR__ . '/../modul/programs/detail.php';
  render_dashboard($data['template'], $data['vars'], $data['title']);
}
else {
  // 🧑‍🎓 Jalur default untuk siswa
  if ($user['role'] === 'student') {
    $activeModules = get_active_module_count($pdo, $user['id']);

    if ($activeModules === 0) {
      // Belum punya modul aktif → beranda.php
      render_dashboard(__DIR__ . '/partials/beranda.php', compact('user'), 'Welcome');
    } else {
      // Sudah punya modul aktif → programs/index.php
      $data = require __DIR__ . '/../modul/programs/index.php';
      render_dashboard($data['template'], $data['vars'], $data['title']);
    }
} else {
  // 🏠 Default untuk non-siswa → home.php
  render_dashboard(__DIR__ . '/partials/home.php', compact('user'), 'Dashboard');
}
}
