<?php

declare(strict_types=1);

/*
 * Categorias da vitrine (Camisetas, Lançamentos).
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(80) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY categories_slug_unique (slug),
            KEY categories_position_index (position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
