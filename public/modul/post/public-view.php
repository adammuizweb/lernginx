<?php
// Public post view — requires auth (teacher/student)
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
$allowed_roles = ['teacher', 'student'];

if (!$user || !in_array($user['role'] ?? '', $allowed_roles, true)) {
    http_response_code(403);
    echo '<h2>Access denied</h2><p>Only users with teacher or student role can view this content.</p>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? $title ?? 'Post', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
</head>
<body>
  <main class="site-main">
    <article class="post-detail">
      <?php if (!empty($updated_display)): ?>
        <div class="post-updated">Updated: <time datetime="<?= htmlspecialchars($updated_iso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($updated_display, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></time></div>
      <?php endif; ?>

      <header>
        <h1><?= htmlspecialchars($title ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        <?php if (!empty($category_label)): ?>
          <div class="post-categories">Category: <?= $category_label ?></div>
        <?php endif; ?>
      </header>

      <?= $thumbnail_html ?>
      <?= $excerpt_html ?>

      <section class="post-content">
        <?= $content_html ?>
      </section>

      <footer class="post-meta">
        <?php if (!empty($created_display)): ?>
          <div class="post-created">Created: <time datetime="<?= htmlspecialchars($created_iso, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($created_display, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></time></div>
        <?php endif; ?>
        <div class="post-author">Written by: <?= $author ?></div>
      </footer>
    </article>
  </main>
</body>
</html>
