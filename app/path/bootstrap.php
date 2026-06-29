<?php
ob_start(); 
// path/bootstrap.php
// Entry point for the private path. Should be required by public bridge.

// 1) Load env
require_once __DIR__ . '/env_loader.php';
$envPath = __DIR__ . '/../.env';
load_env($envPath);

// 2) Config
require_once __DIR__ . '/config.php';

// 3) Start session (set secure cookie params)
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookieParams['path'] ?? '/',
    'domain' => $cookieParams['domain'] ?? '',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4) Database
require_once __DIR__ . '/db.php'; // provides $pdo

// 5) Helpers, session helper, auth
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pages_helper.php';

// 6. Utility function: current UTC time
function get_current_time() {
    return gmdate('Y-m-d H:i:s');
}

// 7. Load main logic
require_once __DIR__ . '/../../app/main.php';