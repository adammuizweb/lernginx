<?php
// tempat daftar modul dashboard/student/index.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../partials/functions/render_category_checkbox.php';

date_default_timezone_set('UTC');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = get_user_from_session($pdo);
if (!$user) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Profile - Dashboard';
    require_once __DIR__ . '/../partials/layout.php';
    exit;
}

// Ambil kebijakan sistem default status
$policyStmt = $pdo->prepare("SELECT value FROM registration_policies WHERE key_name = 'default_student_module_status' LIMIT 1");
$policyStmt->execute();
$policy = $policyStmt->fetchColumn();
$defaultStatus = ($policy !== false) ? (int)$policy : 0;

// Ambil max parent limit (default 2)
$limitStmt = $pdo->prepare("SELECT value FROM registration_policies WHERE key_name = 'max_parent_modules_per_student' LIMIT 1");
$limitStmt->execute();
$limitVal = $limitStmt->fetchColumn();
$maxParentLimit = ($limitVal !== false) ? max(1, (int)$limitVal) : 2;

// Ambil semua kategori
$stmt = $pdo->query("SELECT id, name, slug, parent_id FROM categories ORDER BY COALESCE(parent_id, 0), name");
$categories = $stmt->fetchAll();

// Ambil nama kategori
$categoryNames = [];
foreach ($categories as $row) {
    $categoryNames[(int)$row['id']] = $row['name'];
}

// Ambil modul user
$stmt = $pdo->prepare("SELECT id, category_id, status, is_reviewed FROM modules WHERE user_id = ?");
$stmt->execute([$user['id']]);
$userModules = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $userModules[(int)$r['category_id']] = [
        'id' => (int)$r['id'],
        'status' => (int)$r['status'],
        'is_reviewed' => ((int)$r['is_reviewed'] === 1)
    ];
}

// Ambil modul yang aktif dan sudah direview (dipakai modal konfirmasi)
$reviewedModules = [];
foreach ($userModules as $cid => $mod) {
    if ($mod['status'] === 0 && $mod['is_reviewed']) {
        $reviewedModules[$cid] = $categoryNames[$cid] ?? "Category #$cid";
    }
}

// Bangun pohon kategori
$tree = [];
foreach ($categories as $c) {
    $tree[(int)$c['parent_id']][] = $c;
}

ob_start();
?>

<?php if (!empty($_SESSION['flash_message'])): ?>
  <div id="flashModal" class="modal-flash" role="dialog" aria-modal="true">
    <div class="modal-content">
      <p><?= htmlspecialchars($_SESSION['flash_message']) ?></p>
      <button type="button" onclick="closeFlash()" aria-label="Close">Close</button>
    </div>
  </div>
  <script>
    function closeFlash() {
      const m = document.getElementById('flashModal');
      if (m) m.style.display = 'none';
    }
    window.addEventListener('DOMContentLoaded', () => {
      const modal = document.getElementById('flashModal');
      if (modal) {
        modal.style.display = 'flex';
        setTimeout(closeFlash, 4000);
      }
    });
  </script>
  <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<?php if ($defaultStatus === 1): ?>
  <div class="alert alert-warning">
    The system is in review mode. You can only request new modules. Previously submitted choices cannot be changed.
  </div>
<?php endif; ?>

<h2>My Profile</h2>

<form method="POST" action="daftar.php" id="moduleForm" novalidate>
  <fieldset disabled>
    <div class="form-group">
      <label>Username</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>">
    </div>

    <div class="form-group">
      <label>Role</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars($user['role']) ?>">
    </div>

    <div class="form-group">
      <label>Email</label>
      <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
    </div>

    <div class="form-group">
      <label>Display Name</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars($user['display_name'] ?? '') ?>">
    </div>
  </fieldset>

  <hr>
  <h3>Select Module / Program</h3>

  <div class="module-limit-info" aria-live="polite" style="margin-bottom:8px;">
    <strong>Maximum parent modules you can register:</strong>
    <span id="currentParentCount">0</span> / <span id="maxParentLimit"><?= (int)$maxParentLimit ?></span>
  </div>

  <div class="module-checkboxes" aria-live="polite">
    <?php
      $top = $tree[0] ?? $tree[null] ?? [];
      render_category_checkbox($top, $tree, $userModules, $defaultStatus);
    ?>
  </div>

  <button type="submit" id="submitModules">Save Changes</button>
</form>

<!-- Confirmation Modal -->
<div class="modal-konfirm" id="modalKonfirm" role="dialog" aria-modal="true" style="display:none">
  <div class="modal-body">
    <h3>Confirm Cancellation</h3>
    <p>The following modules have been reviewed by a teacher and will be cancelled:</p>
    <ul id="reviewedList"></ul>
    <p>Cancelling will re-submit or change the status. Continue with cancellation?</p>
    <div class="modal-actions">
      <button class="btn btn-danger" id="confirmCancel">Yes, cancel</button>
      <button class="btn btn-secondary" id="cancelModal">Cancel</button>
    </div>
  </div>
</div>

<script>
  const reviewedModules = <?= json_encode($reviewedModules) ?>;
  const MAX_PARENT_LIMIT = <?= (int)$maxParentLimit ?>;

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('moduleForm');
    const modal = document.getElementById('modalKonfirm');
    const confirmBtn = document.getElementById('confirmCancel');
    const cancelBtn = document.getElementById('cancelModal');
    const reviewedList = document.getElementById('reviewedList');

    if (!form || !modal || !confirmBtn || !cancelBtn || !reviewedList) return;

    form.addEventListener('submit', function (e) {
      const checked = Array.from(document.querySelectorAll('input[name="modules[]"]:checked'))
        .map(cb => parseInt(cb.value));

      const cancelingReviewed = Object.entries(reviewedModules)
        .filter(([id]) => !checked.includes(parseInt(id)))
        .map(([id, name]) => name);

      if (cancelingReviewed.length > 0) {
        e.preventDefault();
        reviewedList.innerHTML = cancelingReviewed.map(name => `<li>${name}</li>`).join('');
        modal.style.display = 'flex';

        confirmBtn.onclick = () => {
          modal.style.display = 'none';
          form.submit();
        };

        cancelBtn.onclick = () => {
          modal.style.display = 'none';
        };
      }
    });
  });
</script>

<script>
  // build parent map: childId => parentId (top-level parent has parentId = 0)
  window.categoryParentMap = <?php
    $map = [];
    foreach ($tree as $pid => $children) {
        foreach ($children as $child) {
            $map[(int)$child['id']] = (int)$pid;
        }
    }
    echo json_encode($map);
  ?>;
</script>
<script src="max_modul.js"></script>
<?php
// include partials modal
require_once __DIR__ . '/../partials/first-login.php';


$content = ob_get_clean();
$pageTitle = 'Profile - Dashboard';
require_once __DIR__ . '/../partials/layout.php';
