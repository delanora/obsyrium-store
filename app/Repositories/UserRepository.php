<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Crypto;
use PDO;

/**
 * Cliente da loja. Os campos sensíveis entram e saem daqui já tratados:
 * CPF e telefone são cifrados na gravação e decifrados na leitura.
 */
final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function emailExists(string $email): bool
    {
        $consulta = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
        $consulta->bindValue(':email', $email);
        $consulta->execute();

        return $consulta->fetchColumn() !== false;
    }

    public function cpfExists(string $cpf): bool
    {
        $consulta = $this->pdo->prepare('SELECT 1 FROM users WHERE cpf_index = :indice LIMIT 1');
        $consulta->bindValue(':indice', Crypto::cpfIndex($cpf));
        $consulta->execute();

        return $consulta->fetchColumn() !== false;
    }

    /**
     * @param array{name: string, cpf: string, phone: string, email: string, password: string} $dados
     */
    public function create(array $dados): int
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO users (name, cpf_encrypted, cpf_index, email, password, phone_encrypted, terms_accepted_at)
             VALUES (:name, :cpf, :cpf_index, :email, :password, :phone, NOW())'
        );

        $consulta->bindValue(':name', $dados['name']);
        $consulta->bindValue(':cpf', Crypto::encrypt($dados['cpf']));
        $consulta->bindValue(':cpf_index', Crypto::cpfIndex($dados['cpf']));
        $consulta->bindValue(':email', $dados['email']);
        $consulta->bindValue(':password', $dados['password']);
        $consulta->bindValue(':phone', Crypto::encrypt($dados['phone']));
        $consulta->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->find('email = :valor', $email);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->find('id = :valor', $id);
    }

    /**
     * Nome e telefone mudam pelo perfil do cliente. O telefone continua cifrado
     * e pode ser apagado (null) quando o cliente limpa o campo.
     */
    public function updateProfile(int $id, string $name, ?string $phone): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE users SET name = :name, phone_encrypted = :phone WHERE id = :id'
        );

        $consulta->bindValue(':name', $name);

        if ($phone === null) {
            $consulta->bindValue(':phone', null, PDO::PARAM_NULL);
        } else {
            $consulta->bindValue(':phone', Crypto::encrypt($phone));
        }

        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();
    }

    public function touchLastLogin(int $id): void
    {
        $consulta = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $consulta = $this->pdo->prepare('UPDATE users SET password = :hash, password_changed_at = NOW() WHERE id = :id');
        $consulta->bindValue(':hash', $hash);
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();
    }

    /**
     * A condição é sempre literal deste arquivo — nunca vem da requisição.
     *
     * @return array<string, mixed>|null
     */
    private function find(string $condicao, int|string $valor): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, name, cpf_encrypted, email, password, phone_encrypted, is_active, deleted_at
               FROM users
              WHERE ' . $condicao . '
              LIMIT 1'
        );

        $consulta->bindValue(':valor', $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $consulta->execute();

        $linha = $consulta->fetch();

        if ($linha === false) {
            return null;
        }

        return [
            'id' => (int) $linha['id'],
            'name' => (string) $linha['name'],
            'cpf' => Crypto::decrypt((string) $linha['cpf_encrypted']),
            'phone' => $linha['phone_encrypted'] !== null ? Crypto::decrypt((string) $linha['phone_encrypted']) : '',
            'email' => (string) $linha['email'],
            'password' => (string) $linha['password'],
            'is_active' => (int) $linha['is_active'] === 1,
            'deleted_at' => $linha['deleted_at'],
        ];
    }
}
