<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$publicFile = __DIR__ . '/public' . $path;
$storageUploadsFile = __DIR__ . $path;

if ($path !== '/' && is_file($publicFile)) {
    return false;
}

if (str_starts_with($path, '/storage/uploads/') && is_file($storageUploadsFile)) {
    return false;
}

require __DIR__ . '/public/index.php';
