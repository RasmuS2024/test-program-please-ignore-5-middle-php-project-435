<?php

declare(strict_types=1);

namespace App\Database;

use InvalidArgumentException;

final class DatabaseUrl
{
    private function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $database,
        public readonly string $user,
        public readonly string $password,
        public readonly ?string $sslMode,
        public readonly ?int $connectTimeout,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $url = getenv('DATABASE_URL');

        if (!is_string($url) || trim($url) === '') {
            throw new InvalidArgumentException('DATABASE_URL is not set.');
        }

        return self::fromString($url);
    }

    public static function fromString(string $url): self
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'])) {
            throw new InvalidArgumentException('DATABASE_URL is not a valid URL.');
        }

        $scheme = strtolower($parts['scheme']);

        if (!in_array($scheme, ['pgsql', 'postgres', 'postgresql'], true)) {
            throw new InvalidArgumentException(
                'DATABASE_URL must use the pgsql://, postgres:// or postgresql:// scheme.'
            );
        }

        $host = $parts['host'] ?? '';
        $database = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

        if ($host === '' || $database === '') {
            throw new InvalidArgumentException(
                'DATABASE_URL must contain a host and a database name.'
            );
        }

        parse_str($parts['query'] ?? '', $query);

        $sslMode = isset($query['sslmode']) && is_string($query['sslmode'])
            ? $query['sslmode']
            : null;

        $connectTimeout = null;

        if (
            isset($query['connect_timeout'])
            && is_string($query['connect_timeout'])
            && ctype_digit($query['connect_timeout'])
        ) {
            $connectTimeout = (int) $query['connect_timeout'];
        }

        return new self(
            host: $host,
            port: isset($parts['port']) ? (int) $parts['port'] : 5432,
            database: urldecode($database),
            user: isset($parts['user']) ? urldecode($parts['user']) : '',
            password: isset($parts['pass']) ? urldecode($parts['pass']) : '',
            sslMode: $sslMode,
            connectTimeout: $connectTimeout,
        );
    }

    public function pdoDsn(): string
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $this->host,
            $this->port,
            $this->database
        );

        if ($this->sslMode !== null) {
            $dsn .= ';sslmode=' . $this->sslMode;
        }

        if ($this->connectTimeout !== null) {
            $dsn .= ';connect_timeout=' . $this->connectTimeout;
        }

        return $dsn;
    }
}
