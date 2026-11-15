<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\CartRepository;
use App\Services\CartService;
use App\Support\Csrf;
use App\Support\Session;

/**
 * Carrinho do cliente: adicionar, alterar e remover item.
 *
 * Só cliente autenticado tem carrinho. Toda escrita exige token CSRF, responde
 * com 303 e volta para a página de origem — o formulário manda o slug junto.
 */
final class CartController
{
    public function add(): void
    {
        $userId = $this->guard();

        $erro = $this->service()->add(
            $userId,
            (int) ($_POST['variant_id'] ?? 0),
            (int) ($_POST['quantity'] ?? 1)
        );

        $this->flash($erro, 'Item adicionado ao carrinho.');
        $this->redirect($this->back());
    }

    public function update(): void
    {
        $userId = $this->guard();

        $erro = $this->service()->update(
            $userId,
            (int) ($_POST['variant_id'] ?? 0),
            (int) ($_POST['quantity'] ?? 0)
        );

        $this->flash($erro, 'Quantidade atualizada.');
        $this->redirect($this->back());
    }

    public function remove(): void
    {
        $userId = $this->guard();

        $erro = $this->service()->remove($userId, (int) ($_POST['variant_id'] ?? 0));

        $this->flash($erro, 'Item removido do carrinho.');
        $this->redirect($this->back());
    }

    /**
     * @return int identificador do cliente autenticado
     */
    private function guard(): int
    {
        if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');

            require dirname(__DIR__, 2) . '/resources/views/errors/403.php';

            exit;
        }

        $userId = Session::userId();

        if ($userId === null) {
            Session::flash('error', 'Entre na sua conta para usar o carrinho.');
            $this->redirect('/login');
        }

        return $userId;
    }

    private function service(): CartService
    {
        $pdo = Connection::get();

        return new CartService($pdo, new CartRepository($pdo));
    }

    private function flash(?string $erro, string $sucesso): void
    {
        if ($erro !== null) {
            Session::flash('error', $erro);

            return;
        }

        Session::flash('success', $sucesso);
    }

    /**
     * Volta para a página do produto de onde veio o envio, sem confiar em
     * Referer: o slug vem do formulário e é reduzido a letras, números e hífen.
     */
    private function back(): string
    {
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_POST['slug'] ?? '')));

        return is_string($slug) && $slug !== '' ? '/produto/' . $slug : '/';
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
