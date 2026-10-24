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
        $this->app = AppFactory::create(new Config(8080, ''));
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
        $response = $this->get('/api/products?category=videokarty&available=true');
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(4, $body['meta']['total']);
        self::assertSame(1, $body['meta']['totalPages']);

        foreach ($body['items'] as $product) {
            self::assertSame('1', $product['categoryId']);
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
        self::assertSame(11, $pageBody['meta']['total']);
        self::assertSame(2, $pageBody['meta']['totalPages']);
        self::assertCount(5, $pageBody['items']);
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

    public function testProductByIdIsReturned(): void
    {
        $response = $this->get('/api/products/1');
        $body = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('1', $body['id']);
        self::assertSame('/images/nvidia-geforce-rtx-4090.svg', $body['imageUrl']);
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
        self::assertArrayHasKey('product', $body[0]);
        self::assertArrayHasKey('price', $body[0]['product']);
        self::assertArrayHasKey('categoryId', $body[0]['product']);
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

    public function testUnknownApiPathReturnsJsonNotFound(): void
    {
        $response = $this->get('/api/nothing-here');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
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

    private function get(string $uri): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $uri);

        return $this->app->handle($request);
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
