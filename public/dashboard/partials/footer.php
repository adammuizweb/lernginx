<?php
if (!defined('DASHBOARD_CONTEXT')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}
?>
<footer class="dashboard-footer">
  <div class="copyright"> © <?= date('Y') ?> <em>lernginx</em></div>
</footer>
