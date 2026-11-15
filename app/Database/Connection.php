<?php

declare(strict_types=1);

namespace App\Database;

use Config\Database as DatabaseConfig;
use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    private static ?PDO $connection = null;

    public static function get(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        try {
            self::$connection = new PDO(
                DatabaseConfig::dsn(),
                DatabaseConfig::username(),
                DatabaseConfig::password(),
                DatabaseConfig::options()
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Falha ao conectar ao banco de dados.', 0, $exception);
        }

        return self::$connection;
    }
}
