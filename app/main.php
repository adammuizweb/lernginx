<?php
define('BASE_URL', '');
function app_url(string $path = '', array $query = []): string {
    $url = rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    return $url;
}
