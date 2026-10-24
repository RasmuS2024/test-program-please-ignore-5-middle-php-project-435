<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config;
use App\Database\Connection;
use PDO;
use PDOException;

final class UserRepository
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return array{id: string, email: string}|null
     */
    public function create(string $email, string $passwordHash): ?array
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO users (email, password_hash)'
            . ' VALUES (:email, :password_hash)'
            . ' ON CONFLICT (email) DO NOTHING'
            . ' RETURNING id, email'
        );

        try {
            $statement->execute([
                'email' => $email,
                'password_hash' => $passwordHash,
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23505') {
                return null;
            }

            throw $exception;
        }

        $row = $statement->fetch();

        return $row === false ? null : UserMapper::user($row);
    }

    /**
     * @return array{id: mixed, email: mixed, password_hash: mixed}|null
     */
    public function credentials(string $email): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, email, password_hash FROM users WHERE email = :email'
        );
        $statement->execute(['email' => $email]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array{id: string, email: string}|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, email FROM users WHERE id = :id'
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();

        return $row === false ? null : UserMapper::user($row);
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Connection::fromConfig($this->config);
    }
}
