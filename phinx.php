<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\Connection;
use App\Database\DatabaseUrl;

$url = DatabaseUrl::fromEnvironment();

return [
    'paths' => [
        'migrations' => [
            'App\\Database\\Migrations' => __DIR__ . '/db/migrations',
        ],
        'seeds' => [
            'App\\Database\\Seeds' => __DIR__ . '/db/seeds',
        ],
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'default',
        'default' => [
            'adapter' => 'pgsql',
            'connection' => Connection::open($url),
            'name' => $url->database,
        ],
    ],
];
