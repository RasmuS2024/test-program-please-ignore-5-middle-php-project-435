<?php

declare(strict_types=1);

namespace App\Tests;

use App\AppFactory;
use App\Config;
use App\Database\Connection;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use PHPUnit\Framework\TestCase;

final class ApiRoutesTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $databaseUrl = getenv('DATABASE_URL');

        if (!is_string($databaseUrl) || $databaseUrl === '') {
            self::fail('DATABASE_URL is required. Run make db-setup before the API tests.');
        }

        $this->app = AppFactory::create(new Config(8080, $databaseUrl));
    }

    public function testCategoriesAreReturned(): void
    {
        $response = $this->get('/api/categories');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = $this->decode($response);
        self::assertCount(3, $body);
        self::assertSame('videokarty', $body[0]['slug']);
    }

    public function testProductListSupportsCategoryAndAvailabilityFilters(): void
    {
        $categoryId = $this->categoryId('videokarty');
        $response = $this->get('/api/products?category=videokarty&available=true');
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(4, $body['meta']['total']);
        self::assertSame(1, $body['meta']['totalPages']);

        foreach ($body['items'] as $product) {
            self::assertSame($categoryId, $product['categoryId']);
            self::assertTrue($product['available']);
        }
    }

    public function testProductListSupportsSearchAndPagination(): void
    {
        $searchResponse = $this->get('/api/products?q=rtx');
        $searchBody = $this->decode($searchResponse);

        self::assertSame(3, $searchBody['meta']['total']);

        $pageResponse = $this->get('/api/products?page=2');
        $pageBody = $this->decode($pageResponse);

        self::assertSame(2, $pageBody['meta']['page']);
        self::assertSame(12, $pageBody['meta']['total']);
        self::assertSame(2, $pageBody['meta']['totalPages']);
        self::assertCount(6, $pageBody['items']);
    }

    public function testProductListSupportsPriceFilter(): void
    {
        $response = $this->get('/api/products?priceMin=20000&priceMax=50000&perPage=48');
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());

        foreach ($body['items'] as $product) {
            self::assertGreaterThanOrEqual(20000, $product['price']['amount']);
            self::assertLessThanOrEqual(50000, $product['price']['amount']);
        }
    }

    public function testProductListReturnsDefaultPageMetaAndProductShape(): void
    {
        $response = $this->get('/api/products');
        $body = $this->decode($response);
        $all = $this->decode($this->get('/api/products?perPage=48'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(1, $body['meta']['page']);
        self::assertSame(6, $body['meta']['perPage']);
        self::assertSame($all['meta']['total'], $body['meta']['total']);
        self::assertSame(
            (int) ceil($body['meta']['total'] / $body['meta']['perPage']),
            $body['meta']['totalPages']
        );
        self::assertCount(min(6, $body['meta']['total']), $body['items']);
        self::assertCount($all['meta']['total'], $all['items']);

        foreach ($body['items'] as $product) {
            $this->assertProductShape($product);
        }
    }

    public function testEmptyProductFiltersAreIgnored(): void
    {
        $default = $this->decode($this->get('/api/products'));
        $empty = $this->decode(
            $this->get('/api/products?category=&q=&priceMin=&priceMax=&available=&page=&perPage=')
        );

        self::assertSame($default, $empty);
    }

    public function testProductSearchIsCaseInsensitiveAndMatchesNameOnly(): void
    {
        $lower = $this->decode($this->get('/api/products?q=rtx&perPage=48'));
        $upper = $this->decode($this->get('/api/products?q=RTX&perPage=48'));
        $trimmed = $this->decode($this->get('/api/products?q=%20RTX%20&perPage=48'));

        self::assertGreaterThan(0, $lower['meta']['total']);
        self::assertSame($lower['meta']['total'], $upper['meta']['total']);
        self::assertSame($lower['items'], $upper['items']);
        self::assertSame($lower['items'], $trimmed['items']);

        foreach ($lower['items'] as $product) {
            self::assertStringContainsStringIgnoringCase('rtx', $product['name']);
        }

        $descriptionOnly = $this->decode($this->get('/api/products?q=DLSS&perPage=48'));
        self::assertSame([], $descriptionOnly['items']);
        self::assertSame(0, $descriptionOnly['meta']['total']);
    }

    public function testProductPriceBoundsAreInclusiveAndIndependent(): void
    {
        $minimum = $this->decode($this->get('/api/products?priceMin=199990&perPage=48'));
        self::assertSame(1, $minimum['meta']['total']);
        self::assertSame('nvidia-geforce-rtx-4090', $minimum['items'][0]['slug']);

        $maximum = $this->decode($this->get('/api/products?priceMax=7990&perPage=48'));
        self::assertSame(1, $maximum['meta']['total']);
        self::assertSame('asrock-b650m-hdv-m2', $maximum['items'][0]['slug']);

        $exact = $this->decode(
            $this->get('/api/products?priceMin=39990&priceMax=39990&perPage=48')
        );
        self::assertSame(2, $exact['meta']['total']);

        foreach ($exact['items'] as $product) {
            self::assertSame(39990, $product['price']['amount']);
        }
    }

    public function testProductFiltersCanBeCombined(): void
    {
        $categoryId = $this->categoryId('videokarty');
        $body = $this->decode(
            $this->get(
                '/api/products?category=videokarty&q=rtx'
                . '&priceMin=39990&priceMax=59990&available=true&perPage=48'
            )
        );

        self::assertSame(2, $body['meta']['total']);
        self::assertCount(2, $body['items']);

        foreach ($body['items'] as $product) {
            self::assertSame($categoryId, $product['categoryId']);
            self::assertStringContainsStringIgnoringCase('rtx', $product['name']);
            self::assertGreaterThanOrEqual(39990, $product['price']['amount']);
            self::assertLessThanOrEqual(59990, $product['price']['amount']);
            self::assertTrue($product['available']);
        }
    }

    public function testProductPaginationDoesNotOverlapAndHandlesPageBeyondLast(): void
    {
        $first = $this->decode($this->get('/api/products'));
        $total = $first['meta']['total'];
        $perPage = $first['meta']['perPage'];
        $totalPages = $first['meta']['totalPages'];

        self::assertSame(1, $first['meta']['page']);
        self::assertGreaterThan(1, $totalPages);
        self::assertSame((int) ceil($total / $perPage), $totalPages);

        $seen = [];
        $previousId = 0;

        for ($page = 1; $page <= $totalPages; $page++) {
            $body = $this->decode($this->get('/api/products?page=' . $page));

            self::assertSame($page, $body['meta']['page']);
            self::assertSame($total, $body['meta']['total']);
            self::assertLessThanOrEqual($perPage, count($body['items']));

            foreach ($body['items'] as $product) {
                $id = (int) $product['id'];
                self::assertGreaterThan($previousId, $id);
                self::assertArrayNotHasKey($product['id'], $seen);
                $seen[$product['id']] = true;
                $previousId = $id;
            }
        }

        self::assertCount($total, $seen);

        $beyond = $this->decode($this->get('/api/products?page=' . ($totalPages + 1)));

        self::assertSame([], $beyond['items']);
        self::assertSame($totalPages + 1, $beyond['meta']['page']);
        self::assertSame($total, $beyond['meta']['total']);
        self::assertSame($totalPages, $beyond['meta']['totalPages']);
    }

    public function testProductListRespectsPerPageMaximum(): void
    {
        $all = $this->decode($this->get('/api/products?perPage=48'));

        self::assertSame(48, $all['meta']['perPage']);
        self::assertSame(1, $all['meta']['totalPages']);
        self::assertCount($all['meta']['total'], $all['items']);

        $response = $this->get('/api/products?perPage=49');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            'https://hexlet.io/problems/validation-error',
            $this->decode($response)['type']
        );
    }

    public function testProductListRejectsInvalidFilters(): void
    {
        $uris = [
            '/api/products?page=0',
            '/api/products?page=abc',
            '/api/products?page=1.5',
            '/api/products?perPage=0',
            '/api/products?perPage=49',
            '/api/products?perPage=abc',
            '/api/products?priceMin=abc',
            '/api/products?priceMin=1.5',
            '/api/products?priceMax=-1',
            '/api/products?available=yes',
            '/api/products?available=2',
            '/api/products?q[]=rtx',
            '/api/products?category[]=videokarty',
        ];

        foreach ($uris as $uri) {
            $response = $this->get($uri);

            self::assertSame(400, $response->getStatusCode(), $uri);
            self::assertSame('application/json', $response->getHeaderLine('Content-Type'), $uri);
            self::assertSame(
                'https://hexlet.io/problems/validation-error',
                $this->decode($response)['type'],
                $uri
            );
        }
    }

    public function testUnknownCategoryReturnsEmptyPage(): void
    {
        $response = $this->get('/api/products?category=unknown');
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $body['items']);
        self::assertSame(0, $body['meta']['total']);
        self::assertSame(0, $body['meta']['totalPages']);
    }

    public function testProductByIdIsReturned(): void
    {
        $list = $this->decode($this->get('/api/products?perPage=48'));
        self::assertNotEmpty($list['items']);

        $expected = $list['items'][0];
        $response = $this->get('/api/products/' . $expected['id']);
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($expected, $body);
        self::assertArrayHasKey('imageUrl', $body);
        $this->assertProductShape($body);
    }

    public function testProductByIdReturnsUnavailableProduct(): void
    {
        $list = $this->decode($this->get('/api/products?perPage=48'));
        $expected = $this->findProduct($list, 'msi-mag-b760-tomahawk');
        $body = $this->decode($this->get('/api/products/' . $expected['id']));

        self::assertFalse($body['available']);
        self::assertSame($expected, $body);
        $this->assertProductShape($body);
    }

    public function testProductWithoutImageOmitsImageUrl(): void
    {
        $list = $this->decode($this->get('/api/products?perPage=48'));
        $product = $this->findProduct($list, 'asrock-b650m-hdv-m2');

        self::assertArrayNotHasKey('imageUrl', $product);
    }

    public function testUnavailableProductIsExcludedFromAvailableFilter(): void
    {
        $list = $this->decode($this->get('/api/products?perPage=48'));
        $product = $this->findProduct($list, 'msi-mag-b760-tomahawk');

        self::assertFalse($product['available']);
        self::assertContains($product['id'], array_column($list['items'], 'id'));

        $available = $this->decode($this->get('/api/products?available=true&perPage=48'));
        self::assertNotContains($product['id'], array_column($available['items'], 'id'));
    }

    public function testProductListAcceptsNumericBooleanAvailability(): void
    {
        $available = $this->decode($this->get('/api/products?available=1&perPage=48'));

        self::assertGreaterThan(0, $available['meta']['total']);
        foreach ($available['items'] as $product) {
            self::assertTrue($product['available']);
        }

        $all = $this->decode($this->get('/api/products?available=0&perPage=48'));
        $unavailable = $this->findProduct($all, 'msi-mag-b760-tomahawk');

        self::assertFalse($unavailable['available']);
    }

    public function testUnknownProductReturnsNotFoundProblem(): void
    {
        foreach (['does-not-exist', '999999'] as $id) {
            $response = $this->get('/api/products/' . $id);

            self::assertSame(404, $response->getStatusCode(), $id);
            self::assertSame(
                'https://hexlet.io/problems/not-found',
                $this->decode($response)['type'],
                $id
            );
        }
    }

    public function testPromosContainFullProducts(): void
    {
        $response = $this->get('/api/promos');
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertTrue(array_is_list($body));
        self::assertGreaterThanOrEqual(2, count($body));

        $productIds = [];
        foreach ($body as $promo) {
            self::assertArrayHasKey('id', $promo);
            self::assertArrayHasKey('title', $promo);
            self::assertArrayHasKey('text', $promo);
            self::assertArrayHasKey('product', $promo);

            self::assertIsString($promo['id']);
            self::assertNotSame('', $promo['id']);
            self::assertIsString($promo['title']);
            self::assertNotSame('', $promo['title']);
            self::assertIsString($promo['text']);
            self::assertNotSame('', $promo['text']);
            self::assertIsArray($promo['product']);

            $this->assertProductShape($promo['product']);
            self::assertTrue($promo['product']['available']);

            $product = $this->decode($this->get('/api/products/' . $promo['product']['id']));
            self::assertSame($product, $promo['product']);

            $productIds[] = $promo['product']['id'];
        }

        self::assertCount(count($body), array_unique($productIds));
    }

    public function testPromosExcludeUnavailableProducts(): void
    {
        $promos = $this->decode($this->get('/api/promos'));
        self::assertNotEmpty($promos);

        $promo = $promos[0];
        $productId = (int) $promo['product']['id'];
        $pdo = $this->database();

        try {
            $statement = $pdo->prepare('UPDATE products SET available = FALSE WHERE id = :id');
            $statement->execute(['id' => $productId]);

            $remaining = $this->decode($this->get('/api/promos'));
            $remainingProductIds = array_column(array_column($remaining, 'product'), 'id');

            self::assertNotContains($promo['product']['id'], $remainingProductIds);
            self::assertLessThan(count($promos), count($remaining));
        } finally {
            $statement = $pdo->prepare('UPDATE products SET available = TRUE WHERE id = :id');
            $statement->execute(['id' => $productId]);
        }

        $restored = $this->decode($this->get('/api/promos'));
        self::assertContains(
            $promo['product']['id'],
            array_column(array_column($restored, 'product'), 'id')
        );
    }

    public function testPromosReturnEmptyListWhenNoBlocksExist(): void
    {
        $pdo = $this->database();
        $rows = $pdo->query(
            'SELECT id, slug, title, body, product_id FROM promos ORDER BY id'
        )->fetchAll();
        self::assertNotEmpty($rows);

        try {
            $pdo->exec('DELETE FROM promos');

            $response = $this->get('/api/promos');

            self::assertSame(200, $response->getStatusCode());
            self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
            self::assertSame([], $this->decode($response));
        } finally {
            $statement = $pdo->prepare(
                'INSERT INTO promos (id, slug, title, body, product_id)'
                . ' VALUES (:id, :slug, :title, :body, :product_id)'
            );

            foreach ($rows as $row) {
                $statement->execute($row);
            }
        }
    }

    public function testAnonymousUserAlwaysGetsUnauthorizedProblem(): void
    {
        $response = $this->get('/api/me');

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            [
                'type' => 'https://hexlet.io/problems/unauthorized',
                'title' => 'Unauthorized',
                'status' => 401,
            ],
            $this->decode($response)
        );
    }

    public function testCatalogReturnsInternalErrorWhenDatabaseIsUnavailable(): void
    {
        $app = AppFactory::create(
            new Config(
                8080,
                'pgsql://invalid:invalid@127.0.0.1:1/invalid?connect_timeout=1'
            )
        );
        $response = $this->getFrom($app, '/api/categories');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            'https://hexlet.io/problems/internal-error',
            $this->decode($response)['type']
        );
    }

    public function testUnknownApiPathReturnsJsonNotFound(): void
    {
        $response = $this->get('/api/nothing-here');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $rawBody = (string) $response->getBody();
        self::assertJson($rawBody);
        self::assertStringNotContainsString('<html', $rawBody);

        self::assertSame(
            [
                'type' => 'https://hexlet.io/problems/not-found',
                'title' => 'Resource not found',
                'status' => 404,
            ],
            $this->decode($response)
        );
    }

    public function testSpaFallbackReturnsIndexHtml(): void
    {
        $index = dirname(__DIR__) . '/public/index.html';

        if (!is_file($index)) {
            self::markTestSkipped('Run make build before testing the SPA fallback.');
        }

        $response = $this->get('/cart');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<div id="root"></div>', (string) $response->getBody());
    }

    /**
     * @param array<string, mixed> $product
     */
    private function assertProductShape(array $product): void
    {
        foreach (['id', 'slug', 'name', 'description', 'price', 'categoryId', 'available'] as $key) {
            self::assertArrayHasKey($key, $product);
        }

        self::assertIsString($product['id']);
        self::assertIsString($product['slug']);
        self::assertIsString($product['name']);
        self::assertIsString($product['description']);
        self::assertIsArray($product['price']);
        self::assertIsInt($product['price']['amount']);
        self::assertSame('RUB', $product['price']['currency']);
        self::assertIsString($product['categoryId']);
        self::assertIsBool($product['available']);
    }

    private function categoryId(string $slug): string
    {
        foreach ($this->decode($this->get('/api/categories')) as $category) {
            if ($category['slug'] === $slug) {
                return $category['id'];
            }
        }

        self::fail('Category not found: ' . $slug);
    }

    /**
     * @param array{items: list<array<string, mixed>>} $list
     *
     * @return array<string, mixed>
     */
    private function findProduct(array $list, string $slug): array
    {
        foreach ($list['items'] as $product) {
            if ($product['slug'] === $slug) {
                return $product;
            }
        }

        self::fail('Product not found: ' . $slug);
    }

    private function database(): PDO
    {
        $databaseUrl = getenv('DATABASE_URL');

        if (!is_string($databaseUrl) || $databaseUrl === '') {
            self::fail('DATABASE_URL is required. Run make db-setup before the API tests.');
        }

        return Connection::fromConfig(new Config(8080, $databaseUrl));
    }

    private function get(string $uri): ResponseInterface
    {
        return $this->getFrom($this->app, $uri);
    }

    private function getFrom(App $app, string $uri): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $uri);

        return $app->handle($request);
    }

    /**
     * @return array<mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertIsArray($body);

        return $body;
    }
}
