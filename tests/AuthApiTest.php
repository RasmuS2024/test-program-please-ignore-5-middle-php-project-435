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

final class AuthApiTest extends TestCase
{
    private App $app;
    private Config $config;
    private string $email;

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
        $this->email = 'auth-' . bin2hex(random_bytes(6)) . '@example.com';
        $this->emails[] = $this->email;
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($this->emails === []) {
            return;
        }

        $statement = $this->pdo()->prepare(
            'DELETE FROM users WHERE email = :email'
        );

        foreach ($this->emails as $email) {
            $statement->execute(['email' => $email]);
        }
    }

    public function testRegistrationCreatesUserAndOpensSession(): void
    {
        $response = $this->request(
            'POST',
            '/api/users',
            [
                'email' => '  ' . strtoupper($this->email) . ' ',
                'password' => 'secret1',
            ]
        );
        $body = $this->decode($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame($this->email, $body['email']);
        self::assertMatchesRegularExpression('/^\d+$/', $body['id']);
        self::assertArrayNotHasKey('passwordHash', $body);
        self::assertArrayNotHasKey('password_hash', $body);

        $cookie = $this->sessionCookie($response);
        self::assertStringContainsString('HttpOnly', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('SameSite=Lax', $response->getHeaderLine('Set-Cookie'));
        self::assertStringNotContainsString('Secure', $response->getHeaderLine('Set-Cookie'));

        $me = $this->request('GET', '/api/me', null, $cookie);
        $meBody = $this->decode($me);

        self::assertSame(200, $me->getStatusCode());
        self::assertSame($body, $meBody);

        $anonymous = $this->request('GET', '/api/me');
        $this->assertProblem($anonymous, 401, 'unauthorized');

        $passwordHash = $this->pdo()
            ->query(
                'SELECT password_hash FROM users WHERE email = '
                . $this->pdo()->quote($this->email)
            )
            ->fetchColumn();

        self::assertIsString($passwordHash);
        self::assertNotSame('secret1', $passwordHash);
        self::assertTrue(password_verify('secret1', $passwordHash));
    }

    public function testRegistrationRejectsDuplicateNormalizedEmail(): void
    {
        $first = $this->request(
            'POST',
            '/api/users',
            ['email' => $this->email, 'password' => 'secret1']
        );

        self::assertSame(201, $first->getStatusCode());

        $duplicate = $this->request(
            'POST',
            '/api/users',
            [
                'email' => ' ' . strtoupper($this->email) . ' ',
                'password' => 'another-secret',
            ]
        );

        $this->assertProblem($duplicate, 409, 'email-taken');
        self::assertSame('', $duplicate->getHeaderLine('Set-Cookie'));

        $count = $this->pdo()
            ->query(
                'SELECT COUNT(*) FROM users WHERE email = '
                . $this->pdo()->quote($this->email)
            )
            ->fetchColumn();

        self::assertSame('1', (string) $count);
    }

    public function testRegistrationValidatesRequestBody(): void
    {
        $invalidEmail = $this->request(
            'POST',
            '/api/users',
            ['email' => 'not-an-email', 'password' => 'secret1']
        );
        $shortPassword = $this->request(
            'POST',
            '/api/users',
            ['email' => $this->email, 'password' => '12345']
        );
        $missingPassword = $this->request(
            'POST',
            '/api/users',
            ['email' => $this->email]
        );
        $malformedBody = $this->requestWithRawBody('POST', '/api/users', '{');

        $this->assertProblem($invalidEmail, 400, 'validation-error');
        $this->assertProblem($shortPassword, 400, 'validation-error');
        $this->assertProblem($missingPassword, 400, 'validation-error');
        $this->assertProblem($malformedBody, 400, 'validation-error');
    }

    public function testInvalidRegistrationDoesNotQueryDatabase(): void
    {
        $app = AppFactory::create(
            new Config(
                8080,
                'pgsql://invalid:invalid@127.0.0.1:1/invalid?connect_timeout=1'
            )
        );
        $response = $this->requestFrom(
            $app,
            'POST',
            '/api/users',
            ['email' => 'invalid', 'password' => '12345']
        );

        $this->assertProblem($response, 400, 'validation-error');
    }

    public function testLoginRejectsWrongCredentialsWithTheSameProblem(): void
    {
        $this->request(
            'POST',
            '/api/users',
            ['email' => $this->email, 'password' => 'secret1']
        );

        $wrongPassword = $this->request(
            'POST',
            '/api/session',
            ['email' => $this->email, 'password' => 'wrong-pass']
        );
        $unknownEmail = $this->request(
            'POST',
            '/api/session',
            ['email' => 'missing-' . bin2hex(random_bytes(4)) . '@example.com', 'password' => 'secret1']
        );

        $this->assertProblem($wrongPassword, 401, 'invalid-credentials');
        $this->assertProblem($unknownEmail, 401, 'invalid-credentials');
        self::assertSame($this->decode($wrongPassword), $this->decode($unknownEmail));
        self::assertSame('', $wrongPassword->getHeaderLine('Set-Cookie'));
        self::assertSame('', $unknownEmail->getHeaderLine('Set-Cookie'));
    }

    public function testLoginReplacesExistingSession(): void
    {
        $registration = $this->request(
            'POST',
            '/api/users',
            ['email' => $this->email, 'password' => 'secret1']
        );
        $oldCookie = $this->sessionCookie($registration);

        $login = $this->request(
            'POST',
            '/api/session',
            [
                'email' => ' ' . strtoupper($this->email) . ' ',
                'password' => 'secret1',
            ],
            $oldCookie
        );
        $user = $this->decode($login);
        $newCookie = $this->sessionCookie($login);

        self::assertSame(200, $login->getStatusCode());
        self::assertSame($this->email, $user['email']);
        self::assertNotSame($oldCookie, $newCookie);

        $me = $this->request('GET', '/api/me', null, $newCookie);
        self::assertSame(200, $me->getStatusCode());
        self::assertSame($user, $this->decode($me));

        $oldSession = $this->request('GET', '/api/me', null, $oldCookie);
        $this->assertProblem($oldSession, 401, 'unauthorized');
    }

    public function testLogoutDestroysServerSideSession(): void
    {
        $registration = $this->request(
            'POST',
            '/api/users',
            ['email' => $this->email, 'password' => 'secret1']
        );
        $cookie = $this->sessionCookie($registration);

        $logout = $this->request('DELETE', '/api/session', null, $cookie);

        self::assertSame(204, $logout->getStatusCode());
        self::assertSame('', (string) $logout->getBody());
        self::assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'));

        $me = $this->request('GET', '/api/me', null, $cookie);
        $this->assertProblem($me, 401, 'unauthorized');
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
        return $this->requestFrom($this->app, $method, $uri, $body, $cookie);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function requestFrom(
        App $app,
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

        return $app->handle($request);
    }

    private function requestWithRawBody(
        string $method,
        string $uri,
        string $body
    ): ResponseInterface {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream($body));

        return $this->app->handle($request);
    }

    private function sessionCookie(ResponseInterface $response): string
    {
        $setCookie = $response->getHeaderLine('Set-Cookie');

        self::assertNotSame('', $setCookie);
        self::assertStringStartsWith('PHPSESSID=', $setCookie);

        return explode(';', $setCookie, 2)[0];
    }

    private function assertProblem(
        ResponseInterface $response,
        int $status,
        string $type
    ): void {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = $this->decode($response);
        self::assertSame('https://hexlet.io/problems/' . $type, $body['type']);
        self::assertSame($status, $body['status']);
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
