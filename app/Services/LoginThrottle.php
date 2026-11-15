<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LoginAttemptRepository;
use App\Support\Crypto;

/**
 * Bloqueio temporário de login por conta e por origem.
 *
 * Cada falha é registrada; quando a conta acumula 5 falhas ou o IP acumula 20
 * dentro de 15 minutos, o login fica bloqueado até a falha mais antiga dessa
 * janela expirar. Um login bem-sucedido limpa as falhas da conta.
 */
final class LoginThrottle
{
    private const WINDOW_MINUTES = 15;
    private const MAX_PER_EMAIL = 5;
    private const MAX_PER_IP = 20;
    private const KEEP_HOURS = 24;

    public function __construct(private readonly LoginAttemptRepository $attempts)
    {
    }

    /**
     * @return int|null segundos restantes de bloqueio, ou null quando liberado
     */
    public function blockedFor(string $email, string $ip): ?int
    {
        if ($email === '') {
            return null;
        }

        $porEmail = $this->attempts->failuresByEmail($this->emailIndex($email), self::WINDOW_MINUTES);

        if ($porEmail['total'] >= self::MAX_PER_EMAIL) {
            return $this->remaining($porEmail['idade']);
        }

        if ($ip !== '') {
            $porIp = $this->attempts->failuresByIp($ip, self::WINDOW_MINUTES);

            if ($porIp['total'] >= self::MAX_PER_IP) {
                return $this->remaining($porIp['idade']);
            }
        }

        return null;
    }

    public function recordFailure(string $email, string $ip): void
    {
        if ($email === '') {
            return;
        }

        $this->attempts->record($this->emailIndex($email), $ip);
        $this->attempts->purgeBefore(self::KEEP_HOURS * 60);
    }

    public function clear(string $email): void
    {
        if ($email === '') {
            return;
        }

        $this->attempts->clearForEmail($this->emailIndex($email));
    }

    public static function describe(int $segundos): string
    {
        $minutos = max(1, (int) ceil($segundos / 60));

        return sprintf(
            'Muitas tentativas de login. Tente novamente em %d minuto%s.',
            $minutos,
            $minutos === 1 ? '' : 's'
        );
    }

    private function emailIndex(string $email): string
    {
        return Crypto::blindIndex(mb_strtolower(trim($email)), 'login');
    }

    /**
     * @param int|null $idade segundos desde a falha mais antiga da janela
     */
    private function remaining(?int $idade): int
    {
        if ($idade === null) {
            return self::WINDOW_MINUTES * 60;
        }

        return max(1, self::WINDOW_MINUTES * 60 - $idade);
    }
}
