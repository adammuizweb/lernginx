<?php
// dashboard/partials/programs/children.php
// Revisi: lebih robust, gunakan mapping dari admin/submenu/children.json jika ada.
// Menggunakan BASE_URL jika didefinisikan untuk path base yang eksplisit.

if (!defined('DASHBOARD_CONTEXT')) {
    define('DASHBOARD_CONTEXT', true);
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

// Pastikan $program, $children, $posts, $show_posts disediakan oleh controller.
// Fallback nama program:
$title_safe = $title_safe ?? ($program['name'] ?? 'Program');

// Load user/DB helpers if not already loaded
$user = $user ?? (function_exists('get_user_from_session') ? get_user_from_session($pdo) : null);

// Load children mapping file (admin submenu) dengan path yang aman
$base = defined('BASE_URL') && BASE_URL !== '' ? rtrim(BASE_URL, "/") : '';
// jika BASE_URL diset sebagai root web, kita tetap butuh filesystem path;
// asumsi: bootstrap menyediakan BASE_PATH atau gunakan relatif dari __DIR__
$fs_base = defined('BASE_PATH') ? rtrim(BASE_PATH, "/") : realpath(__DIR__ . '/../../..');

$children_map_file_candidates = [
    // prefer explicit admin path if exists
    $fs_base . '/dashboard/admin/submenu/children.json',
    __DIR__ . '/../../admin/submenu/children.json',
    __DIR__ . '/../admin/submenu/children.json',
];

// cari file yang ada
$children_map = [];
foreach ($children_map_file_candidates as $f) {
    if ($f && file_exists($f)) {
        $json = @file_get_contents($f);
        $children_map = @json_decode($json, true) ?: [];
        break;
    }
}

// Fallback: beberapa mapping statis (legacy) untuk memastikan ada konten
$legacy_map = [
    'jepang' => [
        'url' => 'https://lottie.host/d0c8d6ed-8321-47cf-9b08-73d22b15f161/BvSKXOAWZP.lottie',
        'label' => 'Jepang',
        'type' => 'lottie',
        'desc' => ''
    ],
    'bahasa-inggris' => [
        'url' => 'https://lottie.host/8a84b532-9dd9-408b-a295-6e0ba1c84bf1/0zAdA6j7cf.lottie',
        'label' => 'Bahasa Inggris',
        'type' => 'lottie',
        'desc' => ''
    ]
];

// safe helpers
function _h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function _slugify($s) { return preg_replace('/[^a-z0-9\-]+/','-', strtolower(trim($s))); }

// include lottie runtime once (only if used)
$lottie_included = false;
foreach ($children as $c) {
    $slugTest = isset($c['slug']) ? _slugify($c['slug']) : '';
    $metaTest = $children_map[$slugTest] ?? null;
    if ($metaTest && ($metaTest['type'] ?? '') === 'lottie') { $lottie_included = true; break; }
}
if ($lottie_included) {
    echo '<script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.8.5/dist/dotlottie-wc.js" type="module"></script>';
}
?>

<section class="kategori-view program-view">
  <h2><?= _h($title_safe) ?></h2>

<?php if (!empty($children)): ?>
  <h3>Subtopik</h3>

  <div class="programs-grid__container program-child-grid" role="list">
<?php foreach ($children as $child):
    $slug = _slugify($child['slug'] ?? ($child['name'] ?? ''));
    $meta = $children_map[$slug] ?? null;

    // Validate: skip if no mapping or inactive
    if (!$meta || empty($meta['active'])) continue;

    $name = _h($child['name'] ?? '');
    $url = _h("/dashboard/topic/{$slug}/");

    $media_url = $meta['url'] ?? '';
    $media_type = $meta['type'] ?? '';

    // Check student access
    $category_id = null;
    if (function_exists('get_category_id_by_slug')) {
        try {
            $category_id = get_category_id_by_slug($pdo, $slug);
        } catch (Exception $e) {
            $category_id = null;
        }
    }

    $is_siswa = (($user['role'] ?? '') === 'student');
    $is_inactive = $is_siswa && $category_id && function_exists('userCanAccessCategory') && !userCanAccessCategory($pdo, $user['id'], $category_id);

    $card_class = 'programs-grid__card program-child-card fade-up' . ($is_inactive ? ' inactive' : '');
?>
  <a href="<?= $url ?>" class="<?= _h($card_class) ?>" role="listitem" aria-label="<?= $name ?>">
    <div class="programs-grid__visual">
      <?php if ($media_type === 'lottie' && $media_url): ?>
        <dotlottie-wc src="<?= _h($media_url) ?>" style="width:300px;height:300px" autoplay loop></dotlottie-wc>
      <?php elseif ($media_type === 'image' && $media_url): ?>
        <img src="<?= _h($media_url) ?>" alt="<?= $name ?>" style="max-width:300px;max-height:300px">
      <?php else: ?>
        <div class="programs-grid__fallback">📁</div>
      <?php endif; ?>

      <?php if ($is_inactive): ?>
        <div class="programs-grid__overlay"></div>
      <?php endif; ?>
    </div>

    <div class="programs-grid__info">
      <div class="programs-grid__name"><?= $name ?></div>
      <?php if (!empty($meta['desc'])): ?>
        <div class="programs-grid__desc"><?= _h($meta['desc']) ?></div>
      <?php endif; ?>
    </div>
  </a>
<?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
// Show posts from parent if requested
if ($show_posts && !empty($posts)): ?>
  <h3>Materi Langsung di Program Ini</h3>
  <ul class="kategori-post-list program-direct-posts">
    <?php foreach ($posts as $post):
        $title = _h($post['title'] ?? 'Untitled');
        $post_slug  = _h($post['slug'] ?? '');
        $cat_path = function_exists('get_category_path') ? get_category_path($pdo, $post['category_id']) : ($post['category_slug'] ?? '');
        $post_url = _h("/dashboard/post/{$cat_path}/{$post_slug}/");

        // thumbnail fallback
        $thumbUrl = null;
        if (!empty($post['featured_image'])) {
          $thumbUrl = $post['featured_image'];
        } elseif (!empty($post['youtube_url'])) {
          if (preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $post['youtube_url'], $m) && !empty($m[1])) {
            $thumbUrl = 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg';
          }
        }

        // category label
        $catLabel = '—';
        if (!empty($post['category_id']) && function_exists('get_category_by_id')) {
          $cat = get_category_by_id($pdo, $post['category_id']);
          if ($cat) {
            if (!empty($cat['parent_id'])) {
              $parent = get_category_by_id($pdo, $cat['parent_id']);
              $catLabel = _h(($parent['name'] ?? '') . ' / ' . ($cat['name'] ?? ''));
            } else {
              $catLabel = _h($cat['name'] ?? '');
            }
          }
        }

        // author
        $authorName = 'Unknown';
        if (!empty($post['author_id']) && function_exists('get_user_by_id')) {
          $u = get_user_by_id($pdo, $post['author_id']);
          if ($u) $authorName = _h($u['display_name'] ?? $u['username']);
        }

        // dates
        $created = !empty($post['created_at']) ? date('j M Y', strtotime($post['created_at'])) : '—';
        $updated = !empty($post['updated_at']) ? date('j M Y', strtotime($post['updated_at'])) : $created;

        $text = strip_tags($post['excerpt'] ?? $post['content'] ?? '');
        $excerpt = mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, 200, 'UTF-8');
        if (mb_strlen($text, 'UTF-8') > 200) $excerpt .= '…';
    ?>
      <li class="kategori-post-item">
        <div class="kategori-post-thumb" aria-hidden="true">
          <?php if ($thumbUrl): ?>
            <img src="<?= _h($thumbUrl) ?>" alt="<?= $title ?>">
          <?php else: ?>
            <svg width="100%" height="100%" viewBox="0 0 4 3" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" style="background:#f4f4f4;display:block;">
              <rect width="4" height="3" fill="#f4f4f4"></rect>
              <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#bbb" font-size="0.35">No image</text>
            </svg>
          <?php endif; ?>
        </div>

        <div class="kategori-post-body">
          <div class="kategori-post-header">
            <h3 class="kategori-post-title"><a href="<?= $post_url ?>"><?= $title ?></a></h3>
            <div class="kategori-post-meta-right"><?= _h($updated) ?></div>
          </div>

          <div class="kategori-post-meta">
            <div class="kategori-post-meta-left">
              <span class="meta-cat" title="Category"><?= $catLabel ?></span>
              <span class="meta-author">by <?= $authorName ?></span>
            </div>
            <div class="kategori-post-meta-right"><?= _h($created) ?></div>
          </div>

          <?php if ($excerpt): ?>
            <div class="kategori-post-excerpt"><?= nl2br(_h($excerpt)) ?></div>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if (empty($children) && (!$show_posts || empty($posts))): ?>
  <p>No subtopics or materials available for this program yet.</p>
<?php endif; ?>

</section>
