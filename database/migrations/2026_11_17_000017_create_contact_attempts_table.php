<?php

declare(strict_types=1);

/*
 * Tentativas de envio do formulário de contato, para limitar por IP.
 *
 * O IP não é guardado em claro: entra como SHA-256 em `ip_hash`, que já basta
 * para contar quantos envios vieram do mesmo lugar. As linhas antigas são
 * apagadas na hora de registrar a próxima, então a tabela não cresce.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE contact_attempts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contact_attempts_ip_index (ip_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
