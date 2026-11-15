<?php

declare(strict_types=1);

/*
 * Variações de tamanho de cada produto (PP, P, M, G, GG).
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE product_variants (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id INT UNSIGNED NOT NULL,
            size VARCHAR(4) NOT NULL,
            sku VARCHAR(40) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_variants_sku_unique (sku),
            UNIQUE KEY product_variants_product_size_unique (product_id, size),
            CONSTRAINT product_variants_product_foreign FOREIGN KEY (product_id)
                REFERENCES products (id) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
