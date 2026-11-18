<?php

declare(strict_types=1);

/*
 * Mensagens ganham a origem: o formulário do Contato e o "Tem alguma ideia?"
 * da home gravam na mesma caixa, e o painel mostra qual é qual.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "ALTER TABLE contact_messages
            ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'contato' AFTER phone"
    );
};