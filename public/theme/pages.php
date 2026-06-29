<?php
require_once __DIR__ . '/../includes/bootstrap_front.php';

$pages = get_all_pages($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pages | lernginx</title>
  <style>
    body {
      font-family: system-ui, sans-serif;
      margin: 2rem;
      background: #fafafa;
    }
    .page-list {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
    }
    .page-card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.08);
      overflow: hidden;
      transition: transform .2s ease, box-shadow .2s ease;
      display: flex;
      flex-direction: column;
    }
    .page-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .page-thumb {
      width: 100%;
      height: 180px;
      object-fit: cover;
      background: #eee;
    }
    .page-content {
      padding: 1rem;
    }
    .page-title {
      font-size: 1.2rem;
      font-weight: 600;
      color: #222;
      margin-bottom: .5rem;
    }
    .page-excerpt {
      font-size: .95rem;
      color: #555;
      line-height: 1.4;
    }
    .page-meta {
      margin-top: .8rem;
      font-size: .8rem;
      color: #777;
    }
  </style>
</head>
<body>

  <h1>Pages</h1>
  <div class="page-list">
    <?php foreach ($pages as $p): ?>
      <article class="page-card">
        <?php if ($p['thumbnail']): ?>
          <img src="<?= htmlspecialchars($p['thumbnail']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" class="page-thumb">
        <?php endif; ?>
        <div class="page-content">
          <h2 class="page-title"><?= htmlspecialchars($p['title']) ?></h2>
          <?php if (!empty($p['excerpt'])): ?>
            <p class="page-excerpt"><?= htmlspecialchars($p['excerpt']) ?></p>
          <?php endif; ?>
          <div class="page-meta">
            <?= htmlspecialchars($p['author_name'] ?? 'Anonim') ?> • <?= date('d M Y', strtotime($p['created_at'])) ?>
          </div>
          <a href="/<?= htmlspecialchars($p['slug']) ?>/" style="display:inline-block;margin-top:.8rem;color:#0066cc;">Lihat Halaman →</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

</body>
</html>
