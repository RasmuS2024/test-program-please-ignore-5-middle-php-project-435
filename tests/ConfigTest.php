<?php

declare(strict_types=1);

namespace App\Tests;

use App\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testReadsSettingsFromEnvironment(): void
    {
        $oldPort = getenv('PORT');
        $oldDatabaseUrl = getenv('DATABASE_URL');

        try {
            putenv('PORT=9090');
            putenv('DATABASE_URL=pgsql://user:password@127.0.0.1:5433/store');

            $config = Config::fromEnvironment();

            self::assertSame(9090, $config->port);
            self::assertSame(
                'pgsql://user:password@127.0.0.1:5433/store',
                $config->databaseUrl
            );
        } finally {
            self::restoreEnvironment('PORT', $oldPort);
            self::restoreEnvironment('DATABASE_URL', $oldDatabaseUrl);
        }
    }

    public function testUsesDefaultsWhenEnvironmentIsEmpty(): void
    {
        $oldPort = getenv('PORT');
        $oldDatabaseUrl = getenv('DATABASE_URL');

        try {
            putenv('PORT');
            putenv('DATABASE_URL');

            $config = Config::fromEnvironment();

            self::assertSame(8080, $config->port);
            self::assertSame('', $config->databaseUrl);
        } finally {
            self::restoreEnvironment('PORT', $oldPort);
            self::restoreEnvironment('DATABASE_URL', $oldDatabaseUrl);
        }
    }

    private static function restoreEnvironment(string $name, string|false $value): void
    {
        if ($value === false) {
            putenv($name);

            return;
        }

        putenv($name . '=' . $value);
    }
}
