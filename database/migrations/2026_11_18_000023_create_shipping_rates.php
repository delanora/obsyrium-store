<?php

declare(strict_types=1);

/*
 * Frete próprio: uma taxa por estado, com prazo em dias, mais as escolhas que
 * valem para a loja inteira (frete grátis a partir de um valor e retirada).
 *
 * A tabela nasce com os 27 estados a **zero**, ou seja, entrega grátis com
 * prazo a combinar: assim o checkout continua fechando pedido antes de a loja
 * configurar o frete, e nada é inventado no lugar dela. O painel edita preço e
 * prazo de cada estado.
 *
 * `orders.shipping_label` guarda o que o cliente escolheu ("Entrega · SP" ou
 * "Retirada na loja"), separado de `ship_label`, que já era o rótulo do
 * endereço.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE shipping_rates (
            state CHAR(2) NOT NULL,
            price_cents INT UNSIGNED NOT NULL DEFAULT 0,
            min_days TINYINT UNSIGNED NOT NULL DEFAULT 0,
            max_days TINYINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (state)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $estados = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
        'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    $inserir = $pdo->prepare(
        'INSERT IGNORE INTO shipping_rates (state, price_cents, min_days, max_days)
         VALUES (:uf, 0, 0, 0)'
    );

    foreach ($estados as $uf) {
        $inserir->execute([':uf' => $uf]);
    }

    $configuracoes = $pdo->prepare('INSERT IGNORE INTO settings (name, value) VALUES (:name, :value)');

    foreach ([
        'shipping_free_from_cents' => '0',
        'shipping_pickup_enabled' => '0',
        'shipping_pickup_text' => 'Retirada combinada com a loja pelo WhatsApp.',
    ] as $nome => $valor) {
        $configuracoes->execute([':name' => $nome, ':value' => $valor]);
    }

    $pdo->exec(
        "ALTER TABLE orders
            ADD COLUMN shipping_label VARCHAR(60) NOT NULL DEFAULT '' AFTER ship_label"
    );
};
