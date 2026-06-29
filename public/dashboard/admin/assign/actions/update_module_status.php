<?php
// dashboard/admin/assign/actions/update_module_status.php

$userId = (int)($_POST['user_id'] ?? 0);
$categoryId = (int)($_POST['category_id'] ?? 0);
$newStatus = (int)($_POST['status'] ?? -1);

if ($userId && $categoryId && in_array($newStatus, [0, 1, 2], true)) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO modules (user_id, category_id, status, is_reviewed, reviewed_by, reviewed_at, created_at, updated_at)
            VALUES (?, ?, ?, 1, ?, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                is_reviewed = 1,
                reviewed_by = VALUES(reviewed_by),
                reviewed_at = NOW(),
                updated_at = NOW()
        ");
        $stmt->execute([$userId, $categoryId, $newStatus, $user['id']]);
        echo json_encode(['ok' => true, 'status' => $newStatus]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parameter tidak valid']);
}