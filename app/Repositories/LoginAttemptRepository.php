<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Falhas de login por conta (índice cego do e-mail) e por IP.
 *
 * As janelas de tempo usam o relógio do banco (`NOW()`), não o do PHP: os dois
 * podem estar em fusos diferentes, e um corte calculado no PHP poderia cair no
 * futuro e desligar o bloqueio sem ninguém notar.
 */
final class LoginAttemptRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(string $emailIndex, string $ip): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO login_attempts (email_index, ip) VALUES (:email_index, :ip)'
        );

        $consulta->bindValue(':email_index', $emailIndex);
        $consulta->bindValue(':ip', $ip);
        $consulta->execute();
    }

    /**
     * @return array{total: int, idade: int|null} idade em segundos da falha mais antiga
     */
    public function failuresByEmail(string $emailIndex, int $janelaMinutos): array
    {
        return $this->count('email_index = :valor', $emailIndex, $janelaMinutos);
    }

    /**
     * @return array{total: int, idade: int|null}
     */
    public function failuresByIp(string $ip, int $janelaMinutos): array
    {
        return $this->count('ip = :valor', $ip, $janelaMinutos);
    }

    public function clearForEmail(string $emailIndex): void
    {
        $consulta = $this->pdo->prepare('DELETE FROM login_attempts WHERE email_index = :email_index');
        $consulta->bindValue(':email_index', $emailIndex);
        $consulta->execute();
    }

    public function purgeBefore(int $minutos): void
    {
        $janela = max(1, $minutos);

        $this->pdo->exec(
            "DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL {$janela} MINUTE)"
        );
    }

    /**
     * A condição é literal deste arquivo e a janela é um inteiro do código —
     * nenhum dos dois vem da requisição.
     *
     * @return array{total: int, idade: int|null}
     */
    private function count(string $condicao, string $valor, int $janelaMinutos): array
    {
        $janela = max(1, $janelaMinutos);

        $consulta = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                    TIMESTAMPDIFF(SECOND, MIN(created_at), NOW()) AS idade
               FROM login_attempts
              WHERE ' . $condicao . '
                AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $janela . ' MINUTE)'
        );

        $consulta->bindValue(':valor', $valor);
        $consulta->execute();

        $linha = $consulta->fetch();

        return [
            'total' => (int) ($linha['total'] ?? 0),
            'idade' => $linha['idade'] !== null ? (int) $linha['idade'] : null,
        ];
    }
}
