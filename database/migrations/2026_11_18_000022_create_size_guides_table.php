<?php

declare(strict_types=1);

/*
 * Guia de medidas: uma linha por tamanho, com a largura e o comprimento da peça
 * em centímetros. As medidas nascem vazias (são da peça real, quem mede é a
 * loja, pelo painel); enquanto nenhuma estiver preenchida, o guia não aparece
 * na página do produto.
 *
 * Os tamanhos são os que a loja usa no seletor, de PP a XG.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE size_guides (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(10) NOT NULL,
            chest_cm SMALLINT UNSIGNED NULL,
            length_cm SMALLINT UNSIGNED NULL,
            position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY size_guides_label_unique (label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $inserir = $pdo->prepare(
        'INSERT INTO size_guides (label, position) VALUES (:label, :position)'
    );

    foreach (['PP', 'P', 'M', 'G', 'GG', 'XG'] as $posicao => $tamanho) {
        $inserir->execute([':label' => $tamanho, ':position' => $posicao]);
    }
};
