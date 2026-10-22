<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    public static function create(?Config $config = null): App
    {
        $config ??= Config::fromEnvironment();

        $app = SlimAppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->add(
            static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ) use ($config): ResponseInterface {
                return $handler->handle($request->withAttribute(Config::class, $config));
            }
        );

        $app->get(
            '/api/health',
            static function (
                ServerRequestInterface $request,
                ResponseInterface $response
            ): ResponseInterface {
                return self::json($response, ['status' => 'ok']);
            }
        );

        return $app;
    }

    private static function json(ResponseInterface $response, array $payload): ResponseInterface
    {
        $response->getBody()->write(
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
