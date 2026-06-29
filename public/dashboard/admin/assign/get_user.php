<?php
define('DASHBOARD_CONTEXT', true);

// path ke bootstrap utama (sesuaikan struktur project)
require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// pastikan $pdo tersedia (bootstrap biasanya menyediakan)
if (!isset($pdo) || !$pdo) {
    if (function_exists('get_pdo')) {
        $pdo = get_pdo();
    } else {
        error_log('get_user.php: $pdo not available and get_pdo() not found');
        echo json_encode(['success' => false, 'message' => 'Server configuration error']);
        exit;
    }
}

// gunakan get_user_from_session($pdo) sesuai referensi profile/index.php
$currentUser = null;
if (function_exists('get_user_from_session')) {
    try {
        $currentUser = get_user_from_session($pdo);
    } catch (Throwable $t) {
        error_log('get_user.php: get_user_from_session() threw: ' . $t->getMessage());
        $currentUser = null;
    }
} else {
    error_log('get_user.php: get_user_from_session() not defined in bootstrap');
}

// debug log singkat (tidak dikembalikan ke client)
error_log(sprintf("get_user.php called. currentUserId=%s; cookie_keys=%s",
    $currentUser['id'] ?? '(none)',
    implode(',', array_keys($_COOKIE ?: []))
));

// id validation
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'User ID tidak valid']);
    exit;
}

// auth check: pastikan ada user yang ter-auth
if (empty($currentUser) || empty($currentUser['id'])) {
    error_log('get_user.php: not authenticated (get_user_from_session returned empty)');
    echo json_encode(['success' => false, 'message' => 'Tidak terautentikasi']);
    exit;
}

try {
    // ambil user target: prefer helper get_user_full_by_id jika ada
    if (function_exists('get_user_full_by_id')) {
        try {
            $user = get_user_full_by_id($pdo, $id);
        } catch (ArgumentCountError $e) {
            // beberapa implementasi mungkin hanya perlu id
            $user = get_user_full_by_id($id);
        }
    } else {
        $stmt = $pdo->prepare("SELECT
            id, username, display_name, email, foto_profil, nomor_telpon, alamat_rumah,
            tanggal_lahir, asal_sekolah, tahun_masuk, jurusan, nisn, role,
            created_at, updated_at
          FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    // jangan kirim password
    if (isset($user['password'])) unset($user['password']);

// Convert timestamps assuming DB values represent UTC time.
// This will produce ISO8601 with +07:00 (e.g. 2025-10-26T21:25:18+07:00)
try {
    if (!empty($user['created_at'])) {
        $dt = new DateTime($user['created_at'], new DateTimeZone('Asia/Jakarta'));
        $user['created_at'] = $dt->format('c');
    } else {
        $user['created_at'] = null;
    }

    if (!empty($user['updated_at'])) {
        $dt = new DateTime($user['updated_at'], new DateTimeZone('Asia/Jakarta'));
        $user['updated_at'] = $dt->format('c');
    } else {
        $user['updated_at'] = null;
    }
} catch (Exception $e) {
    error_log('get_user.php: date conversion failed: ' . $e->getMessage());
    // fallback: send raw DB values if conversion fails
    $user['created_at'] = !empty($user['created_at']) ? $user['created_at'] : null;
    $user['updated_at'] = !empty($user['updated_at']) ? $user['updated_at'] : null;
}


    echo json_encode(['success' => true, 'user' => $user]);
    exit;
} catch (PDOException $e) {
    error_log('get_user error (PDO): ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error while loading data.']);
    exit;
} catch (Throwable $t) {
    error_log('get_user unexpected error: ' . $t->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error while loading data.']);
    exit;
}
