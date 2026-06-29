<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    echo json_encode(['ok' => false, 'message' => 'Empty slug']);
    exit;
}

// choose available slugify function
if (function_exists('slugify')) {
    $base = slugify($slug);
} elseif (function_exists('pages_slugify')) {
    $base = pages_slugify($slug);
} else {
    // fallback minimal
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

// helper: check across tables
function slug_exists_any(PDO $pdo, string $slug): bool {
    $tables = ['pages','posts','categories','tags'];
    foreach ($tables as $t) {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM {$t} WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
            if ($stmt->fetchColumn()) return true;
        } catch (Exception $e) {
            // ignore table not exists
        }
    }
    return false;
}

// if base available -> ok
if (! slug_exists_any($pdo, $base)) {
    echo json_encode(['ok' => true, 'available' => true, 'suggested' => $base]);
    exit;
}

// else propose unique with suffix
$i = 1;
while (true) {
    $cand = $base . '-' . $i++;
    if (! slug_exists_any($pdo, $cand)) {
        echo json_encode(['ok' => true, 'available' => false, 'suggested' => $cand]);
        exit;
    }
}
