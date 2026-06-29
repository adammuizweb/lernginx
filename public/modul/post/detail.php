<?php
// modul/post/detail.php
// Menyiapkan data post lalu merender template dashboard atau publik.

// Pastikan environment terinisialisasi
if (!defined('LERNIGNX_CONTEXT')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}
$in_dashboard = defined('DASHBOARD_CONTEXT') && DASHBOARD_CONTEXT === true;

// Ambil slug dan cat_path
$slug = $_GET['slug'] ?? null;
$cat_path = $_GET['cat_path'] ?? null;

if (!$slug) {
    if ($in_dashboard) {
        // untuk dashboard: jangan exit, kembalikan pesan singkat sebagai vars atau template
        return [
            'template' => dirname(__DIR__, 2) . '/dashboard/partials/post-view.php',
            'vars' => ['pageTitle' => 'Error', 'content_html' => '<p>Post slug not provided.</p>'],
            'title' => 'Error'
        ];
    }
    http_response_code(400);
    exit('Post slug not provided.');
}

// Ambil post dari DB
$post = get_post_by_slug($pdo, $slug);
if (!$post) {
    if ($in_dashboard) {
        return [
            'template' => dirname(__DIR__, 2) . '/dashboard/partials/post-view.php',
            'vars' => ['pageTitle' => 'Post Not Found', 'content_html' => '<p>Post not found.</p>'],
            'title' => 'Post Not Found'
        ];
    }
    http_response_code(404);
    exit('Post not found.');
}

// Validasi canonical path jika diperlukan
if ($cat_path && function_exists('get_category_path')) {
    $actual_path = get_category_path($pdo, (int)$post['category_id']);
    if ($actual_path !== $cat_path) {
        $canonical = get_post_url($pdo, $post);
        if ($in_dashboard) {
            return [
                'template' => dirname(__DIR__, 2) . '/dashboard/partials/post-view.php',
                'vars' => ['pageTitle' => 'URL Mismatch', 'content_html' => '<p>URL mismatch. <a href="' . htmlspecialchars($canonical, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">View original version</a>.</p>'],
                'title' => 'URL Mismatch'
            ];
        }
        header("Location: {$canonical}", true, 301);
        exit;
    }
}

// --- Siapkan data yang akan digunakan template ---
$title_raw = $post['title'] ?? '';
$title = htmlspecialchars($title_raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$updated_display = $post['updated_at'] ? date('j M Y H:i', strtotime($post['updated_at'])) : '';
$updated_iso = $post['updated_at'] ? date('c', strtotime($post['updated_at'])) : '';
$created_display = $post['created_at'] ? date('j M Y H:i', strtotime($post['created_at'])) : '';
$created_iso = $post['created_at'] ? date('c', strtotime($post['created_at'])) : '';

$author = htmlspecialchars($post['author_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$category_label = '';
if (function_exists('get_category_path')) {
    $path = get_category_path($pdo, (int)$post['category_id']);
    if ($path !== '') {
        $parts = explode('/', $path);
        $names = [];
        foreach ($parts as $slugPart) {
            $cat = get_category_by_slug($pdo, $slugPart);
            if ($cat) $names[] = htmlspecialchars($cat['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($names) $category_label = implode(' » ', $names);
    }
}
if ($category_label === '') {
    $category_label = htmlspecialchars($post['category_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$thumbnail_html = '';
if (!empty($post['thumbnail'])) {
    $thumb = htmlspecialchars($post['thumbnail'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $thumb_alt = htmlspecialchars($title_raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $thumbnail_html = '<figure class="post-thumbnail"><img src="' . $thumb . '" alt="' . $thumb_alt . '"></figure>';
}

$excerpt_html = '';
if (!empty($post['excerpt'])) {
    $excerpt = trim($post['excerpt']);
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_\-]{6,})#i', $excerpt, $m)) {
        $video_id = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $excerpt_html = '<div class="post-video"><iframe src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen></iframe></div>';
    } else {
        $excerpt_html = '<p class="post-excerpt">' . nl2br(htmlspecialchars($excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
    }
}

$content_html = $post['content'] ?? '';

$vars = [
    'pageTitle' => $title_raw,
    'title' => $title_raw,
    'title_safe' => $title,
    'updated_display' => $updated_display,
    'updated_iso' => $updated_iso,
    'created_display' => $created_display,
    'created_iso' => $created_iso,
    'author' => $author,
    'category_label' => $category_label,
    'thumbnail_html' => $thumbnail_html,
    'excerpt_html' => $excerpt_html,
    'content_html' => $content_html,
    'post' => $post,
];

// Jika dipanggil dari dashboard: jangan echo sama sekali, kembalikan info untuk render_dashboard
if ($in_dashboard) {
    return [
        'template' => dirname(__DIR__, 2) . '/dashboard/partials/post-view.php',
        'vars' => $vars,
        'title' => $title_raw
    ];
}

// Untuk akses publik, gunakan template publik jika ada (ini boleh include/echo)
$public_template = __DIR__ . '/public-view.php';
if (is_file($public_template)) {
    extract($vars, EXTR_SKIP);
    include $public_template;
    return;
}

// Fallback publik render (sederhana)
echo '<article class="post-detail">';
if ($updated_display) {
    echo '<div class="post-updated">Updated: <time datetime="' . htmlspecialchars($updated_iso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($updated_display) . '</time></div>';
}
echo '<h1>' . htmlspecialchars($title_raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>';
if ($category_label) echo '<div class="post-categories">Category: ' . $category_label . '</div>';
echo $thumbnail_html;
echo $excerpt_html;
echo '<section class="post-content">' . $content_html . '</section>';
if ($created_display) echo '<div class="post-created">Created: <time datetime="' . htmlspecialchars($created_iso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($created_display) . '</time></div>';
echo '<div class="post-author">Written by: ' . $author . '</div>';
echo '</article>';

// selesai untuk publik
return;
