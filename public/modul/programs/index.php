<?php
$standalone = !defined('DASHBOARD_CONTEXT');
if ($standalone) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
    define('DASHBOARD_CONTEXT', false);
}

// Ambil semua kategori parent
$programs = get_parent_categories($pdo); // pastikan helper ini ada

return [
    'template' => dirname(__DIR__, 2) . '/dashboard/partials/programs/index.php',
    'vars' => ['programs' => $programs],
    'title' => 'Programs'
];
