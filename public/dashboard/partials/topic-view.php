<?php
// dashboard/partials/topic-view.php
if (!defined('DASHBOARD_CONTEXT')) {
  define('DASHBOARD_CONTEXT', true);
  require_once __DIR__ . '/../../../includes/bootstrap.php';
  http_response_code(403);
  exit('Direct access not allowed.');
}

$user_role = $user['role'] ?? null;
$category_id = $category['id'] ?? null;

// 🔒 Guard akses kategori utama
if (
  $category_id &&
  $user_role === 'student' &&
  !userHasDirectModule($pdo, $user['id'], $category_id)
) {
  include __DIR__ . '/no-access.php';
  return;
}

// 🔍 Filter post berdasarkan akses eksplisit
$filtered_posts = [];
foreach ($posts as $post) {
  $cid = $post['category_id'] ?? null;
  if (!$cid) continue;

  if (in_array($user_role, ['admin', 'teacher']) || userHasDirectModule($pdo, $user['id'], $cid)) {
    $filtered_posts[] = $post;
  }
}
?>

<section>
  <h2><?= htmlspecialchars($title_safe ?? 'Topik') ?></h2>

<?php if (!empty($info)): ?>
<div class="topicpst-category-info"><?= sanitize_quill($info) ?></div>
<?php endif; ?>

  <?php if (empty($filtered_posts)): ?>
    <p>No accessible posts in this category.</p>
  <?php else: ?>
    <section class="topicpst-section">
      <?php foreach ($filtered_posts as $post): ?>
        <?php
          $thumbUrl = null;

          // Prioritas: youtube thumbnail jika ada, jika tidak pakai featured_image, jika tidak ada tetap null
          if (!empty($post['youtube_url'])) {
            preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $post['youtube_url'], $m);
            if (!empty($m[1])) {
              $thumbUrl = 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg';
            }
          }

          if (empty($thumbUrl) && !empty($post['thumbnail'])) {
            $thumbUrl = $post['thumbnail'];
          }

          $authorName = 'Unknown';
          if (!empty($post['author_id'])) {
            $u = get_user_by_id($pdo, $post['author_id']);
            if ($u) {
              // use display_name if not empty, otherwise use username
              $name = !empty($u['display_name']) ? $u['display_name'] : ($u['username'] ?? 'Unknown');
              $authorName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
          }

          $url = get_dashboard_post_url($post);
          $title = htmlspecialchars($post['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
          $created = !empty($post['created_at']) ? date('j M Y', strtotime($post['created_at'])) : '—';
          $updated = !empty($post['updated_at']) ? date('j M Y', strtotime($post['updated_at'])) : $created;

          // Ambil cuplikan dari content (250 karakter), bukan dari excerpt
          $text = strip_tags($post['content'] ?? '');
          $excerpt = mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, 250, 'UTF-8');
          if (mb_strlen($text, 'UTF-8') > 250) $excerpt .= '…';
        ?>

        <details class="topicpst-toggle">
          <summary class="topicpst-summary"><?= $title ?></summary>

          <div class="topicpst-meta-row">
            <div class="topicpst-meta-left">Published: <?= htmlspecialchars($created) ?></div>
            <div class="topicpst-meta-right">Updated: <?= htmlspecialchars($updated) ?></div>
          </div>

          <div class="topicpst-thumb">
            <?php if ($thumbUrl): ?>
              <a href="<?= htmlspecialchars($url) ?>" class="topicpst-thumb-link">
                <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="<?= $title ?>" style="max-width:100%; max-height:100%;">
              </a>
            <?php else: ?>
              <div class="topicpst-thumb-placeholder">Thumbnail from featured image or embed</div>
            <?php endif; ?>
          </div>

          <p class="topicpst-excerpt"><?= nl2br(htmlspecialchars($excerpt)) ?></p>

<div class="topicpst-button-wrapper">
  <div class="topicpst-author">by <?= $authorName ?></div>
  <a href="<?= htmlspecialchars($url) ?>">
    <button class="topicpst-button">View Posts</button>
  </a>
</div>
        </details>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</section>

<style>
.topicpst-category-info {
  background-color: var(--surface);
  border-left: 4px solid var(--accent);
  padding: 1rem;
  margin-bottom: 1.5rem;
  font-size: 1rem;
  color: var(--text);
  border-radius: var(--radius);
  box-shadow: var(--card-shadow);
}

.topicpst-section {
  margin-top: 2rem;
  padding: 1rem;
  background-color: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--card-shadow);
}

.topicpst-toggle {
  border: 1px solid var(--border);
  border-radius: var(--radius);
  margin-bottom: 1.5rem;
  padding: 1rem;
  background-color: var(--bg);
  transition: box-shadow var(--transition-fast);
}

.topicpst-toggle[open] {
  box-shadow: var(--card-shadow);
}

.topicpst-summary {
  font-weight: 600;
  font-size: 1.1rem;
  color: var(--text);
  cursor: pointer;
  position: relative;
  padding-right: 24px;
}

.topicpst-summary::after {
  content: "−";
  position: absolute;
  right: 0;
  font-size: 20px;
  color: var(--accent);
}

.topicpst-toggle:not([open]) .topicpst-summary::after {
  content: "+";
}

.topicpst-meta-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.9rem;
  color: var(--muted);
  margin: 0.5rem 0 1rem;
}

.topicpst-thumb {
  position: relative;
  width: 100%;
  max-width: 600px;
  aspect-ratio: 16 / 9;
  background-color: black;
  border-radius: var(--radius);
  overflow: hidden;
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 0 auto 1rem;
}

.topicpst-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}


.topicpst-thumb-placeholder {
  color: white;
  font-size: 26px;
  text-align: center;
}

.topicpst-excerpt {
  font-size: 1rem;
  line-height: 1.6;
  color: var(--text);
  margin-bottom: 1rem;
}

.topicpst-button-wrapper {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1rem;
  gap: 1rem;
}

.topicpst-author {
  font-size: 0.95rem;
  color: var(--muted);
  font-style: italic;
}


.topicpst-button {
  border: 1px solid var(--accent);
  border-radius: 999px;
  padding: 8px 16px;
  background-color: transparent;
  color: var(--accent);
  font-weight: 500;
  cursor: pointer;
  transition: background-color var(--transition-fast), color var(--transition-fast);
}

.topicpst-button:hover {
  background-color: var(--accent);
  color: white;
}
.topicpst-thumb-link {
  display: block;
  width: 100%;
  height: 100%;
  border-radius: var(--radius);
  overflow: hidden;
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.topicpst-thumb-link:hover {
  transform: scale(1.02);
  box-shadow: 0 0 0 2px var(--accent);
}

</style>
