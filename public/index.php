<?php

declare(strict_types=1);

ini_set('default_charset', '');

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
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $contentTypes = [
            'css' => 'text/css',
            'html' => 'text/html; charset=UTF-8',
            'js' => 'text/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
        ];

        header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($file));
        readfile($file);

        return;
    }

    if ($path === '/' && is_file(__DIR__ . '/index.html')) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile(__DIR__ . '/index.html');

        return;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

App\AppFactory::create()->run();
