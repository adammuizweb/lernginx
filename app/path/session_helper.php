<?php
// path/session_helper.php
// Assumes $pdo is available (bootstrap loads db.php before this file).
// Session cookie params set in bootstrap.

function create_session(PDO $pdo, int $user_id, int $duration_seconds = 3600): string {
    $raw_token = bin2hex(random_bytes(32)); // 64 hex
    $hash_token = hash('sha256', $raw_token);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $expires_at = date('Y-m-d H:i:s', time() + $duration_seconds);

    $stmt = $pdo->prepare("INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $hash_token, $ip, $agent, $expires_at]);

    // Set cookie (HttpOnly)
    setcookie('lernginx_session', $raw_token, [
        'expires' => time() + $duration_seconds,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    return $raw_token;
}

function get_user_from_session(PDO $pdo) {
    if (empty($_COOKIE['lernginx_session'])) return null;

    $raw_token = $_COOKIE['lernginx_session'];
    if (!is_string($raw_token) || strlen($raw_token) < 10) return null;

    $hash_token = hash('sha256', $raw_token);

$stmt = $pdo->prepare("SELECT s.id AS session_id, s.user_id, s.expires_at, u.* 
                       FROM sessions s 
                       JOIN users u ON s.user_id = u.id
                       WHERE s.session_token = ? 
                         AND s.is_valid = 1 
                         AND (s.expires_at IS NULL OR s.expires_at > NOW())
                         AND (u.is_deleted IS NULL OR u.is_deleted = 0)
                       LIMIT 1");
    $stmt->execute([$hash_token]);
    $row = $stmt->fetch();

    if (!$row) return null;

    // update last_activity
    if (!empty($row['session_id'])) {
        $pdo->prepare("UPDATE sessions SET last_activity = NOW() WHERE id = ?")->execute([$row['session_id']]);
    }

    return $row; // contains user columns + session_id
}

function destroy_session(PDO $pdo): void {
    if (!empty($_COOKIE['lernginx_session'])) {
        $raw_token = $_COOKIE['lernginx_session'];
        $hash_token = hash('sha256', $raw_token);
        $pdo->prepare("UPDATE sessions SET is_valid = 0 WHERE session_token = ?")->execute([$hash_token]);

        // Clear cookie
        setcookie('lernginx_session', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        unset($_COOKIE['lernginx_session']);
    }

    // If PHP session was used elsewhere, destroy it
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}
