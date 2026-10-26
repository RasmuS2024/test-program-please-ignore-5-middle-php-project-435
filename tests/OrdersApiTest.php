<?php

declare(strict_types=1);

namespace App\Tests;

use App\AppFactory;
use App\Config;
use App\Database\Connection;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class OrdersApiTest extends TestCase
{
    private App $app;
    private Config $config;

    /** @var list<string> */
    private array $emails = [];

    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        $databaseUrl = getenv('DATABASE_URL');

        if (!is_string($databaseUrl) || $databaseUrl === '') {
            self::fail('DATABASE_URL is required. Run make db-setup before the API tests.');
        }

        $this->config = new Config(8080, $databaseUrl);
        $this->app = AppFactory::create($this->config);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($this->emails === []) {
            return;
        }

        $pdo = $this->pdo();
        $deleteOrders = $pdo->prepare(
            'DELETE FROM orders'
            . ' WHERE user_id IN (SELECT id FROM users WHERE email = :email)'
        );
        $deleteUser = $pdo->prepare('DELETE FROM users WHERE email = :email');

        foreach ($this->emails as $email) {
            $deleteOrders->execute(['email' => $email]);
            $deleteUser->execute(['email' => $email]);
        }
    }

    public function testAnonymousCannotCreateOrReadOrders(): void
    {
        $product = $this->availableProducts(1)[0];
        $payload = $this->orderPayload([
            ['productId' => $product['id'], 'quantity' => 1],
        ]);

        $this->assertProblem(
            $this->request('POST', '/api/orders', $payload),
            401,
            'unauthorized'
        );
        $this->assertProblem(
            $this->request('POST', '/api/orders', []),
            401,
            'unauthorized'
        );
        $this->assertProblem(
            $this->request('GET', '/api/orders'),
            401,
            'unauthorized'
        );
        $this->assertProblem(
            $this->request('GET', '/api/orders/1'),
            401,
            'unauthorized'
        );
        $this->assertProblem(
            $this->request('GET', '/api/orders/not-a-number'),
            401,
            'unauthorized'
        );
    }

    public function testCreateDeliveryOrderCalculatesTotalAndIgnoresClientPrices(): void
    {
        $user = $this->registerUser();
        $products = $this->availableProducts(2);
        $expectedTotal = $products[0]['price']['amount'] * 2 + $products[1]['price']['amount'];

        $payload = [
            'items' => [
                [
                    'productId' => $products[0]['id'],
                    'quantity' => 2,
                    'priceAtPurchase' => ['amount' => 1, 'currency' => 'RUB'],
                ],
                ['productId' => $products[1]['id'], 'quantity' => 1],
            ],
            'shipping' => [
                'method' => 'delivery',
                'recipientName' => '  Иван Петров  ',
                'phone' => '  +7 999 000-11-22  ',
                'address' => '  ул. Ленина, 1  ',
            ],
            'total' => ['amount' => 1, 'currency' => 'RUB'],
        ];

        $response = $this->request('POST', '/api/orders', $payload, $user['cookie']);
        $order = $this->decode($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertOrderShape($order);
        self::assertSame('paid', $order['status']);
        self::assertSame('delivery', $order['shipping']['method']);
        self::assertSame('Иван Петров', $order['shipping']['recipientName']);
        self::assertSame('+7 999 000-11-22', $order['shipping']['phone']);
        self::assertSame('ул. Ленина, 1', $order['shipping']['address']);
        self::assertSame($expectedTotal, $order['total']['amount']);
        self::assertSame('RUB', $order['total']['currency']);
        self::assertCount(2, $order['items']);

        $itemsByProduct = [];

        foreach ($order['items'] as $item) {
            $itemsByProduct[$item['productId']] = $item;
        }

        $firstItem = $itemsByProduct[$products[0]['id']];
        $secondItem = $itemsByProduct[$products[1]['id']];

        self::assertSame($products[0]['name'], $firstItem['productName']);
        self::assertSame($products[0]['price'], $firstItem['priceAtPurchase']);
        self::assertSame(2, $firstItem['quantity']);
        self::assertSame($products[1]['name'], $secondItem['productName']);
        self::assertSame($products[1]['price'], $secondItem['priceAtPurchase']);
        self::assertSame(1, $secondItem['quantity']);

        $statement = $this->pdo()->prepare(
            'SELECT user_id, status, shipping_method, recipient_name, phone, address, total_rub'
            . ' FROM orders WHERE id = :id'
        );
        $statement->execute(['id' => (int) $order['id']]);
        $row = $statement->fetch();

        self::assertIsArray($row);
        self::assertSame((int) $user['user']['id'], (int) $row['user_id']);
        self::assertSame('paid', $row['status']);
        self::assertSame('delivery', $row['shipping_method']);
        self::assertSame('Иван Петров', $row['recipient_name']);
        self::assertSame('+7 999 000-11-22', $row['phone']);
        self::assertSame('ул. Ленина, 1', $row['address']);
        self::assertSame($expectedTotal, (int) $row['total_rub']);

        $statement = $this->pdo()->prepare(
            'SELECT product_id, product_name, price_rub, quantity'
            . ' FROM order_items WHERE order_id = :id ORDER BY id'
        );
        $statement->execute(['id' => (int) $order['id']]);
        $rows = $statement->fetchAll();

        self::assertCount(2, $rows);
        self::assertSame((int) $products[0]['id'], (int) $rows[0]['product_id']);
        self::assertSame($products[0]['name'], $rows[0]['product_name']);
        self::assertSame($products[0]['price']['amount'], (int) $rows[0]['price_rub']);
        self::assertSame(2, (int) $rows[0]['quantity']);

        $list = $this->request('GET', '/api/orders', null, $user['cookie']);
        self::assertSame(200, $list->getStatusCode());
        self::assertSame([$order], $this->decode($list));

        $detail = $this->request(
            'GET',
            '/api/orders/' . $order['id'],
            null,
            $user['cookie']
        );
        self::assertSame(200, $detail->getStatusCode());
        self::assertSame($order, $this->decode($detail));
    }

    public function testCreatePickupOrderWithoutAddress(): void
    {
        $user = $this->registerUser();
        $product = $this->availableProducts(1)[0];
        $payload = [
            'items' => [['productId' => $product['id'], 'quantity' => 1]],
            'shipping' => [
                'method' => 'pickup',
                'recipientName' => 'Мария',
                'phone' => '+7 900 000-00-00',
                'address' => '   ',
            ],
        ];

        $response = $this->request('POST', '/api/orders', $payload, $user['cookie']);
        $order = $this->decode($response);

        self::assertSame(201, $response->getStatusCode());
        $this->assertOrderShape($order);
        self::assertSame('pickup', $order['shipping']['method']);
        self::assertArrayNotHasKey('address', $order['shipping']);

        $statement = $this->pdo()->prepare(
            'SELECT address FROM orders WHERE id = :id'
        );
        $statement->execute(['id' => (int) $order['id']]);

        self::assertNull($statement->fetchColumn());
    }

    public function testDeliveryRequiresNonEmptyAddress(): void
    {
        $user = $this->registerUser();
        $product = $this->availableProducts(1)[0];
        $addresses = [null, '', '   '];

        foreach ($addresses as $address) {
            $shipping = [
                'method' => 'delivery',
                'recipientName' => 'Иван',
                'phone' => '+7 900 000-00-00',
            ];

            if ($address !== null) {
                $shipping['address'] = $address;
            }

            $response = $this->request(
                'POST',
                '/api/orders',
                [
                    'items' => [['productId' => $product['id'], 'quantity' => 1]],
                    'shipping' => $shipping,
                ],
                $user['cookie']
            );

            $body = $this->assertProblem($response, 400, 'address-required');
            self::assertArrayNotHasKey('unavailableProductIds', $body);
        }

        self::assertSame(0, $this->orderCountForUser((int) $user['user']['id']));
    }

    public function testOrderValidationRejectsInvalidInput(): void
    {
        $user = $this->registerUser();
        $product = $this->availableProducts(1)[0];
        $item = ['productId' => $product['id'], 'quantity' => 1];
        $shipping = [
            'method' => 'delivery',
            'recipientName' => 'Иван',
            'phone' => '+7 900 000-00-00',
            'address' => 'ул. Ленина, 1',
        ];

        $cases = [
            'empty body' => [],
            'empty items' => ['items' => [], 'shipping' => $shipping],
            'items object' => ['items' => $item, 'shipping' => $shipping],
            'missing product id' => [
                'items' => [['quantity' => 1]],
                'shipping' => $shipping,
            ],
            'non-string product id' => [
                'items' => [['productId' => 1, 'quantity' => 1]],
                'shipping' => $shipping,
            ],
            'missing quantity' => [
                'items' => [['productId' => $product['id']]],
                'shipping' => $shipping,
            ],
            'zero quantity' => [
                'items' => [['productId' => $product['id'], 'quantity' => 0]],
                'shipping' => $shipping,
            ],
            'negative quantity' => [
                'items' => [['productId' => $product['id'], 'quantity' => -1]],
                'shipping' => $shipping,
            ],
            'fractional quantity' => [
                'items' => [['productId' => $product['id'], 'quantity' => 1.5]],
                'shipping' => $shipping,
            ],
            'string quantity' => [
                'items' => [['productId' => $product['id'], 'quantity' => '1']],
                'shipping' => $shipping,
            ],
            'missing shipping' => ['items' => [$item]],
            'invalid method' => [
                'items' => [$item],
                'shipping' => ['method' => 'courier'] + $shipping,
            ],
            'empty recipient name' => [
                'items' => [$item],
                'shipping' => ['recipientName' => '   '] + $shipping,
            ],
            'empty phone' => [
                'items' => [$item],
                'shipping' => ['phone' => ''] + $shipping,
            ],
            'non-string address' => [
                'items' => [$item],
                'shipping' => ['address' => 123] + $shipping,
            ],
        ];

        foreach ($cases as $name => $payload) {
            $response = $this->request(
                'POST',
                '/api/orders',
                $payload,
                $user['cookie']
            );

            self::assertSame(
                400,
                $response->getStatusCode(),
                $name . ' should be rejected'
            );
            $this->assertProblem($response, 400, 'validation-error');
        }

        $malformed = $this->requestWithRawBody(
            'POST',
            '/api/orders',
            '{',
            $user['cookie']
        );
        $this->assertProblem($malformed, 400, 'validation-error');
        self::assertSame(0, $this->orderCountForUser((int) $user['user']['id']));
    }

    public function testUnavailableProductsBlockEntireOrderAndReturnAllIds(): void
    {
        $user = $this->registerUser();
        $available = $this->availableProducts(1)[0];
        $unavailable = $this->unavailableProduct();
        $missingId = '999999999';
        $invalidId = 'not-a-product';

        $response = $this->request(
            'POST',
            '/api/orders',
            [
                'items' => [
                    ['productId' => $available['id'], 'quantity' => 1],
                    ['productId' => $unavailable['id'], 'quantity' => 1],
                    ['productId' => $missingId, 'quantity' => 1],
                    ['productId' => $invalidId, 'quantity' => 1],
                    ['productId' => $unavailable['id'], 'quantity' => 2],
                ],
                'shipping' => [
                    'method' => 'delivery',
                    'recipientName' => 'Иван',
                    'phone' => '+7 900 000-00-00',
                    'address' => 'ул. Ленина, 1',
                ],
            ],
            $user['cookie']
        );

        $body = $this->assertProblem($response, 400, 'unavailable-items');
        self::assertSame(
            [$unavailable['id'], $missingId, $invalidId],
            $body['unavailableProductIds']
        );

        $userId = (int) $user['user']['id'];
        self::assertSame(0, $this->orderCountForUser($userId));
        self::assertSame(0, $this->itemCountForUser($userId));
    }

    public function testDuplicateProductIdsAreAggregated(): void
    {
        $user = $this->registerUser();
        $product = $this->availableProducts(1)[0];
        $expectedTotal = $product['price']['amount'] * 3;

        $response = $this->request(
            'POST',
            '/api/orders',
            [
                'items' => [
                    ['productId' => $product['id'], 'quantity' => 1],
                    ['productId' => $product['id'], 'quantity' => 2],
                ],
                'shipping' => [
                    'method' => 'delivery',
                    'recipientName' => 'Иван',
                    'phone' => '+7 900 000-00-00',
                    'address' => 'ул. Ленина, 1',
                ],
            ],
            $user['cookie']
        );

        $order = $this->decode($response);

        self::assertSame(201, $response->getStatusCode());
        $this->assertCount(1, $order['items']);
        self::assertSame($product['id'], $order['items'][0]['productId']);
        self::assertSame(3, $order['items'][0]['quantity']);
        self::assertSame($expectedTotal, $order['total']['amount']);

        $statement = $this->pdo()->prepare(
            'SELECT quantity FROM order_items WHERE order_id = :id'
        );
        $statement->execute(['id' => (int) $order['id']]);
        $rows = $statement->fetchAll();

        self::assertCount(1, $rows);
        self::assertSame(3, (int) $rows[0]['quantity']);
    }

    public function testOrderSnapshotsSurviveProductChanges(): void
    {
        $user = $this->registerUser();
        $product = $this->availableProducts(1)[0];
        $order = $this->createOrderFor($user['cookie'], $product, 2);
        $originalName = $product['name'];
        $originalPrice = $product['price']['amount'];
        $newName = $originalName . ' (изменено)';
        $newPrice = $originalPrice + 1000;

        try {
            $statement = $this->pdo()->prepare(
                'UPDATE products SET name = :name, price_rub = :price WHERE id = :id'
            );
            $statement->execute([
                'name' => $newName,
                'price' => $newPrice,
                'id' => (int) $product['id'],
            ]);

            $detail = $this->request(
                'GET',
                '/api/orders/' . $order['id'],
                null,
                $user['cookie']
            );
            $stored = $this->decode($detail);

            self::assertSame(200, $detail->getStatusCode());
            self::assertSame($originalName, $stored['items'][0]['productName']);
            self::assertSame($originalPrice, $stored['items'][0]['priceAtPurchase']['amount']);
            self::assertSame($originalPrice * 2, $stored['total']['amount']);

            $list = $this->decode(
                $this->request('GET', '/api/orders', null, $user['cookie'])
            );
            self::assertSame($originalName, $list[0]['items'][0]['productName']);
            self::assertSame($originalPrice, $list[0]['items'][0]['priceAtPurchase']['amount']);
        } finally {
            $statement = $this->pdo()->prepare(
                'UPDATE products SET name = :name, price_rub = :price WHERE id = :id'
            );
            $statement->execute([
                'name' => $originalName,
                'price' => $originalPrice,
                'id' => (int) $product['id'],
            ]);
        }
    }

    public function testOrderItemSnapshotSurvivesProductDeletion(): void
    {
        $user = $this->registerUser();
        $categoryId = (int) $this->pdo()
            ->query('SELECT id FROM categories ORDER BY id LIMIT 1')
            ->fetchColumn();
        $slug = 'order-snapshot-' . bin2hex(random_bytes(4));
        $name = 'Товар для снимка';
        $price = 4321;
        $statement = $this->pdo()->prepare(
            'INSERT INTO products'
            . ' (slug, name, description, price_rub, category_id, image_url, available)'
            . ' VALUES (:slug, :name, :description, :price, :category_id, NULL, TRUE)'
            . ' RETURNING id'
        );
        $statement->execute([
            'slug' => $slug,
            'name' => $name,
            'description' => 'Временный товар для теста',
            'price' => $price,
            'category_id' => $categoryId,
        ]);
        $productId = (int) $statement->fetchColumn();

        try {
            $order = $this->createOrderFor(
                $user['cookie'],
                ['id' => (string) $productId, 'name' => $name, 'price' => [
                    'amount' => $price,
                    'currency' => 'RUB',
                ]],
                1
            );

            $statement = $this->pdo()->prepare(
                'DELETE FROM products WHERE id = :id'
            );
            $statement->execute(['id' => $productId]);

            $detail = $this->request(
                'GET',
                '/api/orders/' . $order['id'],
                null,
                $user['cookie']
            );
            $stored = $this->decode($detail);

            self::assertSame(200, $detail->getStatusCode());
            self::assertSame((string) $productId, $stored['items'][0]['productId']);
            self::assertSame($name, $stored['items'][0]['productName']);
            self::assertSame($price, $stored['items'][0]['priceAtPurchase']['amount']);
            self::assertSame($price, $stored['total']['amount']);
        } finally {
            $statement = $this->pdo()->prepare(
                'DELETE FROM products WHERE id = :id'
            );
            $statement->execute(['id' => $productId]);
        }
    }

    public function testOrdersAreIsolatedAndNewestFirst(): void
    {
        $firstUser = $this->registerUser();
        $secondUser = $this->registerUser();
        $products = $this->availableProducts(2);
        $firstOrder = $this->createOrderFor($firstUser['cookie'], $products[0], 1);
        $secondOrder = $this->createOrderFor($firstUser['cookie'], $products[1], 1);
        $foreignOrder = $this->createOrderFor(
            $secondUser['cookie'],
            $products[0],
            1,
            ['userId' => $firstUser['user']['id']]
        );

        $firstListResponse = $this->request(
            'GET',
            '/api/orders',
            null,
            $firstUser['cookie']
        );
        $firstList = $this->decode($firstListResponse);

        self::assertSame(200, $firstListResponse->getStatusCode());
        self::assertCount(2, $firstList);
        self::assertSame($secondOrder['id'], $firstList[0]['id']);
        self::assertSame($firstOrder['id'], $firstList[1]['id']);
        self::assertNotContains($foreignOrder['id'], array_column($firstList, 'id'));

        $secondList = $this->decode(
            $this->request('GET', '/api/orders', null, $secondUser['cookie'])
        );
        self::assertCount(1, $secondList);
        self::assertSame($foreignOrder['id'], $secondList[0]['id']);

        $this->assertProblem(
            $this->request(
                'GET',
                '/api/orders/' . $firstOrder['id'],
                null,
                $secondUser['cookie']
            ),
            404,
            'not-found'
        );
        $this->assertProblem(
            $this->request(
                'GET',
                '/api/orders/' . $foreignOrder['id'],
                null,
                $firstUser['cookie']
            ),
            404,
            'not-found'
        );
        $this->assertProblem(
            $this->request('GET', '/api/orders/999999999', null, $firstUser['cookie']),
            404,
            'not-found'
        );
        $this->assertProblem(
            $this->request('GET', '/api/orders/not-a-number', null, $firstUser['cookie']),
            404,
            'not-found'
        );

        $own = $this->request(
            'GET',
            '/api/orders/' . $secondOrder['id'],
            null,
            $firstUser['cookie']
        );
        self::assertSame(200, $own->getStatusCode());
        self::assertSame($secondOrder, $this->decode($own));

        $statement = $this->pdo()->prepare(
            'SELECT user_id FROM orders WHERE id = :id'
        );
        $statement->execute(['id' => (int) $foreignOrder['id']]);
        self::assertSame(
            (int) $secondUser['user']['id'],
            (int) $statement->fetchColumn()
        );
    }

    public function testEmptyOrderListReturnsEmptyArray(): void
    {
        $user = $this->registerUser();
        $response = $this->request('GET', '/api/orders', null, $user['cookie']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame([], $this->decode($response));
    }

    /**
     * @return array{user: array<mixed>, cookie: string}
     */
    private function registerUser(): array
    {
        $email = 'orders-' . bin2hex(random_bytes(6)) . '@example.com';
        $this->emails[] = $email;
        $response = $this->request(
            'POST',
            '/api/users',
            ['email' => $email, 'password' => 'secret1']
        );

        self::assertSame(201, $response->getStatusCode());

        return [
            'user' => $this->decode($response),
            'cookie' => $this->sessionCookie($response),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availableProducts(int $count): array
    {
        $body = $this->decode(
            $this->request('GET', '/api/products?available=true&perPage=48')
        );
        self::assertGreaterThanOrEqual($count, count($body['items']));

        return array_slice($body['items'], 0, $count);
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableProduct(): array
    {
        $body = $this->decode(
            $this->request('GET', '/api/products?perPage=48')
        );

        foreach ($body['items'] as $product) {
            if ($product['available'] === false) {
                return $product;
            }
        }

        self::fail('The catalog seed must contain an unavailable product.');
    }

    /**
     * @param list<array{productId: string, quantity: int}> $items
     * @return array<string, mixed>
     */
    private function orderPayload(array $items): array
    {
        return [
            'items' => $items,
            'shipping' => [
                'method' => 'delivery',
                'recipientName' => 'Иван',
                'phone' => '+7 900 000-00-00',
                'address' => 'ул. Ленина, 1',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function createOrderFor(
        string $cookie,
        array $product,
        int $quantity,
        array $extra = []
    ): array {
        $response = $this->request(
            'POST',
            '/api/orders',
            $extra + $this->orderPayload([
                ['productId' => $product['id'], 'quantity' => $quantity],
            ]),
            $cookie
        );
        $order = $this->decode($response);

        self::assertSame(201, $response->getStatusCode());

        return $order;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function assertOrderShape(array $order): void
    {
        self::assertArrayHasKey('id', $order);
        self::assertMatchesRegularExpression('/^\d+$/', $order['id']);
        self::assertSame('paid', $order['status']);
        self::assertIsArray($order['items']);
        self::assertNotSame([], $order['items']);
        self::assertIsArray($order['shipping']);
        self::assertIsArray($order['total']);
        self::assertSame('RUB', $order['total']['currency']);
        self::assertIsInt($order['total']['amount']);
        self::assertIsString($order['createdAt']);
        self::assertNotFalse(strtotime($order['createdAt']));

        foreach ($order['items'] as $item) {
            self::assertArrayHasKey('productId', $item);
            self::assertIsString($item['productId']);
            self::assertIsString($item['productName']);
            self::assertSame('RUB', $item['priceAtPurchase']['currency']);
            self::assertIsInt($item['priceAtPurchase']['amount']);
            self::assertIsInt($item['quantity']);
            self::assertGreaterThan(0, $item['quantity']);
        }
    }

    private function orderCountForUser(int $userId): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM orders WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    private function itemCountForUser(int $userId): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM order_items oi'
            . ' JOIN orders o ON o.id = oi.order_id'
            . ' WHERE o.user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(
        string $method,
        string $uri,
        ?array $body = null,
        ?string $cookie = null
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream(
                    json_encode($body, JSON_THROW_ON_ERROR)
                ));
        }

        if ($cookie !== null) {
            $request = $request->withHeader('Cookie', $cookie);
        }

        return $this->app->handle($request);
    }

    private function requestWithRawBody(
        string $method,
        string $uri,
        string $body,
        ?string $cookie = null
    ): ResponseInterface {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream($body));

        if ($cookie !== null) {
            $request = $request->withHeader('Cookie', $cookie);
        }

        return $this->app->handle($request);
    }

    private function sessionCookie(ResponseInterface $response): string
    {
        $setCookie = $response->getHeaderLine('Set-Cookie');

        self::assertNotSame('', $setCookie);
        self::assertStringStartsWith('PHPSESSID=', $setCookie);

        return explode(';', $setCookie, 2)[0];
    }

    /**
     * @return array<mixed>
     */
    private function assertProblem(
        ResponseInterface $response,
        int $status,
        string $type
    ): array {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = $this->decode($response);
        self::assertSame('https://hexlet.io/problems/' . $type, $body['type']);
        self::assertSame($status, $body['status']);
        self::assertArrayHasKey('title', $body);

        return $body;
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

    private function pdo(): PDO
    {
        return $this->pdo ??= Connection::fromConfig($this->config);
    }
}
