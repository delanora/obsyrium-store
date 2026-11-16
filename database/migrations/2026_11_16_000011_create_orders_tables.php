<?php

declare(strict_types=1);

/*
 * Pedidos: o retrato do que o cliente comprou, independente do carrinho.
 *
 * Preço, nome, tamanho e SKU são copiados para order_items, e o endereço de
 * entrega é copiado cifrado para orders. Mudar o preço na loja ou o endereço
 * no perfil depois não reescreve o histórico.
 *
 * payments nasce vazia: o gateway entra na Fase 6 e grava aqui a cobrança,
 * ligada ao pedido por order_id.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE orders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            number VARCHAR(20) NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'aguardando_pagamento\',
            pieces SMALLINT UNSIGNED NOT NULL,
            subtotal_cents INT UNSIGNED NOT NULL,
            shipping_cents INT UNSIGNED NOT NULL DEFAULT 0,
            total_cents INT UNSIGNED NOT NULL,
            ship_label VARCHAR(40) NOT NULL,
            ship_recipient_encrypted VARBINARY(512) NOT NULL,
            ship_postal_code CHAR(8) NOT NULL,
            ship_street_encrypted VARBINARY(512) NOT NULL,
            ship_number_encrypted VARBINARY(255) NOT NULL,
            ship_complement_encrypted VARBINARY(512) NULL,
            ship_district_encrypted VARBINARY(512) NOT NULL,
            ship_city_encrypted VARBINARY(512) NOT NULL,
            ship_state CHAR(2) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY orders_number_unique (number),
            KEY orders_user_index (user_id, created_at),
            KEY orders_status_index (status, created_at),
            CONSTRAINT orders_user_foreign FOREIGN KEY (user_id)
                REFERENCES users (id) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE order_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id INT UNSIGNED NOT NULL,
            variant_id INT UNSIGNED NULL,
            product_name VARCHAR(150) NOT NULL,
            product_slug VARCHAR(160) NOT NULL,
            variant_size VARCHAR(4) NOT NULL,
            sku VARCHAR(40) NOT NULL,
            unit_price_cents INT UNSIGNED NOT NULL,
            quantity SMALLINT UNSIGNED NOT NULL,
            line_total_cents INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_items_order_index (order_id),
            KEY order_items_variant_index (variant_id),
            CONSTRAINT order_items_order_foreign FOREIGN KEY (order_id)
                REFERENCES orders (id) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT order_items_variant_foreign FOREIGN KEY (variant_id)
                REFERENCES product_variants (id) ON UPDATE CASCADE ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE payments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id INT UNSIGNED NOT NULL,
            provider VARCHAR(30) NOT NULL,
            method VARCHAR(30) NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pendente\',
            amount_cents INT UNSIGNED NOT NULL,
            external_id VARCHAR(120) NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY payments_external_unique (provider, external_id),
            KEY payments_order_index (order_id),
            CONSTRAINT payments_order_foreign FOREIGN KEY (order_id)
                REFERENCES orders (id) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
