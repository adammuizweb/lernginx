<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
    exit('Access denied.');
}

$map_file = __DIR__ . '/children.json';
$map = file_exists($map_file) ? json_decode(file_get_contents($map_file), true) : [];

$stmt = $pdo->query("SELECT slug, name, parent_id FROM categories WHERE parent_id IS NOT NULL");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($categories as $cat) {
    $slug = $cat['slug'];
    $name = htmlspecialchars($cat['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $parent = get_category_by_id($pdo, $cat['parent_id'])['slug'] ?? null;

    if (!isset($map[$slug]) && $parent) {
        $map[$slug] = [
            'parent' => $parent,
            'type' => 'image',
            'url' => '',
            'desc' => $name,
            'active' => true
        ];
    }
}

file_put_contents($map_file, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
header('Location: index.php');
