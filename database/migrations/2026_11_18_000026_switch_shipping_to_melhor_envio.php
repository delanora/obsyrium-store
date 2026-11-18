<?php

declare(strict_types=1);

/*
 * Troca da cotação dos Correios pelo Melhor Envio.
 *
 * A 000025 guardava credenciais do CWS (usuário, senha, contrato e DR). Como a
 * loja passou a cotar pelo Melhor Envio, essas linhas saem e entram as do
 * aplicativo OAuth (client_id, client_secret, e-mail do User-Agent, sandbox e
 * tokens). A tabela `shipping_quotes` continua a mesma: é só cache.
 *
 * Se alguém já tinha escolhido o modo `correios`, ele passa a valer
 * `melhorenvio`; enquanto não houver conexão, o checkout segue na tabela por
 * estado.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "DELETE FROM settings
          WHERE name IN (
              'shipping_correios_user',
              'shipping_correios_password',
              'shipping_correios_contract',
              'shipping_correios_dr'
          )"
    );

    $pdo->exec(
        "UPDATE settings SET value = 'melhorenvio'
          WHERE name = 'shipping_mode' AND value = 'correios'"
    );

    $configuracoes = $pdo->prepare('INSERT IGNORE INTO settings (name, value) VALUES (:name, :value)');

    foreach ([
        'shipping_melhorenvio_client_id' => '',
        'shipping_melhorenvio_client_secret' => '',
        'shipping_melhorenvio_email' => '',
        'shipping_melhorenvio_sandbox' => '0',
        'shipping_melhorenvio_access_token' => '',
        'shipping_melhorenvio_refresh_token' => '',
        'shipping_melhorenvio_expires_at' => '0',
    ] as $nome => $valor) {
        $configuracoes->execute([':name' => $nome, ':value' => $valor]);
    }
};
