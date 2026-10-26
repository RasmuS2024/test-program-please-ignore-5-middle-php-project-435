<?php

declare(strict_types=1);

namespace App\Orders;

use InvalidArgumentException;

final class CreateOrder
{
    private const MAX_QUANTITY = 2147483647;

    /**
     * @param list<array{productId: string, quantity: int}> $items
     */
    private function __construct(
        public readonly array $items,
        public readonly ShippingDetails $shipping,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws AddressRequiredException
     */
    public static function fromParsedBody(mixed $body): self
    {
        if (!is_array($body) || array_is_list($body)) {
            throw new InvalidArgumentException('Request body must be a JSON object.');
        }

        $items = self::items($body['items'] ?? null);
        $shipping = self::shipping($body['shipping'] ?? null);

        return new self($items, $shipping);
    }

    /**
     * @return list<array{productId: string, quantity: int}>
     */
    private static function items(mixed $items): array
    {
        if (!is_array($items) || !array_is_list($items) || $items === []) {
            throw new InvalidArgumentException('Items must be a non-empty array.');
        }

        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Each item must be an object.');
            }

            $productId = $item['productId'] ?? null;
            $quantity = $item['quantity'] ?? null;

            if (!is_string($productId)) {
                throw new InvalidArgumentException('Product id must be a string.');
            }

            if (
                !is_int($quantity)
                || $quantity < 1
                || $quantity > self::MAX_QUANTITY
            ) {
                throw new InvalidArgumentException('Quantity must be a positive integer.');
            }

            $result[] = [
                'productId' => $productId,
                'quantity' => $quantity,
            ];
        }

        return $result;
    }

    /**
     * @throws InvalidArgumentException
     * @throws AddressRequiredException
     */
    private static function shipping(mixed $shipping): ShippingDetails
    {
        if (!is_array($shipping) || array_is_list($shipping)) {
            throw new InvalidArgumentException('Shipping must be an object.');
        }

        $method = $shipping['method'] ?? null;
        $recipientName = $shipping['recipientName'] ?? null;
        $phone = $shipping['phone'] ?? null;
        $rawAddress = $shipping['address'] ?? null;

        if (!is_string($method) || !in_array($method, ['delivery', 'pickup'], true)) {
            throw new InvalidArgumentException('Shipping method is invalid.');
        }

        if (!is_string($recipientName) || trim($recipientName) === '') {
            throw new InvalidArgumentException('Recipient name is required.');
        }

        if (!is_string($phone) || trim($phone) === '') {
            throw new InvalidArgumentException('Phone is required.');
        }

        if ($rawAddress !== null && !is_string($rawAddress)) {
            throw new InvalidArgumentException('Address must be a string.');
        }

        $address = is_string($rawAddress) ? trim($rawAddress) : null;

        if ($method === 'delivery' && ($address === null || $address === '')) {
            throw new AddressRequiredException();
        }

        if ($method === 'pickup') {
            $address = null;
        }

        return new ShippingDetails(
            method: $method,
            recipientName: trim($recipientName),
            phone: trim($phone),
            address: $address,
        );
    }
}
