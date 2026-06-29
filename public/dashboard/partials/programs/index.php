<?php
if (!defined('DASHBOARD_CONTEXT')) {
    define('DASHBOARD_CONTEXT', true);
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

// load dotlottie script sekali
echo '<script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.8.5/dist/dotlottie-wc.js" type="module"></script>';

// load mapping dari JSON
$map_file = __DIR__ . '/../../admin/menu/programs.json';
$programs_map = file_exists($map_file) ? json_decode(file_get_contents($map_file), true) : [];
?>

<section class="programs-grid">
  <div class="programs-grid__header">
    <h1 class="fade-up">Hello <?= htmlspecialchars($user['display_name'] ?? $user['username']) ?> !!</h1>
    <p class="programs-grid__intro fade-up">
      Welcome to lernginx — a free learning management system for secondary school students.
      Explore our programs and start your learning journey today! 🎓
    </p>
  </div>

  <?php if (empty($programs)): ?>
    <div class="programs-grid__empty">No programs available yet.</div>
  <?php else: ?>
    <div class="programs-grid__container" role="list">
      <?php foreach ($programs as $i => $prog):
        $name = htmlspecialchars($prog['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $slug = htmlspecialchars($prog['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $category_id = (int) $prog['id'];
        $url = rtrim(BASE_URL, '/') . '/dashboard/programs/' . $slug . '/';

        $is_siswa = ($user['role'] ?? '') === 'student';
        $is_inactive = $is_siswa && !userCanAccessCategory($pdo, $user['id'], $category_id);
        $card_class = 'programs-grid__card fade-up' . ($is_inactive ? ' inactive' : '');

        $meta = $programs_map[$slug] ?? null;
        $type = $meta['type'] ?? '';
        $media_url = $meta['url'] ?? '';
        $short_desc = $meta['desc'] ?? '';
      ?>
      <a href="<?= $url ?>" class="<?= $card_class ?>" role="listitem" aria-label="<?= $name ?>">
        <div class="programs-grid__visual">
          <?php if ($media_url): ?>
            <?php switch ($type):
              case 'lottie': ?>
                <dotlottie-wc src="<?= htmlspecialchars($media_url) ?>" style="width:300px;height:300px" autoplay loop></dotlottie-wc>
                <?php break;
              case 'image':
              case 'gif':
              case 'svg': ?>
                <img src="<?= htmlspecialchars($media_url) ?>" alt="" style="width:300px;height:300px">
                <?php break;
              default: ?>
                <div class="programs-grid__fallback">📁</div>
            <?php endswitch; ?>
          <?php else: ?>
            <div class="programs-grid__fallback">📁</div>
          <?php endif; ?>

          <?php if ($is_inactive): ?>
            <div class="programs-grid__overlay"></div>
          <?php endif; ?>
        </div>
        <div class="programs-grid__info">
          <div class="programs-grid__name"><?= $name ?></div>
          <?php if ($short_desc): ?>
            <div class="programs-grid__desc"><?= $short_desc ?></div>
          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <aside class="programs-grid__note fade-up">
      <p>🕕 <strong>Enroll now</strong> to secure your spot in our upcoming programs!</p>
      <p><strong>Join today and start learning!</strong></p>
    </aside>
  <?php endif; ?>
</section>
