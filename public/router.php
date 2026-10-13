<?php
declare(strict_types=1);

// Router for PHP's built-in development server. Existing files must be
// returned directly; application requests continue through the front controller.
$publicRoot = realpath(__DIR__);
$requestPath = rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
$candidate = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim($requestPath, '/'));

if (
    $requestPath !== '/'
    && $publicRoot !== false
    && $candidate !== false
    && str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR)
    && is_file($candidate)
) {
    return false;
}

require __DIR__ . '/index.php';
