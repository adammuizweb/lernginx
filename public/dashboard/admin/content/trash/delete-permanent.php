<?php
require_once __DIR__ . '/../../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'teacher') {
    header('Location: /dashboard/');
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('ID tidak valid.');
}

// Optional: cek apakah post memang ada
$stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    exit('Post tidak ditemukan.');
}

// Hapus permanen
$stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
$stmt->execute([$id]);

header('Location: index.php?delete=success');
exit;
