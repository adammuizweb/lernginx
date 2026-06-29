<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'admin') {
    error_log('user-setting access denied. user=' . json_encode($user) . ' remote=' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $content = '<p>Access denied.</p>';
    $pageTitle = 'User Settings';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}
$total_deleted = $pdo->query("SELECT COUNT(*) FROM users WHERE is_deleted = 1")->fetchColumn();

// Params
$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 200);
$filter_role = trim((string)($_GET['role'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5;
$offset = ($page - 1) * $perPage;

// Build WHERE dan binding
$where = ['is_deleted = 0'];
$binds = [];

if ($q !== '') {
    $where[] = "(username LIKE :q_user OR email LIKE :q_email)";
    $binds[':q_user'] = "%{$q}%";
    $binds[':q_email'] = "%{$q}%";
}

$allowed_roles = ['student','teacher','admin'];
if ($filter_role !== '' && in_array($filter_role, $allowed_roles, true)) {
    $where[] = "role = :role";
    $binds[':role'] = $filter_role;
} else {
    $filter_role = '';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$count_sql = "SELECT COUNT(*) AS cnt FROM users {$where_sql}";
$stmt = $pdo->prepare($count_sql);
foreach ($binds as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->execute();
$total = (int)$stmt->fetchColumn();
$totalPages = (int)max(1, ceil($total / $perPage));

// Fetch page
$sql = "SELECT id, username, email, role FROM users {$where_sql} ORDER BY id ASC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
foreach ($binds as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

function page_url($p, $q, $role) {
    $qs = [];
    if ($q !== '') $qs['q'] = $q;
    if ($role !== '') $qs['role'] = $role;
    $qs['page'] = $p;
    return './?' . http_build_query($qs);
}

error_log('user-setting init: uri='.$_SERVER['REQUEST_URI'].' user='.json_encode($user));

ob_start();
?>
<h2>User Settings</h2>

<form method="get" action="<?= htmlspecialchars($_SERVER['SCRIPT_NAME']) ?>" style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
  <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES,'UTF-8') ?>" placeholder="Search username or email" />
  <select name="role">
    <option value="">All roles</option>
    <option value="student" <?= $filter_role==='student' ? 'selected' : '' ?>>Student</option>
    <option value="teacher" <?= $filter_role==='teacher'  ? 'selected' : '' ?>>Teacher</option>
    <option value="admin" <?= $filter_role==='admin' ? 'selected' : '' ?>>Admin</option>
  </select>
  <button type="submit">Search</button>
</form>

<table border="1" cellpadding="6" cellspacing="0">
  <thead>
    <tr>
      <th>ID</th><th>Username</th><th>Email</th><th>New Password</th><th>Role</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
<?php foreach($users as $u): ?>
  <tr>
    <td><?= htmlspecialchars($u['id'], ENT_QUOTES,'UTF-8') ?></td>
    <td><?= htmlspecialchars($u['username'], ENT_QUOTES,'UTF-8') ?></td>

    <td>
      <form method="POST" action="save.php" class="form-inline">
        <input type="hidden" name="id" value="<?= htmlspecialchars($u['id'], ENT_QUOTES,'UTF-8') ?>">
        <input type="email" name="email" value="<?= htmlspecialchars($u['email'], ENT_QUOTES,'UTF-8') ?>" required>
    </td>

    <td>
        <input type="password" name="password" placeholder="Leave blank to keep current">
    </td>

    <td>
        <select name="role" required>
          <option value="student" <?= $u['role'] === 'student' ? 'selected' : '' ?>>Student</option>
          <option value="teacher" <?= $u['role'] === 'teacher'  ? 'selected' : '' ?>>Teacher</option>
          <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
    </td>

    <td style="display:flex; gap:4px;">
        <button type="submit">Save</button>
        <button type="button" class="btn-base btn-red" data-id="<?= (int)$u['id'] ?>" data-name="<?= htmlspecialchars($u['username']) ?>">Delete</button>
      </form>
    </td>
  </tr>
<?php endforeach; ?>
  </tbody>
</table>

<?php if ($totalPages > 1): ?>
<nav class="pagination" style="margin-top:12px;">
  <?php if ($page > 1): ?><a href="<?= page_url($page-1, $q, $filter_role) ?>">‹ Prev</a><?php endif; ?>
  <?php for ($p=max(1,$page-3); $p<=min($totalPages,$page+3); $p++): ?>
    <?= $p === $page ? "<strong>$p</strong>" : '<a href="'.page_url($p,$q,$filter_role).'">'.$p.'</a>' ?>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?><a href="<?= page_url($page+1, $q, $filter_role) ?>">Next ›</a><?php endif; ?>
  <span style="margin-left:12px;color:var(--text-muted);">Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($total) ?> users)</span>
</nav>
<?php endif; ?>
<p style="margin-bottom:12px;">
  <a href="trash/">🗑️ View Trash (<?= $total_deleted ?? 0 ?>)</a>
</p>

<!-- Modal Confirm Delete -->
<div id="modalConfirmDelete" class="modal-konfirm" style="display:none;">
  <div class="modal-body">
    <h3>Confirm Deletion</h3>
    <p>Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
    <div class="modal-actions">
      <button class="btn btn-secondary" id="cancelDelete">Cancel</button>
      <button class="btn btn-danger" id="confirmDelete">Delete</button>
    </div>
  </div>
</div>

<!-- Modal Flash -->
<div id="modalFlash" class="modal-flash" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal-content">
    <div id="modalFlashMessage"></div>
    <button id="modalFlashClose">Close</button>
  </div>
</div>

<script>
(function(){
  let deleteId = null;

  // Flash modal
  function showFlash(msg) {
    const modal = document.getElementById('modalFlash');
    document.getElementById('modalFlashMessage').innerHTML = msg;
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden','false');
  }
  document.getElementById('modalFlashClose').addEventListener('click',()=> {
    const modal = document.getElementById('modalFlash');
    modal.style.display='none';
  });

  // Delete confirm modal
  const modalDel = document.getElementById('modalConfirmDelete');
  const btnCancel = document.getElementById('cancelDelete');
  const btnConfirm = document.getElementById('confirmDelete');
  const nameEl = document.getElementById('deleteUserName');

  document.querySelectorAll('[data-id][data-name]').forEach(btn=>{
    btn.addEventListener('click',()=>{
      deleteId = btn.dataset.id;
      nameEl.textContent = btn.dataset.name;
      modalDel.style.display = 'flex';
    });
  });
  btnCancel.addEventListener('click',()=> modalDel.style.display='none');
  btnConfirm.addEventListener('click',()=>{
    if (!deleteId) return;
    window.location = 'delete.php?id=' + encodeURIComponent(deleteId);
  });

  // Flash from URL
  const params = new URLSearchParams(window.location.search);
  if (params.get('deleted')==='1') showFlash('User deleted successfully.');
  else if (params.get('error')) showFlash('Error: ' + params.get('error'));
})();
</script>
<?php
$content = ob_get_clean();
$pageTitle = 'User Settings - Dashboard';
require_once __DIR__ . '/../../partials/layout.php';
