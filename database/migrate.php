<?php

declare(strict_types=1);

/*
 * Executor de migrations do projeto.
 *
 *   php database/migrate.php            aplica as migrations pendentes
 *   php database/migrate.php --status   mostra o estado de cada arquivo
 *
 * Cada arquivo em database/migrations/ devolve um callable que recebe o PDO
 * e cria/altera o schema. O controle do que já rodou fica na tabela
 * `migrations`, por nome de arquivo.
 */

use App\Database\Connection;
use Config\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$status = isset($argv[1]) && $argv[1] === '--status';

try {
    $pdo = Connection::get();
} catch (Throwable $erro) {
    fwrite(STDERR, 'Não foi possível conectar ao banco de dados.' . PHP_EOL);
    exit(1);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration VARCHAR(190) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY migrations_migration_unique (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$aplicadas = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$arquivos = glob(__DIR__ . '/migrations/*.php');
sort($arquivos);

$executadas = 0;

foreach ($arquivos as $arquivo) {
    $nome = basename($arquivo);
    $jaAplicada = in_array($nome, $aplicadas, true);

    if ($status) {
        printf("  %-58s %s\n", $nome, $jaAplicada ? 'aplicada' : 'pendente');
        continue;
    }

    if ($jaAplicada) {
        continue;
    }

    $migration = require $arquivo;

    if (!is_callable($migration)) {
        fwrite(STDERR, "Migration inválida (não devolve callable): {$nome}" . PHP_EOL);
        exit(1);
    }

    try {
        $migration($pdo);
    } catch (Throwable $erro) {
        fwrite(STDERR, "Falhou em {$nome}: " . $erro->getMessage() . PHP_EOL);
        exit(1);
    }

    $pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)')
        ->execute(['migration' => $nome]);

    echo "  aplicada: {$nome}" . PHP_EOL;
    $executadas++;
}

if (!$status) {
    echo $executadas === 0
        ? '  Nenhuma migration pendente.' . PHP_EOL
        : "  Concluído: {$executadas} migration(s)." . PHP_EOL;
}
