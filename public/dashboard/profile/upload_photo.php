<?php
// upload_photo.php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Buffer output from bootstrap (in case bootstrap echoes something)
ob_start();
require_once __DIR__ . '/../../includes/bootstrap.php';
$bootstrap_output = ob_get_clean();

date_default_timezone_set('UTC');
header('Content-Type: application/json; charset=utf-8');

$debug = [];

try {
    $maxBytes = 300 * 1024; // 300 KB
    $allowedExt = ['png','jpg','jpeg','webp'];
    $allowedMimeStarts = ['image/'];

    $debug['bootstrap_output_snippet'] = substr($bootstrap_output, 0, 1000);

    // helper response JSON
    function json_resp($arr, $status = 200) {
        http_response_code($status);
        echo json_encode($arr);
        exit;
    }

    // ensure auth function exists
    if (!function_exists('get_user_from_session')) {
        error_log('UPLOAD: get_user_from_session not found; bootstrap output: ' . substr($bootstrap_output,0,800));
        json_resp(['success' => false, 'message' => 'Server misconfiguration (missing auth).', 'debug'=>$debug], 500);
    }

    $user = get_user_from_session($pdo);
    if (!$user) {
        json_resp(['success' => false, 'message' => 'Access denied. Please login again.', 'debug'=>$debug], 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_resp(['success' => false, 'message' => 'Method not allowed.', 'debug'=>$debug], 405);
    }

    if (!isset($_FILES['foto']) || !is_uploaded_file($_FILES['foto']['tmp_name'])) {
        json_resp(['success' => false, 'message' => 'No file uploaded.', 'debug'=>$debug], 400);
    }

    $file = $_FILES['foto'];
    $debug['file_keys'] = array_intersect_key($file, array_flip(['name','type','size','tmp_name','error']));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errcode = (int)$file['error'];
        error_log("UPLOAD: upload error code $errcode");
        json_resp(['success' => false, 'message' => 'Upload error (code: ' . $errcode . ').', 'debug'=>$debug], 400);
    }

    if ($file['size'] > $maxBytes) {
        json_resp(['success' => false, 'message' => 'File too large. Maximum 300 KB.', 'debug'=>$debug], 400);
    }

    // === MIME detection: Fallback chain ===
    $mime = null;

    // 1) finfo if available
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f !== false) {
            $mime = finfo_file($f, $file['tmp_name']);
            finfo_close($f);
        }
    }

    // 2) mime_content_type if available
    if (empty($mime) && function_exists('mime_content_type')) {
        $m2 = @mime_content_type($file['tmp_name']);
        if ($m2 !== false) $mime = $m2;
    }

    // 3) getimagesize (returns mime in 'mime' index)
    if (empty($mime)) {
        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo !== false && isset($imgInfo['mime'])) {
            $mime = $imgInfo['mime'];
        }
    }

    // 4) last fallback: use client-provided type (less secure)
    if (empty($mime) && !empty($file['type'])) {
        $mime = $file['type'];
    }

    if (empty($mime)) {
        json_resp(['success' => false, 'message' => 'Failed to detect file type.', 'debug'=>$debug], 400);
    }

    $debug['detected_mime'] = $mime;

    $validMime = false;
    foreach ($allowedMimeStarts as $p) {
        if (stripos($mime, $p) === 0) { $validMime = true; break; }
    }
    if (!$validMime) {
        json_resp(['success' => false, 'message' => 'File format not allowed (mime: ' . $mime . ').', 'debug'=>$debug], 400);
    }

    // extension from filename (sanitized)
    $origName = $file['name'];
    $pathinfo = pathinfo($origName);
    $ext = strtolower($pathinfo['extension'] ?? '');
    $nameOnly = $pathinfo['filename'] ?? 'file';
    $debug['orig_name'] = $origName;
    $debug['ext'] = $ext;
    if (!in_array($ext, $allowedExt, true)) {
        json_resp(['success' => false, 'message' => 'File format not allowed (ext).', 'debug'=>$debug], 400);
    }

    // verify it is a valid image
    if (@getimagesize($file['tmp_name']) === false) {
        json_resp(['success' => false, 'message' => 'File is not a valid image.', 'debug'=>$debug], 400);
    }

    // determine public save location
    $baseRel = '/dashboard/profile/static_unchanged/based-registration/foto_profile';
    $year = date('Y');
    $month = date('m');

    $publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);
    $targetDir = $publicRoot . $baseRel . '/' . $year . '/' . $month;

    $debug['publicRoot'] = $publicRoot;
    $debug['targetDir'] = $targetDir;

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            error_log('UPLOAD: failed to create folder ' . $targetDir);
            json_resp(['success' => false, 'message' => 'Failed to create storage folder.', 'debug'=>$debug], 500);
        }
    }
    if (!is_writable($targetDir)) {
        error_log('UPLOAD: target dir not writable ' . $targetDir);
        json_resp(['success' => false, 'message' => 'Storage folder is not writable by the server.', 'debug'=>$debug], 500);
    }

    // safe filename
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $rand = '';
    for ($i=0;$i<10;$i++) $rand .= $chars[random_int(0, strlen($chars)-1)];
    $baseSafe = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $nameOnly);
    $timestamp = date('YmdHis');
    $newFilename = $baseSafe . '-' . $timestamp . '-' . $rand . '.' . $ext;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $newFilename;
    $debug['targetPath'] = $targetPath;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        error_log('UPLOAD: move_uploaded_file failed. tmp=' . $file['tmp_name'] . ' -> ' . $targetPath);
        json_resp(['success' => false, 'message' => 'Failed to save file.', 'debug'=>$debug], 500);
    }

    $storedUrl = $baseRel . '/' . $year . '/' . $month . '/' . $newFilename;

    // update DB
    try {
        $stmt = $pdo->prepare("UPDATE users SET foto_profil = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$storedUrl, $user['id']]);
    } catch (PDOException $e) {
        @unlink($targetPath);
        error_log('UPLOAD DB ERR: ' . $e->getMessage());
        json_resp(['success' => false, 'message' => 'Failed to save info to database.', 'debug'=>$debug, 'db_error'=>$e->getMessage()], 500);
    }

    json_resp(['success' => true, 'url' => $storedUrl, 'debug' => $debug], 200);

} catch (Throwable $t) {
    error_log('UPLOAD EXCEPTION: ' . $t->getMessage() . ' in ' . $t->getFile() . ':' . $t->getLine());
    json_resp(['success'=>false, 'message'=>'Server error (exception).', 'exception'=>['msg'=>$t->getMessage(),'file'=>$t->getFile(),'line'=>$t->getLine()]], 500);
}
