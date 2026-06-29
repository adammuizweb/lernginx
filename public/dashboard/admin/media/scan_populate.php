<?php
// lokasi: /dashboard/admin/media/scan_populate.php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
function json_resp($a, $c = 200){ http_response_code($c); echo json_encode($a); exit; }

// Hanya POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_resp(['success'=>false,'message'=>'Metode tidak diizinkan'],405);
}

// Auth
$user = get_user_from_session($pdo);
if (!$user || !in_array($user['role'], ['teacher','admin'])) {
    json_resp(['success'=>false,'message'=>'Unauthorized'],401);
}

// read requested source: posts|profile|all (default: all)
$source = trim($_POST['source'] ?? 'all');

$publicRoot = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);

// map source keys to real folders (whitelist)
$map = [
    'posts'   => $publicRoot . '/assets/img',
    'profile' => $publicRoot . '/dashboard/profile/static_unchanged/based-registration',
];

// validate requested sources
$sourcesToScan = [];
if ($source === 'all') {
    foreach ($map as $k => $v) $sourcesToScan[$k] = $v;
} else {
    if (!array_key_exists($source, $map)) {
        json_resp(['success'=>false,'message'=>'Sumber tidak dikenal'],400);
    }
    $sourcesToScan[$source] = $map[$source];
}

// helper: mime for images
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

// main scan
$results = [];
$totalAdded = 0;

foreach ($sourcesToScan as $key => $dir) {
    $real = realpath($dir);
    if (!$real || !is_dir($real)) {
        // return message per-source but continue scanning others
        $results[$key] = [
            'success' => false,
            'message' => "Folder tidak ditemukan: {$dir}",
            'items' => []
        ];
        continue;
    }

    $items = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $basename = $file->getBasename();
        // skip dotfiles like .htaccess, .DS_Store
        if (substr($basename, 0, 1) === '.') continue;

        $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) continue;

        $path = $file->getPathname();
        $rel = str_replace($publicRoot, '', $path);
        if ($rel !== '' && $rel[0] !== '/') $rel = '/' . $rel;

        $mime = get_mime_image($path);

        $items[] = [
            'url' => $rel,
            'path' => $path,
            'filename' => $basename,
            'ext' => $ext,
            'mime' => $mime,
            'size' => $file->getSize(),
            'uploaded_at' => date('Y-m-d H:i:s', $file->getMTime())
        ];
    }

    // sort newest first
    usort($items, function($a,$b){ return strcmp($b['uploaded_at'], $a['uploaded_at']); });

    $results[$key] = [
        'success' => true,
        'message' => 'Scan completed',
        'total' => count($items),
        'items' => $items
    ];
    $totalAdded += count($items);
}

// final response
json_resp([
    'success' => true,
    'message' => 'Scan selesai',
    'scanned_sources' => array_keys($results),
    'total_items' => $totalAdded,
    'results' => $results
]);
