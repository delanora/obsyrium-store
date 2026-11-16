<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sessão do cliente: cookie HttpOnly, SameSite=Lax e Secure sob HTTPS.
 * O identificador é regenerado no login para impedir fixação de sessão.
 */
final class Session
{
    private const NAME = 'obsyrium_session';
    private const USER_KEY = 'user_id';
    private const ADMIN_KEY = 'admin_id';
    private const FLASH_KEY = '_flash';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => self::isHttps(),
        ]);

        session_name(self::NAME);
        session_start();
    }

    public static function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::isStarted() ? ($_SESSION[$key] ?? $default) : $default;
    }

    public static function forget(string $key): void
    {
        if (self::isStarted()) {
            unset($_SESSION[$key]);
        }
    }

    public static function login(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION[self::USER_KEY] = $userId;
        Csrf::rotate();
    }

    /**
     * Abre a sessão de administração sem tocar na sessão do cliente: o mesmo
     * navegador pode estar comprando na loja e operando o painel.
     */
    public static function loginAdmin(int $adminId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION[self::ADMIN_KEY] = $adminId;
        Csrf::rotate();
    }

    public static function adminId(): ?int
    {
        $id = self::get(self::ADMIN_KEY);

        return is_int($id) ? $id : null;
    }

    /**
     * Sai do painel sem derrubar a conta do cliente no mesmo navegador.
     */
    public static function adminLogout(): void
    {
        if (!self::isStarted()) {
            return;
        }

        unset($_SESSION[self::ADMIN_KEY]);
        session_regenerate_id(true);
        Csrf::rotate();
    }

    public static function userId(): ?int
    {
        $id = self::get(self::USER_KEY);

        return is_int($id) ? $id : null;
    }

    /**
     * Encerra só a sessão do cliente, sem derrubar um painel aberto no mesmo
     * navegador.
     */
    public static function forgetUser(): void
    {
        if (!self::isStarted()) {
            return;
        }

        unset($_SESSION[self::USER_KEY]);
        session_regenerate_id(true);
        Csrf::rotate();
    }

    public static function logout(): void
    {
        if (!self::isStarted()) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function flash(string $type, string $message): void
    {
        self::put(self::FLASH_KEY, ['type' => $type, 'message' => $message]);
    }

    /**
     * @return array{type: string, message: string}|null
     */
    public static function takeFlash(): ?array
    {
        if (!self::isStarted() || !isset($_SESSION[self::FLASH_KEY])) {
            return null;
        }

        $flash = $_SESSION[self::FLASH_KEY];
        unset($_SESSION[self::FLASH_KEY]);

        return is_array($flash) && isset($flash['type'], $flash['message']) ? $flash : null;
    }

    private static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return $https !== '' && strtolower((string) $https) !== 'off';
    }
}
