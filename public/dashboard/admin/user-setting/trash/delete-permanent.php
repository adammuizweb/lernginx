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

if ($id === (int)$user['id']) {
    header('Location: index.php?error=' . rawurlencode('Cannot delete your own account.'));
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);

    header('Location: index.php?deleted=1');
    exit;
} catch (Throwable $e) {
    error_log('delete permanent failed: ' . $e->getMessage());
    header('Location: index.php?error=' . rawurlencode('Failed to permanently delete user'));
    exit;
}
