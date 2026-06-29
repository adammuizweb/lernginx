<?php
// upload_photo.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../includes/bootstrap.php';
date_default_timezone_set('UTC');

header('Content-Type: application/json; charset=utf-8');

$maxBytes = 300 * 1024; // 300 KB
$allowedExt = ['png','jpg','jpeg','webp'];
$allowedMime = ['image/png','image/jpeg','image/webp'];

$user = get_user_from_session($pdo);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Please login again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!isset($_FILES['foto']) || !is_uploaded_file($_FILES['foto']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
    exit;
}

$file = $_FILES['foto'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error (code: ' . $file['error'] . ').']);
    exit;
}

if ($file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum 300 KB.']);
    exit;
}

// check file mime type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowedMime, true)) {
    echo json_encode(['success' => false, 'message' => 'File format not allowed (mime).']);
    exit;
}

// extension based on name (sanitize)
$origName = $file['name'];
$pathinfo = pathinfo($origName);
$ext = strtolower($pathinfo['extension'] ?? '');
if (!in_array($ext, $allowedExt, true)) {
    echo json_encode(['success' => false, 'message' => 'File format not allowed (ext).']);
    exit;
}

// Create target folder: dashboard/profile/static_unchanged/based-registration/foto_profile/{YYYY}/{MM}
$baseRel = '/dashboard/profile/static_unchanged/based-registration/foto_profile';
$year = date('Y');
$month = date('m');

// compute real server path (root from project)
// going up 3 levels from dashboard/profile/ to public root.
// Adjust if directory structure differs.
$publicRoot = realpath(__DIR__ . '/../../../'); // should be project root (adjust if needed)
if (!$publicRoot) $publicRoot = __DIR__;

// target dir full path
$targetDir = $publicRoot . $baseRel . '/' . $year . '/' . $month;

// Ensure directory exists
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create storage folder.']);
        exit;
    }
}

// generate filename: basename-YYYYmmddHHMMSS-rand10.ext
function randstr($len = 10) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $chars[random_int(0, strlen($chars)-1)];
    return $s;
}

$baseSafe = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', pathinfo($pathinfo['filename'], PATHINFO_BASENAME));
$timestamp = date('YmdHis');
$rand = randstr(10);
$newFilename = $baseSafe . '-' . $timestamp . '-' . $rand . '.' . $ext;

$targetPath = $targetDir . '/' . $newFilename;

// move file to target
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

// Build public URL path for DB storage using $baseRel + /year/month/filename
// Example: /dashboard/profile/static_unchanged/based-registration/foto_profile/2025/10/...
$storedUrl = $baseRel . '/' . $year . '/' . $month . '/' . $newFilename;

// Update DB: users.foto_profil
try {
    $stmt = $pdo->prepare("UPDATE users SET foto_profil = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$storedUrl, $user['id']]);
} catch (PDOException $e) {
    // if DB update fails, delete the uploaded file
    @unlink($targetPath);
    echo json_encode(['success' => false, 'message' => 'Failed to save info to database: ' . $e->getMessage()]);
    exit;
}

// success
echo json_encode(['success' => true, 'url' => $storedUrl]);
exit;
