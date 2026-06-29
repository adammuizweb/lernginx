<?php
require_once __DIR__ . '/../includes/bootstrap.php';
destroy_session($pdo);
header('Location: /login/');
exit;
