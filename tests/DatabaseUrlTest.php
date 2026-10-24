<?php

declare(strict_types=1);

namespace App\Tests;

use App\Database\DatabaseUrl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DatabaseUrlTest extends TestCase
{
    public function testParsesPgsqlUrl(): void
    {
        $url = DatabaseUrl::fromString(
            'pgsql://user:p%40ss@127.0.0.1:5433/store?sslmode=require&connect_timeout=5'
        );

        self::assertSame('127.0.0.1', $url->host);
        self::assertSame(5433, $url->port);
        self::assertSame('store', $url->database);
        self::assertSame('user', $url->user);
        self::assertSame('p@ss', $url->password);
        self::assertSame('require', $url->sslMode);
        self::assertSame(5, $url->connectTimeout);
        self::assertSame(
            'pgsql:host=127.0.0.1;port=5433;dbname=store;sslmode=require;connect_timeout=5',
            $url->pdoDsn()
        );
    }

    public function testAcceptsPostgresSchemes(): void
    {
        foreach (['postgres', 'postgresql'] as $scheme) {
            $url = DatabaseUrl::fromString($scheme . '://user:password@db.example.com/app');

            self::assertSame('db.example.com', $url->host);
            self::assertSame(5432, $url->port);
            self::assertSame('app', $url->database);
        }
    }

    public function testRejectsUnsupportedScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DatabaseUrl::fromString('mysql://user:password@localhost/store');
    }

    public function testRequiresDatabaseName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DatabaseUrl::fromString('pgsql://user:password@localhost');
    }
}
