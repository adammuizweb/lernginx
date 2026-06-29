<?php
// dashboard/admin/assign/includes/fetch_data.php

// -------------------- Search / Filter / Limit params --------------------
$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 200);

$allowedPerPage = [5,10,20,50];
$defaultPerPage = 5;
$perPage = (int)($_GET['per_page'] ?? $defaultPerPage);
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = $defaultPerPage;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$filter_category = (int)($_GET['category'] ?? 0);
$filter_status = (isset($_GET['status']) && $_GET['status'] !== '') ? (int)$_GET['status'] : '';

// -------------------- Fetch categories --------------------
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------- Fetch students with filters --------------------
$where = ["role = 'student'", "is_deleted = 0"];
$binds = [];

if ($q !== '') {
    $where[] = "(username LIKE :q1 OR display_name LIKE :q2 OR email LIKE :q3)";
    $like = "%{$q}%";
    $binds[':q1'] = $like;
    $binds[':q2'] = $like;
    $binds[':q3'] = $like;
}

if ($filter_category > 0) {
    $where[] = "EXISTS (SELECT 1 FROM modules m WHERE m.user_id = users.id AND m.category_id = :filter_cat)";
    $binds[':filter_cat'] = $filter_category;
    if ($filter_status !== '') {
        $where[] = "EXISTS (SELECT 1 FROM modules m2 WHERE m2.user_id = users.id AND m2.category_id = :filter_cat2 AND m2.status = :filter_status)";
        $binds[':filter_cat2'] = $filter_category;
        $binds[':filter_status'] = $filter_status;
    }
} else {
    if ($filter_status !== '') {
        $where[] = "EXISTS (SELECT 1 FROM modules m3 WHERE m3.user_id = users.id AND m3.status = :filter_status_only)";
        $binds[':filter_status_only'] = $filter_status;
    }
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$count_sql = "SELECT COUNT(*) FROM users {$where_sql}";
$stmt = $pdo->prepare($count_sql);
foreach ($binds as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) || ctype_digit((string)$v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Fetch students
$sql = "SELECT id, username, display_name, email FROM users {$where_sql} ORDER BY username ASC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
foreach ($binds as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) || ctype_digit((string)$v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------- Build modulesMap --------------------
$modulesMap = [];
$studentIds = array_map(fn($s) => (int)$s['id'], $students);

if (!empty($studentIds)) {
    $pl = implode(',', array_fill(0, count($studentIds), '?'));
    $sql = "
        SELECT m.user_id, m.id AS module_id, m.category_id, m.status, m.is_reviewed, c.name AS category_name, c.parent_id
        FROM modules m
        JOIN categories c ON c.id = m.category_id
        WHERE m.user_id IN ($pl)
          AND m.status IN (0,1,2)
          AND (
              c.parent_id IS NOT NULL
              OR (
                 c.parent_id IS NULL
                 AND NOT EXISTS (SELECT 1 FROM categories cc WHERE cc.parent_id = c.id)
              )
          )
        ORDER BY m.user_id, c.name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($studentIds);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $modulesMap[(int)$r['user_id']][] = $r;
    }
}

// -------------------- Fetch policy --------------------
$stmt = $pdo->prepare("
    SELECT rp.value, rp.updated_at, u.username AS updated_by
    FROM registration_policies rp
    LEFT JOIN users u ON u.id = rp.updated_by
    WHERE rp.key_name = 'default_student_module_status'
    LIMIT 1
");
$stmt->execute();
$policyRow = $stmt->fetch(PDO::FETCH_ASSOC);
$currentPolicy = $policyRow['value'] ?? '0';