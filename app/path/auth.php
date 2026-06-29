<?php
// path/auth.php

/**
 * Register user (siswa). Throws PDOException on duplicate or DB error.
 */
function register_user(PDO $pdo, string $username, string $email, string $password, string $created_at): int {
    // basic validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException("Invalid email address.");
    }
    if (strlen($password) < 6) {
        throw new InvalidArgumentException("Password must be at least 6 characters.");
    }

    // check duplicates
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        throw new Exception("Username or email already registered.");
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("
    INSERT INTO users (username, email, password, role, created_at)
    VALUES (?, ?, ?, 'student', ?)
");
$stmt->execute([$username, $email, $password_hash, $created_at]);
    return (int)$pdo->lastInsertId();
}

/**
 * Authenticate user by email + password. Returns user array or false.
 */
function authenticate_user(PDO $pdo, string $email, string $password) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

/**
 * Require login: redirect to public login page if not logged in.
 * Returns user row if logged in.
 */
function require_login(PDO $pdo) {
    $user = get_user_from_session($pdo);
    if (!$user) {
        header('Location: /login/');
        exit;
    }
    return $user;
}
