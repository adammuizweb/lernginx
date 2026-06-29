<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php?error=' . rawurlencode('Invalid ID'));
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_deleted = 0, deleted_by = NULL, deleted_at = NULL
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);

    header('Location: index.php?restored=1');
    exit;
} catch (Throwable $e) {
    error_log('restore user failed: ' . $e->getMessage());
    header('Location: index.php?error=' . rawurlencode('Failed to restore user'));
    exit;
}
