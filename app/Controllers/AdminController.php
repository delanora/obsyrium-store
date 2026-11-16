<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\AdminRepository;
use App\Repositories\LoginAttemptRepository;
use App\Services\AdminAuthService;
use App\Services\LoginThrottle;
use App\Services\OrderService;
use App\Support\Csrf;
use App\Support\Session;

/**
 * Painel da administração: login e operação de pedidos.
 *
 * Toda rota além do login passa por `admin()`, que confere a sessão de
 * administrador antes de qualquer leitura. Toda escrita exige CSRF e responde
 * com 303 para o navegador não repetir o envio.
 */
final class AdminController
{
    private const TITLE_LOGIN = 'Entrar — Administração Obsyrium';

    private const TITLE_ORDERS = 'Pedidos — Administração Obsyrium';

    public function showLogin(): void
    {
        if ($this->auth()->currentAdmin() !== null) {
            $this->redirect('/admin/pedidos');
        }

        $this->renderLogin();
    }

    public function login(): void
    {
        if (!$this->csrfOk()) {
            return;
        }

        $erro = $this->auth()->login($_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ($erro === null) {
            Session::flash('success', 'Painel aberto.');
            $this->redirect('/admin/pedidos');
        }

        $this->renderLogin(['credentials' => $erro], (string) ($_POST['email'] ?? ''));
    }

    public function logout(): void
    {
        if (!$this->csrfOk()) {
            return;
        }

        $this->auth()->logout();
        Session::flash('success', 'Você saiu do painel.');
        $this->redirect('/admin/login');
    }

    public function orders(): void
    {
        $admin = $this->admin();
        $status = isset($_GET['status']) ? (string) $_GET['status'] : '';

        $this->render('orders', self::TITLE_ORDERS, $admin, [
            'pedidos' => (new OrderService(Connection::get()))->adminOrders($status),
            'filtro' => isset(OrderService::STATUS_LABELS[$status]) ? $status : '',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function order(array $params): void
    {
        $admin = $this->admin();
        $numero = (string) ($params['number'] ?? '');
        $pedido = (new OrderService(Connection::get()))->adminOrder($numero);

        if ($pedido === null) {
            Session::flash('error', 'Pedido não encontrado.');
            $this->redirect('/admin/pedidos');
        }

        $this->render('order', sprintf('Pedido %s — Administração Obsyrium', $numero), $admin, [
            'pedido' => $pedido,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function changeStatus(array $params): void
    {
        $this->admin();

        $numero = (string) ($params['number'] ?? '');

        if (!$this->csrfOk()) {
            return;
        }

        $erro = (new OrderService(Connection::get()))
            ->changeStatus($numero, (string) ($_POST['status'] ?? ''));

        if ($erro !== null) {
            Session::flash('error', $erro);
        } else {
            Session::flash('success', sprintf(
                'Pedido %s agora está como %s.',
                $numero,
                mb_strtolower(OrderService::statusLabel((string) ($_POST['status'] ?? '')))
            ));
        }

        $this->redirect('/admin/pedidos/' . rawurlencode($numero));
    }

    /**
     * @return array<string, mixed>
     */
    private function admin(): array
    {
        $admin = $this->auth()->currentAdmin();

        if ($admin === null) {
            Session::flash('error', 'Entre com uma conta de administração.');
            $this->redirect('/admin/login');
        }

        return $admin;
    }

    private function auth(): AdminAuthService
    {
        $pdo = Connection::get();

        return new AdminAuthService(new AdminRepository($pdo), new LoginThrottle(new LoginAttemptRepository($pdo)));
    }

    /**
     * @param array<string, string> $erros
     */
    private function renderLogin(array $erros = [], string $email = ''): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $title = self::TITLE_LOGIN;
        $errors = $erros;
        $old = ['email' => $email];
        $adminAtual = null;
        $content = $basePath . '/resources/views/admin/login.php';

        require $basePath . '/resources/views/layouts/admin.php';
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $contexto
     */
    private function render(string $view, string $title, array $admin, array $contexto): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $adminAtual = $admin;
        $adminPedidos = $contexto['pedidos'] ?? [];
        $adminFiltro = $contexto['filtro'] ?? '';
        $adminPedido = $contexto['pedido'] ?? null;
        $content = $basePath . '/resources/views/admin/' . $view . '.php';

        require $basePath . '/resources/views/layouts/admin.php';
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

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
