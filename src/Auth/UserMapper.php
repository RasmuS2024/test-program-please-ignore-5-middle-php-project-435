<?php

declare(strict_types=1);

namespace App\Auth;

final class UserMapper
{
    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, email: string}
     */
    public static function user(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'email' => (string) $row['email'],
        ];
    }
}
