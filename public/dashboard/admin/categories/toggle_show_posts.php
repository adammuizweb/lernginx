<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied.');
}

$id = (int)($_POST['id'] ?? 0);
$show = isset($_POST['show_posts']) ? 1 : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE categories SET show_posts = ? WHERE id = ?");
    $stmt->execute([$show, $id]);
}

$redirect = $_POST['redirect'] ?? 'index.php';
header("Location: $redirect");
exit;
