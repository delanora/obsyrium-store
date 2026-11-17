<?php

declare(strict_types=1);

/*
 * Configurações da loja e mensagens do formulário de contato.
 *
 * `settings` é uma tabela chave/valor para o que a operação precisa mudar sem
 * deploy. Começa com os dois canais da página Contato, vazios: o painel
 * preenche o WhatsApp e o @ do Instagram, e a página passa a exibi-los.
 *
 * `contact_messages` guarda o que o cliente escreve no formulário. O e-mail
 * transacional ainda depende do domínio, então a mensagem fica no banco e é
 * lida pelo painel, em vez de se perder.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE settings (
            name VARCHAR(60) NOT NULL,
            value VARCHAR(255) NOT NULL DEFAULT \'\',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE contact_messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contact_messages_created_index (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $inserir = $pdo->prepare('INSERT IGNORE INTO settings (name, value) VALUES (:name, :value)');

    foreach (['contact_whatsapp', 'contact_instagram'] as $nome) {
        $inserir->execute([':name' => $nome, ':value' => '']);
    }
};
