<?php

declare(strict_types=1);

/*
 * Cores numa lista própria, editável pelo painel.
 *
 * O campo de cor do produto deixou de ser texto livre: a tela escolhe entre as
 * cores cadastradas aqui e o "+ adicionar nova cor" grava neste tabela, então a
 * cor nova aparece no seletor das próximas vezes. `position` guarda a ordem de
 * exibição — as cores de fábrica primeiro, as que já estavam em uso no fim.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE product_colors (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(40) NOT NULL,
            position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_colors_name_unique (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $inserir = $pdo->prepare('INSERT IGNORE INTO product_colors (name, position) VALUES (:name, :position)');

    foreach (App\Services\ProductAdminService::COLORS as $indice => $cor) {
        $inserir->execute([':name' => $cor, ':position' => $indice + 1]);
    }

    // Cores que já estão em uso no catálogo e não estão na lista entram no fim.
    $pdo->exec(
        "INSERT IGNORE INTO product_colors (name, position)
         SELECT DISTINCT color, 100 FROM products WHERE color <> ''"
    );
};
