<?php
// lokasi: /dashboard/admin/media/delete.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

function json_resp($a,$c=200){ http_response_code($c); echo json_encode($a); exit; }

$user = get_user_from_session($pdo);
if ( ! $user || ! in_array($user['role'], ['teacher','admin']) ) {
    json_resp(['success'=>false,'message'=>'Unauthorized'],401);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$url = trim($_POST['url'] ?? '');
if ($id <= 0 && $url === '') json_resp(['success'=>false,'message'=>'Missing id or url'],400);

try {
  $row = null;
  if ($id > 0) {
    $stmt = $pdo->prepare("SELECT path,url FROM media WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
  } else {
    $stmt = $pdo->prepare("SELECT path,url FROM media WHERE url = :url LIMIT 1");
    $stmt->execute([':url'=>$url]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // if no DB row, still attempt to delete file if path exists (fallback)
  $publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);
  if (!$row) {
    // try resolve path from url param
    if ($url === '') json_resp(['success'=>false,'message'=>'Not found'],404);
    $path = realpath($publicRoot . $url);
    if (!$path || !is_file($path)) json_resp(['success'=>false,'message'=>'Not found'],404);
    // safety: ensure under assets/img
    $allowedBase = realpath($publicRoot . '/assets/img');
    if (strpos($path, $allowedBase) !== 0) json_resp(['success'=>false,'message'=>'Invalid path'],400);
    $ok = @unlink($path);
    json_resp(['success'=>true,'deleted_file'=>$ok ? 1 : 0]);
  }

  $path = $row['path'];
  if (!$path || !is_file($path)) {
    // drop DB record anyway
    $del = $pdo->prepare("DELETE FROM media WHERE url = :url");
    $del->execute([':url'=>$row['url']]);
    json_resp(['success'=>true,'deleted_file'=>0,'message'=>'File not found; record removed']);
  }

  // ensure path inside allowed folder
  $allowedBase = realpath($publicRoot . '/assets/img');
  if (strpos(realpath($path), $allowedBase) !== 0) json_resp(['success'=>false,'message'=>'Invalid path'],400);

  $ok = @unlink($path);
  $del = $pdo->prepare("DELETE FROM media WHERE url = :url");
  $del->execute([':url'=>$row['url']]);

  json_resp(['success'=>true,'deleted_file'=>$ok ? 1 : 0]);
} catch(Throwable $t){
  error_log('media delete error: '.$t->getMessage());
  json_resp(['success'=>false,'message'=>'Server error'],500);
}
