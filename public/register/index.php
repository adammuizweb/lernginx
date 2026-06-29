<?php
require_once __DIR__ . '/../includes/bootstrap.php';
date_default_timezone_set('UTC');

// pastikan fungsi register_user, create_session, get_user_from_session ada di bootstrap.php
$user = get_user_from_session($pdo);
if ($user) {
    header('Location: /dashboard/');
    exit;
}

$message = '';
$site_key = RECAPTCHA_SITE_KEY;
$secret_key = RECAPTCHA_SECRET_KEY;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ambil input mentah
    $username_raw = $_POST['username'] ?? '';
    $email_raw    = $_POST['email'] ?? '';
    $password_raw = $_POST['password'] ?? '';
    $captcha      = $_POST['g-recaptcha-response'] ?? '';

    // normalisasi dasar
    $username = strtolower(trim($username_raw));
    $email    = trim($email_raw);
    $password = $password_raw;
    $created_at = date('Y-m-d H:i:s'); 


    // validasi dasar server-side (simple tapi tegas)
    if ($username === '') {
        $message = 'Username is required.';
    } elseif (!preg_match('/^[a-z0-9]{3,30}$/', $username)) {
        $message = 'Username must be 3–30 characters, lowercase letters and numbers only.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } elseif (!empty($secret_key) && !verify_recaptcha($secret_key, $captcha)) {
        $message = 'reCAPTCHA verification failed.';
    } else {
        try {
            // pastikan register_user dan create_session menangani prepared statements
            $user_id = register_user($pdo, $username, $email, $password, $created_at);
            create_session($pdo, $user_id, 3600 * 24 * 7);
            header('Location: /dashboard/');
            exit;
        } catch (Exception $e) {
            $message = 'Registration failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register — lernginx</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php if ($site_key): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap');
    :root{
      --bg:#0b0f14; --card:rgba(18,25,35,0.9); --accent:#00fff2;
      --text:#d8e0e8; --input-bg:rgba(255,255,255,0.04); --radius:12px; --trans:.18s;
    }
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
      font-family:'Poppins',sans-serif;background:radial-gradient(circle at 20% 20%, #071018, var(--bg) 70%);
      color:var(--text);}
    .card{width:380px;padding:2rem;border-radius:var(--radius);background:var(--card);
      box-shadow:0 8px 30px rgba(0,0,0,0.6);backdrop-filter:blur(12px);position:relative}
    h2{color:var(--accent);margin:0 0 1rem;text-shadow:0 0 12px #00fff250}
    .form-group{position:relative;margin-bottom:1rem}
    input{width:100%;padding:0.85rem 1rem;border-radius:10px;border:1px solid transparent;
      background:var(--input-bg);color:var(--text);outline:none;transition:var(--trans)}
    input:focus{border-color:var(--accent);box-shadow:0 0 10px #00fff220}
    button{width:100%;padding:0.9rem;border:none;border-radius:10px;background:var(--accent);color:#000;font-weight:600;cursor:pointer}
    .footer{margin-top:0.9rem;font-size:0.88rem;color:#8ca0a8;text-align:center}
    .footer a{color:var(--accent);text-decoration:none}

    /* tooltip / popup notification (non blocking) */
    .popup {
      position: absolute;
      left: 50%;
      transform: translateX(-50%) translateY(-8px);
      top: -50px;
      background: linear-gradient(180deg,#041018,#062026);
      color:#cfeff0;
      padding:0.6rem 0.9rem;
      border-radius:10px;
      box-shadow:0 12px 30px rgba(0,0,0,0.6), 0 0 12px rgba(0,255,242,0.06);
      opacity:0; pointer-events:none; transition:var(--trans);
      font-size:0.9rem; z-index:30; white-space:nowrap;
    }
    .popup.visible { opacity:1; pointer-events:auto; transform: translateX(-50%) translateY(0); }

    /* small inline tooltip for username (right side) */
    .help-inline {
      position:absolute; right:10px; top:50%; transform:translateY(-50%);
      width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;
      background:rgba(255,255,255,0.03);color:var(--accent); cursor:help;
    }
    .help-bubble {
      position:absolute; right:56px; top:50%; transform:translateY(-50%) translateX(8px);
      background:#06161a;color:#cfeff0;padding:0.5rem 0.8rem;border-radius:10px;
      box-shadow:0 8px 24px rgba(0,0,0,0.6);opacity:0;pointer-events:none;transition:var(--trans);
      font-size:0.87rem; max-width:240px;
    }
    .help-bubble.visible { opacity:1; pointer-events:auto; transform:translateY(-50%) translateX(0); }

    /* warning popup below input */
    .warning-bubble {
      position:absolute; left:0; top:110%; transform:translateY(6px);
      background:#2b0a0a;color:#ffd6d6;padding:0.45rem 0.7rem;border-radius:8px;border:1px solid rgba(255,0,0,0.12);
      font-size:0.85rem;box-shadow:0 10px 30px rgba(0,0,0,0.5);opacity:0;pointer-events:none;transition:var(--trans);
      white-space:nowrap;
    }
    .warning-bubble.visible { opacity:1; pointer-events:auto; transform:translateY(0); }
    .toggle-password { position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:var(--accent) }

    .global-message { margin-bottom:1rem; padding:0.7rem; border-radius:8px; background:rgba(255,0,0,0.06); color:#ffbdbd; border:1px solid rgba(255,0,0,0.12); }
  </style>
</head>
<body>
  <div class="card" role="main" aria-labelledby="title">
    <h2 id="title">Create New Account</h2>

    <?php if ($message): ?>
      <div class="global-message" role="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
                <div class="help-bubble" id="username-bubble" aria-hidden="true">
          Username untuk login dan URL; hanya huruf kecil dan angka, tanpa spasi (3–30 karakter).
        </div>
      <div class="form-group" style="margin-bottom:1.6rem">
        <input id="username" name="username" placeholder="Username (e.g., johndoe)" autocomplete="username" required value="<?= isset($username_raw) ? htmlspecialchars($username_raw) : '' ?>">
        <div class="help-inline" id="username-help" aria-hidden="true">❓</div>
        <div class="warning-bubble" id="username-warning" aria-hidden="true"></div>
      </div>

      <div class="form-group">
        <input id="email" name="email" type="email" placeholder="Email" autocomplete="email" required value="<?= isset($email_raw) ? htmlspecialchars($email_raw) : '' ?>">
      </div>

      <div class="form-group" style="margin-bottom:1.4rem">
        <input id="password" name="password" type="password" placeholder="Password (min. 8 characters)" autocomplete="new-password" required>
        <div class="toggle-password" id="toggle-password" title="Show / hide password" role="button" aria-pressed="false">👁️</div>
      </div>

      <?php if ($site_key): ?><div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($site_key) ?>"></div><?php endif; ?>

      <button type="submit">Register Now</button>
    </form>
    <div class="footer">Already have an account? <a href="/login/">Sign in</a></div>
  </div>

  <script>
    // Elemen
    const usernameInput = document.getElementById('username');
    const helpInline = document.getElementById('username-help');
    const helpBubble = document.getElementById('username-bubble');
    const warning = document.getElementById('username-warning');
    const toggle = document.getElementById('toggle-password');
    const password = document.getElementById('password');

    // tampilkan help bubble saat fokus atau hover help icon (touch-friendly toggle on click)
    function showHelp() { helpBubble.classList.add('visible'); helpBubble.setAttribute('aria-hidden','false'); }
    function hideHelp() { helpBubble.classList.remove('visible'); helpBubble.setAttribute('aria-hidden','true'); }

    usernameInput.addEventListener('focus', showHelp);
    usernameInput.addEventListener('blur', () => { setTimeout(hideHelp, 150); });
    helpInline.addEventListener('mouseenter', showHelp);
    helpInline.addEventListener('mouseleave', hideHelp);
    helpInline.addEventListener('click', () => {
      const visible = helpBubble.classList.contains('visible');
      visible ? hideHelp() : showHelp();
    });

    // validasi realtime: only a-z0-9 allowed; length 3-30
    function isAllowedSoFar(v) { return /^[a-z0-9]*$/.test(v); }
    function isValidFinal(v) { return /^[a-z0-9]{3,30}$/.test(v); }

    usernameInput.addEventListener('input', () => {
      const val = usernameInput.value;
      if (!isAllowedSoFar(val)) {
        warning.textContent = 'Hanya huruf kecil dan angka diperbolehkan.';
        warning.classList.add('visible'); warning.setAttribute('aria-hidden','false');
        usernameInput.style.borderColor = '#ff6666';
        usernameInput.style.boxShadow = '0 0 10px rgba(255, 102, 102, 0.5)';
      } else if (val.length > 0 && val.length < 3) {
        warning.textContent = 'Username terlalu pendek (minimal 3 karakter).';
        warning.classList.add('visible'); warning.setAttribute('aria-hidden','false');
        usernameInput.style.borderColor = '#ff6666';
        usernameInput.style.boxShadow = '0 0 10px rgba(255, 102, 102, 0.35)';
      } else {
        warning.classList.remove('visible'); warning.setAttribute('aria-hidden','true');
        usernameInput.style.borderColor = '';
        usernameInput.style.boxShadow = '';
      }
    });

    usernameInput.addEventListener('blur', () => {
      // normalisasi ringan: lower + remove invalid chars (keputusan desain minimal)
      usernameInput.value = usernameInput.value.toLowerCase().replace(/[^a-z0-9]/g, '');
      if (usernameInput.value && !isValidFinal(usernameInput.value)) {
        warning.textContent = 'Username harus 3–30 karakter, hanya huruf kecil dan angka.';
        warning.classList.add('visible'); warning.setAttribute('aria-hidden','false');
      } else {
        warning.classList.remove('visible'); warning.setAttribute('aria-hidden','true');
      }
    });

    // toggle password visibility
    toggle.addEventListener('click', () => {
      if (password.type === 'password') { password.type = 'text'; toggle.textContent = '🙈'; toggle.setAttribute('aria-pressed','true'); }
      else { password.type = 'password'; toggle.textContent = '👁️'; toggle.setAttribute('aria-pressed','false'); }
    });

    // mild client-side submit guard
    document.querySelector('form').addEventListener('submit', function(e) {
      const u = usernameInput.value;
      const p = password.value;
      if (!isValidFinal(u)) {
        e.preventDefault();
        warning.textContent = 'Username harus 3–30 karakter, hanya huruf kecil dan angka.';
        warning.classList.add('visible'); warning.setAttribute('aria-hidden','false');
        usernameInput.focus();
        return;
      }
      if (p.length < 8) {
        e.preventDefault();
        alert('Password minimal 8 karakter.');
        password.focus();
        return;
      }
      // submit dilanjutkan; server-side tetap akan memvalidasi lagi
    });
  </script>
</body>
</html>
