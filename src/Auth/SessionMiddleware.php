<?php

declare(strict_types=1);

namespace App\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class SessionMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $session = new SessionManager();
        $sessionId = SessionManager::sessionIdFromRequest($request);

        if ($sessionId !== null) {
            try {
                $session->start($sessionId);
            } catch (Throwable) {
                // An unreadable session must behave like an anonymous request.
            }
        }

        try {
            return $handler->handle(
                $request->withAttribute(SessionManager::class, $session)
            );
        } finally {
            $session->close();
        }
    }
}
