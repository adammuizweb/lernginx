<?php
// dashboard/admin/assign/actions/get_user_modules.php

$userId = (int)$_GET['user_id'];
$stmt = $pdo->prepare("
    SELECT m.id AS module_id, m.category_id, m.status, m.is_reviewed, m.reviewed_by, m.reviewed_at, c.name, c.parent_id
    FROM modules m
    JOIN categories c ON c.id = m.category_id
    WHERE m.user_id = ? AND m.status IN (0,1,2)
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows);