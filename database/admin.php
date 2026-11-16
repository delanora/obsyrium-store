<?php

declare(strict_types=1);

/*
 * Contas de administração pelo terminal.
 *
 *   php database/admin.php criar "Nome" email@daloja.com "senha com 10+"
 *   php database/admin.php senha email@daloja.com "nova senha com 10+"
 *   php database/admin.php listar
 *
 * Não existe cadastro público de administrador: a conta nasce aqui, com a
 * senha já em hash Argon2id.
 */

use App\Database\Connection;
use App\Repositories\AdminRepository;
use Config\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

/**
 * @return never
 */
function falhar(string $mensagem): void
{
    fwrite(STDERR, '  ' . $mensagem . PHP_EOL);
    exit(1);
}

$comando = (string) ($argv[1] ?? '');

try {
    $pdo = Connection::get();
} catch (Throwable $erro) {
    falhar('Não foi possível conectar ao banco de dados.');
}

$admins = new AdminRepository($pdo);

switch ($comando) {
    case 'criar':
        $nome = trim((string) ($argv[2] ?? ''));
        $email = mb_strtolower(trim((string) ($argv[3] ?? '')));
        $senha = (string) ($argv[4] ?? '');

        if (mb_strlen($nome) < 3) {
            falhar('Informe o nome completo.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            falhar('Informe um e-mail válido.');
        }

        if (mb_strlen($senha) < 10) {
            falhar('A senha do administrador precisa ter ao menos 10 caracteres.');
        }

        if ($admins->emailExists($email)) {
            falhar('Já existe um administrador com esse e-mail. Use "senha" para trocar a senha.');
        }

        $id = $admins->create($nome, $email, password_hash($senha, PASSWORD_ARGON2ID));
        printf("  administrador #%d criado: %s%s", $id, $email, PHP_EOL);
        break;

    case 'senha':
        $email = mb_strtolower(trim((string) ($argv[2] ?? '')));
        $senha = (string) ($argv[3] ?? '');

        if (mb_strlen($senha) < 10) {
            falhar('A senha do administrador precisa ter ao menos 10 caracteres.');
        }

        $admin = $admins->findByEmail($email);

        if ($admin === null) {
            falhar('Administrador não encontrado.');
        }

        $admins->updatePassword((int) $admin['id'], password_hash($senha, PASSWORD_ARGON2ID));
        printf("  senha de %s atualizada%s", $email, PHP_EOL);
        break;

    case 'listar':
        $linhas = $pdo->query('SELECT id, name, email, is_active, last_login_at FROM admins ORDER BY id')->fetchAll();

        if ($linhas === []) {
            echo '  nenhum administrador cadastrado' . PHP_EOL;
            break;
        }

        foreach ($linhas as $linha) {
            printf(
                "  #%d %-28s %-32s %s · último acesso %s%s",
                (int) $linha['id'],
                (string) $linha['name'],
                (string) $linha['email'],
                (int) $linha['is_active'] === 1 ? 'ativo' : 'inativo',
                $linha['last_login_at'] ?? 'nunca',
                PHP_EOL
            );
        }
        break;

    default:
        echo '  uso:' . PHP_EOL;
        echo '    php database/admin.php criar "Nome" email@daloja.com "senha"' . PHP_EOL;
        echo '    php database/admin.php senha email@daloja.com "senha"' . PHP_EOL;
        echo '    php database/admin.php listar' . PHP_EOL;
}
