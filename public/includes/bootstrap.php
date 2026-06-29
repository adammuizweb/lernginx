<?php
// public bridge
if (!defined('LERNIGNX_CONTEXT')) define('LERNIGNX_CONTEXT', true);

// error display optional
ini_set('display_errors', 1);
error_reporting(E_ALL);

// path ke real bootstrap (sesuaikan)
$real = dirname(__DIR__, 2) . '/app/path/bootstrap.php';
if (!file_exists($real)) {
    http_response_code(500);
    die('Configuration error: bootstrap not found.');
}
require_once $real;
