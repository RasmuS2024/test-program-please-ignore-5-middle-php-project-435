<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($path) ? rawurldecode($path) : '/';
    $publicDir = realpath(__DIR__);
    $file = realpath(__DIR__ . $path);

    if (
        $path !== '/'
        && $file !== false
        && $publicDir !== false
        && $file !== __FILE__
        && str_starts_with($file, $publicDir . DIRECTORY_SEPARATOR)
        && is_file($file)
    ) {
        return false;
    }

    if ($path === '/' && is_file(__DIR__ . '/index.html')) {
        readfile(__DIR__ . '/index.html');

        return;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

App\AppFactory::create()->run();
