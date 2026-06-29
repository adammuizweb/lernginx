<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin_phone = '6281234567890'; // admin WhatsApp number placeholder

function normalize_phone($no_hp) {
    $no_hp = preg_replace('/\D/', '', $no_hp);
    if (strpos($no_hp, '0') === 0) {
        $no_hp = '62' . substr($no_hp, 1);
    } elseif (strpos($no_hp, '+62') === 0) {
        $no_hp = substr($no_hp, 1);
    } elseif (strpos($no_hp, '8') === 0) {
        $no_hp = '62' . $no_hp;
    }
    return $no_hp;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $no_hp_input = normalize_phone($_POST['no_hp']);

    $stmt = $pdo->prepare("SELECT id, display_name, email, nomor_telpon FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $message = "<p class='msg error'>Email tidak ditemukan.</p>";
    } else {
        $no_hp_db = normalize_phone($user['nomor_telpon']);
        if ($no_hp_input !== $no_hp_db) {
            $message = "<p class='msg error'>Nomor HP tidak cocok dengan yang terdaftar.</p>";
        } else {
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at)
                           VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
                ->execute([$user['id'], $token]);

            $reset_link = "https://lernginx.lan/dashboard/admin/reset_confirm.php?token=$token";
            $msg_text = "🔐 *Password Reset Request*\n"
                      . "Name: {$user['display_name']}\n"
                      . "Email: {$user['email']}\n"
                      . "Phone: {$no_hp_db}\n\n"
                      . "Confirm at: $reset_link";

            $wa_url = "https://wa.me/{$admin_phone}?text=" . rawurlencode($msg_text);
            $message = "<p class='msg success'>Reset request sent to admin successfully.<br>Open WhatsApp now for confirmation.</p>
                        <script>setTimeout(()=>{ window.location.href='$wa_url'; }, 2000);</script>";
        }
    }
}
?>

<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Reset Password | lernginx</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body {
    font-family: 'Poppins', sans-serif;
    background: radial-gradient(circle at top left, #0f2027, #203a43, #2c5364);
    color: #eee;
    height: 100vh;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .reset-container {
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(8px);
    padding: 40px 50px;
    border-radius: 16px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 0 40px rgba(0,0,0,0.3);
    text-align: center;
  }
  h2 {
    color: #00e5ff;
    letter-spacing: 1px;
    margin-bottom: 25px;
  }
  input {
    width: 100%;
    padding: 12px;
    margin: 10px 0 20px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    background: rgba(255,255,255,0.1);
    color: #fff;
    outline: none;
    transition: background 0.2s;
  }
  input:focus {
    background: rgba(255,255,255,0.2);
  }
  button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #00e5ff, #0072ff);
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.3s;
  }
  button:hover {
    opacity: 0.85;
  }
  .msg {
    padding: 10px;
    border-radius: 8px;
    font-weight: 500;
  }
  .msg.error {
    background: rgba(255, 77, 77, 0.2);
    color: #ff6b6b;
  }
  .msg.success {
    background: rgba(0, 230, 118, 0.2);
    color: #00e676;
  }
</style>
</head>
<body>
<div class="reset-container">
  <h2>Reset Password</h2>
  <?= $message ?>
  <form method="POST" autocomplete="off">
    <input type="email" name="email" placeholder="Email Address" required>
    <input type="text" name="no_hp" placeholder="Example: 6281234567890" required>
    <button type="submit">Send Request</button>
  </form>
</div>
</body>
</html>
