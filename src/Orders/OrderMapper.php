<?php

declare(strict_types=1);

namespace App\Orders;

use DateTimeImmutable;
use DateTimeZone;

final class OrderMapper
{
    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public static function order(array $row, array $items): array
    {
        $shipping = [
            'method' => (string) $row['shipping_method'],
            'recipientName' => (string) $row['recipient_name'],
            'phone' => (string) $row['phone'],
        ];

        $address = $row['address'] ?? null;

        if (is_string($address) && $address !== '') {
            $shipping['address'] = $address;
        }

        return [
            'id' => (string) $row['id'],
            'status' => (string) $row['status'],
            'items' => array_map(
                static fn (array $item): array => self::item($item),
                $items
            ),
            'shipping' => $shipping,
            'total' => self::money((int) $row['total_rub']),
            'createdAt' => self::dateTime((string) $row['created_at']),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function item(array $row): array
    {
        return [
            'productId' => (string) ($row['product_id'] ?? ''),
            'productName' => (string) $row['product_name'],
            'priceAtPurchase' => self::money((int) $row['price_rub']),
            'quantity' => (int) $row['quantity'],
        ];
    }

    /**
     * @return array{amount: int, currency: string}
     */
    private static function money(int $amount): array
    {
        return [
            'amount' => $amount,
            'currency' => 'RUB',
        ];
    }

    private static function dateTime(string $value): string
    {
        return (new DateTimeImmutable($value))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
