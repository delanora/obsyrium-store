<?php

declare(strict_types=1);

/*
 * Clientes da loja. Guarda apenas o necessário para identificar a pessoa e
 * autenticá-la: o CPF fica só com os 11 dígitos e a senha é sempre um hash
 * gerado por password_hash() — nunca a senha em texto puro.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            cpf CHAR(11) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY users_cpf_unique (cpf),
            UNIQUE KEY users_email_unique (email),
            KEY users_created_index (created_at),
            CONSTRAINT users_cpf_check CHECK (cpf REGEXP \'^[0-9]{11}$\')
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
