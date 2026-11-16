<?php

declare(strict_types=1);

/*
 * Categorias com subcategorias e produtos em mais de uma categoria.
 *
 * `categories.parent_id` cria o segundo nível (categoria → subcategoria);
 * `product_categories` guarda os vínculos. `products.category_id` continua
 * sendo a categoria principal (a primeira escolhida), por isso todo produto
 * segue aparecendo na vitrine e na página do item sem consulta extra.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'ALTER TABLE categories
            ADD COLUMN parent_id INT UNSIGNED NULL AFTER id,
            ADD KEY categories_parent_index (parent_id),
            ADD CONSTRAINT categories_parent_foreign FOREIGN KEY (parent_id)
                REFERENCES categories (id) ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $pdo->exec(
        'CREATE TABLE product_categories (
            product_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (product_id, category_id),
            KEY product_categories_category_index (category_id, position),
            CONSTRAINT product_categories_product_foreign FOREIGN KEY (product_id)
                REFERENCES products (id) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT product_categories_category_foreign FOREIGN KEY (category_id)
                REFERENCES categories (id) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'INSERT IGNORE INTO product_categories (product_id, category_id, position)
         SELECT id, category_id, 0 FROM products'
    );
};
