<?php
// theme/index.php
require_once __DIR__ . '/../includes/bootstrap_front.php'; // harus load $pdo + helper

$page = $_GET['page'] ?? 'home';
$bodyClass = 'page-' . $page;

// apakah halaman ini termasuk yang punya hero nav?
// $useHeroNav = in_array($page, ['home','program','scivibe','glowverse','artskill','esport','moduls','offline','online','profile', 'page']);
$useHeroNav = true;
$bodyClass = 'page-' . $page;

// check if slug matches a main category (dynamic program page)
$categoryData = null;
$childCategories = [];
if (!function_exists('get_category_by_slug')) {
    require_once __DIR__ . '/../includes/bootstrap_front.php';
}
$catMatch = get_category_by_slug($pdo, $page);
if ($catMatch && $catMatch['parent_id'] === null) {
    $categoryData = $catMatch;
    $childCategories = get_child_categories($pdo, (int)$catMatch['id']);
}

$staticFile = __DIR__ . "/partials/main/{$page}.php";
$isStatic = file_exists($staticFile) && !$categoryData;
$mainFile = $categoryData ? (__DIR__ . "/partials/main/program-detail.php") : $staticFile;
$pageData = null;

// canonical
$currentSlug = $page;
$canonicalUrl = "https://lernginx.lan/" . ($currentSlug !== 'home' ? $currentSlug . '/' : '');

if (! $isStatic) {
    $pageData = get_page_by_slug($pdo, $page); // helper di bootstrap_front
    if ($pageData) {
        // ambil tags jika perlu
        $t = $pdo->prepare("SELECT t.id,t.name,t.slug FROM tags t JOIN page_tag pt ON pt.tag_id=t.id WHERE pt.page_id=? ORDER BY t.name");
        $t->execute([(int)$pageData['id']]);
        $pageData['tags'] = $t->fetchAll(PDO::FETCH_ASSOC);

        // gunakan template khusus untuk DB page
        $mainFile = __DIR__ . "/partials/main/page.php";
    } else {
        $mainFile = file_exists(__DIR__ . "/partials/main/404.php") ? __DIR__ . "/partials/main/404.php" : null;
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32x32.png">
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
  <title><?= isset($pageData['title']) ? htmlspecialchars($pageData['title']) . ' — lernginx' : 'lernginx' ?></title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/theme/partials/style.css">
  <link rel="stylesheet" href="/assets/animation/animation.css">
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
  <?php include THEME_PATH . '/partials/header.php'; ?>

  <main>
    <?php
    if ($categoryData) {
        include $mainFile;
    } elseif ($isStatic && $mainFile && file_exists($mainFile)) {
        include $mainFile;
    } elseif (!empty($pageData)) {
        include THEME_PATH . '/partials/main/page.php'; // template article untuk DB page
    } else {
        http_response_code(404);
        if ($mainFile && file_exists($mainFile)) include $mainFile;
        else echo '<section><h1>404</h1><p>Page not found.</p></section>';
    }
    ?>
  </main>

  <?php include THEME_PATH . '/partials/footer.php'; ?>
</body>
</html>
