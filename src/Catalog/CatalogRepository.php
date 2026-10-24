<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Config;
use App\Database\Connection;
use PDO;

final class CatalogRepository
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        $rows = $this->pdo()
            ->query('SELECT id, slug, name FROM categories ORDER BY id')
            ->fetchAll();

        return array_map(
            static fn (array $row): array => CatalogMapper::category($row),
            $rows
        );
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     meta: array{page: int, perPage: int, total: int, totalPages: int}
     * }
     */
    public function search(CatalogQuery $query): array
    {
        $where = [];
        $parameters = [];

        if ($query->category !== null) {
            $where[] = 'c.slug = :category';
            $parameters['category'] = $query->category;
        }

        if ($query->search !== null) {
            $where[] = 'POSITION(lower(:search) IN lower(p.name)) > 0';
            $parameters['search'] = $query->search;
        }

        if ($query->priceMin !== null) {
            $where[] = 'p.price_rub >= :price_min';
            $parameters['price_min'] = $query->priceMin;
        }

        if ($query->priceMax !== null) {
            $where[] = 'p.price_rub <= :price_max';
            $parameters['price_max'] = $query->priceMax;
        }

        if ($query->available === true) {
            $where[] = 'p.available = TRUE';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $pdo = $this->pdo();

        $countStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM products p'
            . ' JOIN categories c ON c.id = p.category_id'
            . $whereSql
        );
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();

        $offset = ($query->page - 1) * $query->perPage;
        $statement = $pdo->prepare(
            'SELECT p.id, p.slug, p.name, p.description, p.price_rub,'
            . ' p.category_id, p.image_url, p.available'
            . ' FROM products p'
            . ' JOIN categories c ON c.id = p.category_id'
            . $whereSql
            . ' ORDER BY p.id LIMIT :limit OFFSET :offset'
        );

        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':limit', $query->perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $items = array_map(
            static fn (array $row): array => CatalogMapper::product($row),
            $statement->fetchAll()
        );

        return [
            'items' => $items,
            'meta' => [
                'page' => $query->page,
                'perPage' => $query->perPage,
                'total' => $total,
                'totalPages' => $total === 0 ? 0 : (int) ceil($total / $query->perPage),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function product(string $id): ?array
    {
        if (!ctype_digit($id) || strlen($id) > 18) {
            return null;
        }

        $statement = $this->pdo()->prepare(
            'SELECT id, slug, name, description, price_rub, category_id, image_url, available'
            . ' FROM products WHERE id = :id'
        );
        $statement->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();

        return $row === false ? null : CatalogMapper::product($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function promos(): array
    {
        $rows = $this->pdo()->query(
            'SELECT pr.id AS promo_id, pr.title, pr.body,'
            . ' p.id AS product_id, p.slug AS product_slug, p.name AS product_name,'
            . ' p.description AS product_description, p.price_rub AS product_price_rub,'
            . ' p.category_id AS product_category_id, p.image_url AS product_image_url,'
            . ' p.available AS product_available'
            . ' FROM promos pr'
            . ' JOIN products p ON p.id = pr.product_id'
            . ' ORDER BY pr.id'
        )->fetchAll();

        return array_map(
            static fn (array $row): array => CatalogMapper::promo($row),
            $rows
        );
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Connection::fromConfig($this->config);
    }
}
