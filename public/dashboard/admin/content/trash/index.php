<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'teacher') {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Recycle Bin';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// Ambil post yang dihapus
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.status, p.deleted_at,
           c.name AS category_name,
           u.username AS author_name
    FROM posts p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.author_id = u.id
    WHERE p.deleted_at IS NOT NULL
    ORDER BY p.deleted_at DESC
");
$stmt->execute();
$deleted_posts = $stmt->fetchAll();

// Tangkap output HTML sebagai $content
ob_start();
?>

<h1>🗑️ Recycle Bin (Konten Dihapus)</h1>
<p><a href="../index.php">← Back to Content List</a></p>

<?php if ($_GET['restore'] ?? null === 'success'): ?>
    <div class="alert">✅ Content restored successfully.</div>
<?php elseif ($_GET['delete'] ?? null === 'success'): ?>
    <div class="alert">🗑️ Content permanently deleted.</div>
<?php endif; ?>

<table class="content-table">
    <thead>
        <tr>
            <th>Title</th>
            <th>Kategori</th>
            <th>Status</th>
            <th>Penulis</th>
            <th>Dihapus Pada</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($deleted_posts)): ?>
        <tr><td colspan="6" style="text-align:center;">Tidak ada konten di recycle bin.</td></tr>
    <?php else: ?>
        <?php foreach ($deleted_posts as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['title']) ?></td>
            <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($p['status']) ?></td>
            <td><?= htmlspecialchars($p['author_name'] ?? '-') ?></td>
            <td><?= date('d M Y H:i', strtotime($p['deleted_at'])) ?></td>
            <td>
                <a href="restore.php?id=<?= $p['id'] ?>" class="btn restore">Restore</a>
                <a href="delete-permanent.php?id=<?= $p['id'] ?>" class="btn delete"
                   onclick="return confirm('Are you sure you want to permanently delete this content?')">Delete Permanently</a>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
$pageTitle = 'Recycle Bin';
require_once __DIR__ . '/../../partials/layout.php';
