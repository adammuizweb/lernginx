<section class="hprogram-detail-hero">
  <div class="hprogram-detail-inner">
    <div class="hprogram-detail-body">
      <h1><?= htmlspecialchars($categoryData['name'] ?? 'Program') ?></h1>
      <p><?= htmlspecialchars($categoryData['description'] ?? 'Explore our learning modules.') ?></p>
      <a class="hprograms-cta" href="/moduls/">Explore Modules</a>
    </div>
  </div>
</section>

<section class="hprogram-detail-modules">
  <h2>Topics</h2>
  <?php if (!empty($childCategories)): ?>
    <div class="hprograms-grid">
      <?php foreach ($childCategories as $child): ?>
        <a class="hprograms-card" href="/modul/topic/<?= htmlspecialchars($child['slug']) ?>/">
          <h3><?= htmlspecialchars($child['name']) ?></h3>
          <p><?= htmlspecialchars($child['description'] ?? '') ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p>No modules available yet. Check back soon.</p>
  <?php endif; ?>
</section>
