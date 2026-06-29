<?php
// ./theme/partials/main/page.php
// pastikan $pageData sudah tersedia (array)
if (!isset($pageData) || !is_array($pageData)) {
    echo '<section style="padding:2rem;"><h1>Halaman kosong</h1></section>';
    return;
}
?>
<section class="lernginx-main-container">
  <div class="lernginx-main-content page-article">
    <header class="page-header">
      <h1 class="article-title"><?= htmlspecialchars($pageData['title']) ?></h1>
      <?php if (!empty($pageData['excerpt'])): ?>
        <p class="page-excerpt"><?= htmlspecialchars($pageData['excerpt']) ?></p>
      <?php endif; ?>

      <div class="page-meta">
        <?php if (!empty($pageData['author_name'])): ?>
          <span class="meta-author">Ditulis oleh <?= htmlspecialchars($pageData['author_name']) ?></span>
        <?php endif; ?>
        <?php if (!empty($pageData['created_at'])): ?>
          <time datetime="<?= htmlspecialchars($pageData['created_at']) ?>"><?= date('j M Y H:i', strtotime($pageData['created_at'])) ?></time>
        <?php endif; ?>
        <?php if (!empty($pageData['updated_at'])): ?>
          <span class="meta-updated">(Diubah <?= date('j M Y H:i', strtotime($pageData['updated_at'])) ?>)</span>
        <?php endif; ?>
      </div>
    </header>

    <?php if (!empty($pageData['thumbnail'])): ?>
      <figure class="page-thumbnail"><img src="<?= htmlspecialchars($pageData['thumbnail']) ?>" alt=""></figure>
    <?php endif; ?>

    <div class="page-body">
      <?= $pageData['content'] /* diasumsikan aman / sudah di-sanitize saat simpan */ ?>
    </div>

    <?php if (!empty($pageData['tags']) && is_array($pageData['tags'])): ?>
      <footer class="page-tags">
        Tags:
        <?php foreach ($pageData['tags'] as $t): ?>
          <a class="tag" href="/?tag=<?= urlencode($t['slug']) ?>">#<?= htmlspecialchars($t['name']) ?></a>
        <?php endforeach; ?>
      </footer>
    <?php endif; ?>
  </div>
</section>
