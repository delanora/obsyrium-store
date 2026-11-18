<?php

declare(strict_types=1);

/*
 * Frete automático: peso por produto, configuração dos Correios e cache.
 *
 * `products.weight_grams` guarda o peso da peça embalada — é ele que entra na
 * soma do carrinho enviada aos Correios. O padrão de 300 g é o mínimo aceito
 * por PAC e SEDEX, então um produto sem peso cadastrado ainda cota.
 *
 * As configurações nascem no modo `estado`: a loja segue vendendo com a tabela
 * por estado (toda em zero) até alguém preencher contrato e credenciais do CWS
 * no painel. A senha é gravada cifrada, com a mesma Crypto dos endereços.
 *
 * `shipping_quotes` é só cache: token dos Correios e o preço/prazo por
 * CEP + peso, com validade. Apagar essas linhas não perde nada de verdade.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'ALTER TABLE products
            ADD COLUMN weight_grams INT UNSIGNED NOT NULL DEFAULT 300 AFTER material'
    );

    $pdo->exec(
        'CREATE TABLE shipping_quotes (
            cache_key CHAR(64) NOT NULL,
            payload MEDIUMTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (cache_key),
            KEY shipping_quotes_expires_index (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $configuracoes = $pdo->prepare('INSERT IGNORE INTO settings (name, value) VALUES (:name, :value)');

    foreach ([
        'shipping_mode' => 'estado',
        'shipping_origin_postal_code' => '',
        'shipping_correios_user' => '',
        'shipping_correios_password' => '',
        'shipping_correios_contract' => '',
        'shipping_correios_dr' => '0',
        'shipping_default_weight_grams' => '300',
    ] as $nome => $valor) {
        $configuracoes->execute([':name' => $nome, ':value' => $valor]);
    }
};
