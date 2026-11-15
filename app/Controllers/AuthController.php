<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\LoginAttemptRepository;
use App\Services\LoginThrottle;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Support\Csrf;
use App\Support\Session;

/**
 * Cadastro, login e saída do cliente. Toda escrita exige token CSRF e o
 * redirecionamento pós-POST usa 303 para o navegador não repetir o envio.
 */
final class AuthController
{
    private const TITLE_REGISTER = 'Criar conta — Obsyrium Store';
    private const TITLE_LOGIN = 'Entrar — Obsyrium Store';

    public function showRegister(): void
    {
        $this->render('register', self::TITLE_REGISTER);
    }

    public function register(): void
    {
        if (!$this->csrfOk()) {
            return;
        }

        $erros = $this->service()->register($_POST);

        if ($erros === []) {
            Session::flash('success', 'Conta criada. Você já está conectado.');
            $this->redirect('/');
        }

        $this->render('register', self::TITLE_REGISTER, $erros, $_POST);
    }

    public function showLogin(): void
    {
        if ($this->service()->currentUser() !== null) {
            $this->redirect('/');
        }

        $this->render('login', self::TITLE_LOGIN);
    }

    public function login(): void
    {
        if (!$this->csrfOk()) {
            return;
        }

        $erro = $this->service()->login($_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ($erro === null) {
            Session::flash('success', 'Bem-vindo de volta.');
            $this->redirect('/');
        }

        $this->render('login', self::TITLE_LOGIN, ['credentials' => $erro], [
            'email' => (string) ($_POST['email'] ?? ''),
        ]);
    }

    public function logout(): void
    {
        if (!$this->csrfOk()) {
            return;
        }

        $this->service()->logout();
        Session::flash('success', 'Você saiu da conta.');
        $this->redirect('/');
    }

    private function service(): AuthService
    {
        return new AuthService(
            new UserRepository(Connection::get()),
            new LoginThrottle(new LoginAttemptRepository(Connection::get()))
        );
    }

    private function csrfOk(): bool
    {
        if (Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
            return true;
        }

        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');

        require dirname(__DIR__, 2) . '/resources/views/errors/403.php';

        return false;
    }

    /**
     * @param array<string, string> $erros
     * @param array<string, mixed> $antigo
     */
    private function render(string $view, string $title, array $erros = [], array $antigo = []): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $errors = $erros;
        $old = $antigo;
        $content = $basePath . '/resources/views/auth/' . $view . '.php';

        require $basePath . '/resources/views/layouts/site.php';
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
