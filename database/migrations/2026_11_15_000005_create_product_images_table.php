<?php

declare(strict_types=1);

/*
 * Imagens do produto. position define a ordem; a menor é a principal.
 */
return static function (\PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE product_images (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id INT UNSIGNED NOT NULL,
            path VARCHAR(255) NOT NULL,
            alt VARCHAR(160) NOT NULL DEFAULT \'\',
            position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_images_product_index (product_id, position),
            CONSTRAINT product_images_product_foreign FOREIGN KEY (product_id)
                REFERENCES products (id) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
