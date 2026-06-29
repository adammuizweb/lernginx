<?php
// dashboard/admin/pages/index.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

// Validasi user
$user = get_user_from_session($pdo);
if ( ! $user || ! in_array($user['role'], ['teacher','admin']) ) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Pages - Dashboard';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// -----------------------------
// Handle bulk actions (POST)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected = $_POST['selected'] ?? [];
    $action = $_POST['bulk_action'];
    $ids = array_values(array_filter(array_map('intval', $selected)));

    if (empty($ids)) {
        $_SESSION['flash'] = 'Please select at least 1 page for bulk action.';
    } else {
        try {
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            if ($action === 'delete') {
                // soft delete many
                $sql = "UPDATE pages SET is_deleted = 1, deleted_at = :deleted_at WHERE id IN ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $params = array_merge([date('Y-m-d H:i:s')], $ids);
                $stmt->execute($params);
                $_SESSION['flash'] = 'Pages moved to recycle bin successfully.';
            } elseif ($action === 'draft' || $action === 'published' || $action === 'private') {
                $sql = "UPDATE pages SET status = ? WHERE id IN ($placeholders)";
                $params = array_merge([$action], $ids);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $_SESSION['flash'] = 'Page statuses updated successfully.';
            } else {
                $_SESSION['flash'] = 'Unknown action.';
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Bulk action pages failed: ' . $e->getMessage());
            $_SESSION['flash'] = 'An error occurred while processing the bulk action.';
        }
    }

    $redirect = $_SERVER['PHP_SELF'] . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
    header('Location: ' . $redirect);
    exit;
}

// -----------------------------
// Pagination & filters (GET)
// -----------------------------
$perPageOptions = [5,10,20,50,100];
$per_page = 10;
if (isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $perPageOptions, true)) {
    $per_page = (int)$_GET['per_page'];
}
$page = 1;
if (isset($_GET['page']) && (int)$_GET['page'] > 0) $page = (int)$_GET['page'];

$status = trim($_GET['status'] ?? '');
$author_raw = $_GET['author_id'] ?? '';
$tag = trim($_GET['tag'] ?? '');
$created_from = trim($_GET['created_from'] ?? '');
$created_to = trim($_GET['created_to'] ?? '');
$updated_from = trim($_GET['updated_from'] ?? '');
$updated_to = trim($_GET['updated_to'] ?? '');
$q = trim($_GET['q'] ?? '');

$filters = [
    'status' => $status,
    'author_id' => $author_raw !== '' ? (int)$author_raw : '',
    'tag' => $tag,
    'created_from' => $created_from,
    'created_to' => $created_to,
    'updated_from' => $updated_from,
    'updated_to' => $updated_to,
    'q' => $q,
];


// Build options for helper
$opts = [
    'per_page' => $per_page,
    'page' => $page,
    'status' => $filters['status'] ?: null,
    'author_id' => $filters['author_id'] ?: null,
    'tag' => $filters['tag'] ?: null,
    'created_from' => $filters['created_from'] ?: null,
    'created_to' => $filters['created_to'] ?: null,
    'updated_from' => $filters['updated_from'] ?: null,
    'updated_to' => $filters['updated_to'] ?: null,
    'q' => $filters['q'] ?: null,
    'includeDeleted' => false,
];

// gunakan helper pages_list yang sudah tersedia di bootstrap
$res = pages_list($pdo, $opts);
$pages = $res['items'];
$total = $res['total'];
$total_pages = $res['total_pages'];

// authors list (simple)
$authors = [];
try {
    $authStmt = $pdo->query("SELECT id, COALESCE(username, display_name) AS username FROM users ORDER BY username ASC");
    $authors = $authStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore; optional
}

// helper to preserve querystring
function build_query(array $overrides = []) {
    $qs = array_merge($_GET, $overrides);
    return http_build_query($qs);
}

// capture HTML
ob_start();
?>

<?php if (!empty($_SESSION['flash'])): ?>
  <div id="flashModal" class="flash-modal">
    <div class="flash-modal-content">
      <div class="flash-modal-header">
        <span class="flash-modal-title">Notifikasi</span>
        <button class="flash-modal-close" onclick="closeFlashModal()">×</button>
      </div>
      <div class="flash-modal-body">
        <?= htmlspecialchars($_SESSION['flash']) ?>
      </div>
      <div class="flash-modal-footer">
        <button onclick="closeFlashModal()">Oke</button>
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

<!-- CONFIRM DELETE MODAL -->
<div id="confirmDeleteModal" class="flash-modal" style="display:none;">
  <div class="flash-modal-content">
    <div class="flash-modal-header">
      <span class="flash-modal-title">Confirm Delete</span>
      <button class="flash-modal-close" onclick="closeDeleteModal()">×</button>
    </div>
    <div class="flash-modal-body">
      Beneran, yakin ingin hapus halaman ini?
    </div>
    <div class="flash-modal-footer">
      <button onclick="closeDeleteModal()">Cancel</button>
      <a id="confirmDeleteBtn" href="#" class="btn">Yes, Delete</a>
    </div>
  </div>
</div>

<!-- FILTERS -->
<details class="filters-wrapper" <?= !empty($_GET) ? 'open' : '' ?>>
  <summary style="cursor:pointer; font-weight:bold; padding:6px 0; font-size:1rem;">🔍 Search Filters</summary>

  <form method="get" class="filters-form" style="margin:0.5rem 0 1rem 0;">
    <label>Per page:
      <select name="per_page" onchange="this.form.submit()">
        <?php foreach ($perPageOptions as $opt): ?>
          <option value="<?= $opt ?>" <?= $per_page === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Status:
      <select name="status">
        <option value="">— All —</option>
        <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="private" <?= $filters['status'] === 'private' ? 'selected' : '' ?>>Private</option>
      </select>
    </label>

    <label>Author:
      <select name="author_id">
        <option value="">— All —</option>
        <?php foreach ($authors as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= ((string)$filters['author_id'] === (string)$a['id']) ? 'selected' : '' ?>><?= htmlspecialchars($a['username']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    
    <label style="margin-left:8px;">
  Tag:
  <input type="text" name="tag" placeholder="Tag name" value="<?= htmlspecialchars($_GET['tag'] ?? '') ?>">
</label>


    <label style="margin-left:8px;">Created from <input type="date" name="created_from" value="<?= htmlspecialchars($filters['created_from']) ?>"></label>
    <label>to <input type="date" name="created_to" value="<?= htmlspecialchars($filters['created_to']) ?>"></label>

    <label style="margin-left:8px;">
      Search:
      <input type="text" name="q" placeholder="title, slug, or content" value="<?= htmlspecialchars($filters['q']) ?>">
    </label>

    <button type="submit">Apply</button>
    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn" style="margin-left:6px;">Reset</a>
  </form>
</details>

<!-- BULK ACTION FORM -->
<form method="post" id="bulkForm" style="margin-top:16px;">
  <?php if ($user['role'] === 'admin'): ?>
    <div style="margin-bottom:8px;">
      <select name="bulk_action" id="bulk_action">
        <option value="">Aksi massal...</option>
        <option value="delete">Delete (Recycle bin)</option>
        <option value="draft">Jadikan Draft</option>
        <option value="published">Jadikan Published</option>
        <option value="private">Set Private</option>
      </select>
      <button type="submit" onclick="return confirmBulkAction()">Jalankan</button>
    </div>
  <?php endif; ?>

  <p><a href="add.php" class="btn add">+ Add New Page</a></p>

  <table class="content-table">
    <thead>
      <tr>
        <?php if ($user['role'] === 'admin'): ?>
          <th><input type="checkbox" id="selectAll" /></th>
        <?php else: ?>
          <th>#</th>
        <?php endif; ?>
        <th>Title</th>
        <th>Slug</th>
        <th>Tags</th>
        <th>Status</th>
        <th>Author</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($pages)): ?>
        <tr><td colspan="8">No pages found.</td></tr>
      <?php endif; ?>

      <?php foreach ($pages as $p): ?>
        <tr>
          <?php if ($user['role'] === 'admin'): ?>
            <td><input type="checkbox" name="selected[]" value="<?= (int)$p['id'] ?>" class="rowCheckbox" /></td>
          <?php else: ?>
            <td>•</td>
          <?php endif; ?>
          <td><?= htmlspecialchars($p['title']) ?></td>
          <td><?= htmlspecialchars($p['slug'] ?? '') ?></td>
          <td>
            <?php if (!empty($p['tags'])): ?>
              <?php foreach ($p['tags'] as $t): ?>
                <span class="tag"><?= htmlspecialchars($t['name']) ?></span>
              <?php endforeach; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><?= htmlspecialchars($p['status']) ?></td>
          <td><?= htmlspecialchars($p['author_name'] ?? '-') ?></td>
          <td><?= date('j M Y H:i', strtotime($p['created_at'])) ?></td>
          <td>
            <a href="edit.php?id=<?= $p['id'] ?>" class="btn edit">Edit</a>
            <a href="#" class="btn del" onclick="showDeleteModal(<?= $p['id'] ?>)">Delete</a>
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

<script>
function showDeleteModal(id){
  const m = document.getElementById('confirmDeleteModal');
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
  if (!action) { alert('Please select a bulk action.'); return false; }
  const anyChecked = Array.from(document.querySelectorAll('.rowCheckbox')).some(cb => cb.checked);
  if (!anyChecked) { alert('Please select at least 1 page.'); return false; }
  if (action === 'delete') return confirm('Are you sure you want to move selected pages to recycle bin?');
  return true;
}
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Pages - Dashboard';
require_once __DIR__ . '/../../partials/layout.php';
