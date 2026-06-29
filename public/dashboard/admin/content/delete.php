<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

$user = get_user_from_session($pdo);
if ( ! $user || !in_array($user['role'], ['teacher', 'admin']) ) {
    header('Location: /dashboard/');
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ( ! $id ) {
    http_response_code(400);
    exit('ID tidak valid.');
}

// Ambil post dulu untuk validasi author
$stmt = $pdo->prepare("SELECT author_id FROM posts WHERE id = ? AND is_deleted = 0");
$stmt->execute([$id]);
$post = $stmt->fetch();

if ( ! $post ) {
    http_response_code(404);
    exit('Post tidak ditemukan.');
}

// Validasi: guru hanya boleh hapus post miliknya sendiri
if ($user['role'] === 'teacher' && $post['author_id'] !== $user['id']) {
    $_SESSION['flash'] = 'Guru hanya dapat menghapus post yang mereka buat sendiri.';
    header('Location: index.php');
    exit;
}

// Soft delete
$stmt = $pdo->prepare("UPDATE posts SET is_deleted = 1, deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
$success = $stmt->execute([$id]);

if ($success) {
    header('Location: index.php?delete=success');
    exit;
} else {
    http_response_code(500);
    exit('Failed to delete post.');
}