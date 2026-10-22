<?php

declare(strict_types=1);

namespace App;

final class Config
{
    public function __construct(
        public readonly int $port,
        public readonly string $databaseUrl,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $port = getenv('PORT');
        $port = $port === false || $port === '' ? 8080 : (int) $port;

        return new self(
            port: $port,
            databaseUrl: (string) (getenv('DATABASE_URL') ?: ''),
        );
    }
}
