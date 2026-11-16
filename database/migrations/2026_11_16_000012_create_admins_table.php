<?php

declare(strict_types=1);

/*
 * Contas de administração da loja, separadas das contas de cliente.
 *
 * Um administrador não compra e não tem CPF: por isso a tabela própria, em vez
 * de um papel em `users`. A conta é criada pelo comando `database/admin.php`,
 * nunca por formulário público.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE admins (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY admins_email_unique (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
