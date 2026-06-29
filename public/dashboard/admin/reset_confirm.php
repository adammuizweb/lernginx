<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
    http_response_code(403);
    exit('Access denied.');
}

$token = $_GET['token'] ?? '';
if (!$token) {
    echo "<p style='color:red'>Token not found.</p>";
    exit;
}

// Ambil data user berdasarkan token reset
$stmt = $pdo->prepare("
    SELECT 
        pr.user_id,
        u.display_name,
        u.email,
        u.nomor_telpon
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.token = ? 
      AND pr.expires_at > NOW()
");
$stmt->execute([$token]);
$data = $stmt->fetch();

if (!$data) {
    ob_start();
    echo "<h2 style='color:red'>Invalid or expired token.</h2>";
    $content = ob_get_clean();
    $pageTitle = 'Reset Password - Dashboard';
    require_once __DIR__ . '/../partials/layout.php';
    exit;
}

// Generate password baru
$new_pass = bin2hex(random_bytes(4));
$hash = password_hash($new_pass, PASSWORD_DEFAULT);

// Update password dan wajib ganti setelah login
$pdo->prepare("UPDATE users SET password=?, must_change_password=1 WHERE id=?")
    ->execute([$hash, $data['user_id']]);

// Hapus token setelah dipakai
$pdo->prepare("DELETE FROM password_resets WHERE token=?")->execute([$token]);

// Normalisasi nomor HP user ke format 62
$no_hp_user = preg_replace('/\D/', '', $data['nomor_telpon']);
if (strpos($no_hp_user, '0') === 0) {
    $no_hp_user = '62' . substr($no_hp_user, 1);
} elseif (strpos($no_hp_user, '+62') === 0) {
    $no_hp_user = substr($no_hp_user, 1);
} elseif (strpos($no_hp_user, '8') === 0) {
    $no_hp_user = '62' . $no_hp_user;
}

// Buat pesan WA
$wa_msg = rawurlencode(
    "Hello {$data['display_name']},\n".
    "Your account password has been reset by admin.\n".
    "New password: {$new_pass}\n".
    "Please login and change your password immediately."
);
$wa_link = "https://wa.me/{$no_hp_user}?text={$wa_msg}";

// Tangkap output HTML untuk layout dashboard
ob_start();
?>

<h2>🔐 Password Reset Successful</h2>

<div class="reset-result">
  <p><strong>Name:</strong> <?= htmlspecialchars($data['display_name']) ?></p>
  <p><strong>Email:</strong> <?= htmlspecialchars($data['email']) ?></p>
  <p><strong>Phone Number:</strong> <?= htmlspecialchars($data['nomor_telpon']) ?></p>
  <p><strong>New Password:</strong> <code><?= htmlspecialchars($new_pass) ?></code></p>

  <a class="btn primary" href="<?= htmlspecialchars($wa_link) ?>" target="_blank">
    Send to User via WhatsApp
  </a>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Reset Password - Dashboard';
require_once __DIR__ . '/../partials/layout.php';
