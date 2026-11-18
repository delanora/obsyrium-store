<?php

declare(strict_types=1);

/*
 * Código de rastreio do pedido, preenchido pelo painel quando a peça é
 * postada. Fica separado do endereço e das situações: o cliente vê o código na
 * aba Compras, junto do pedido.
 *
 * A coluna nasce vazia e o padrão é `''` para o painel não precisar lidar com
 * NULL ao salvar.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "ALTER TABLE orders
            ADD COLUMN tracking_code VARCHAR(60) NOT NULL DEFAULT '' AFTER status"
    );
};
