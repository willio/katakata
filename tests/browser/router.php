<?php
declare(strict_types=1);

$publicRoot = dirname(__DIR__, 2) . '/public';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$publicRealPath = realpath($publicRoot);
$requestedFile = realpath($publicRoot . $requestPath);

if (
    $requestPath !== '/'
    && $publicRealPath !== false
    && $requestedFile !== false
    && str_starts_with($requestedFile, $publicRealPath . DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
) {
    return false;
}

require $publicRoot . '/index.php';
