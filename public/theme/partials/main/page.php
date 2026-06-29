<?php
if (empty($pageData) || !is_array($pageData)) {
  echo '<section><h1>Halaman kosong</h1></section>';
  return;
}
?>
<section class="lernginx-main-container" style="margin-top: 60px;">
  <div class="lernginx-main-content page-article">
    <header class="page-header">
      <h1><?= htmlspecialchars($pageData['title']) ?></h1>
      <?php if (!empty($pageData['excerpt'])): ?>
        <p class="page-excerpt"><?= htmlspecialchars($pageData['excerpt']) ?></p>
      <?php endif; ?>

      <div class="page-meta">
        <?= !empty($pageData['author_name']) ? '<span class="meta-author">Ditulis oleh '.htmlspecialchars($pageData['author_name']).'</span>' : '' ?>
        <?= !empty($pageData['created_at']) ? '<time datetime="'.htmlspecialchars($pageData['created_at']).'">'.date('j M Y H:i',strtotime($pageData['created_at'])).'</time>' : '' ?>
        <?= !empty($pageData['updated_at']) ? '<span class="meta-updated">(Diubah '.date('j M Y H:i',strtotime($pageData['updated_at'])).')</span>' : '' ?>
      </div>
    </header>

    <?php if (!empty($pageData['thumbnail'])): ?>
      <figure class="page-thumbnail"><img src="<?= htmlspecialchars($pageData['thumbnail']) ?>" alt=""></figure>
    <?php endif; ?>

    <div class="page-body">
      <?= $pageData['content'] /* diasumsikan sudah sanitized saat simpan */ ?>
    </div>

    <?php if (!empty($pageData['tags'])): ?>
      <footer class="page-tags">Tags:
        <?php foreach ($pageData['tags'] as $t): ?>
          <a class="tag" href="/?tag=<?= urlencode($t['slug']) ?>">#<?= htmlspecialchars($t['name']) ?></a>
        <?php endforeach; ?>
      </footer>
    <?php endif; ?>
  </div>
</section>
