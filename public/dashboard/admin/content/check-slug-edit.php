<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$slug = trim($_GET['slug'] ?? '');
$exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;
$type = trim($_GET['type'] ?? 'category'); // for edit kategori default type=category

if ($slug === '') {
    echo json_encode(['ok' => false, 'message' => 'Empty slug']);
    exit;
}

// helper: cek slug di tabel, dengan optional pengecualian id
function slug_exists_in_table(PDO $pdo, string $table, string $slug, ?int $exclude_id): bool {
    if ($exclude_id) {
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug = ? AND id != ? LIMIT 1");
        $stmt->execute([$slug, $exclude_id]);
    } else {
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
    }
    return (bool) $stmt->fetchColumn();
}

// cek di posts dan categories, tapi jika exclude_id diberikan, abaikan row dengan id tersebut di tabel sesuai type
$inPosts = slug_exists_in_table($pdo, 'posts', $slug, $type === 'post' ? $exclude_id : null);
$inCats  = slug_exists_in_table($pdo, 'categories', $slug, $type === 'category' ? $exclude_id : null);

$available = ! $inPosts && ! $inCats;

if ($available) {
    echo json_encode(['ok' => true, 'available' => true, 'suggested' => $slug]);
    exit;
}

// jika tidak available, dari sisi edit kita tetap gunakan ensure_unique_slug untuk menyarankan
$suggested = ensure_unique_slug($pdo, $slug);
echo json_encode(['ok' => true, 'available' => false, 'suggested' => $suggested]);
