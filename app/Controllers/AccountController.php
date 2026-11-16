<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\AddressRepository;
use App\Repositories\UserRepository;
use App\Services\AccountService;
use App\Support\Csrf;
use App\Support\Session;

/**
 * Área do cliente: perfil, endereços e compras.
 *
 * Todas as rotas exigem sessão de cliente. A navegação lateral fica em
 * `partials/account-nav.php` e cada aba é uma rota própria, o que mantém o
 * menu marcado, o título certo e o botão voltar do navegador funcionando.
 */
final class AccountController
{
    private const TITLES = [
        'dados' => 'Meus dados — Obsyrium Store',
        'enderecos' => 'Endereços — Obsyrium Store',
        'compras' => 'Compras — Obsyrium Store',
    ];

    public function show(): void
    {
        $usuario = $this->usuario();

        $this->paginaDados($usuario);
    }

    public function saveProfile(): void
    {
        $usuario = $this->usuario();

        if (!$this->csrfOk()) {
            return;
        }

        $erros = $this->service()->saveProfile($usuario['id'], $_POST);

        if ($erros === []) {
            Session::flash('success', 'Seus dados foram atualizados.');
            $this->redirect('/conta');
        }

        $this->paginaDados($usuario, $erros, $_POST);
    }

    public function addresses(): void
    {
        $this->paginaEnderecos();
    }

    /**
     * @param array<string, string> $params
     */
    public function editAddress(array $params): void
    {
        $usuario = $this->usuario();
        $endereco = $this->service()->address((int) ($params['id'] ?? 0), $usuario['id']);

        if ($endereco === null) {
            Session::flash('error', 'Endereço não encontrado.');
            $this->redirect('/conta/enderecos');
        }

        $this->paginaEnderecos([], [], $endereco);
    }

    public function saveAddress(): void
    {
        $usuario = $this->usuario();

        if (!$this->csrfOk()) {
            return;
        }

        $servico = $this->service();
        $id = (int) ($_POST['address_id'] ?? 0);
        $edicao = $id > 0 ? $servico->address($id, $usuario['id']) : null;

        if ($id > 0 && $edicao === null) {
            Session::flash('error', 'Endereço não encontrado.');
            $this->redirect('/conta/enderecos');
        }

        $erros = $servico->saveAddress($usuario['id'], $_POST);

        if ($erros === []) {
            Session::flash('success', $id > 0 ? 'Endereço atualizado.' : 'Endereço cadastrado.');
            $this->redirect('/conta/enderecos');
        }

        $this->paginaEnderecos($erros, $_POST, $edicao);
    }

    public function removeAddress(): void
    {
        $usuario = $this->usuario();

        if (!$this->csrfOk()) {
            return;
        }

        $removido = $this->service()->removeAddress((int) ($_POST['address_id'] ?? 0), $usuario['id']);

        Session::flash(
            $removido ? 'success' : 'error',
            $removido ? 'Endereço removido.' : 'Endereço não encontrado.'
        );

        $this->redirect('/conta/enderecos');
    }

    public function defaultAddress(): void
    {
        $usuario = $this->usuario();

        if (!$this->csrfOk()) {
            return;
        }

        $definido = $this->service()->makeDefault((int) ($_POST['address_id'] ?? 0), $usuario['id']);

        Session::flash(
            $definido ? 'success' : 'error',
            $definido ? 'Endereço principal atualizado.' : 'Endereço não encontrado.'
        );

        $this->redirect('/conta/enderecos');
    }

    public function orders(): void
    {
        $usuario = $this->usuario();

        $this->render('orders', self::TITLES['compras'], [
            'usuario' => $usuario,
            'aba' => 'compras',
        ]);
    }

    /**
     * @param array<string, mixed> $usuario
     * @param array<string, string> $erros
     * @param array<string, mixed> $antigo
     */
    private function paginaDados(array $usuario, array $erros = [], array $antigo = []): void
    {
        $servico = $this->service();

        $this->render('profile', self::TITLES['dados'], [
            'usuario' => $usuario,
            'aba' => 'dados',
            'cpf' => $servico->cpfLabel((string) $usuario['cpf']),
            'telefone' => $servico->phoneLabel((string) $usuario['phone']),
            'errors' => $erros,
            'old' => $antigo,
        ]);
    }

    /**
     * @param array<string, string> $erros
     * @param array<string, mixed> $antigo
     * @param array<string, mixed>|null $edicao
     */
    private function paginaEnderecos(array $erros = [], array $antigo = [], ?array $edicao = null): void
    {
        $usuario = $this->usuario();
        $servico = $this->service();

        $this->render('addresses', self::TITLES['enderecos'], [
            'usuario' => $usuario,
            'aba' => 'enderecos',
            'enderecos' => $servico->addressesFor($usuario['id']),
            'ufs' => $servico->ufs(),
            'edicao' => $edicao,
            'errors' => $erros,
            'old' => $antigo,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function usuario(): array
    {
        $usuario = $this->service()->user(Session::userId() ?? 0);

        if ($usuario === null || !$usuario['is_active'] || $usuario['deleted_at'] !== null) {
            Session::logout();
            Session::flash('error', 'Entre na sua conta para abrir o perfil.');
            $this->redirect('/login');
        }

        return $usuario;
    }

    private function service(): AccountService
    {
        $pdo = Connection::get();

        return new AccountService(new UserRepository($pdo), new AddressRepository($pdo));
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
     * @param array<string, mixed> $contexto
     */
    private function render(string $view, string $title, array $contexto): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $errors = $contexto['errors'] ?? [];
        $old = $contexto['old'] ?? [];
        $contaUsuario = $contexto['usuario'];
        $contaAba = $contexto['aba'];
        $contaCpf = $contexto['cpf'] ?? '';
        $contaTelefone = $contexto['telefone'] ?? '';
        $contaEnderecos = $contexto['enderecos'] ?? [];
        $contaUfs = $contexto['ufs'] ?? [];
        $contaEdicao = $contexto['edicao'] ?? null;
        $content = $basePath . '/resources/views/account/' . $view . '.php';

        require $basePath . '/resources/views/layouts/site.php';
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
