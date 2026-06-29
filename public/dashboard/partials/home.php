<?php
// /dashboard/views/home.php
if (!defined('DASHBOARD_CONTEXT')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

?>
<section>
  <h2>Welcome, <?= htmlspecialchars($user['display_name'] ?? $user['username']) ?> 👋</h2>
  <p>Peran Anda: <strong><?= htmlspecialchars($user['role']) ?></strong></p>
  <p>Silakan pilih menu di sidebar untuk mulai menjelajah konten.</p>
</section>
