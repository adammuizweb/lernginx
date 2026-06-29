<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
    exit('Access denied.');
}

$map_file = __DIR__ . '/children.json';
$map = file_exists($map_file) ? json_decode(file_get_contents($map_file), true) : [];

foreach ($_POST['map'] ?? [] as $slug => $data) {
    $map[$slug]['type'] = $data['type'] ?? 'image';
    $map[$slug]['url'] = trim($data['url'] ?? '');
    $map[$slug]['desc'] = trim($data['desc'] ?? '');
    $map[$slug]['active'] = !empty($data['active']);
}

file_put_contents($map_file, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
header('Location: index.php');
