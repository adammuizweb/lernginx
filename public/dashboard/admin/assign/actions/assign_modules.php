<?php
// dashboard/admin/assign/actions/assign_modules.php

header('Content-Type: application/json; charset=utf-8');
$targetUserId = (int)($_POST['user_id'] ?? 0);
if (!$targetUserId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'user_id tidak valid']);
    exit;
}

$modulesInput = $_POST['modules'] ?? [];
$toSet = [];
foreach ($modulesInput as $k => $v) {
    $cid = (int)$k;
    $v = ($v === '' || $v === null) ? null : (int)$v;
    if ($cid && $v !== null && in_array($v, [0,1,2], true)) {
        $toSet[$cid] = $v;
    }
}

if (empty($toSet)) {
    echo json_encode(['ok' => true, 'message' => 'Tidak ada perubahan yang dikirimkan.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $insertOrUpdate = $pdo->prepare("
        INSERT INTO modules (user_id, category_id, status, is_reviewed, reviewed_by, reviewed_at, created_at, updated_at)
        VALUES (?, ?, ?, 1, ?, NOW(), NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            is_reviewed = 1,
            reviewed_by = VALUES(reviewed_by),
            reviewed_at = NOW(),
            updated_at = NOW()
    ");
    foreach ($toSet as $cid => $status) {
        $insertOrUpdate->execute([$targetUserId, $cid, $status, $user['id']]);
    }
    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
}