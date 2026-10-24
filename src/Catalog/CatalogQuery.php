<?php

declare(strict_types=1);

namespace App\Catalog;

use InvalidArgumentException;

final class CatalogQuery
{
    public function __construct(
        public readonly ?string $category,
        public readonly ?string $search,
        public readonly ?int $priceMin,
        public readonly ?int $priceMax,
        public readonly ?bool $available,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromArray(array $query): self
    {
        return new self(
            category: self::stringParameter($query, 'category'),
            search: self::stringParameter($query, 'q'),
            priceMin: self::optionalIntegerParameter($query, 'priceMin'),
            priceMax: self::optionalIntegerParameter($query, 'priceMax'),
            available: self::optionalBooleanParameter($query, 'available'),
            page: self::integerParameter($query, 'page', 1, 1),
            perPage: self::integerParameter($query, 'perPage', 6, 1, 48),
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function stringParameter(array $query, string $name): ?string
    {
        $value = $query[$name] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException($name);
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function optionalIntegerParameter(array $query, string $name): ?int
    {
        $value = $query[$name] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || preg_match('/^\d+$/', $value) !== 1) {
            throw new InvalidArgumentException($name);
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function integerParameter(
        array $query,
        string $name,
        int $default,
        int $minimum,
        ?int $maximum = null
    ): int {
        $value = self::optionalIntegerParameter($query, $name);

        if ($value === null) {
            return $default;
        }

        if ($value < $minimum || ($maximum !== null && $value > $maximum)) {
            throw new InvalidArgumentException($name);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function optionalBooleanParameter(array $query, string $name): ?bool
    {
        $value = $query[$name] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if ($value === true || $value === 'true') {
            return true;
        }

        if ($value === false || $value === 'false') {
            return false;
        }

        throw new InvalidArgumentException($name);
    }
}
