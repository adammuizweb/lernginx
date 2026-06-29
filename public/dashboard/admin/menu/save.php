<?php
$map_file = __DIR__ . '/programs.json';
$map = file_exists($map_file) ? json_decode(file_get_contents($map_file), true) : [];

if (!empty($_POST['map'])) {
  foreach ($_POST['map'] as $slug => $data) {
    $map[$slug] = [
      'type' => $data['type'] ?? 'image',
      'url' => trim($data['url']),
      'desc' => trim($data['desc'])
    ];
  }
}

// Program baru ditambahkan otomatis saat kategori utama dibuat.
// Blok manual ini dinonaktifkan agar tidak membingungkan user.
/*
if (!empty($_POST['new_slug']) && !empty($_POST['new_url'])) {
  $slug = trim($_POST['new_slug']);
  $map[$slug] = [
    'type' => $_POST['new_type'] ?? 'image',
    'url' => trim($_POST['new_url']),
    'desc' => trim($_POST['new_desc'])
  ];
}
*/

file_put_contents($map_file, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
header('Location: index.php');
