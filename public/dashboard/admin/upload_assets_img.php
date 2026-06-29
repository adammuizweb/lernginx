<?php
// lokasi: /dashboard/admin/upload_assets_img.php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

ob_start();
require_once __DIR__ . '/../../includes/bootstrap.php';
$bootstrap_output = ob_get_clean();

date_default_timezone_set('UTC');
header('Content-Type: application/json; charset=utf-8');

try {
    // === Konfigurasi ===
    $maxBytes = 500 * 1024; // 500 KB
    $allowedExt = ['jpeg', 'jpg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    // helper respons JSON
    function json_resp($arr, $status = 200) {
        http_response_code($status);
        echo json_encode($arr);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_resp(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
    }

    if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        json_resp(['success' => false, 'message' => 'Tidak ada file diupload.'], 400);
    }

    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_resp(['success' => false, 'message' => 'Upload gagal (kode: '.$file['error'].').'], 400);
    }

    if ($file['size'] > $maxBytes) {
        json_resp(['success' => false, 'message' => 'File too large. Maximum 500 KB.'], 400);
    }

    // === Deteksi MIME ===
    $mime = null;
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $mime = finfo_file($f, $file['tmp_name']);
            finfo_close($f);
        }
    }
    if (empty($mime) && function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    }
    if (empty($mime)) {
        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo && isset($imgInfo['mime'])) $mime = $imgInfo['mime'];
    }
    if (empty($mime)) $mime = $file['type'] ?? '';

    if (!in_array($mime, $allowedMime, true)) {
        json_resp(['success' => false, 'message' => 'File format not allowed.'], 400);
    }

    // === Ekstensi aman ===
    $pathinfo = pathinfo($file['name']);
    $ext = strtolower($pathinfo['extension'] ?? '');
    $nameOnly = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $pathinfo['filename'] ?? 'file');

    if (!in_array($ext, $allowedExt, true)) {
        json_resp(['success' => false, 'message' => 'Ekstensi file tidak diizinkan.'], 400);
    }

    // === Pastikan valid image ===
    if (@getimagesize($file['tmp_name']) === false) {
        json_resp(['success' => false, 'message' => 'File bukan gambar yang valid.'], 400);
    }

    // === Buat folder tujuan ===
    $publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);
    $baseRel = '/assets/img';
    $year = date('Y');
    $month = date('m');
    $targetDir = $publicRoot . $baseRel . '/' . $year . '/' . $month;

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            json_resp(['success' => false, 'message' => 'Failed to create storage folder.'], 500);
        }
    }

    // === .htaccess keamanan ===
    $htaccessPath = $targetDir . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        $ht = <<<HT
# Non-executable uploads
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<FilesMatch "\.(php|phtml|phar|pl|py|jsp|asp|sh|cgi)$">
    Require all denied
</FilesMatch>
HT;
        @file_put_contents($htaccessPath, $ht);
        @chmod($htaccessPath, 0644);
    }

    // === Re-encode gambar (hapus payload) ===
    $imgInfo = getimagesize($file['tmp_name']);
    $mime = $imgInfo['mime'];
    $loader = null; $saver = null; $saveExt = null;

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $loader = 'imagecreatefromjpeg';
            $saver  = fn($img, $p) => imagejpeg($img, $p, 90);
            $saveExt = 'jpg';
            break;
        case 'image/png':
            $loader = 'imagecreatefrompng';
            $saver  = fn($img, $p) => imagepng($img, $p, 6);
            $saveExt = 'png';
            break;
        case 'image/webp':
            $loader = 'imagecreatefromwebp';
            $saver  = fn($img, $p) => imagewebp($img, $p, 80);
            $saveExt = 'webp';
            break;
    }

    if (!$loader || !$saver) {
        json_resp(['success' => false, 'message' => 'Server tidak mendukung format ini.'], 400);
    }

    $img = @$loader($file['tmp_name']);
    if (!$img) json_resp(['success' => false, 'message' => 'Failed to process image.'], 500);

    // === Resize opsional (maks 3000px) ===
    $maxW = 3000; $maxH = 3000;
    $w = imagesx($img); $h = imagesy($img);
    if ($w > $maxW || $h > $maxH) {
        $ratio = min($maxW / $w, $maxH / $h);
        $nw = (int)($w * $ratio); $nh = (int)($h * $ratio);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }

    // === Generate nama acak ===
    $rand = bin2hex(random_bytes(8));
    $timestamp = date('YmdHis');
    $newFilename = "{$nameOnly}-{$timestamp}-{$rand}.{$saveExt}";
    $targetPath = "{$targetDir}/{$newFilename}";

    // === Simpan file ===
    if (!$saver($img, $targetPath)) {
        imagedestroy($img);
        json_resp(['success' => false, 'message' => 'Failed to save image.'], 500);
    }
    imagedestroy($img);
    @chmod($targetPath, 0644);

    $storedUrl = "{$baseRel}/{$year}/{$month}/{$newFilename}";

    json_resp([
        'success' => true,
        'url' => $storedUrl
    ], 200);

} catch (Throwable $t) {
    error_log('UPLOAD EXCEPTION: ' . $t->getMessage());
    json_resp(['success' => false, 'message' => 'Server error.'], 500);
}
