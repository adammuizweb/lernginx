<?php
// safe defaults so header can be included from anywhere
if (!isset($useHeroNav)) $useHeroNav = false;
if (!isset($page)) $page = $_GET['page'] ?? 'home';

function isActive($target) {
    $pageLocal = $GLOBALS['page'] ?? ($_GET['page'] ?? 'home');
    return $pageLocal === $target ? 'active' : '';
}

$overHero = $useHeroNav ? 'over-hero' : '';
?>
<?php
// lazily load main categories for dynamic submenu
if (!isset($GLOBALS['_nav_categories'])) {
    try {
        if (!isset($pdo) && file_exists(__DIR__ . '/../../includes/bootstrap_front.php')) {
            require_once __DIR__ . '/../../includes/bootstrap_front.php';
        }
        if (isset($pdo) && function_exists('get_main_categories')) {
            $GLOBALS['_nav_categories'] = get_main_categories($pdo);
        } else {
            $GLOBALS['_nav_categories'] = [];
        }
    } catch (Throwable $e) {
        $GLOBALS['_nav_categories'] = [];
    }
}
$navCats = $GLOBALS['_nav_categories'];
?>
<nav class="lernginx-nav <?= $overHero ?>">
  <div class="lernginx-nav-left">
    <button class="lernginx-nav-toggle" onclick="toggleMenu()">☰</button>
    <span class="lernginx-nav-logo-text">lernginx</span>
  </div>

  <div class="lernginx-nav-right">
    <div class="lernginx-nav-menu-wrapper">
      <ul class="lernginx-nav-menu">
        <li><a href="/" class="<?= isActive('home') ?>">Home</a></li>
        <li>
          <a href="/program/" class="<?= isActive('program') ?>">Programs</a>
          <ul class="lernginx-nav-submenu">
            <?php foreach ($navCats as $cat): ?>
              <li><a href="/<?= htmlspecialchars($cat['slug']) ?>/"><?= htmlspecialchars($cat['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </li>
        <li><a href="/moduls/" class="<?= isActive('modul') ?>">Moduls</a></li>
        <li><a href="/profile/" class="<?= isActive('profile') ?>">Profile</a></li>
        <li><a href="/register/" class="<?= isActive('signup') ?>">Sign Up</a></li>
      </ul>
    </div>
  </div>
</nav>

<script>
function toggleMenu() {
  document.querySelector('.lernginx-nav-menu-wrapper').classList.toggle('active');
}
</script>
