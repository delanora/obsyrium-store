<?php

declare(strict_types=1);

/*
 * Carrinho de compras: um carrinho aberto por cliente e um item por variação.
 *
 * O preço não é copiado para cá — o total é sempre lido de products.price_cents,
 * para que uma mudança de preço valha na hora. Quem guarda o preço praticado é
 * o pedido, na hora do fechamento.
 */
return static function (PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE carts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY carts_user_unique (user_id),
            CONSTRAINT carts_user_foreign FOREIGN KEY (user_id)
                REFERENCES users (id) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE cart_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            cart_id INT UNSIGNED NOT NULL,
            variant_id INT UNSIGNED NOT NULL,
            quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cart_items_variant_unique (cart_id, variant_id),
            KEY cart_items_variant_index (variant_id),
            CONSTRAINT cart_items_cart_foreign FOREIGN KEY (cart_id)
                REFERENCES carts (id) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT cart_items_variant_foreign FOREIGN KEY (variant_id)
                REFERENCES product_variants (id) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
