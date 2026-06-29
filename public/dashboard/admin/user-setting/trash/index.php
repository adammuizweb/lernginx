<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Total
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_deleted = 1");
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Data
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.role, u.deleted_at, 
           d.username AS deleted_by_name
    FROM users u
    LEFT JOIN users d ON u.deleted_by = d.id
    WHERE u.is_deleted = 1
    ORDER BY u.deleted_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$deletedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<h2>User Trash</h2>
<p style="margin-bottom:10px;">
  <a href="../index.php">← Back to User Settings</a>
</p>

<?php if (empty($deletedUsers)): ?>
  <p>No users in trash.</p>
<?php else: ?>
<table border="1" cellpadding="6" cellspacing="0">
  <thead>
    <tr>
      <th>ID</th>
      <th>Username</th>
      <th>Email</th>
      <th>Role</th>
      <th>Deleted By</th>
      <th>Deleted At</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($deletedUsers as $u): ?>
    <tr>
      <td><?= htmlspecialchars($u['id']) ?></td>
      <td><?= htmlspecialchars($u['username']) ?></td>
      <td><?= htmlspecialchars($u['email']) ?></td>
      <td><?= htmlspecialchars($u['role']) ?></td>
      <td><?= htmlspecialchars($u['deleted_by_name'] ?? '-') ?></td>
      <td><?= htmlspecialchars($u['deleted_at']) ?></td>
      <td style="display:flex;gap:6px;">
        <form method="POST" action="restore.php" onsubmit="return confirm('Restore this user?');">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button type="submit">Restore</button>
        </form>
        <form method="POST" action="delete-permanent.php" onsubmit="return confirm('Permanently delete this user?');">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button type="submit" style="background:#c00;color:#fff;">Delete Permanently</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($totalPages > 1): ?>
  <nav class="pagination" style="margin-top:10px;">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page - 1 ?>">‹ Prev</a>
    <?php endif; ?>
    <strong>Page <?= $page ?> of <?= $totalPages ?></strong>
    <?php if ($page < $totalPages): ?>
      <a href="?page=<?= $page + 1 ?>">Next ›</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
$pageTitle = 'User Trash - Dashboard';
require_once __DIR__ . '/../../../partials/layout.php';
