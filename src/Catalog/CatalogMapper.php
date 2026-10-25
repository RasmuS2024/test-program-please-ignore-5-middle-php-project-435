<?php

declare(strict_types=1);

namespace App\Catalog;

final class CatalogMapper
{
    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function category(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function product(array $row): array
    {
        $product = [
            'id' => (string) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'description' => (string) $row['description'],
            'price' => [
                'amount' => (int) $row['price_rub'],
                'currency' => 'RUB',
            ],
            'categoryId' => (string) $row['category_id'],
            'available' => self::boolean($row['available']),
        ];

        $imageUrl = $row['image_url'] ?? null;

        if (is_string($imageUrl) && $imageUrl !== '') {
            $product['imageUrl'] = $imageUrl;
        }

        return $product;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function promo(array $row): array
    {
        $product = self::product([
            'id' => $row['product_id'],
            'slug' => $row['product_slug'],
            'name' => $row['product_name'],
            'description' => $row['product_description'],
            'price_rub' => $row['product_price_rub'],
            'category_id' => $row['product_category_id'],
            'image_url' => $row['product_image_url'] ?? null,
            'available' => $row['product_available'],
        ]);

        return [
            'id' => (string) $row['promo_id'],
            'title' => (string) $row['title'],
            'text' => (string) $row['body'],
            'product' => $product,
        ];
    }

    private static function boolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }
}
