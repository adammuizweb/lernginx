<?php
// dashboard/admin/tags/index.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

// auth
$user = get_user_from_session($pdo);
if ( ! $user || ! in_array($user['role'], ['teacher','admin']) ) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Tags - Dashboard';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// Handle bulk actions for tags
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected = $_POST['selected'] ?? [];
    $action = $_POST['bulk_action'];
    $ids = array_values(array_filter(array_map('intval', $selected)));

    if (empty($ids)) {
        $_SESSION['flash'] = 'Please select at least 1 tag for bulk action.';
    } else {
        try {
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($action === 'delete') {
                // deleting tags will cascade to page_tag because of FK
                $sql = "DELETE FROM tags WHERE id IN ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($ids);
                $_SESSION['flash'] = 'Tags deleted successfully.';
            } else {
                $_SESSION['flash'] = 'Unknown action.';
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Bulk action tags failed: ' . $e->getMessage());
            $_SESSION['flash'] = 'An error occurred while processing the bulk action.';
        }
    }

    $redirect = $_SERVER['PHP_SELF'] . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
    header('Location: ' . $redirect);
    exit;
}

// pagination & search GET
$perPageOptions = [10,20,50,100];
$per_page = 20;
if (isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $perPageOptions, true)) $per_page = (int)$_GET['per_page'];
$page = 1;
if (isset($_GET['page']) && (int)$_GET['page'] > 0) $page = (int)$_GET['page'];
$offset = ($page - 1) * $per_page;

$q = trim($_GET['q'] ?? '');

// count tags
$where = '1=1';
$params = [];
if ($q !== '') {
    $where = '(name LIKE :q OR slug LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tags WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// select tags
$sql = "SELECT * FROM tags WHERE $where ORDER BY name ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// helper to preserve qs
function build_query(array $overrides = []) {
    $qs = array_merge($_GET, $overrides);
    return http_build_query($qs);
}

// capture html
ob_start();
?>

<?php if (!empty($_SESSION['flash'])): ?>
  <div id="flashModal" class="flash-modal">
    <div class="flash-modal-content">
      <div class="flash-modal-header">
        <span class="flash-modal-title">Notification</span>
        <button class="flash-modal-close" onclick="closeFlashModal()">×</button>
      </div>
      <div class="flash-modal-body">
        <?= htmlspecialchars($_SESSION['flash']) ?>
      </div>
      <div class="flash-modal-footer">
        <button onclick="closeFlashModal()">OK</button>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const m = document.getElementById('flashModal');
      if (m) m.style.display = 'flex';
    });
    function closeFlashModal(){ document.getElementById('flashModal').style.display = 'none'; }
  </script>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- BULK ACTION FORM -->
<form method="post" id="bulkForm" style="margin-top:16px;">
  <?php if ($user['role'] === 'admin'): ?>
    <div style="margin-bottom:8px;">
      <select name="bulk_action" id="bulk_action">
        <option value="">Bulk action...</option>
        <option value="delete">Delete</option>
      </select>
      <button type="submit" onclick="return confirmBulkAction()">Execute</button>
    </div>
  <?php endif; ?>

  <p>
    <a href="add.php" class="btn add">+ Add New Tag</a>
    <form method="get" style="display:inline-block; margin-left:12px;">
      <input type="text" name="q" placeholder="search name or slug" value="<?= htmlspecialchars($q) ?>">
      <button type="submit">Search</button>
    </form>
  </p>

  <table class="content-table">
    <thead>
      <tr>
        <?php if ($user['role'] === 'admin'): ?>
          <th><input type="checkbox" id="selectAll" /></th>
        <?php else: ?>
          <th>#</th>
        <?php endif; ?>
        <th>Name</th>
        <th>Slug</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($tags)): ?>
        <tr><td colspan="5">No tags found.</td></tr>
      <?php endif; ?>

      <?php foreach ($tags as $t): ?>
        <tr>
          <?php if ($user['role'] === 'admin'): ?>
            <td><input type="checkbox" name="selected[]" value="<?= (int)$t['id'] ?>" class="rowCheckbox" /></td>
          <?php else: ?>
            <td>•</td>
          <?php endif; ?>
          <td><?= htmlspecialchars($t['name']) ?></td>
          <td><?= htmlspecialchars($t['slug']) ?></td>
          <td><?= date('j M Y H:i', strtotime($t['created_at'])) ?></td>
          <td>
            <a href="edit.php?id=<?= $t['id'] ?>" class="btn edit">Edit</a>
            <a href="#" class="btn del" onclick="showDeleteModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['name'])) ?>')">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</form>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
  <nav class="pagination" aria-label="Pagination" style="margin-top:0.8rem;">
    <?php
      $range = 2;
      $start = max(1, $page - $range);
      $end = min($total_pages, $page + $range);
    ?>
    <?php if ($page > 1): ?>
      <a href="?<?= build_query(['page'=>1]) ?>">&laquo; First</a>
      <a href="?<?= build_query(['page'=>$page-1]) ?>">&lsaquo; Prev</a>
    <?php endif; ?>
    <?php for ($i=$start;$i<=$end;$i++): ?>
      <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
      <?php else: ?><a href="?<?= build_query(['page'=>$i]) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
      <a href="?<?= build_query(['page'=>$page+1]) ?>">Next &rsaquo;</a>
      <a href="?<?= build_query(['page'=>$total_pages]) ?>">Last &raquo;</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<!-- CONFIRM DELETE MODAL -->
<div id="confirmDeleteModal" class="flash-modal" style="display:none;">
  <div class="flash-modal-content">
    <div class="flash-modal-header">
      <span class="flash-modal-title">Confirm Delete Tag</span>
      <button class="flash-modal-close" onclick="closeDeleteModal()">×</button>
    </div>
    <div class="flash-modal-body" id="confirmDeleteBody">
    </div>
    <div class="flash-modal-footer">
      <button onclick="closeDeleteModal()">Cancel</button>
      <a id="confirmDeleteBtn" href="#" class="btn">Yes, Delete</a>
    </div>
  </div>
</div>

<script>
function showDeleteModal(id, name){
  const m = document.getElementById('confirmDeleteModal');
  document.getElementById('confirmDeleteBody').innerText = "Are you sure you want to delete tag: " + name + " ? (will be removed from all pages)";
  document.getElementById('confirmDeleteBtn').href = 'delete.php?id=' + id;
  m.style.display = 'flex';
}
function closeDeleteModal(){ document.getElementById('confirmDeleteModal').style.display = 'none'; }

const selectAllEl = document.getElementById('selectAll');
if (selectAllEl) {
  selectAllEl.addEventListener('change', function(){
    const checked = this.checked;
    document.querySelectorAll('.rowCheckbox').forEach(cb => cb.checked = checked);
  });
}

function confirmBulkAction(){
  const action = document.getElementById('bulk_action').value;
  if (!action) { alert('Please select a bulk action first.'); return false; }
  const anyChecked = Array.from(document.querySelectorAll('.rowCheckbox')).some(cb => cb.checked);
  if (!anyChecked) { alert('Please select at least 1 tag.'); return false; }
  if (action === 'delete') return confirm('Are you sure you want to delete selected tags? (will be removed from all pages)');
  return true;
}
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Tags - Dashboard';
require_once __DIR__ . '/../../partials/layout.php';
