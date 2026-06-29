<?php
// lokasi: /dashboard/admin/media/register_upload.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

function json_resp($a,$c=200){ http_response_code($c); echo json_encode($a); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_resp(['success'=>false,'message'=>'Metode tidak diizinkan'],405);

$user = get_user_from_session($pdo);
if ( ! $user || ! in_array($user['role'], ['teacher','admin']) ) {
    json_resp(['success'=>false,'message'=>'Unauthorized'],401);
}

$url = trim($_POST['url'] ?? '');
if (!$url) json_resp(['success'=>false,'message'=>'Missing url'],400);

// Only allow internal assets
if (strpos($url, '/assets/img/') !== 0) json_resp(['success'=>false,'message'=>'Invalid url'],400);

$publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);
$path = realpath($publicRoot . $url);
if (!$path || !is_file($path)) json_resp(['success'=>false,'message'=>'File not found'],404);

// Ensure file is under assets/img
$allowedBase = realpath($publicRoot . '/assets/img');
if (strpos($path, $allowedBase) !== 0) json_resp(['success'=>false,'message'=>'Invalid path'],400);

// gather info
$size = @filesize($path) ?: 0;
$mime = null;
if (function_exists('finfo_open')) {
  $f = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($f, $path);
  finfo_close($f);
}
$imgInfo = @getimagesize($path);
$width = $imgInfo[0] ?? null;
$height = $imgInfo[1] ?? null;
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$filename = basename($path);

try {
  // ensure table exists (safe guard)
  $pdo->exec("CREATE TABLE IF NOT EXISTS media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(512) NOT NULL UNIQUE,
    path VARCHAR(1024) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    `ext` VARCHAR(10) DEFAULT NULL,
    mime VARCHAR(64) DEFAULT NULL,
    size INT DEFAULT 0,
    width INT DEFAULT NULL,
    height INT DEFAULT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT NULL,
    note VARCHAR(255) DEFAULT NULL,
    INDEX (uploaded_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(Throwable $t){
  // ignore if cannot create
}

try {
  $stmt = $pdo->prepare("SELECT id FROM media WHERE url = :url LIMIT 1");
  $stmt->execute([':url'=>$url]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    json_resp(['success'=>true,'message'=>'Already registered','id'=>$row['id']]);
  }
  $ins = $pdo->prepare("INSERT INTO media (url,path,filename,`ext`,mime,size,width,height,uploaded_by) VALUES (:url,:path,:filename,:ext,:mime,:size,:width,:height,:uid)");
  $uid = $user['id'] ?? null;
  $ins->execute([
    ':url'=>$url, ':path'=>$path, ':filename'=>$filename, ':ext'=>$ext, ':mime'=>$mime, ':size'=>$size,
    ':width'=>$width, ':height'=>$height, ':uid'=>$uid
  ]);
  json_resp(['success'=>true,'message'=>'Registered','id'=>$pdo->lastInsertId()]);
} catch (Throwable $t) {
  error_log('register_upload error: '.$t->getMessage());
  json_resp(['success'=>false,'message'=>'Server error'],500);
}
