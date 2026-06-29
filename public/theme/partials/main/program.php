<?php
if (!isset($pdo) || !function_exists('get_main_categories')) {
    require_once __DIR__ . '/../../../includes/bootstrap_front.php';
}
$allPrograms = get_main_categories($pdo);
$programIcons = ['/assets/img/program2.png','/assets/img/program3.png','/assets/img/program4.png','/assets/img/program5.png'];
?>
<section class="hprograms-hero page-program">
  <div class="hprograms-hero-inner">
    <div class="hprograms-hero-media fade-up">
      <img src="/assets/img/program1.png" alt="Programs">
    </div>
    <div class="hprograms-hero-body fade-up">
      <h1>EXPLORE OUR PROGRAMS</h1>
      <p>Discover hands-on learning experiences, interactive courses, and expert mentorship across science, technology, arts, and language.</p>
      <a class="hprograms-cta" href="#modules">View Programs</a>
    </div>
  </div>
</section>

<section class="hprograms-modules fade-up" id="modules">
  <h2>Our Programs</h2>
  <div class="hprograms-grid">
    <?php foreach ($allPrograms as $i => $prog): 
      $imgIdx = $i % count($programIcons);
    ?>
      <a class="hprograms-card" href="/<?= htmlspecialchars($prog['slug']) ?>/">
        <img src="<?= $programIcons[$imgIdx] ?>" alt="<?= htmlspecialchars($prog['name']) ?>">
        <h3><?= htmlspecialchars($prog['name']) ?></h3>
      </a>
    <?php endforeach; ?>
  </div>
</section>
