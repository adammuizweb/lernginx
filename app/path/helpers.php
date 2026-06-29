<?php
// path/helpers.php

/**
 * Verify Google reCAPTCHA v2 response.
 * Uses cURL if available; falls back to file_get_contents.
 * Returns boolean.
 */
function verify_recaptcha(string $secret_key, string $response_token): bool {
    if (empty($response_token) || empty($secret_key)) return false;

    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = http_build_query([
        'secret' => $secret_key,
        'response' => $response_token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // Try cURL first
    if (function_exists('curl_init')) {
        $ch = curl_init($verify_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
    } else {
        // fallback
        $opts = ['http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $data,
            'timeout' => 10
        ]];
        $context = stream_context_create($opts);
        $result = @file_get_contents($verify_url, false, $context);
    }

    if (!$result) return false;
    $json = json_decode($result, true);
    return !empty($json['success']) && $json['success'] === true;
}

// path/helpers.php
if (!function_exists('get_descendant_category_ids')) {
    function get_descendant_category_ids(PDO $pdo, int $parent_id): array {
        try {
            $sql = "WITH RECURSIVE cte AS (
                        SELECT id FROM categories WHERE id = ?
                        UNION ALL
                        SELECT c.id FROM categories c
                        JOIN cte ON c.parent_id = cte.id
                    )
                    SELECT id FROM cte";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$parent_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($rows) return array_map('intval', $rows);
        } catch (PDOException $e) {
            // fallback iterative jika MySQL < 8
        }

        $ids = [$parent_id];
        $queue = [$parent_id];
        while (!empty($queue)) {
            $placeholders = implode(',', array_fill(0, count($queue), '?'));
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id IN ($placeholders)");
            $stmt->execute($queue);
            $children = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            $queue = [];
            foreach ($children as $c) {
                $intc = (int)$c;
                if (!in_array($intc, $ids, true)) {
                    $ids[] = $intc;
                    $queue[] = $intc;
                }
            }
        }
        return $ids;
    }
}

// Ambil semua post dari satu kategori
if (!function_exists('get_posts_by_category_id')) {
    /**
     * Ambil posts pada satu kategori.
     * Mengembalikan array asosiatif, hanya posts yang published dan tidak dihapus.
     */
    function get_posts_by_category_id(PDO $pdo, int $category_id, int $limit = 50): array {
        return get_posts_by_category_ids($pdo, [$category_id], $limit, 0);
    }
}

// Ambil semua post dari banyak kategori
if (!function_exists('get_posts_by_category_ids')) {
    /**
     * Ambil posts untuk banyak kategori.
     * - $cat_ids: array of int
     * - hanya posts yang published dan tidak dihapus
     * - mengembalikan array asosiatif
     */
    function get_posts_by_category_ids(PDO $pdo, array $cat_ids, int $limit = 0, int $offset = 0): array {
        $cat_ids = array_map('intval', array_values($cat_ids));
        if (empty($cat_ids)) return [];

        $placeholders = implode(',', array_fill(0, count($cat_ids), '?'));
        $sql = "
            SELECT p.*, u.display_name AS author_name, c.name AS category_name
            FROM posts p
            LEFT JOIN users u ON p.author_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.category_id IN ($placeholders)
              AND p.status = 'published'
              AND p.deleted_at IS NULL
            ORDER BY p.created_at DESC
        ";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($cat_ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


// copilot

if (!function_exists('get_category_by_slug')) {
    function get_category_by_slug(PDO $pdo, string $slug): ?array {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        return $category ?: null;
    }
}

if (!function_exists('get_post_by_slug')) {
    function get_post_by_slug(PDO $pdo, string $slug): ?array {
        $stmt = $pdo->prepare("SELECT p.*, u.display_name AS author_name, c.name AS category_name
                               FROM posts p
                               LEFT JOIN users u ON p.author_id = u.id
                               LEFT JOIN categories c ON p.category_id = c.id
                               WHERE p.slug = ? AND p.status = 'published' AND p.deleted_at IS NULL
                               LIMIT 1");
        $stmt->execute([$slug]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        return $post ?: null;
    }
}

if (!function_exists('get_category_path')) {
    function get_category_path(PDO $pdo, int $category_id): string {
        $path = [];
        while ($category_id) {
            $stmt = $pdo->prepare("SELECT id, slug, parent_id FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cat) break;
            array_unshift($path, $cat['slug']);
            $category_id = $cat['parent_id'];
        }
        return implode('/', $path);
    }
}

if (!function_exists('get_post_url')) {
    function get_post_url(PDO $pdo, array $post): string {
        $path = get_category_path($pdo, (int)$post['category_id']);
        $path = trim($path, '/'); // e.g. "glowverse/jepang"
        $slug = trim($post['slug'], '/');
        // canonical full path under modul (ubah kategori -> topic)
        return "/modul/topic" . ($path !== '' ? '/' . $path : '') . '/' . $slug . '/';
    }
}

if (!function_exists('render_dashboard')) {
    /**
     * Render a dashboard partial inside the dashboard layout.
     *
     * @param string|null $partialPath Full filesystem path or path relative to dashboard/ (e.g. 'partials/home.php' or __DIR__ . '/partials/home.php')
     * @param array $vars Variables to extract for the partial (EXTR_SKIP)
     * @param string|null $pageTitle Optional page title
     */
    function render_dashboard(?string $partialPath, array $vars = [], string $pageTitle = null): void {
        // Make sure core globals exist
        global $pdo, $user;

        // Resolve relative paths: if caller passed a path relative to dashboard folder,
        // try to resolve to the public dashboard location.
        if ($partialPath && !is_file($partialPath)) {
            $candidate = __DIR__ . '/../../public/dashboard/' . ltrim($partialPath, '/');
            if (is_file($candidate)) {
                $partialPath = $candidate;
            }
        }

        // Validate view file
        if ($partialPath && !is_file($partialPath)) {
            http_response_code(500);
            echo '<h2>View file not found</h2><pre>' . htmlspecialchars((string)$partialPath) . '</pre>';
            return;
        }

        // Prepare environment for partial
        if (!isset($user) && function_exists('get_user_from_session')) {
            $user = get_user_from_session($pdo);
        }

        // Provide vars to partial, but do not overwrite existing important variables
        extract($vars, EXTR_SKIP);

        // Mark dashboard context so partials can prevent direct access
        if (!defined('DASHBOARD_CONTEXT')) define('DASHBOARD_CONTEXT', true);

        // Capture partial output
        ob_start();
        if ($partialPath) {
            include $partialPath;
        }
        $content = ob_get_clean();

        // Set default pageTitle if provided via param or $pageTitle variable from caller
        if ($pageTitle === null && isset($vars['pageTitle'])) {
            $pageTitle = $vars['pageTitle'];
        }
        if ($pageTitle === null) $pageTitle = 'Dashboard lernginx';

        // Expose $pageTitle and $content to layout
        // Resolve layout path (adjust if your layout lives elsewhere)
        $layoutPath = dirname(__DIR__, 2) . '/public/dashboard/partials/layout.php';
        if (!is_file($layoutPath)) {
            http_response_code(500);
            echo '<h2>Layout file not found</h2>';
            return;
        }

        // Layout expects $pageTitle and $content
        include $layoutPath;
    }
}

function get_dashboard_post_url(array $post): string {
    global $pdo;
    $path = get_category_path($pdo, (int)$post['category_id']);
    $path = trim($path, '/');
    $slug = trim($post['slug'], '/');
    return "/dashboard/post/" . ($path !== '' ? $path . '/' : '') . $slug . '/';
}

if (!function_exists('get_category_tree')) {
    function get_category_tree(PDO $pdo, $parent_id = null): array {
        $stmt = $pdo->prepare("SELECT id, name, slug FROM categories WHERE parent_id " . ($parent_id === null ? "IS NULL" : "= ?") . " ORDER BY name");
        $stmt->execute($parent_id === null ? [] : [$parent_id]);
        $categories = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['children'] = get_category_tree($pdo, $row['id']);
            $categories[] = $row;
        }
        return $categories;
    }
}


function slugify($str) {
    $str = mb_strtolower(trim($str), 'UTF-8');

    // coba transliterasi Intl
    if (function_exists('transliterator_transliterate')) {
        $trans = transliterator_transliterate('Any-Latin; Latin-ASCII', $str);
        if ($trans !== null) $str = $trans;
    } else {
        // fallback ke iconv yang menerima argumen ketiga
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        if ($trans !== false) $str = $trans;
    }

    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/\s+/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}


function ensure_unique_slug(PDO $pdo, $baseSlug) {
    $slug = $baseSlug;
    $i = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT 1 FROM posts WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $inPosts = (bool) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT 1 FROM categories WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $inCats = (bool) $stmt->fetchColumn();

        if (!$inPosts && !$inCats) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $i++;
    }
}

// pastikan file ini di-include setelah bootstrap yang menyediakan $pdo
if (!function_exists('get_dashboard_post_url')) {
    function get_dashboard_post_url(array $post): string {
        // cocokkan path sesuai routing dashboard kamu
        $id = (int)($post['id'] ?? 0);
        $slug = rawurlencode($post['slug'] ?? '');
        if ($slug === '') {
            return "./edit.php?id={$id}";
        }
        return "./edit.php?id={$id}&slug={$slug}";
    }
}

if (!function_exists('get_category_by_id')) {
    function get_category_by_id(PDO $pdo, ?int $id): ?array {
        if (empty($id)) return null;
        $stmt = $pdo->prepare("SELECT id, name, slug, parent_id, description, info, created_at, updated_at, show_posts FROM categories WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('get_user_by_id')) {
    function get_user_by_id(PDO $pdo, ?int $id): ?array {
        if (empty($id)) return null;
        $stmt = $pdo->prepare("SELECT id, username, email, display_name, is_deleted, created_at, updated_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('get_user_display_name')) {
    function get_user_display_name(PDO $pdo, ?int $id): string {
        $u = get_user_by_id($pdo, $id);
        if (!$u) return 'Unknown';
        if (!empty($u['display_name'])) return htmlspecialchars($u['display_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return htmlspecialchars($u['username'] ?? 'User', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('extract_youtube_id')) {
    function extract_youtube_id(string $url): ?string {
        $url = trim($url);
        if ($url === '') return null;
        // common patterns
        if (preg_match('#(?:v=|\/v\/|youtu\.be\/|\/embed\/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}

if (!function_exists('get_post_thumbnail_url')) {
    function get_post_thumbnail_url(PDO $pdo, array $post): ?string {
        // prefer explicit featured_image column if exists
        if (!empty($post['featured_image'])) {
            return $post['featured_image'];
        }
        // fallback to youtube thumbnail
        if (!empty($post['youtube_url'])) {
            $id = extract_youtube_id($post['youtube_url']);
            if ($id) return "https://i.ytimg.com/vi/{$id}/hqdefault.jpg";
        }
        // no image
        return null;
    }
}

if (!function_exists('get_parent_categories')) {
    function get_parent_categories(PDO $pdo): array {
        $stmt = $pdo->query("SELECT id, name, slug, description, created_at, updated_at 
                             FROM categories 
                             WHERE parent_id IS NULL 
                             ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('get_child_categories')) {
    function get_child_categories(PDO $pdo, int $parent_id): array {
        $stmt = $pdo->prepare("SELECT id, name, slug, description, created_at, updated_at 
                               FROM categories 
                               WHERE parent_id = ? 
                               ORDER BY name ASC");
        $stmt->execute([$parent_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Cek apakah user boleh akses sebuah post
function userCanAccessPost(PDO $pdo, int $userId, int $postId): bool {
    $sql = "
        SELECT 1
        FROM posts p
        JOIN categories_closure cc ON cc.descendant_id = p.category_id
        JOIN modules m ON m.user_id = ? AND m.category_id = cc.ancestor_id AND m.status = 0
        WHERE p.id = ?
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $postId]);
    return (bool) $stmt->fetchColumn();
}

// punya topic-view user bisa lihat posts siapapun dan apapun
function userCanAccessCategory(PDO $pdo, int $userId, int $categoryId): bool {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM modules
        WHERE user_id = ? AND category_id = ? AND status = 0
        LIMIT 1
    ");
    $stmt->execute([$userId, $categoryId]);
    return (bool) $stmt->fetchColumn();
}

function getUserModuleStatus(PDO $pdo, int $userId, int $categoryId): ?int {
    $stmt = $pdo->prepare("SELECT status FROM modules WHERE user_id = ? AND category_id = ?");
    $stmt->execute([$userId, $categoryId]);
    $status = $stmt->fetchColumn();
    return $status !== false ? (int)$status : null;
}

// helper lintas sistem
function set_flash(string $message): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash_message'] = $message;
}

function get_flash(): ?string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['flash_message'])) return null;
    $msg = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    return $msg;
}

// profile
if (!function_exists('get_user_full_by_id')) {
    function get_user_full_by_id(PDO $pdo, ?int $id): ?array {
        if (empty($id)) return null;

        $stmt = $pdo->prepare("SELECT 
            id, username, email, role, display_name, foto_profil, nomor_telpon, alamat_rumah,
            tanggal_lahir, asal_sekolah, tahun_masuk,
            jurusan, nisn, is_deleted, created_at, updated_at, deleted_at, deleted_by
            FROM users WHERE id = ? LIMIT 1
        ");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}


// efek gelap main modul tidak aktif

function get_programs_with_user_status(PDO $pdo, int $user_id): array {
  $stmt = $pdo->prepare("
    SELECT 
      c.id AS category_id,
      c.name,
      c.slug,
      m.status
    FROM categories c
    LEFT JOIN modules m 
      ON m.category_id = c.id AND m.user_id = ?
    WHERE c.parent_id IS NULL
    ORDER BY c.name ASC
  ");
  $stmt->execute([$user_id]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// efek gelap child
function get_category_id_by_slug(PDO $pdo, string $slug): ?int {
  $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
  $stmt->execute([$slug]);
  $id = $stmt->fetchColumn();
  return $id !== false ? (int)$id : null;
}

// cuma kategori yang tercentang tanpa mengecualikan parent child
function userHasDirectModule(PDO $pdo, int $userId, int $categoryId): bool {
  $stmt = $pdo->prepare("
    SELECT 1 FROM modules
    WHERE user_id = ? AND category_id = ? AND status = 0
    LIMIT 1
  ");
  $stmt->execute([$userId, $categoryId]);
  return (bool) $stmt->fetchColumn();
}

// sanitasi quill
function sanitize_quill($html) {
  // Hapus tag berbahaya
  $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
  $html = preg_replace('#<iframe(.*?)>(.*?)</iframe>#is', '', $html);
  $html = preg_replace('#<object(.*?)>(.*?)</object>#is', '', $html);
  $html = preg_replace('#<embed(.*?)>(.*?)</embed>#is', '', $html);

  // Hapus atribut JS event
  $html = preg_replace('/on\w+="[^"]*"/i', '', $html);
  $html = preg_replace("/on\w+='[^']*'/i", '', $html);

  // Hapus javascript: dari href/src
  $html = preg_replace('/(href|src)\s*=\s*"javascript:[^"]*"/i', '', $html);
  $html = preg_replace("/(href|src)\s*=\s*'javascript:[^']*'/i", '', $html);

  return $html;
}
