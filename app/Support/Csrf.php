<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Token CSRF por sessão, comparado com hash_equals (tempo constante).
 */
final class Csrf
{
    private const KEY = '_csrf';
    public const FIELD = '_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        $token = is_string($token) && $token !== '' ? $token : bin2hex(random_bytes(32));

        Session::put(self::KEY, $token);

        return $token;
    }

    /**
     * Troca o token da sessão. Chamado no login para que um token obtido
     * antes da autenticação não valha depois dela.
     */
    public static function rotate(): void
    {
        Session::put(self::KEY, bin2hex(random_bytes(32)));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . self::token() . '">';
    }

    public static function validate(mixed $token): bool
    {
        $expected = Session::get(self::KEY);

        return is_string($token)
            && $token !== ''
            && is_string($expected)
            && hash_equals($expected, $token);
    }
}
