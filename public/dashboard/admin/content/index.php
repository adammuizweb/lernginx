<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
date_default_timezone_set('UTC');

// Validasi user
$user = get_user_from_session($pdo);
if ( ! $user || !in_array($user['role'], ['teacher', 'admin']) ) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Content - Dashboard';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// -----------------------------
// Handle bulk actions (POST)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected = $_POST['selected'] ?? [];
    $action = $_POST['bulk_action'];
    // sanitize ids
    $ids = array_values(array_filter(array_map('intval', $selected)));

    if (empty($ids)) {
        $_SESSION['flash'] = 'Select at least 1 post untuk melakukan aksi massal.';
        // redirect kembali ke GET (preserve query string)
        $redirect = $_SERVER['PHP_SELF'] . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($action === 'delete') {
            // soft delete
            $sql = "UPDATE posts SET is_deleted = 1 WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);
            $_SESSION['flash'] = 'Posts moved to recycle bin successfully.';
        } elseif ($action === 'draft' || $action === 'published') {
            $sql = "UPDATE posts SET status = ? WHERE id IN ($placeholders)";
            $params = array_merge([$action], $ids);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $_SESSION['flash'] = 'Post statuses updated successfully.';
        } else {
            $_SESSION['flash'] = 'Unknown action.';
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Bulk action failed: ' . $e->getMessage());
        $_SESSION['flash'] = 'An error occurred while processing bulk action.';
    }

    $redirect = $_SERVER['PHP_SELF'] . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
    header('Location: ' . $redirect);
    exit;
}
// -----------------------------
// Inisialisasi pagination, page, dan filter dari GET
// -----------------------------
$perPageOptions = [5,10,20,50,100];

// per_page: gunakan nilai GET jika valid, default 10
$per_page = 10;
if (isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $perPageOptions, true)) {
    $per_page = (int)$_GET['per_page'];
}

// page: minimal 1
$page = 1;
if (isset($_GET['page']) && (int)$_GET['page'] > 0) {
    $page = (int)$_GET['page'];
}
$offset = ($page - 1) * $per_page;

// Ambil filter dari querystring — pastikan kunci selalu ada (agar tidak undefined)
$filters = [
    'category_id'   => $_GET['category_id'] ?? '',
    'status'        => $_GET['status'] ?? '',
    'author_id'     => $_GET['author_id'] ?? '',
    'created_from'  => $_GET['created_from'] ?? '',
    'created_to'    => $_GET['created_to'] ?? '',
    'updated_from'  => $_GET['updated_from'] ?? '',
    'updated_to'    => $_GET['updated_to'] ?? '',
    'q'             => $_GET['q'] ?? '',
];

// sanitize sederhana: trim string filters
foreach (['status','created_from','created_to','updated_from','updated_to','q'] as $k) {
    if (is_string($filters[$k])) $filters[$k] = trim($filters[$k]);
}
if ($filters['category_id'] !== '') $filters['category_id'] = (int)$filters['category_id'];
if ($filters['author_id'] !== '') $filters['author_id'] = (int)$filters['author_id'];


// -----------------------------
// Build WHERE clauses safely (robust)
// -----------------------------
$where = ["(p.is_deleted = 0 OR p.is_deleted IS NULL)"];
$params = []; // keys tanpa leading colon

if ($filters['category_id'] !== '') {
    $where[] = "p.category_id = :category_id";
    $params['category_id'] = (int)$filters['category_id'];
}
if ($filters['status'] !== '') {
    $where[] = "p.status = :status";
    $params['status'] = $filters['status'];
}
if ($filters['author_id'] !== '') {
    $where[] = "p.author_id = :author_id";
    $params['author_id'] = (int)$filters['author_id'];
}
if (!empty($filters['created_from'])) {
    $where[] = "p.created_at >= :created_from";
    $params['created_from'] = $filters['created_from'] . ' 00:00:00';
}
if (!empty($filters['created_to'])) {
    $where[] = "p.created_at <= :created_to";
    $params['created_to'] = $filters['created_to'] . ' 23:59:59';
}
if (!empty($filters['updated_from'])) {
    $where[] = "p.updated_at >= :updated_from";
    $params['updated_from'] = $filters['updated_from'] . ' 00:00:00';
}
if (!empty($filters['updated_to'])) {
    $where[] = "p.updated_at <= :updated_to";
    $params['updated_to'] = $filters['updated_to'] . ' 23:59:59';
}
if (!empty($filters['q'])) {
    // gunakan dua placeholder berbeda untuk menghindari duplikat named-param
    $where[] = "(p.title LIKE :q_title OR p.slug LIKE :q_slug)";
    $params['q_title'] = '%' . $filters['q'] . '%';
    $params['q_slug']  = '%' . $filters['q'] . '%';
}

$where_sql = implode(' AND ', $where);

// -----------------------------
// COUNT (bind semua param secara eksplisit)
// -----------------------------
$countSql = "SELECT COUNT(*) FROM posts p WHERE $where_sql";
$countStmt = $pdo->prepare($countSql);

// bind semua param (tambahkan ':' saat bind)
foreach ($params as $k => $v) {
    $name = ':' . $k;
    if (is_int($v)) {
        $countStmt->bindValue($name, $v, PDO::PARAM_INT);
    } else {
        $countStmt->bindValue($name, $v, PDO::PARAM_STR);
    }
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// -----------------------------
// SELECT dengan LIMIT/OFFSET (bind semua param juga)
// -----------------------------
$sql = "
    SELECT p.*, c.name AS category_name, u.username AS author_name
    FROM posts p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.author_id = u.id
    WHERE $where_sql
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);

// bind filter params
foreach ($params as $k => $v) {
    $name = ':' . $k;
    if (is_int($v)) {
        $stmt->bindValue($name, $v, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($name, $v, PDO::PARAM_STR);
    }
}
// bind limit/offset
$stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
// load categories & authors for filters
$catsStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $catsStmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// Load authors (only guru/admin) — safe: detect columns dynamically
// -----------------------------
$hasRole = false;
$hasUsername = false;
try {
    $colStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'users' 
          AND COLUMN_NAME = :col
    ");

    $colStmt->execute([':col' => 'role']);
    $hasRole = (bool)$colStmt->fetchColumn();

    $colStmt->execute([':col' => 'username']);
    $hasUsername = (bool)$colStmt->fetchColumn();
} catch (Exception $e) {
    // jika DB user tidak izinkan INFORMATION_SCHEMA, fallback aman
    $hasRole = false;
    $hasUsername = false;
}

// Build author query depending on schema
if ($hasRole) {
    // gunakan username jika tersedia, jika tidak fallback ke display_name
    $nameCol = $hasUsername ? 'username' : 'display_name';
    $authSql = "SELECT id, {$nameCol} AS username FROM users WHERE role IN ('teacher','admin') ORDER BY {$nameCol} ASC";
} else {
    // fallback: ambil semua users tapi gunakan display_name/username bila ada
    // ini memastikan UI tetap berfungsi walau tidak ada kolom role
    if ($hasUsername) {
        $authSql = "SELECT id, username FROM users ORDER BY username ASC";
    } else {
        $authSql = "SELECT id, display_name AS username FROM users ORDER BY display_name ASC";
    }
}
$authStmt = $pdo->query($authSql);
$authors = $authStmt->fetchAll(PDO::FETCH_ASSOC);

// helper to build querystring preserving filters (for pagination links)
function build_query(array $overrides = []) {
    $qs = array_merge($_GET, $overrides);
    return http_build_query($qs);
}

// Tangkap output HTML sebagai $content
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
      document.getElementById('flashModal').style.display = 'flex';
      setTimeout(() => { /* optional auto close after 4s */ }, 4000);
    });
    function closeFlashModal() {
      document.getElementById('flashModal').style.display = 'none';
    }
  </script>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- konfirm del (per-row) -->
<div id="confirmDeleteModal" class="flash-modal" style="display:none;">
  <div class="flash-modal-content">
    <div class="flash-modal-header">
      <span class="flash-modal-title">Confirm Delete</span>
      <button class="flash-modal-close" onclick="closeDeleteModal()">×</button>
    </div>
    <div class="flash-modal-body">
      Are you sure you want to delete this post?
    </div>
    <div class="flash-modal-footer">
      <button onclick="closeDeleteModal()">Cancel</button>
      <a id="confirmDeleteBtn" href="#" class="btn">Yes, Delete</a>
    </div>
  </div>
</div>

<!-- COLLAPSIBLE FILTERS -->
<details class="filters-wrapper" <?= !empty($_GET) ? 'open' : '' ?>>
  <summary style="cursor:pointer; font-weight:bold; padding:6px 0; font-size:1rem;">
    🔍 Search Filters
  </summary>

  <form method="get" class="filters-form" style="margin:0.5rem 0 1rem 0;">
    <label>Per page:
      <select name="per_page" onchange="this.form.submit()">
        <?php foreach ($perPageOptions as $opt): ?>
          <option value="<?= $opt ?>" <?= $per_page === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Category:
      <select name="category_id">
        <option value="">— All —</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= ((string)$filters['category_id'] === (string)$cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Status:
      <select name="status">
        <option value="">— All —</option>
        <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
      </select>
    </label>

    <label>Author:
      <select name="author_id">
        <option value="">— All —</option>
        <?php foreach ($authors as $a): ?>
          <option value="<?= $a['id'] ?>" <?= ((string)$filters['author_id'] === (string)$a['id']) ? 'selected' : '' ?>><?= htmlspecialchars($a['username']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div style="display:inline-block;margin-left:8px;">
      <label>Created from <input type="date" name="created_from" value="<?= htmlspecialchars($filters['created_from']) ?>"></label>
      <label>to <input type="date" name="created_to" value="<?= htmlspecialchars($filters['created_to']) ?>"></label>
    </div>

    <div style="display:inline-block;margin-left:8px;">
      <label>Updated from <input type="date" name="updated_from" value="<?= htmlspecialchars($filters['updated_from']) ?>"></label>
      <label>to <input type="date" name="updated_to" value="<?= htmlspecialchars($filters['updated_to']) ?>"></label>
    </div>

    <label style="margin-left:8px;">
      Search:
      <input type="text" name="q" placeholder="title or slug" value="<?= htmlspecialchars($filters['q']) ?>">
    </label>

    <button type="submit">Apply</button>
    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn" style="margin-left:6px;">Reset</a>
  </form>
</details>

<!-- BULK ACTION FORM -->
<form method="post" id="bulkForm" style="margin-top: 16px">
  <?php if ($user['role'] === 'admin'): ?>
    <div style="margin-bottom:8px;">
      <select name="bulk_action" id="bulk_action">
        <option value="">Bulk action...</option>
        <option value="delete">Delete (Recycle bin)</option>
        <option value="draft">Set as Draft</option>
        <option value="published">Set as Published</option>
      </select>
      <button type="submit" onclick="return confirmBulkAction()">Execute</button>
    </div>
  <?php endif; ?>

  <p><a href="add.php" class="btn add">+ Add New Post</a></p>

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
        <th>Category</th>
        <th>Status</th>
        <th>Author</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($posts)): ?>
        <tr><td colspan="8">No posts found.</td></tr>
      <?php endif; ?>

      <?php foreach ($posts as $p): ?>
        <tr>
          <?php if ($user['role'] === 'admin'): ?>
            <td><input type="checkbox" name="selected[]" value="<?= (int)$p['id'] ?>" class="rowCheckbox" /></td>
          <?php else: ?>
            <td>•</td>
          <?php endif; ?>
          <td><?= htmlspecialchars($p['title']) ?></td>
          <td><?= htmlspecialchars($p['slug'] ?? '') ?></td>
          <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
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
      $range = 2; // show current +/- range pages
      $start = max(1, $page - $range);
      $end = min($total_pages, $page + $range);
    ?>
    <?php if ($page > 1): ?>
      <a href="?<?= build_query(['page' => 1]) ?>">&laquo; First</a>
      <a href="?<?= build_query(['page' => $page - 1]) ?>">&lsaquo; Prev</a>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?<?= build_query(['page' => $i]) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <a href="?<?= build_query(['page' => $page + 1]) ?>">Next &rsaquo;</a>
      <a href="?<?= build_query(['page' => $total_pages]) ?>">Last &raquo;</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<script>
// konfirm del (per-row)
function showDeleteModal(postId) {
  const modal = document.getElementById('confirmDeleteModal');
  const confirmBtn = document.getElementById('confirmDeleteBtn');
  confirmBtn.href = 'delete.php?id=' + postId;
  modal.style.display = 'flex';
}
function closeDeleteModal() {
  document.getElementById('confirmDeleteModal').style.display = 'none';
}

// select all checkbox
document.getElementById('selectAll').addEventListener('change', function() {
  const checked = this.checked;
  document.querySelectorAll('.rowCheckbox').forEach(cb => cb.checked = checked);
});

function confirmBulkAction() {
  const action = document.getElementById('bulk_action').value;
  if (!action) {
    alert('Select bulk action first terlebih dahulu.');
    return false;
  }
  const anyChecked = Array.from(document.querySelectorAll('.rowCheckbox')).some(cb => cb.checked);
  if (!anyChecked) {
    alert('Select at least 1 post.');
    return false;
  }
  if (action === 'delete') {
    return confirm('Are you sure you want to move selected posts to recycle bin?');
  }
  // otherwise proceed
  return true;
}
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Content - Dashboard';
require_once __DIR__ . '/../../partials/layout.php';
