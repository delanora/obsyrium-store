<?php

declare(strict_types=1);

/*
 * Fotos do topo da página inicial.
 *
 * Cada linha é um slide do carrossel da home: a foto é obrigatória e o texto é
 * opcional. Todos os campos de texto vazios significam "sem texto": a loja
 * mostra só a foto, sem véu escuro e sem botão.
 *
 * A imagem fica em `public/uploads/hero` e a coluna guarda o caminho público.
 * `position` é a ordem de envio e `is_active` permite esconder uma foto sem
 * apagar o arquivo.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE hero_slides (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            image VARCHAR(255) NOT NULL,
            image_alt VARCHAR(160) NOT NULL DEFAULT '',
            eyebrow VARCHAR(80) NOT NULL DEFAULT '',
            title VARCHAR(160) NOT NULL DEFAULT '',
            text VARCHAR(400) NOT NULL DEFAULT '',
            button_label VARCHAR(60) NOT NULL DEFAULT '',
            button_href VARCHAR(255) NOT NULL DEFAULT '',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY hero_slides_position_index (position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
