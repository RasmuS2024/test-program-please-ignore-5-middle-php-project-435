<?php

declare(strict_types=1);

namespace App;

use App\Catalog\StubCatalog;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    private const PROBLEM_BASE = 'https://hexlet.io/problems/';

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

        $app->get(
            '/api/categories',
            static function (
                ServerRequestInterface $request,
                ResponseInterface $response
            ): ResponseInterface {
                return self::json($response, StubCatalog::categories());
            }
        );

        $app->get(
            '/api/products',
            static function (
                ServerRequestInterface $request,
                ResponseInterface $response
            ): ResponseInterface {
                try {
                    $page = StubCatalog::search($request->getQueryParams());
                } catch (InvalidArgumentException) {
                    return self::problem(
                        $response,
                        400,
                        'validation-error',
                        'Validation error'
                    );
                }

                return self::json($response, $page);
            }
        );

        $app->get(
            '/api/products/{id}',
            static function (
                ServerRequestInterface $request,
                ResponseInterface $response,
                array $args
            ): ResponseInterface {
                $id = $args['id'] ?? null;
                $product = is_string($id) ? StubCatalog::product($id) : null;

                if ($product === null) {
                    return self::problem($response, 404, 'not-found', 'Product not found');
                }

                return self::json($response, $product);
            }
        );

        $app->get(
            '/api/promos',
            static function (
                ServerRequestInterface $request,
                ResponseInterface $response
            ): ResponseInterface {
                return self::json($response, StubCatalog::promos());
            }
        );

        $app->get(
            '/api/me',
            static function (
                ServerRequestInterface $request,
                ResponseInterface $response
            ): ResponseInterface {
                return self::problem($response, 401, 'unauthorized', 'Unauthorized');
            }
        );

        $apiNotFound = static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ): ResponseInterface {
            return self::problem($response, 404, 'not-found', 'Resource not found');
        };

        $app->any('/api', $apiNotFound);
        $app->any('/api/', $apiNotFound);
        $app->any('/api/{path:.*}', $apiNotFound);

        $spa = static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ): ResponseInterface {
            $index = dirname(__DIR__) . '/public/index.html';

            if (!is_file($index)) {
                return self::problem($response, 404, 'not-found', 'Resource not found');
            }

            $response->getBody()->write((string) file_get_contents($index));

            return $response
                ->withHeader('Content-Type', 'text/html; charset=UTF-8')
                ->withStatus(200);
        };

        $app->get('/', $spa);
        $app->get('/{path:.*}', $spa);

        return $app;
    }

    private static function json(
        ResponseInterface $response,
        array $payload,
        int $status = 200
    ): ResponseInterface {
        $response->getBody()->write(
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private static function problem(
        ResponseInterface $response,
        int $status,
        string $type,
        string $title
    ): ResponseInterface {
        return self::json(
            $response,
            [
                'type' => self::PROBLEM_BASE . $type,
                'title' => $title,
                'status' => $status,
            ],
            $status
        );
    }
}
