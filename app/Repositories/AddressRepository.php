<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Crypto;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Endereços de entrega do cliente.
 *
 * Destinatário, rua, número, complemento, bairro e cidade são cifrados na
 * gravação e decifrados na leitura, como o CPF e o telefone em
 * UserRepository. CEP e UF continuam em texto puro, como a tabela define.
 *
 * Toda gravação que mexe em mais de uma linha (trocar o principal, remover o
 * principal e promover outro) roda dentro de uma transação.
 */
final class AddressRepository
{
    private const COLUNAS = 'id, label, recipient_encrypted, postal_code, street_encrypted,
                    number_encrypted, complement_encrypted, district_encrypted, city_encrypted, state, is_default';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allForUser(int $userId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT ' . self::COLUNAS . '
               FROM addresses
              WHERE user_id = :user AND deleted_at IS NULL
              ORDER BY is_default DESC, id ASC'
        );
        $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
        $consulta->execute();

        return array_map(fn (array $linha): array => $this->hidratar($linha), $consulta->fetchAll());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT ' . self::COLUNAS . '
               FROM addresses
              WHERE id = :id AND user_id = :user AND deleted_at IS NULL
              LIMIT 1'
        );
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
        $consulta->execute();

        $linha = $consulta->fetch();

        return $linha === false ? null : $this->hidratar($linha);
    }

    /**
     * O primeiro endereço do cliente vira principal automaticamente.
     *
     * @param array<string, mixed> $dados
     */
    public function create(int $userId, array $dados): int
    {
        $this->pdo->beginTransaction();

        try {
            $principal = $this->countForUser($userId) === 0 || (bool) $dados['is_default'];

            if ($principal) {
                $this->limparPrincipal($userId);
            }

            $consulta = $this->pdo->prepare(
                'INSERT INTO addresses
                    (user_id, label, recipient_encrypted, postal_code, street_encrypted, number_encrypted,
                     complement_encrypted, district_encrypted, city_encrypted, state, is_default)
                 VALUES
                    (:user, :label, :recipient, :postal_code, :street, :number,
                     :complement, :district, :city, :state, :is_default)'
            );

            $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
            $this->bindDados($consulta, $dados);
            $consulta->bindValue(':is_default', $principal ? 1 : 0, PDO::PARAM_INT);
            $consulta->execute();

            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            return $id;
        } catch (Throwable $erro) {
            $this->pdo->rollBack();

            throw $erro;
        }
    }

    /**
     * O endereço principal continua principal mesmo que a caixa seja
     * desmarcada: o cadastro nunca fica sem um endereço de referência.
     *
     * @param array<string, mixed> $dados
     */
    public function update(int $id, int $userId, array $dados): void
    {
        $this->pdo->beginTransaction();

        try {
            $principal = (bool) $dados['is_default'] || $this->ehPrincipal($id, $userId);

            if ($principal) {
                $this->limparPrincipal($userId);
            }

            $consulta = $this->pdo->prepare(
                'UPDATE addresses
                    SET label = :label, recipient_encrypted = :recipient, postal_code = :postal_code,
                        street_encrypted = :street, number_encrypted = :number, complement_encrypted = :complement,
                        district_encrypted = :district, city_encrypted = :city, state = :state,
                        is_default = :is_default
                  WHERE id = :id AND user_id = :user AND deleted_at IS NULL'
            );

            $consulta->bindValue(':id', $id, PDO::PARAM_INT);
            $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
            $this->bindDados($consulta, $dados);
            $consulta->bindValue(':is_default', $principal ? 1 : 0, PDO::PARAM_INT);
            $consulta->execute();

            $this->pdo->commit();
        } catch (Throwable $erro) {
            $this->pdo->rollBack();

            throw $erro;
        }
    }

    /**
     * Exclusão lógica: o pedido antigo pode precisar do endereço depois.
     */
    public function remove(int $id, int $userId): bool
    {
        $this->pdo->beginTransaction();

        try {
            $eraPrincipal = $this->ehPrincipal($id, $userId);

            $consulta = $this->pdo->prepare(
                'UPDATE addresses SET deleted_at = NOW()
                  WHERE id = :id AND user_id = :user AND deleted_at IS NULL'
            );
            $consulta->bindValue(':id', $id, PDO::PARAM_INT);
            $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
            $consulta->execute();

            if ($consulta->rowCount() === 0) {
                $this->pdo->rollBack();

                return false;
            }

            if ($eraPrincipal) {
                $this->promoverPrimeiro($userId);
            }

            $this->pdo->commit();

            return true;
        } catch (Throwable $erro) {
            $this->pdo->rollBack();

            throw $erro;
        }
    }

    public function makeDefault(int $id, int $userId): bool
    {
        $this->pdo->beginTransaction();

        try {
            if ($this->findForUser($id, $userId) === null) {
                $this->pdo->rollBack();

                return false;
            }

            $this->limparPrincipal($userId);

            $consulta = $this->pdo->prepare(
                'UPDATE addresses SET is_default = 1
                  WHERE id = :id AND user_id = :user AND deleted_at IS NULL'
            );
            $consulta->bindValue(':id', $id, PDO::PARAM_INT);
            $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
            $consulta->execute();

            $this->pdo->commit();

            return true;
        } catch (Throwable $erro) {
            $this->pdo->rollBack();

            throw $erro;
        }
    }

    private function countForUser(int $userId): int
    {
        $consulta = $this->pdo->prepare(
            'SELECT COUNT(*) FROM addresses WHERE user_id = :user AND deleted_at IS NULL'
        );
        $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
        $consulta->execute();

        return (int) $consulta->fetchColumn();
    }

    private function ehPrincipal(int $id, int $userId): bool
    {
        $consulta = $this->pdo->prepare(
            'SELECT is_default FROM addresses
              WHERE id = :id AND user_id = :user AND deleted_at IS NULL
              LIMIT 1'
        );
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
        $consulta->execute();

        return (int) $consulta->fetchColumn() === 1;
    }

    private function limparPrincipal(int $userId): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE addresses SET is_default = 0 WHERE user_id = :user AND is_default = 1'
        );
        $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
        $consulta->execute();
    }

    private function promoverPrimeiro(int $userId): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE addresses SET is_default = 1
              WHERE user_id = :user AND deleted_at IS NULL
              ORDER BY id ASC
              LIMIT 1'
        );
        $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
        $consulta->execute();
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function bindDados(PDOStatement $consulta, array $dados): void
    {
        $consulta->bindValue(':label', $dados['label']);
        $consulta->bindValue(':recipient', Crypto::encrypt($dados['recipient']));
        $consulta->bindValue(':postal_code', $dados['postal_code']);
        $consulta->bindValue(':street', Crypto::encrypt($dados['street']));
        $consulta->bindValue(':number', Crypto::encrypt($dados['number']));

        if ($dados['complement'] === '') {
            $consulta->bindValue(':complement', null, PDO::PARAM_NULL);
        } else {
            $consulta->bindValue(':complement', Crypto::encrypt($dados['complement']));
        }

        $consulta->bindValue(':district', Crypto::encrypt($dados['district']));
        $consulta->bindValue(':city', Crypto::encrypt($dados['city']));
        $consulta->bindValue(':state', $dados['state']);
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
            'label' => (string) $linha['label'],
            'recipient' => Crypto::decrypt((string) $linha['recipient_encrypted']),
            'postal_code' => (string) $linha['postal_code'],
            'street' => Crypto::decrypt((string) $linha['street_encrypted']),
            'number' => Crypto::decrypt((string) $linha['number_encrypted']),
            'complement' => $linha['complement_encrypted'] !== null
                ? Crypto::decrypt((string) $linha['complement_encrypted'])
                : '',
            'district' => Crypto::decrypt((string) $linha['district_encrypted']),
            'city' => Crypto::decrypt((string) $linha['city_encrypted']),
            'state' => (string) $linha['state'],
            'is_default' => (int) $linha['is_default'] === 1,
        ];
    }
}
