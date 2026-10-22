<?php

declare(strict_types=1);

namespace App\Tests;

use App\AppFactory;
use App\Config;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class HealthTest extends TestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $app = AppFactory::create(new Config(8080, 'pgsql://invalid:invalid@127.0.0.1:1/invalid'));
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');

        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['status' => 'ok'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
