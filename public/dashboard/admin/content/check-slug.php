<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    echo json_encode(['ok' => false, 'message' => 'Empty slug']);
    exit;
}

$candidate = slugify($slug);
$suggested = ensure_unique_slug($pdo, $candidate);
$available = ($suggested === $candidate);

echo json_encode(['ok' => true, 'available' => $available, 'suggested' => $suggested]);
