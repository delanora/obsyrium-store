<?php

declare(strict_types=1);

/*
 * Mensagens de contato ganham telefone e o estado da inbox.
 *
 * `phone` entra junto do e-mail: o cliente informa os dois no formulário.
 * `is_read` guarda se o painel já abriu a mensagem e `deleted_at` é a lixeira:
 * apagar é só preencher a data, então a mensagem pode ser restaurada. A
 * exclusão definitiva acontece só a partir da lixeira.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "ALTER TABLE contact_messages
            ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT '' AFTER email,
            ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER message,
            ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER is_read,
            ADD KEY contact_messages_status_index (deleted_at, is_read)"
    );
};
