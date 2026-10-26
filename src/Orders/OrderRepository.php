<?php

declare(strict_types=1);

namespace App\Orders;

use App\Config;
use App\Database\Connection;
use PDO;
use RuntimeException;
use Throwable;

final class OrderRepository
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{id: string, name: string, price_rub: int, available: bool}>
     */
    public function findProductsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $statement = $this->pdo()->prepare(
            'SELECT id, name, price_rub, available FROM products'
            . ' WHERE id = ANY(CAST(:ids AS BIGINT[]))'
        );
        $statement->execute(['ids' => self::idArray($ids)]);

        $products = [];

        foreach ($statement->fetchAll() as $row) {
            $products[(int) $row['id']] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'price_rub' => (int) $row['price_rub'],
                'available' => self::boolean($row['available']),
            ];
        }

        return $products;
    }

    /**
     * @param list<array{productId: string, productName: string, priceRub: int, quantity: int}> $items
     * @return array<string, mixed>
     */
    public function create(
        int $userId,
        ShippingDetails $shipping,
        int $totalRub,
        array $items
    ): array {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'INSERT INTO orders'
                . ' (user_id, status, shipping_method, recipient_name, phone, address, total_rub)'
                . ' VALUES'
                . ' (:user_id, :status, :shipping_method, :recipient_name, :phone, :address, :total_rub)'
                . ' RETURNING id, status, shipping_method, recipient_name, phone, address,'
                . ' total_rub, created_at'
            );
            $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $statement->bindValue(':status', 'paid');
            $statement->bindValue(':shipping_method', $shipping->method);
            $statement->bindValue(':recipient_name', $shipping->recipientName);
            $statement->bindValue(':phone', $shipping->phone);

            if ($shipping->address === null) {
                $statement->bindValue(':address', null, PDO::PARAM_NULL);
            } else {
                $statement->bindValue(':address', $shipping->address);
            }

            $statement->bindValue(':total_rub', $totalRub, PDO::PARAM_INT);
            $statement->execute();

            $orderRow = $statement->fetch();

            if ($orderRow === false) {
                throw new RuntimeException('Failed to create order.');
            }

            $orderId = (int) $orderRow['id'];
            $itemStatement = $pdo->prepare(
                'INSERT INTO order_items'
                . ' (order_id, product_id, product_name, price_rub, quantity)'
                . ' VALUES (:order_id, :product_id, :product_name, :price_rub, :quantity)'
            );
            $itemRows = [];

            foreach ($items as $item) {
                $itemStatement->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $itemStatement->bindValue(':product_id', (int) $item['productId'], PDO::PARAM_INT);
                $itemStatement->bindValue(':product_name', $item['productName']);
                $itemStatement->bindValue(':price_rub', $item['priceRub'], PDO::PARAM_INT);
                $itemStatement->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
                $itemStatement->execute();

                $itemRows[] = [
                    'product_id' => $item['productId'],
                    'product_name' => $item['productName'],
                    'price_rub' => $item['priceRub'],
                    'quantity' => $item['quantity'],
                ];
            }

            $order = OrderMapper::order($orderRow, $itemRows);
            $pdo->commit();

            return $order;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ordersForUser(int $userId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, status, shipping_method, recipient_name, phone, address,'
            . ' total_rub, created_at'
            . ' FROM orders WHERE user_id = :user_id'
            . ' ORDER BY created_at DESC, id DESC'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();

        if ($rows === []) {
            return [];
        }

        $orderIds = array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        );
        $itemsByOrder = $this->itemsByOrderIds($orderIds);

        return array_map(
            static fn (array $row): array => OrderMapper::order(
                $row,
                $itemsByOrder[(int) $row['id']] ?? []
            ),
            $rows
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function orderForUser(int $userId, string $id): ?array
    {
        if (!ctype_digit($id) || strlen($id) > 18) {
            return null;
        }

        $orderId = (int) $id;
        $statement = $this->pdo()->prepare(
            'SELECT id, status, shipping_method, recipient_name, phone, address,'
            . ' total_rub, created_at'
            . ' FROM orders WHERE id = :id AND user_id = :user_id'
        );
        $statement->bindValue(':id', $orderId, PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $itemsByOrder = $this->itemsByOrderIds([$orderId]);

        return OrderMapper::order($row, $itemsByOrder[$orderId] ?? []);
    }

    /**
     * @param list<int> $orderIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function itemsByOrderIds(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $statement = $this->pdo()->prepare(
            'SELECT order_id, product_id, product_name, price_rub, quantity'
            . ' FROM order_items WHERE order_id = ANY(CAST(:ids AS BIGINT[]))'
            . ' ORDER BY order_id, id'
        );
        $statement->execute(['ids' => self::idArray($orderIds)]);

        $itemsByOrder = [];

        foreach ($statement->fetchAll() as $row) {
            $itemsByOrder[(int) $row['order_id']][] = $row;
        }

        return $itemsByOrder;
    }

    /**
     * @param non-empty-list<int> $ids
     */
    private static function idArray(array $ids): string
    {
        return '{' . implode(',', $ids) . '}';
    }

    private static function boolean(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't';
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Connection::fromConfig($this->config);
    }
}
