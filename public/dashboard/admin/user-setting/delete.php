<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php?error=' . rawurlencode('Invalid user ID'));
    exit;
}

if ($id === (int)$user['id']) {
    header('Location: index.php?error=' . rawurlencode('Cannot delete your own account'));
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE users
        SET is_deleted = 1, deleted_by = :admin_id, deleted_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':admin_id' => $user['id'],
        ':id' => $id
    ]);

    header('Location: index.php?deleted=1');
    exit;
} catch (Throwable $e) {
    error_log('delete user failed: '.$e->getMessage());
    header('Location: index.php?error=' . rawurlencode('Failed to delete user'));
    exit;
}
