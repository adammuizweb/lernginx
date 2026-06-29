<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
    exit('Access denied.');
}

$map_file = __DIR__ . '/programs.json';
$map = file_exists($map_file) ? json_decode(file_get_contents($map_file), true) : [];

$stmt = $pdo->query("SELECT slug, name FROM categories WHERE parent_id IS NULL");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($categories as $cat) {
    $slug = $cat['slug'];
    $name = htmlspecialchars($cat['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if (!isset($map[$slug])) {
        $map[$slug] = [
            'type' => 'image',
            'url' => '',
            'desc' => $name
        ];
    }
}

file_put_contents($map_file, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
header('Location: index.php');
