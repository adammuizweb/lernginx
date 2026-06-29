<?php
// /includes/bootstrap_front.php
// Frontend lightweight bootstrap — hanya koneksi DB + helper halaman.
// Safe: tidak memuat session/auth/dashboard so no Forbidden side-effects.

if (!defined('LERNIGNX_CONTEXT')) define('LERNIGNX_CONTEXT', true);
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!defined('THEME_PATH')) {
    define('THEME_PATH', realpath(__DIR__ . '/../theme'));
}
// --- Try to locate backend root where env_loader.php and .env live ---
// Try several candidate locations (adapt if perlu)
$candidates = [
    __DIR__ . '/../../app/path',       // main path dir
    __DIR__ . '/../../app',            // fallback app root
    __DIR__ . '/../..'                 // relative fallback
];

$backendRoot = null;
foreach ($candidates as $cand) {
    // prefer the directory that actually contains env_loader.php
    if (file_exists($cand . '/env_loader.php')) {
        $backendRoot = rtrim($cand, '/');
        break;
    }
    // or if there's a 'path' subdir with env_loader
    if (file_exists($cand . '/path/env_loader.php')) {
        $backendRoot = rtrim($cand . '/path', '/');
        break;
    }
}

// If not found, provide a clear error to fix path quickly
if ($backendRoot === null) {
    // You can set $backendRoot manually below if detection fails.
    // Example: $backendRoot = __DIR__ . '/../../app/path';
    // --- temporary fail with helpful message ---
    http_response_code(500);
    die('bootstrap_front error: could not locate env_loader.php. Please set $backendRoot in includes/bootstrap_front.php to the directory containing env_loader.php.');
}

// --- load env loader and .env ---
require_once $backendRoot . '/env_loader.php';

// prefer explicit .env path near backendRoot if exists
$envFileCandidates = [
    $backendRoot . '/.env',
    dirname($backendRoot) . '/.env',
    __DIR__ . '/../../app/.env'
];

$envPath = null;
foreach ($envFileCandidates as $f) {
    if (file_exists($f)) { $envPath = $f; break; }
}

if ($envPath === null) {
    http_response_code(500);
    die('bootstrap_front error: .env file not found. Checked: ' . implode(', ', $envFileCandidates));
}

// load env (env_loader implementation should define load_env)
load_env($envPath);

// --- create PDO using env values (read-only usage) ---
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: null;
$charset = 'utf8mb4';

$dsn = 'mysql:host=' . $host . ';dbname=' . $db . ';charset=' . $charset;
if ($port) $dsn .= ';port=' . $port;

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('bootstrap_front error: DB connection failed: ' . $e->getMessage());
}

// --- lightweight category helper ---
if (!function_exists('get_category_by_slug')) {
    function get_category_by_slug(PDO $pdo, string $slug): ?array {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        return $category ?: null;
    }
}
if (!function_exists('get_child_categories')) {
    function get_child_categories(PDO $pdo, int $parent_id): array {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
        $stmt->execute([$parent_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('get_main_categories')) {
    function get_main_categories(PDO $pdo): array {
        $stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- minimal helper: get_page_by_slug ---
if (!function_exists('get_page_by_slug')) {
    function get_page_by_slug(PDO $pdo, string $slug) {
        $sql = "
            SELECT p.*, u.display_name AS author_name
            FROM pages p
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.slug = :slug
              AND (p.is_deleted = 0 OR p.is_deleted IS NULL)
              AND p.status = 'published'
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// helper pages list
if (!function_exists('get_all_pages')) {
    function get_all_pages(PDO $pdo, $onlyPublished = true) {
        $sql = "
            SELECT 
                p.id, 
                p.title, 
                p.slug, 
                p.excerpt, 
                p.thumbnail, 
                p.status, 
                p.created_at, 
                u.display_name AS author_name
            FROM pages p
            LEFT JOIN users u ON p.created_by = u.id
            WHERE (p.is_deleted = 0 OR p.is_deleted IS NULL)
        ";
        if ($onlyPublished) {
            $sql .= " AND p.status = 'published'";
        }
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
