<?php
// /dashboard/admin/user-setting/save.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

if (!defined('APP_SECRET_KEY')) {
    define('APP_SECRET_KEY', 'replace-with-real-secret');
}

$user = get_user_from_session($pdo);
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$email = trim((string)($_POST['email'] ?? ''));
$role = (string)($_POST['role'] ?? '');
$password = (string)($_POST['password'] ?? '');

$allowed_roles = ['student', 'teacher', 'admin'];

if ($id <= 0) {
    $err = 'Invalid user ID';
    header('Location: index.php?error=' . rawurlencode($err));
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Invalid email format';
    header('Location: index.php?error=' . rawurlencode($err));
    exit;
}
if (!in_array($role, $allowed_roles, true)) {
    $err = 'Invalid role';
    header('Location: index.php?error=' . rawurlencode($err));
    exit;
}
if ($id === (int)$user['id'] && $role !== 'admin') {
    $err = 'Cannot change your own account role to non-admin';
    header('Location: index.php?error=' . rawurlencode($err));
    exit;
}

try {
    if ($password !== '') {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users
            SET email = ?, password = ?, role = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$email, $password_hash, $role, $id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE users
            SET email = ?, role = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$email, $role, $id]);
    }

    // --- Force logout for the edited user ---
    // 1) If there is a helper function that supports destroying sessions for a specific user, call it.
    if (function_exists('destroy_session_for_user')) {
        try { destroy_session_for_user($pdo, $id); } catch (Throwable $e) { error_log('destroy_session_for_user failed: '.$e->getMessage()); }
    } elseif (function_exists('destroy_user_sessions')) {
        try { destroy_user_sessions($pdo, $id); } catch (Throwable $e) { error_log('destroy_user_sessions failed: '.$e->getMessage()); }
    } else {
        // 2) Try common DB session table cleanup (best-effort, ignore errors)
        try {
            $pdo->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$id]);
        } catch (Throwable $e) {
            // ignore
        }
        try {
            $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$id]);
        } catch (Throwable $e) {
            // ignore
        }
    }

    // 3) If system uses a session_version / token_version column pattern, increment it so all tokens become invalid.
    try {
        $pdo->prepare("UPDATE users SET session_version = COALESCE(session_version, 0) + 1 WHERE id = ?")
            ->execute([$id]);
    } catch (Throwable $e) {
        // ignore if column not present
    }

    // Redirect back with flags to show confirmation and note that edited user was forced to re-login
    header('Location: ./?ok=1&force_logout=1&uid=' . rawurlencode((string)$id));
    exit;
} catch (PDOException $e) {
    error_log('User update error: ' . $e->getMessage());
    $err = 'A database error occurred';
    header('Location: index.php?error=' . rawurlencode($err));
    exit;
}
