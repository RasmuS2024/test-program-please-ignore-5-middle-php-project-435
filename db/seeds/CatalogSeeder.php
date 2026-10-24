<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use PDO;
use Phinx\Seed\AbstractSeed;
use RuntimeException;

final class CatalogSeeder extends AbstractSeed
{
    private const CATEGORIES = [
        ['slug' => 'videokarty', 'name' => 'Видеокарты'],
        ['slug' => 'processory', 'name' => 'Процессоры'],
        ['slug' => 'materinskie-platy', 'name' => 'Материнские платы'],
    ];

    private const PRODUCTS = [
        [
            'slug' => 'nvidia-geforce-rtx-4090',
            'name' => 'NVIDIA GeForce RTX 4090',
            'description' => 'Флагманская видеокарта для 4K-гейминга и работы с нейросетями.',
            'price_rub' => 199990,
            'category' => 'videokarty',
            'image_url' => '/images/nvidia-geforce-rtx-4090.svg',
            'available' => true,
        ],
        [
            'slug' => 'nvidia-geforce-rtx-4070',
            'name' => 'NVIDIA GeForce RTX 4070',
            'description' => 'Видеокарта с поддержкой DLSS 3 для игр в 1440p.',
            'price_rub' => 59990,
            'category' => 'videokarty',
            'image_url' => '/images/nvidia-geforce-rtx-4070.svg',
            'available' => true,
        ],
        [
            'slug' => 'nvidia-geforce-rtx-4060-ti',
            'name' => 'NVIDIA GeForce RTX 4060 Ti',
            'description' => 'Энергоэффективная видеокарта для Full HD и 2K.',
            'price_rub' => 39990,
            'category' => 'videokarty',
            'image_url' => '/images/nvidia-geforce-rtx-4060-ti.svg',
            'available' => true,
        ],
        [
            'slug' => 'amd-radeon-rx-7800-xt',
            'name' => 'AMD Radeon RX 7800 XT',
            'description' => 'Производительная видеокарта с большим объёмом видеопамяти.',
            'price_rub' => 54990,
            'category' => 'videokarty',
            'image_url' => '/images/amd-radeon-rx-7800-xt.svg',
            'available' => true,
        ],
        [
            'slug' => 'amd-ryzen-9-7950x',
            'name' => 'AMD Ryzen 9 7950X',
            'description' => '16-ядерный процессор для требовательных рабочих задач.',
            'price_rub' => 49990,
            'category' => 'processory',
            'image_url' => '/images/amd-ryzen-9-7950x.svg',
            'available' => true,
        ],
        [
            'slug' => 'amd-ryzen-7-7800x3d',
            'name' => 'AMD Ryzen 7 7800X3D',
            'description' => 'Игровой процессор с дополнительным кешем 3D V-Cache.',
            'price_rub' => 34990,
            'category' => 'processory',
            'image_url' => '/images/amd-ryzen-7-7800x3d.svg',
            'available' => true,
        ],
        [
            'slug' => 'amd-ryzen-5-7600x',
            'name' => 'AMD Ryzen 5 7600X',
            'description' => 'Шестиядерный процессор для сбалансированной сборки.',
            'price_rub' => 24990,
            'category' => 'processory',
            'image_url' => '/images/amd-ryzen-5-7600x.svg',
            'available' => true,
        ],
        [
            'slug' => 'intel-core-i7-14700k',
            'name' => 'Intel Core i7-14700K',
            'description' => '20-ядерный процессор для игр и многопоточных задач.',
            'price_rub' => 39990,
            'category' => 'processory',
            'image_url' => '/images/intel-core-i7-14700k.svg',
            'available' => true,
        ],
        [
            'slug' => 'asus-prime-b650m-a',
            'name' => 'ASUS Prime B650M-A',
            'description' => 'Компактная материнская плата для процессоров AMD AM5.',
            'price_rub' => 14990,
            'category' => 'materinskie-platy',
            'image_url' => '/images/asus-prime-b650m-a.svg',
            'available' => true,
        ],
        [
            'slug' => 'msi-mag-b760-tomahawk',
            'name' => 'MSI MAG B760 Tomahawk',
            'description' => 'Материнская плата с усиленной цепью питания и Wi-Fi.',
            'price_rub' => 17990,
            'category' => 'materinskie-platy',
            'image_url' => '/images/msi-mag-b760-tomahawk.svg',
            'available' => false,
        ],
        [
            'slug' => 'gigabyte-x670-aorus-elite',
            'name' => 'Gigabyte X670 Aorus Elite',
            'description' => 'Материнская плата для флагманских сборок на AM5.',
            'price_rub' => 24990,
            'category' => 'materinskie-platy',
            'image_url' => '/images/gigabyte-x670-aorus-elite.svg',
            'available' => true,
        ],
        [
            'slug' => 'asrock-b650m-hdv-m2',
            'name' => 'ASRock B650M-HDV/M.2',
            'description' => 'Доступная материнская плата для сборки на AMD AM5.',
            'price_rub' => 7990,
            'category' => 'materinskie-platy',
            'image_url' => null,
            'available' => true,
        ],
    ];

    private const PROMOS = [
        [
            'slug' => 'promo-1',
            'title' => 'Флагман нового поколения',
            'body' => 'RTX 4090 для 4K-гейминга и нейросетей.',
            'product' => 'nvidia-geforce-rtx-4090',
        ],
        [
            'slug' => 'promo-2',
            'title' => 'Игровой процессор',
            'body' => 'Ryzen 9 7950X для самых требовательных задач.',
            'product' => 'amd-ryzen-9-7950x',
        ],
        [
            'slug' => 'promo-3',
            'title' => 'Соберите систему на AM5',
            'body' => 'ASUS Prime B650M-A — надёжная основа новой сборки.',
            'product' => 'asus-prime-b650m-a',
        ],
    ];

    public function run(): void
    {
        $pdo = $this->getAdapter()->getConnection();
        $categoryIds = $this->seedCategories($pdo);
        $productIds = $this->seedProducts($pdo, $categoryIds);
        $this->seedPromos($pdo, $productIds);
    }

    /**
     * @return array<string, int>
     */
    private function seedCategories(PDO $pdo): array
    {
        $statement = $pdo->prepare(
            'INSERT INTO categories (slug, name) VALUES (:slug, :name)'
            . ' ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name'
            . ' RETURNING id'
        );
        $ids = [];

        foreach (self::CATEGORIES as $category) {
            $statement->execute([
                'slug' => $category['slug'],
                'name' => $category['name'],
            ]);

            $id = $statement->fetchColumn();
            $statement->closeCursor();

            if ($id === false) {
                throw new RuntimeException('Failed to seed category ' . $category['slug']);
            }

            $ids[$category['slug']] = (int) $id;
        }

        return $ids;
    }

    /**
     * @param array<string, int> $categoryIds
     *
     * @return array<string, int>
     */
    private function seedProducts(PDO $pdo, array $categoryIds): array
    {
        $statement = $pdo->prepare(
            'INSERT INTO products'
            . ' (slug, name, description, price_rub, category_id, image_url, available)'
            . ' VALUES'
            . ' (:slug, :name, :description, :price_rub, :category_id, :image_url, :available)'
            . ' ON CONFLICT (slug) DO UPDATE SET'
            . ' name = EXCLUDED.name,'
            . ' description = EXCLUDED.description,'
            . ' price_rub = EXCLUDED.price_rub,'
            . ' category_id = EXCLUDED.category_id,'
            . ' image_url = EXCLUDED.image_url,'
            . ' available = EXCLUDED.available'
            . ' RETURNING id'
        );
        $ids = [];

        foreach (self::PRODUCTS as $product) {
            $categoryId = $categoryIds[$product['category']] ?? null;

            if ($categoryId === null) {
                throw new RuntimeException('Unknown category ' . $product['category']);
            }

            $statement->bindValue(':slug', $product['slug']);
            $statement->bindValue(':name', $product['name']);
            $statement->bindValue(':description', $product['description']);
            $statement->bindValue(':price_rub', $product['price_rub'], PDO::PARAM_INT);
            $statement->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $statement->bindValue(
                ':image_url',
                $product['image_url'],
                $product['image_url'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
            );
            $statement->bindValue(':available', $product['available'], PDO::PARAM_BOOL);
            $statement->execute();

            $id = $statement->fetchColumn();
            $statement->closeCursor();

            if ($id === false) {
                throw new RuntimeException('Failed to seed product ' . $product['slug']);
            }

            $ids[$product['slug']] = (int) $id;
        }

        return $ids;
    }

    /**
     * @param array<string, int> $productIds
     */
    private function seedPromos(PDO $pdo, array $productIds): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO promos (slug, title, body, product_id)'
            . ' VALUES (:slug, :title, :body, :product_id)'
            . ' ON CONFLICT (slug) DO UPDATE SET'
            . ' title = EXCLUDED.title,'
            . ' body = EXCLUDED.body,'
            . ' product_id = EXCLUDED.product_id'
        );

        foreach (self::PROMOS as $promo) {
            $productId = $productIds[$promo['product']] ?? null;

            if ($productId === null) {
                throw new RuntimeException('Unknown promo product ' . $promo['product']);
            }

            $statement->bindValue(':slug', $promo['slug']);
            $statement->bindValue(':title', $promo['title']);
            $statement->bindValue(':body', $promo['body']);
            $statement->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $statement->execute();
        }
    }
}
