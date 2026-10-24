<?php

declare(strict_types=1);

namespace App\Database;

use App\Config;
use PDO;

final class Connection
{
    public static function fromConfig(Config $config): PDO
    {
        return self::open(DatabaseUrl::fromString($config->databaseUrl));
    }

    public static function open(DatabaseUrl $url): PDO
    {
        return new PDO(
            $url->pdoDsn(),
            $url->user,
            $url->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
