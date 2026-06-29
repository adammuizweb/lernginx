<?php
// /modul/topic/index.php  (jika Anda merename file)

/* Standalone bootstrap jika perlu */
$standalone = !defined('DASHBOARD_CONTEXT');
if ($standalone) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
    define('DASHBOARD_CONTEXT', false);
}

/* Ambil user dari session jika belum ada (standalone mode) */
if (!isset($user) && isset($pdo)) {
    $user = get_user_from_session($pdo);
}

/* Akses: hanya user login dengan role 'teacher' atau 'student' boleh melihat */
$allowed_roles = ['teacher', 'student', 'admin'];
if (empty($user) || !in_array($user['role'] ?? '', $allowed_roles, true)) {
if ($standalone) {
    header('Location: /dashboard/');
    exit;
} else {
    // return a consistent shape so the caller doesn't get NULL
    return [
        'template' => null,
        'vars'     => ['error' => 'Access denied. Only teachers or students can view this page.'],
        'title'    => 'Access Denied'
    ];
}
}

/* === Ambil slug (kompatibel rewrite, PATH_INFO, REQUEST_URI) === */
$slug = null;

if (!empty($_GET['slug'])) {
    $slug = $_GET['slug'];
}

if (!$slug && !empty($_SERVER['PATH_INFO'])) {
    $slug = trim($_SERVER['PATH_INFO'], '/');
}

if (!$slug && !empty($_SERVER['REQUEST_URI'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $parts = array_values(array_filter(explode('/', $uri)));
    while (!empty($parts)) {
        $candidate = array_pop($parts);
        // skip common technical segments
        if ($candidate === 'index.php' || $candidate === 'kategori' || $candidate === 'topic' || $candidate === 'modul' || $candidate === 'dashboard') {
            continue;
        }
        $slug = $candidate;
        break;
    }
}

if ($slug === 'index.php' || $slug === '') {
    if (isset($_GET['slug']) && $_GET['slug'] !== 'index.php' && $_GET['slug'] !== '') {
        $slug = $_GET['slug'];
    } else {
        $slug = null;
    }
}

if (!$slug) {
    if ($standalone) {
        http_response_code(400);
        exit('Topic slug not provided.');
    } else {
        echo '<p>Topic slug not provided.</p>';
        return;
    }
}

$slug = trim($slug);

/* Fetch category (topic) */
$category = get_category_by_slug($pdo, $slug);
if (!$category) {
    if ($standalone) {
        http_response_code(404);
        exit('Category (topic) not found.');
    } else {
        echo '<p>Category (topic) not found.</p>';
        return;
    }
}

/* Fetch category IDs (self + descendants) */
$cat_ids = [$category['id']];
/* cuma spesifik */


/* Ambil posts */
$posts = get_posts_by_category_ids($pdo, $cat_ids);

/* === Siapkan HTML / vars tanpa echo langsung === */
$html = '';
if (empty($posts)) {
    $html .= "<p>No posts in this category yet.</p>";
} else {
    $html .= '<ul>';
    foreach ($posts as $post) {
        // pastikan helper permalink memakai 'topic' (lihat helpers.php)
        $url = get_dashboard_post_url($post);
        $title = htmlspecialchars($post['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $href = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= "<li><a href=\"{$href}\">{$title}</a></li>";
    }
    $html .= '</ul>';
}

/* Jika dipanggil langsung (standalone/public), echo hasilnya */
if ($standalone) {
    echo $html;
    return;
}

/* When included by dashboard, return template + vars without echoing */
return [
    'template' => dirname(__DIR__, 2) . '/dashboard/partials/topic-view.php',
'vars' => [
    'title_safe' => htmlspecialchars($category['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'posts' => $posts,
    'category' => $category,
    'category_id' => $category['id'],
    'info' => $category['info'] ?? null
],
    'title' => 'Category: ' . $category['name']
];













