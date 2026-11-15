<?php

declare(strict_types=1);

/*
 * Tentativas de login que falharam, base do bloqueio temporário.
 *
 * O e-mail não é guardado em texto: fica só o índice cego (HMAC), usado para
 * contar as falhas daquela conta. O IP entra em texto puro porque é a chave do
 * bloqueio por origem e já aparece nos logs do servidor.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE login_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email_index CHAR(64) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY login_attempts_email_index (email_index, created_at),
            KEY login_attempts_ip_index (ip, created_at),
            KEY login_attempts_created_index (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
