<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
    exit('Access denied.');
}

$map_file = __DIR__ . '/children.json';
$map = file_exists($map_file) ? json_decode(file_get_contents($map_file), true) : [];
$map = is_array($map) ? $map : [];

$stmt = $pdo->query("SELECT slug FROM categories WHERE parent_id IS NOT NULL");
$valid_slugs = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'slug');

$cleaned = [];
foreach ($map as $slug => $data) {
    if (in_array($slug, $valid_slugs)) {
        $cleaned[$slug] = $data;
    }
}

file_put_contents($map_file, json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
header('Location: index.php');
