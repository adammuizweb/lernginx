<?php
// dashboard/admin/assign/index.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../modules/register_module.php';
date_default_timezone_set('UTC');

$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher', 'admin'])) {
    $content = '<p>Access denied.</p>';
    $pageTitle = 'Admin - Dashboard';
    require_once __DIR__ . '/../../partials/layout.php';
    exit;
}

// Handle AJAX and POST actions first (exit early)
$ajaxAction = $_POST['ajax_action'] ?? $_GET['ajax'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_policy'])) {
    require __DIR__ . '/actions/update_policy.php';
    exit;
}

switch ($ajaxAction) {
    case 'assign_modules':
        require __DIR__ . '/actions/assign_modules.php';
        exit;
    case 'get_user_modules':
        require __DIR__ . '/actions/get_user_modules.php';
        exit;
    case 'update_module_status':
        require __DIR__ . '/actions/update_module_status.php';
        exit;
}

// Handle non-AJAX form submission (module registration)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action']) && !isset($_POST['update_policy'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $selected = array_filter(array_map('intval', $_POST['modules'] ?? []));
    $status = isset($_POST['status']) && $_POST['status'] == 1 ? 1 : 0;

    if ($userId && !empty($selected)) {
        try {
            registerModules($pdo, $userId, $selected, $status);
            $message = "Module registered successfully for user ID {$userId}.";
        } catch (Exception $e) {
            $message = "Failed to register module: " . $e->getMessage();
        }
    } else {
        $message = "Incomplete data.";
    }
}

// Fetch all data needed for the view
require __DIR__ . '/includes/fetch_data.php';
require __DIR__ . '/includes/helpers.php';

// Render the view
ob_start();
require __DIR__ . '/views/assign_page.php';
$content = ob_get_clean();

$pageTitle = 'Assign Module - Dashboard';
require_once __DIR__ . '/../../partials/layout.php';