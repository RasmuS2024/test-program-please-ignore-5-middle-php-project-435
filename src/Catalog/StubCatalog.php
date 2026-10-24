<?php

declare(strict_types=1);

namespace App\Catalog;

use InvalidArgumentException;

/**
 * Temporary in-memory catalog used until the database layer is implemented.
 *
 * The data intentionally covers every category, both availability states and
 * more than one page, so the frontend can be exercised end to end.
 */
final class StubCatalog
{
    /**
     * @return list<array{id: string, slug: string, name: string}>
     */
    public static function categories(): array
    {
        return [
            ['id' => '1', 'slug' => 'videokarty', 'name' => 'Видеокарты'],
            ['id' => '2', 'slug' => 'processory', 'name' => 'Процессоры'],
            ['id' => '3', 'slug' => 'materinskie-platy', 'name' => 'Материнские платы'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function products(): array
    {
        return [
            [
                'id' => '1',
                'slug' => 'nvidia-geforce-rtx-4090',
                'name' => 'NVIDIA GeForce RTX 4090',
                'description' => 'Флагманская видеокарта для 4K-гейминга и работы с нейросетями.',
                'price' => ['amount' => 199990, 'currency' => 'RUB'],
                'categoryId' => '1',
                'imageUrl' => '/images/nvidia-geforce-rtx-4090.svg',
                'available' => true,
            ],
            [
                'id' => '2',
                'slug' => 'nvidia-geforce-rtx-4070',
                'name' => 'NVIDIA GeForce RTX 4070',
                'description' => 'Видеокарта с поддержкой DLSS 3 для игр в 1440p.',
                'price' => ['amount' => 59990, 'currency' => 'RUB'],
                'categoryId' => '1',
                'imageUrl' => '/images/nvidia-geforce-rtx-4070.svg',
                'available' => true,
            ],
            [
                'id' => '3',
                'slug' => 'nvidia-geforce-rtx-4060-ti',
                'name' => 'NVIDIA GeForce RTX 4060 Ti',
                'description' => 'Энергоэффективная видеокарта для Full HD и 2K.',
                'price' => ['amount' => 39990, 'currency' => 'RUB'],
                'categoryId' => '1',
                'imageUrl' => '/images/nvidia-geforce-rtx-4060-ti.svg',
                'available' => true,
            ],
            [
                'id' => '4',
                'slug' => 'amd-radeon-rx-7800-xt',
                'name' => 'AMD Radeon RX 7800 XT',
                'description' => 'Производительная видеокарта с большим объёмом видеопамяти.',
                'price' => ['amount' => 54990, 'currency' => 'RUB'],
                'categoryId' => '1',
                'imageUrl' => '/images/amd-radeon-rx-7800-xt.svg',
                'available' => true,
            ],
            [
                'id' => '5',
                'slug' => 'amd-ryzen-9-7950x',
                'name' => 'AMD Ryzen 9 7950X',
                'description' => '16-ядерный процессор для требовательных рабочих задач.',
                'price' => ['amount' => 49990, 'currency' => 'RUB'],
                'categoryId' => '2',
                'imageUrl' => '/images/amd-ryzen-9-7950x.svg',
                'available' => true,
            ],
            [
                'id' => '6',
                'slug' => 'amd-ryzen-7-7800x3d',
                'name' => 'AMD Ryzen 7 7800X3D',
                'description' => 'Игровой процессор с дополнительным кешем 3D V-Cache.',
                'price' => ['amount' => 34990, 'currency' => 'RUB'],
                'categoryId' => '2',
                'imageUrl' => '/images/amd-ryzen-7-7800x3d.svg',
                'available' => true,
            ],
            [
                'id' => '7',
                'slug' => 'amd-ryzen-5-7600x',
                'name' => 'AMD Ryzen 5 7600X',
                'description' => 'Шестиядерный процессор для сбалансированной сборки.',
                'price' => ['amount' => 24990, 'currency' => 'RUB'],
                'categoryId' => '2',
                'imageUrl' => '/images/amd-ryzen-5-7600x.svg',
                'available' => true,
            ],
            [
                'id' => '8',
                'slug' => 'intel-core-i7-14700k',
                'name' => 'Intel Core i7-14700K',
                'description' => '20-ядерный процессор для игр и многопоточных задач.',
                'price' => ['amount' => 39990, 'currency' => 'RUB'],
                'categoryId' => '2',
                'imageUrl' => '/images/intel-core-i7-14700k.svg',
                'available' => true,
            ],
            [
                'id' => '9',
                'slug' => 'asus-prime-b650m-a',
                'name' => 'ASUS Prime B650M-A',
                'description' => 'Компактная материнская плата для процессоров AMD AM5.',
                'price' => ['amount' => 14990, 'currency' => 'RUB'],
                'categoryId' => '3',
                'imageUrl' => '/images/asus-prime-b650m-a.svg',
                'available' => true,
            ],
            [
                'id' => '10',
                'slug' => 'msi-mag-b760-tomahawk',
                'name' => 'MSI MAG B760 Tomahawk',
                'description' => 'Материнская плата с усиленной цепью питания и Wi-Fi.',
                'price' => ['amount' => 17990, 'currency' => 'RUB'],
                'categoryId' => '3',
                'imageUrl' => '/images/msi-mag-b760-tomahawk.svg',
                'available' => false,
            ],
            [
                'id' => '11',
                'slug' => 'gigabyte-x670-aorus-elite',
                'name' => 'Gigabyte X670 Aorus Elite',
                'description' => 'Материнская плата для флагманских сборок на AM5.',
                'price' => ['amount' => 24990, 'currency' => 'RUB'],
                'categoryId' => '3',
                'imageUrl' => '/images/gigabyte-x670-aorus-elite.svg',
                'available' => true,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function promos(): array
    {
        $products = self::productsById();

        return [
            [
                'id' => 'promo-1',
                'title' => 'Флагман нового поколения',
                'text' => 'RTX 4090 для 4K-гейминга и нейросетей.',
                'product' => $products['1'],
            ],
            [
                'id' => 'promo-2',
                'title' => 'Игровой процессор',
                'text' => 'Ryzen 9 7950X для самых требовательных задач.',
                'product' => $products['5'],
            ],
            [
                'id' => 'promo-3',
                'title' => 'Соберите систему на AM5',
                'text' => 'ASUS Prime B650M-A — надёжная основа новой сборки.',
                'product' => $products['9'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function product(string $id): ?array
    {
        return self::productsById()[$id] ?? null;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     meta: array{page: int, perPage: int, total: int, totalPages: int}
     * }
     */
    public static function search(array $query): array
    {
        $category = self::stringParameter($query, 'category');
        $search = self::stringParameter($query, 'q');
        $priceMin = self::optionalIntegerParameter($query, 'priceMin');
        $priceMax = self::optionalIntegerParameter($query, 'priceMax');
        $available = self::optionalBooleanParameter($query, 'available');
        $page = self::integerParameter($query, 'page', 1, 1);
        $perPage = self::integerParameter($query, 'perPage', 6, 1, 48);

        $categoryId = null;
        if ($category !== null) {
            $categoryId = self::categoryIdBySlug($category);
        }

        $items = array_values(array_filter(
            self::products(),
            static function (array $product) use (
                $categoryId,
                $search,
                $priceMin,
                $priceMax,
                $available
            ): bool {
                if ($categoryId !== null && $product['categoryId'] !== $categoryId) {
                    return false;
                }

                if ($search !== null && !self::contains($product['name'], $search)) {
                    return false;
                }

                if ($priceMin !== null && $product['price']['amount'] < $priceMin) {
                    return false;
                }

                if ($priceMax !== null && $product['price']['amount'] > $priceMax) {
                    return false;
                }

                if ($available === true && $product['available'] !== true) {
                    return false;
                }

                return true;
            }
        ));

        if ($category !== null && $categoryId === null) {
            $items = [];
        }

        $total = count($items);
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function productsById(): array
    {
        $products = [];

        foreach (self::products() as $product) {
            $products[$product['id']] = $product;
        }

        return $products;
    }

    private static function categoryIdBySlug(string $slug): ?string
    {
        foreach (self::categories() as $category) {
            if ($category['slug'] === $slug) {
                return $category['id'];
            }
        }

        return null;
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

    private static function contains(string $haystack, string $needle): bool
    {
        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle) !== false;
        }

        return stripos($haystack, $needle) !== false;
    }
}
