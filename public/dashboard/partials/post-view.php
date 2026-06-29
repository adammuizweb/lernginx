<?php
// dashboard/partials/post-view.fixed.php
// Fix: placeholder image, no auto-generate excerpt, excerpt only for admin/teacher, no <footer> tag
if (!defined('DASHBOARD_CONTEXT')) {
    define('DASHBOARD_CONTEXT', true);
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

$user = get_user_from_session($pdo);
$user_role = $user['role'] ?? '';

if (!$user) {
    http_response_code(403);
    exit('Access denied.');
}

if (empty($post) || !is_array($post)) {
    echo '<p>Post not found.</p>';
    return;
}

// cek akses lebih awal (post sudah ada)
if (!userCanAccessCategory($pdo, $user['id'], $post['category_id'] ?? null) && !in_array($user_role, ['admin', 'teacher'])) {
    include 'no-access.php';
    return;
}

if (!userCanAccessPost($pdo, $user['id'], $post['id'] ?? null) && !in_array($user_role, ['admin', 'teacher'])) {
    echo "<div class='alert alert-warning'>You have not registered for this program yet.</div>";
    return;
}

// helper lokal: ekstrak youtube id aman
function _extract_youtube_id(string $url): ?string {
    $url = trim($url);
    if ($url === '') return null;
    // dukung: youtube.com/watch?v=ID, youtu.be/ID, /embed/ID, v=ID&...
    if (preg_match('#(?:v=|\/v\/|youtu\.be\/|\/embed\/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
        return $m[1];
    }
    return null;
}

// siapkan data
$title = $post['title'] ?? 'Untitled';
$created_iso = !empty($post['created_at']) ? date('c', strtotime($post['created_at'])) : '';
$created_display = !empty($post['created_at']) ? date('j M Y H:i', strtotime($post['created_at'])) : '';
$updated_iso = !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : '';
$updated_display = !empty($post['updated_at']) ? date('j M Y H:i', strtotime($post['updated_at'])) : '';
$author = get_user_display_name($pdo, $post['author_id'] ?? null);

// kategori
$category_label = '—';
if (!empty($post['category_id'])) {
    $cat = get_category_by_id($pdo, $post['category_id']);
    if ($cat) {
        if (!empty($cat['parent_id'])) {
            $parent = get_category_by_id($pdo, $cat['parent_id']);
            $category_label = htmlspecialchars(($parent['name'] ?? '') . ' / ' . $cat['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } else {
            $category_label = htmlspecialchars($cat['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }
}

// youtube embed or thumbnail or featured image or placeholder
$youtube_id = !empty($post['youtube_url']) ? _extract_youtube_id($post['youtube_url']) : null;
$featured = $post['featured_image'] ?? ($post['thumbnail'] ?? null);

$thumbnail_html = '';
if ($youtube_id) {
    // responsive wrapper untuk iframe
    $ytSrc = 'https://www.youtube.com/embed/' . rawurlencode($youtube_id) . '?rel=0';
    $thumbnail_html = '<div class="post-media post-media-youtube"><div class="embed-responsive"><iframe loading="lazy" src="' . htmlspecialchars($ytSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" title="' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div></div>';
} elseif (!empty($featured)) {
    $thumbnail_html = '<div class="post-media post-media-image"><img src="' . htmlspecialchars($featured, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" loading="lazy"></div>';
} else {
    // placeholder SVG sederhana jika tidak ada gambar
    $svg = '<svg width="640" height="360" viewBox="0 0 640 360" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="No image available"><rect width="100%" height="100%" fill="#f3f4f6"/><g transform="translate(0,0)"><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="20" fill="#9ca3af">No image</text></g></svg>';
    $thumbnail_html = '<div class="post-media post-media-placeholder" aria-hidden="true">' . $svg . '</div>';
}

// excerpt: TIDAK auto-generate dari content. Hanya pakai excerpt jika ada. Hanya tampil untuk guru/admin.
$raw_excerpt = $post['excerpt'] ?? '';
$excerpt_text = trim(strip_tags($raw_excerpt));
$excerpt_html = '';
if ($excerpt_text !== '' && in_array($user_role, ['admin', 'teacher'])) {
    $excerpt_html = '<p>' . nl2br(htmlspecialchars($excerpt_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
}

// content_html: gunakan sanitasi sesuai kebijakan
$content_html = sanitize_quill($post['content'] ?? '');

?>
<div class="post-wrapper">
  <article class="post-detail">
    <?php if ($updated_display): ?>
      <div class="post-updated">Updated: <time datetime="<?= htmlspecialchars($updated_iso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($updated_display, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></time></div>
    <?php endif; ?>

    <header class="post-header">
      <h1 class="post-title"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
      <div class="post-meta-top">
        <span class="post-categories">Category: <?= $category_label ?></span>
        <span class="post-author">Written by: <?= $author ?></span>
      </div>
    </header>

    <?php if ($thumbnail_html): ?>
      <?= $thumbnail_html ?>
    <?php endif; ?>

    <?php if ($excerpt_html): ?>
      <div class="post-excerpt"><?= $excerpt_html ?></div>
    <?php endif; ?>

    <section class="post-content">
      <?= $content_html ?>
    </section>

    <div class="post-meta">
      <div class="post-created">Created: <time datetime="<?= htmlspecialchars($created_iso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($created_display, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></time></div>
      <div class="post-author-line">Author: <?= $author ?></div>
    </div>
  </article>
</div>
