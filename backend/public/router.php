<?php

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . $uri;

if (is_file($file)) {
    if (preg_match('/^\/uploads\/memories\/.+\.(jpg|jpeg|png|gif|webp)$/i', $uri)) {
        $mime = mime_content_type($file) ?: 'image/jpeg';
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
    return false;
}

require __DIR__ . '/index.php';
