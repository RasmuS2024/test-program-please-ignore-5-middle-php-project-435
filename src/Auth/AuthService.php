<?php

declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

final class AuthService
{
    private const DUMMY_PASSWORD_HASH = '$2y$10$AqBe5c57NEhP4XhPzhGTxeZvgx8aLmIl36Lj4/JwSL7BmbGN4t6Wq';

    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @return array{id: string, email: string}|null
     */
    public function register(Credentials $credentials): ?array
    {
        $passwordHash = password_hash($credentials->password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new RuntimeException('Could not hash the password.');
        }

        return $this->users->create($credentials->email, $passwordHash);
    }

    /**
     * @return array{id: string, email: string}|null
     */
    public function login(Credentials $credentials): ?array
    {
        $user = $this->users->credentials($credentials->email);
        $passwordHash = $user['password_hash'] ?? self::DUMMY_PASSWORD_HASH;

        if (!is_string($passwordHash)) {
            return null;
        }

        $valid = password_verify($credentials->password, $passwordHash);

        if ($user === null || !$valid) {
            return null;
        }

        return UserMapper::user($user);
    }

    /**
     * @return array{id: string, email: string}|null
     */
    public function user(int $id): ?array
    {
        return $this->users->findById($id);
    }
}
