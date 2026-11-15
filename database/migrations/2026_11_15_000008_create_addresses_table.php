<?php

declare(strict_types=1);

/*
 * Endereços de entrega do cliente, reaproveitados nas próximas compras.
 *
 * Rua, número, complemento, bairro, cidade e destinatário ficam cifrados
 * (AES-256-GCM). CEP e UF continuam em texto puro porque são a base do
 * cálculo de frete e não identificam a residência sozinhos.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE addresses (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            label VARCHAR(40) NOT NULL DEFAULT \'Principal\',
            recipient_encrypted VARBINARY(512) NOT NULL,
            postal_code CHAR(8) NOT NULL,
            street_encrypted VARBINARY(512) NOT NULL,
            number_encrypted VARBINARY(255) NOT NULL,
            complement_encrypted VARBINARY(512) NULL,
            district_encrypted VARBINARY(512) NOT NULL,
            city_encrypted VARBINARY(512) NOT NULL,
            state CHAR(2) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY addresses_user_index (user_id, is_default),
            KEY addresses_postal_code_index (postal_code),
            CONSTRAINT addresses_user_foreign FOREIGN KEY (user_id)
                REFERENCES users (id) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
