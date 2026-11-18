<?php

declare(strict_types=1);

/*
 * Liga e desliga a categoria na loja sem apagar nada.
 *
 * Categoria desativada some do catálogo, dos filtros e das páginas
 * `/categoria/...`, mas continua no painel (com os produtos e as subcategorias
 * ligados a ela) para poder voltar quando quiser.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'ALTER TABLE categories
            ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER position,
            ADD KEY categories_active_index (is_active)'
    );
};
