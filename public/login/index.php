<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = get_user_from_session($pdo);
if ($user) {
    header('Location: /dashboard/');
    exit;
}

$message = '';
$site_key = RECAPTCHA_SITE_KEY;
$secret_key = RECAPTCHA_SECRET_KEY;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = $_POST['g-recaptcha-response'] ?? '';

    if (!empty($secret_key) && !verify_recaptcha($secret_key, $captcha)) {
        $message = 'reCAPTCHA verification failed.';
    } else {
        $user = authenticate_user($pdo, $email, $password);
        if ($user && in_array($user['role'], ['student', 'teacher', 'admin'])) {
            create_session($pdo, $user['id'], 3600 * 24 * 7);
            header('Location: /dashboard/');
            exit;
        } else {
            $message = 'Invalid email or password.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Sign in — lernginx</title>
  <link rel="stylesheet" href="loginms.css">
  <?php if ($site_key): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32x32.png">
  <link rel="stylesheet" href="/assets/animation/animation.css">
</head>
<body>
  <div class="loginms-container fade-up">
    <div class="loginms-particles">
      <div class="loginms-particle"></div>
      <div class="loginms-particle"></div>
      <div class="loginms-particle"></div>
      <div class="loginms-particle"></div>
      <div class="loginms-particle"></div>
    </div>

    <div class="loginms-card">
      <div class="loginms-title">Sign in to lernginx</div>
            <div class="logo">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      </div>
      <div class="loginms-subtext">Use your email and password</div>

      <?php if ($message) echo "<p style='color:#ff7b7b;'>".htmlspecialchars($message)."</p>"; ?>

<form method="post">
  <input class="loginms-input" type="email" name="email" placeholder="Email" required>

  <div class="password-wrapper">
    <input class="loginms-input" type="password" name="password" id="password" placeholder="Password" required>
    <span class="toggle-password" onclick="togglePassword()">
      👁️
    </span>
  </div>

  <?php if ($site_key): ?>
  <center>
    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($site_key) ?>"></div>
  </center>
  <?php endif; ?>
  <button class="loginms-button" type="submit">Sign In</button>
</form>

      <div class="loginms-footer">
        Don't have an account? <a href="/register/">Register</a> — <a href="/reset-password/">Forgot password?</a>
      </div>
    </div>
  </div>
  <script src="/assets/animation/animation.js"></script>
  <script>
function togglePassword() {
  const passwordInput = document.getElementById("password");
  const toggleIcon = document.querySelector(".toggle-password");

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    toggleIcon.textContent = "🙈";
  } else {
    passwordInput.type = "password";
    toggleIcon.textContent = "👁️";
  }
}
</script>

</body>
</html>
