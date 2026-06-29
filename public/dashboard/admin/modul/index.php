<?php
// ini adalah dashboard/admin/modul/index.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

date_default_timezone_set('UTC');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $max = isset($_POST['max_parent']) ? (int)$_POST['max_parent'] : 2;
    $max = max(1, $max);

    // upsert ke registration_policies (asumsikan key_name unik)
    $stmt = $pdo->prepare("
        INSERT INTO registration_policies (key_name, value)
        VALUES ('max_parent_modules_per_student', ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $stmt->execute([$max]);

    $message = 'Maximum parent module limit updated successfully.';
}

// baca current
$limitStmt = $pdo->prepare("SELECT value FROM registration_policies WHERE key_name = 'max_parent_modules_per_student' LIMIT 1");
$limitStmt->execute();
$limitVal = $limitStmt->fetchColumn();
$currentMax = ($limitVal !== false) ? (int)$limitVal : 2;

ob_start();
?>
<h2>Module Settings (Admin)</h2>
<div style="margin-top: 12px;">
  <h3>🧩 Architecture Note: Module Registration Policy</h3>
  <p>The system limits the number of <strong>parent modules</strong> that each student can register for. Submodules (children) are not counted separately.</p>
  <ul>
    <li>The limit applies going forward — existing students will not lose already active modules.</li>
    <li>Already registered data remains; teachers must manually deactivate over-limit modules.</li>
    <li>If the limit is changed (e.g. from 2 to 1), existing students retain old modules but cannot register new ones.</li>
    <li>If the limit is exceeded, the system rejects new registrations with an automatic warning.</li>
  </ul>
  <p class="text-muted">Goal: maintain focus and equal learning opportunities among students.</p>
</div>

<?php if ($message): ?>
  <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" action="">
  <div class="form-group">
    <label for="max_parent">Batas maksimum modul utama per siswa</label>
    <input id="max_parent" name="max_parent" type="number" min="1" value="<?= htmlspecialchars($currentMax) ?>" class="form-control" required>
    <small class="form-text text-muted">Masukkan angka minimal 1. Anak/subkategori tidak dihitung terpisah karena otomatis mengikuti parent.</small>
  </div>

  <button type="submit" class="btn btn-primary">Save</button>
</form>

<?php
$content = ob_get_clean();
$pageTitle = 'Admin - Module Settings';
require_once __DIR__ . '/../../partials/layout.php';
