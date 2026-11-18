<?php

declare(strict_types=1);

/*
 * Alarga `settings.value` de varchar(255) para TEXT.
 *
 * Os tokens do Melhor Envio são guardados cifrados: o refresh_token tem cerca
 * de mil caracteres e, depois do `Crypto`, passa fácil dos 255 da coluna. A
 * troca do código de autorização gravava o access_token e estourava no
 * refresh_token, derrubando o retorno da autorização com erro 500.
 */
return static function (PDO $pdo): void {
    $coluna = $pdo->query("SHOW COLUMNS FROM settings LIKE 'value'")->fetch();

    if (!is_array($coluna)) {
        return;
    }

    if (stripos((string) $coluna['Type'], 'text') !== false) {
        return;
    }

    $pdo->exec('ALTER TABLE settings MODIFY value TEXT NOT NULL');
};
