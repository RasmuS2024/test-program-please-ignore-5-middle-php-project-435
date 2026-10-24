<?php

declare(strict_types=1);

namespace App\Auth;

use InvalidArgumentException;

final class Credentials
{
    private function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromParsedBody(mixed $body): self
    {
        if (!is_array($body)) {
            throw new InvalidArgumentException('Request body must be a JSON object.');
        }

        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;

        if (!is_string($email) || !is_string($password)) {
            throw new InvalidArgumentException('Email and password must be strings.');
        }

        $email = strtolower(trim($email));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email is invalid.');
        }

        if (mb_strlen($password) < 6) {
            throw new InvalidArgumentException('Password is too short.');
        }

        return new self($email, $password);
    }
}
