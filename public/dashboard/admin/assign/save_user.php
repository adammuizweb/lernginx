<?php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
header('Content-Type: application/json');
date_default_timezone_set('UTC');

// only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success'=>false,'message'=>'Invalid method']);
  exit;
}

// ensure $pdo exists (bootstrap usually sets it)
if (!isset($pdo) || !$pdo) {
    if (function_exists('get_pdo')) {
        $pdo = get_pdo();
    } else {
        error_log('save_user.php: $pdo not available and get_pdo() not found');
        echo json_encode(['success'=>false,'message'=>'Server configuration error']);
        exit;
    }
}

// --- AUTH: use same helper as profile (get_user_from_session) ---
$currentUser = null;
if (function_exists('get_user_from_session')) {
    try {
        $currentUser = get_user_from_session($pdo);
    } catch (Throwable $t) {
        error_log('save_user.php: get_user_from_session threw: ' . $t->getMessage());
        $currentUser = null;
    }
} else {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $currentUser = $_SESSION['user'] ?? null;
}

// debug log minimal (optional)
// error_log('save_user.php called by currentUserId=' . ($currentUser['id'] ?? '(none)') . ' role=' . ($currentUser['role'] ?? '(none)'));

// authorize: only admin may update via this endpoint
if (empty($currentUser) || (($currentUser['role'] ?? '') !== 'admin')) {
    echo json_encode(['success'=>false,'message'=>'Tidak diizinkan']);
    exit;
}

// collect & validate input
$input = [
  'id' => isset($_POST['id']) ? (int)$_POST['id'] : 0,
  'display_name' => trim($_POST['display_name'] ?? ''),
  'email' => trim($_POST['email'] ?? ''),
  'nomor_telpon' => trim($_POST['nomor_telpon'] ?? ''),
  'alamat_rumah' => trim($_POST['alamat_rumah'] ?? ''),
  'tanggal_lahir' => trim($_POST['tanggal_lahir'] ?? ''),
  'asal_sekolah' => trim($_POST['asal_sekolah'] ?? ''),
  'tahun_masuk' => trim($_POST['tahun_masuk'] ?? ''),
  'jurusan' => trim($_POST['jurusan'] ?? ''),
  'nisn' => trim($_POST['nisn'] ?? ''),
  'foto_profil' => trim($_POST['foto_profil'] ?? '')
];

if ($input['id'] <= 0) {
  echo json_encode(['success'=>false,'message'=>'User ID tidak valid']);
  exit;
}

if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['success'=>false,'message'=>'Email tidak valid']);
  exit;
}

try {
  // perform update
  $stmt = $pdo->prepare("UPDATE users SET
    display_name = :display_name,
    email = :email,
    nomor_telpon = :nomor_telpon,
    alamat_rumah = :alamat_rumah,
    tanggal_lahir = NULLIF(:tanggal_lahir,''),
    asal_sekolah = :asal_sekolah,
    tahun_masuk = NULLIF(:tahun_masuk,''),
    jurusan = :jurusan,
    nisn = :nisn,
    foto_profil = :foto_profil,
    updated_at = NOW()
    WHERE id = :id LIMIT 1
  ");
  $stmt->execute([
    ':display_name' => $input['display_name'],
    ':email' => $input['email'],
    ':nomor_telpon' => $input['nomor_telpon'],
    ':alamat_rumah' => $input['alamat_rumah'],
    ':tanggal_lahir' => $input['tanggal_lahir'],
    ':asal_sekolah' => $input['asal_sekolah'],
    ':tahun_masuk' => $input['tahun_masuk'],
    ':jurusan' => $input['jurusan'],
    ':nisn' => $input['nisn'],
    ':foto_profil' => $input['foto_profil'],
    ':id' => $input['id']
  ]);

  // ambil kembali created_at + updated_at (dan kembalikan beberapa field yang relevan)
  $stmt2 = $pdo->prepare("SELECT created_at, updated_at FROM users WHERE id = :id LIMIT 1");
  $stmt2->execute([':id' => $input['id']]);
  $row = $stmt2->fetch(PDO::FETCH_ASSOC);

  $created_at_iso = $row['created_at'] ? date('c', strtotime($row['created_at'])) : null;
  $updated_at_iso = $row['updated_at'] ? date('c', strtotime($row['updated_at'])) : null;

  // respond with updated timestamps and the fields we just wrote (so client can update UI without reload)
  $responseUser = [
    'id' => $input['id'],
    'display_name' => $input['display_name'],
    'email' => $input['email'],
    'nomor_telpon' => $input['nomor_telpon'],
    'alamat_rumah' => $input['alamat_rumah'],
    'tanggal_lahir' => $input['tanggal_lahir'],
    'asal_sekolah' => $input['asal_sekolah'],
    'tahun_masuk' => $input['tahun_masuk'],
    'jurusan' => $input['jurusan'],
    'nisn' => $input['nisn'],
    'foto_profil' => $input['foto_profil'],
    'created_at' => $created_at_iso,
    'updated_at' => $updated_at_iso
  ];

  echo json_encode(['success'=>true, 'user' => $responseUser]);
  exit;
} catch (PDOException $e) {
  error_log('save_user error: '.$e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Server error while saving']);
  exit;
}
