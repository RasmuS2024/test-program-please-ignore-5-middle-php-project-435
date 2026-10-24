<?php

declare(strict_types=1);

namespace App\Tests;

use App\AppFactory;
use App\Config;
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

    public function testProductListRejectsInvalidPagination(): void
    {
        $response = $this->get('/api/products?page=0');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            'https://hexlet.io/problems/validation-error',
            $this->decode($response)['type']
        );
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
        self::assertSame($expected['id'], $body['id']);
        self::assertArrayHasKey('imageUrl', $body);
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

        $available = $this->decode($this->get('/api/products?available=true&perPage=48'));
        self::assertNotContains($product['id'], array_column($available['items'], 'id'));
    }

    public function testUnknownProductReturnsNotFoundProblem(): void
    {
        $response = $this->get('/api/products/does-not-exist');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(
            'https://hexlet.io/problems/not-found',
            $this->decode($response)['type']
        );
    }

    public function testPromosContainFullProducts(): void
    {
        $response = $this->get('/api/promos');
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(3, $body);

        $productIds = [];
        foreach ($body as $promo) {
            self::assertArrayHasKey('product', $promo);
            self::assertArrayHasKey('price', $promo['product']);
            self::assertArrayHasKey('categoryId', $promo['product']);
            self::assertTrue($promo['product']['available']);
            $productIds[] = $promo['product']['id'];
        }

        self::assertCount(3, array_unique($productIds));
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
