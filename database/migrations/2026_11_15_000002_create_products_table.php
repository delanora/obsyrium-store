<?php

declare(strict_types=1);

/*
 * Produtos da loja. Preço em centavos para evitar arredondamento em float;
 * compare_price_cents guarda o valor "de" quando a peça está em oferta.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE products (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL,
            color VARCHAR(40) NOT NULL,
            material VARCHAR(80) NOT NULL DEFAULT \'algodão 100%\',
            description TEXT NULL,
            price_cents INT UNSIGNED NOT NULL,
            compare_price_cents INT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY products_slug_unique (slug),
            KEY products_category_index (category_id),
            KEY products_showcase_index (is_active, created_at),
            CONSTRAINT products_category_foreign FOREIGN KEY (category_id)
                REFERENCES categories (id) ON UPDATE CASCADE ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
