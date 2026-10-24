<?php

declare(strict_types=1);

namespace App;

use App\Catalog\CatalogQuery;
use App\Catalog\CatalogRepository;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Throwable;

final class AppFactory
{
    private const PROBLEM_BASE = 'https://hexlet.io/problems/';

    public static function create(
        ?Config $config = null,
        ?CatalogRepository $catalog = null
    ): App {
        $config ??= Config::fromEnvironment();
        $catalog ??= new CatalogRepository($config);

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
            ) use ($catalog): ResponseInterface {
                try {
                    return self::json($response, $catalog->categories());
                } catch (Throwable) {
                    return self::internalError($response);
                }
            }
        );

        $app->get(
            '/api/products',
            static function (
                ServerRequestInterface $request,
                ResponseInterface $response
            ) use ($catalog): ResponseInterface {
                try {
                    $query = CatalogQuery::fromArray($request->getQueryParams());
                } catch (InvalidArgumentException) {
                    return self::problem(
                        $response,
                        400,
                        'validation-error',
                        'Validation error'
                    );
                }

                try {
                    return self::json($response, $catalog->search($query));
                } catch (Throwable) {
                    return self::internalError($response);
                }
            }
        );

        $app->get(
            '/api/products/{id}',
            static function (
                ServerRequestInterface $request,
                ResponseInterface $response,
                array $args
            ) use ($catalog): ResponseInterface {
                $id = $args['id'] ?? null;

                try {
                    $product = is_string($id) ? $catalog->product($id) : null;
                } catch (Throwable) {
                    return self::internalError($response);
                }

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
            ) use ($catalog): ResponseInterface {
                try {
                    return self::json($response, $catalog->promos());
                } catch (Throwable) {
                    return self::internalError($response);
                }
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

    private static function internalError(ResponseInterface $response): ResponseInterface
    {
        return self::problem(
            $response,
            500,
            'internal-error',
            'Internal server error'
        );
    }
}
