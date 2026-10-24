<?php

declare(strict_types=1);

namespace App\Auth;

use Psr\Http\Message\ServerRequestInterface;

final class SessionManager
{
    public const COOKIE_NAME = 'PHPSESSID';
    private const USER_ID_KEY = 'user_id';
    private const ID_PATTERN = '/^[A-Za-z0-9,-]{22,256}$/';

    private bool $active = false;

    public function start(?string $sessionId = null): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if ($sessionId === null || session_id() === $sessionId) {
                $this->active = true;

                return true;
            }

            session_write_close();
        }

        $this->configure();

        session_id('');

        if ($sessionId !== null && preg_match(self::ID_PATTERN, $sessionId) === 1) {
            session_id($sessionId);
        }

        $started = session_start();
        $this->active = $started;

        return $started;
    }

    public function login(int $userId): ?string
    {
        if (!$this->active && !$this->start()) {
            return null;
        }

        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION[self::USER_ID_KEY] = $userId;

        return session_id();
    }

    public function userId(): ?int
    {
        if (!$this->active) {
            return null;
        }

        $userId = $_SESSION[self::USER_ID_KEY] ?? null;

        return is_int($userId) && $userId > 0 ? $userId : null;
    }

    public function logout(): void
    {
        if (!$this->active) {
            return;
        }

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        session_id('');
        $this->active = false;
    }

    public function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->active = false;
    }

    public static function sessionIdFromRequest(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        $cookieValue = $cookies[self::COOKIE_NAME] ?? null;

        if (is_string($cookieValue)) {
            return $cookieValue;
        }

        $cookieHeader = $request->getHeaderLine('Cookie');

        if ($cookieHeader === '') {
            return null;
        }

        foreach (explode(';', $cookieHeader) as $cookie) {
            $parts = explode('=', trim($cookie), 2);

            if (count($parts) === 2 && $parts[0] === self::COOKIE_NAME) {
                return trim($parts[1]);
            }
        }

        return null;
    }

    public static function cookieHeader(string $sessionId, bool $secure): string
    {
        return sprintf(
            '%s=%s; Path=/; HttpOnly; SameSite=Lax%s',
            self::COOKIE_NAME,
            rawurlencode($sessionId),
            $secure ? '; Secure' : ''
        );
    }

    public static function expiredCookieHeader(bool $secure): string
    {
        return sprintf(
            '%s=deleted; Path=/; HttpOnly; SameSite=Lax; Max-Age=0;'
            . ' Expires=Thu, 01 Jan 1970 00:00:00 GMT%s',
            self::COOKIE_NAME,
            $secure ? '; Secure' : ''
        );
    }

    public static function isSecureRequest(ServerRequestInterface $request): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = $request->getHeaderLine('X-Forwarded-Proto');

        return $request->getUri()->getScheme() === 'https'
            || (is_string($https) && $https !== '' && strtolower($https) !== 'off')
            || strtolower(trim(explode(',', $forwardedProto)[0])) === 'https';
    }

    private function configure(): void
    {
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cache_limiter', '');

        session_name(self::COOKIE_NAME);

        $savePath = session_save_path();

        if ($savePath === '' || !is_dir($savePath) || !is_writable($savePath)) {
            $fallback = sys_get_temp_dir();

            if (is_dir($fallback) && is_writable($fallback)) {
                session_save_path($fallback);
            }
        }
    }
}
