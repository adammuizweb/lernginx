<?php
// lokasi: /dashboard/admin/media/list.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
function json_resp($a,$c=200){ http_response_code($c); echo json_encode($a); exit; }

$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher','admin'])) {
    json_resp(['success'=>false,'message'=>'Unauthorized'],401);
}

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = min(60, max(6, (int)($_GET['per'] ?? 24)));
$offset = ($page - 1) * $per;

$source = trim($_GET['source'] ?? 'posts'); // default 'posts'
$publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);

// same whitelist map as scan_populate
$map = [
    'posts'   => $publicRoot . '/assets/img',
    'profile' => $publicRoot . '/dashboard/profile/static_unchanged/based-registration',
];

if ($source === 'all') {
    $dirs = $map;
} else {
    if (!array_key_exists($source, $map)) {
        json_resp(['success'=>false,'message'=>'Sumber tidak dikenal'],400);
    }
    $dirs = [$source => $map[$source]];
}

// MIME helper
function get_mime_image($path){
    if (!is_file($path)) return null;
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f){
            $m = finfo_file($f, $path);
            finfo_close($f);
            if ($m) return $m;
        }
    }
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($path);
        if ($m) return $m;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
        'webp'=>'image/webp','gif'=>'image/gif','svg'=>'image/svg+xml'
    ];
    return $map[$ext] ?? null;
}

$allowedExt = ['jpg','jpeg','png','webp','gif','svg'];
$allFiles = [];

// scan each requested dir and tag source
foreach ($dirs as $key => $dir) {
    $real = realpath($dir);
    if (!$real || !is_dir($real)) continue;

    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $basename = $file->getBasename();
        if (substr($basename, 0, 1) === '.') continue; // skip dotfiles
        $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) continue;

        $path = $file->getPathname();
        $rel = str_replace($publicRoot, '', $path);
        if ($rel !== '' && $rel[0] !== '/') $rel = '/' . $rel;

        $allFiles[] = [
            'source' => $key,
            'url' => $rel,
            'filename' => $basename,
            'ext' => $ext,
            'mime' => get_mime_image($path),
            'size' => $file->getSize(),
            'uploaded_at' => date('Y-m-d H:i:s', $file->getMTime())
        ];
    }
}

// optional search filter
if ($q !== '') {
    $allFiles = array_filter($allFiles, function($f) use ($q) {
        return stripos($f['filename'], $q) !== false || stripos($f['url'], $q) !== false || stripos($f['source'], $q) !== false;
    });
}

// sort newest first
usort($allFiles, function($a,$b){ return strcmp($b['uploaded_at'],$a['uploaded_at']); });

$total = count($allFiles);
$items = array_slice($allFiles, $offset, $per);

json_resp([
    'success' => true,
    'total' => $total,
    'page' => $page,
    'per' => $per,
    'source' => $source,
    'items' => array_values($items)
]);
