<?php

declare(strict_types=1);

/*
 * Cifra os dados sensíveis do cliente e completa o cadastro.
 *
 * O CPF deixa de existir em texto puro: passa a ser guardado cifrado
 * (AES-256-GCM) e acompanhado de um índice cego (HMAC-SHA256) usado para a
 * regra de unicidade e para busca. O telefone segue o mesmo caminho.
 * A senha continua como hash — hash não é cifra, é via de mão única.
 */
return static function (PDO $pdo): void {
    $pdo->exec('ALTER TABLE users DROP CONSTRAINT users_cpf_check');
    $pdo->exec('ALTER TABLE users DROP INDEX users_cpf_unique');
    $pdo->exec('ALTER TABLE users CHANGE COLUMN cpf cpf_encrypted VARBINARY(255) NOT NULL');
    $pdo->exec('ALTER TABLE users ADD COLUMN cpf_index CHAR(64) NOT NULL AFTER cpf_encrypted');
    $pdo->exec('ALTER TABLE users ADD COLUMN phone_encrypted VARBINARY(512) NULL AFTER password');
    $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER is_active');
    $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER email_verified_at');
    $pdo->exec('ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER last_login_at');
    $pdo->exec('ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL AFTER password_changed_at');
    $pdo->exec('ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL AFTER terms_accepted_at');
    $pdo->exec('ALTER TABLE users ADD UNIQUE KEY users_cpf_index_unique (cpf_index)');
    $pdo->exec('ALTER TABLE users ADD KEY users_deleted_index (deleted_at)');
};
