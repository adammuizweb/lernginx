<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$slug = trim($_GET['slug'] ?? '');
$exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;

if ($slug === '') {
    echo json_encode(['ok' => false, 'message' => 'Empty slug']);
    exit;
}

if (function_exists('slugify')) {
    $base = slugify($slug);
} elseif (function_exists('pages_slugify')) {
    $base = pages_slugify($slug);
} else {
    $base = preg_replace('~[^\pL\d]+~u', '-', $slug);
    $base = iconv('utf-8', 'us-ascii//TRANSLIT', $base) ?: $base;
    $base = preg_replace('~[^-\w]+~', '', $base);
    $base = preg_replace('~-+~', '-', $base);
    $base = strtolower(trim($base, '-'));
}

if ($base === '') {
    echo json_encode(['ok' => false, 'message' => 'Empty after slugify']);
    exit;
}

function slug_exists_in_table_excluding(PDO $pdo, string $table, string $slug, ?int $exclude_id): bool {
    try {
        if ($exclude_id) {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug = ? AND id != ? LIMIT 1");
            $stmt->execute([$slug, $exclude_id]);
        } else {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
        }
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// check across tables but exclude the pages row with exclude_id only for pages table
$inPages = slug_exists_in_table_excluding($pdo, 'pages', $base, $exclude_id);
$inPosts = slug_exists_in_table_excluding($pdo, 'posts', $base, null);
$inCats  = slug_exists_in_table_excluding($pdo, 'categories', $base, null);
$inTags  = slug_exists_in_table_excluding($pdo, 'tags', $base, null);

$available = ! ($inPages || $inPosts || $inCats || $inTags);

if ($available) {
    echo json_encode(['ok' => true, 'available' => true, 'suggested' => $base]);
    exit;
}

// propose unique
$i = 1;
while (true) {
    $cand = $base . '-' . $i++;
    $inPages = slug_exists_in_table_excluding($pdo, 'pages', $cand, $exclude_id);
    $inPosts = slug_exists_in_table_excluding($pdo, 'posts', $cand, null);
    $inCats  = slug_exists_in_table_excluding($pdo, 'categories', $cand, null);
    $inTags  = slug_exists_in_table_excluding($pdo, 'tags', $cand, null);
    if (!($inPages || $inPosts || $inCats || $inTags)) {
        echo json_encode(['ok' => true, 'available' => false, 'suggested' => $cand]);
        exit;
    }
}
