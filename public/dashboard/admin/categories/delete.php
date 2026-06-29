<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'admin') {
    header('Location: /dashboard/');
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('ID tidak valid.');
}

// Set parent_id anak-anak kategori ini menjadi NULL
$stmt1 = $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = ?");
$stmt1->execute([$id]);

// Hapus kategori
$stmt2 = $pdo->prepare("DELETE FROM categories WHERE id = ?");
$success = $stmt2->execute([$id]);

if ($success) {
    header('Location: index.php?delete=success');
    exit;
} else {
    http_response_code(500);
    exit('Failed to delete category.');
}
