<?php

declare(strict_types=1);

/*
 * Estoque por variação de tamanho. reserved guarda unidades presas em
 * carrinhos/checkout para não vender duas vezes a mesma peça.
 */
return static function (\PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE stock (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            variant_id INT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 0,
            reserved INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY stock_variant_unique (variant_id),
            CONSTRAINT stock_variant_foreign FOREIGN KEY (variant_id)
                REFERENCES product_variants (id) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT stock_reserved_check CHECK (reserved <= quantity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
