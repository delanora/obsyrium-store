<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/*
 * Leitura e escrita das contas de administração.
 *
 * A senha é sempre gravada como hash (Argon2id, o mesmo do cliente); o
 * repositório não guarda nem devolve senha em texto.
 */
final class AdminRepository
{
    private const COLUNAS = 'id, name, email, password, is_active, last_login_at, created_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT ' . self::COLUNAS . ' FROM admins WHERE email = :email LIMIT 1'
        );

        $consulta->bindValue(':email', mb_strtolower(trim($email)));
        $consulta->execute();

        $linha = $consulta->fetch();

        return $linha === false ? null : $this->hidratar($linha);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT ' . self::COLUNAS . ' FROM admins WHERE id = :id LIMIT 1'
        );

        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();

        $linha = $consulta->fetch();

        return $linha === false ? null : $this->hidratar($linha);
    }

    public function create(string $name, string $email, string $hash): int
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO admins (name, email, password) VALUES (:name, :email, :password)'
        );

        $consulta->bindValue(':name', trim($name));
        $consulta->bindValue(':email', mb_strtolower(trim($email)));
        $consulta->bindValue(':password', $hash);
        $consulta->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function emailExists(string $email): bool
    {
        $consulta = $this->pdo->prepare('SELECT 1 FROM admins WHERE email = :email LIMIT 1');

        $consulta->bindValue(':email', mb_strtolower(trim($email)));
        $consulta->execute();

        return $consulta->fetchColumn() !== false;
    }

    public function updatePassword(int $id, string $hash): void
    {
        $consulta = $this->pdo->prepare('UPDATE admins SET password = :password WHERE id = :id');

        $consulta->bindValue(':password', $hash);
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();
    }

    public function touchLastLogin(int $id): void
    {
        $consulta = $this->pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');

        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();
    }

    /**
     * @param array<string, mixed> $linha
     *
     * @return array<string, mixed>
     */
    private function hidratar(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'name' => (string) $linha['name'],
            'email' => (string) $linha['email'],
            'password' => (string) $linha['password'],
            'is_active' => (int) $linha['is_active'] === 1,
            'last_login_at' => $linha['last_login_at'],
            'created_at' => (string) $linha['created_at'],
        ];
    }
}
