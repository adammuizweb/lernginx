<?php
// dashboard/admin/assign/actions/update_policy.php

if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Hanya admin yang boleh mengubah kebijakan.']);
    exit;
}

$newValue = ($_POST['toggle_policy'] === '1') ? '1' : '0';
$stmt = $pdo->prepare("
    INSERT INTO registration_policies (key_name, value, updated_at, updated_by)
    VALUES ('default_student_module_status', ?, NOW(), ?)
    ON DUPLICATE KEY UPDATE
        value = VALUES(value),
        updated_at = NOW(),
        updated_by = VALUES(updated_by)
");
$stmt->execute([$newValue, $user['id']]);
echo json_encode(['ok' => true, 'value' => $newValue]);