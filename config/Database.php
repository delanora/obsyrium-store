<?php

declare(strict_types=1);

namespace Config;

use PDO;

final class Database
{
    public const CHARSET = 'utf8mb4';

    public static function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            Env::get('DB_HOST', '127.0.0.1'),
            Env::int('DB_PORT', 3306),
            Env::get('DB_DATABASE', ''),
            self::CHARSET
        );
    }

    public static function username(): string
    {
        return Env::get('DB_USERNAME', '');
    }

    public static function password(): string
    {
        return Env::get('DB_PASSWORD', '');
    }

    /**
     * @return array<int, mixed>
     */
    public static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
    }
}
