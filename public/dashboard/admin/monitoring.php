<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if ( ! $user || !in_array($user['role'], ['teacher', 'admin']) ) {
    http_response_code(403);
    exit('Access denied.');
}

// Ambil data modul siswa
$sql = "
SELECT 
  u.id AS user_id,
  u.username,
  c.name AS category_name,
  m.status,
  m.updated_at
FROM modules m
JOIN users u ON u.id = m.user_id
JOIN categories c ON c.id = m.category_id
WHERE u.role = 'student'
ORDER BY u.username, c.name
";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

// Susun data per siswa
$grouped = [];
foreach ($rows as $row) {
    $uid = $row['user_id'];
    if (!isset($grouped[$uid])) {
        $grouped[$uid] = [
            'username' => $row['username'],
            'modules' => []
        ];
    }
    $grouped[$uid]['modules'][] = [
        'category' => $row['category_name'],
        'status' => $row['status'],
        'updated_at' => $row['updated_at']
    ];
}

// Tangkap output HTML sebagai $content
ob_start();
?>

<h2>📊 Student Module Monitoring</h2>

<?php if (empty($grouped)): ?>
  <p>No students have registered for modules yet.</p>
<?php else: ?>
  <?php foreach ($grouped as $uid => $data): ?>
    <div class="monitoring-box">
      <h3><?= htmlspecialchars($data['username']) ?></h3>
      <ul>
        <?php foreach ($data['modules'] as $mod): ?>
          <li>
            <?= htmlspecialchars($mod['category']) ?> —
            <strong><?= $mod['status'] == 0 ? 'Active' : 'Pending' ?></strong>
            <small>(<?= date('j M Y H:i', strtotime($mod['updated_at'])) ?>)</small>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
$pageTitle = 'Monitoring - Dashboard';
require_once __DIR__ . '/../partials/layout.php';
