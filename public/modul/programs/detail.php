<?php
$standalone = !defined('DASHBOARD_CONTEXT');
if ($standalone) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
    define('DASHBOARD_CONTEXT', true);
}

$slug = $_GET['slug'] ?? null;
if (!$slug) {
    http_response_code(404);
    return ['template' => __DIR__ . '/../../dashboard/partials/404/index.php', 'vars' => [], 'title' => 'Not Found'];
}

$program = get_category_by_slug($pdo, $slug);
if (!$program) {
    http_response_code(404);
    return ['template' => __DIR__ . '/../../dashboard/partials/404/index.php', 'vars' => [], 'title' => 'Not Found'];
}

$children = get_child_categories($pdo, (int)$program['id']);
$posts = get_posts_by_category_id($pdo, (int)$program['id'], 100);
$show_posts = isset($program['show_posts']) ? (bool)$program['show_posts'] : true;

return [
    'template' => dirname(__DIR__, 2) . '/dashboard/partials/programs/children.php',
    'vars' => [
        'program' => $program,
        'children' => $children,
        'posts' => $posts,
        'show_posts' => $show_posts,
        'title_safe' => 'Program: ' . $program['name']
    ],
    'title' => 'Program: ' . $program['name']
];
