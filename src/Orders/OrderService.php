<?php

declare(strict_types=1);

namespace App\Orders;

use InvalidArgumentException;

final class OrderService
{
    private const MAX_INT = 2147483647;

    public function __construct(private readonly OrderRepository $orders)
    {
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws UnavailableItemsException
     */
    public function create(int $userId, CreateOrder $request): array
    {
        $quantities = [];
        $positions = [];
        $seenInvalid = [];

        foreach ($request->items as $item) {
            $productId = $item['productId'];
            $canonical = self::canonicalProductId($productId);

            if ($canonical === null) {
                if (!isset($seenInvalid[$productId])) {
                    $seenInvalid[$productId] = true;
                    $positions[] = ['key' => null, 'productId' => $productId];
                }

                continue;
            }

            if (!isset($quantities[$canonical])) {
                $quantities[$canonical] = 0;
                $positions[] = ['key' => $canonical, 'productId' => (string) $canonical];
            }

            $quantities[$canonical] += $item['quantity'];

            if ($quantities[$canonical] > self::MAX_INT) {
                throw new InvalidArgumentException('Quantity is too large.');
            }
        }

        $products = $this->orders->findProductsByIds(array_keys($quantities));
        $unavailable = [];

        foreach ($positions as $position) {
            $key = $position['key'];

            if ($key === null) {
                $unavailable[] = $position['productId'];
                continue;
            }

            $product = $products[$key] ?? null;

            if ($product === null || !$product['available']) {
                $unavailable[] = $position['productId'];
            }
        }

        if ($unavailable !== []) {
            throw new UnavailableItemsException($unavailable);
        }

        $lines = [];
        $total = 0;

        foreach ($positions as $position) {
            $key = $position['key'];

            if ($key === null) {
                continue;
            }

            $product = $products[$key];
            $quantity = $quantities[$key];
            $lineTotal = $product['price_rub'] * $quantity;

            if ($lineTotal > self::MAX_INT) {
                throw new InvalidArgumentException('Order total is too large.');
            }

            $total += $lineTotal;

            if ($total > self::MAX_INT) {
                throw new InvalidArgumentException('Order total is too large.');
            }

            $lines[] = [
                'productId' => $product['id'],
                'productName' => $product['name'],
                'priceRub' => $product['price_rub'],
                'quantity' => $quantity,
            ];
        }

        return $this->orders->create($userId, $request->shipping, $total, $lines);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(int $userId): array
    {
        return $this->orders->ordersForUser($userId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $userId, string $id): ?array
    {
        return $this->orders->orderForUser($userId, $id);
    }

    private static function canonicalProductId(string $productId): ?int
    {
        if (
            $productId === ''
            || strlen($productId) > 18
            || !ctype_digit($productId)
        ) {
            return null;
        }

        return (int) $productId;
    }
}
