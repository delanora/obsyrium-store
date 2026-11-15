<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\CartRepository;
use App\Services\CartService;
use App\Support\Csrf;
use App\Support\Price;
use App\Support\Session;

/**
 * Carrinho do cliente: adicionar, alterar e remover item.
 *
 * Só cliente autenticado tem carrinho. Toda escrita exige token CSRF, responde
 * com 303 e volta para a página de origem — o formulário manda o slug junto.
 */
final class CartController
{
    private const FALLBACK_IMAGE = '/assets/images/placeholder.svg';

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
     * Página do carrinho com os itens do cliente autenticado.
     */
    public function show(): void
    {
        $userId = Session::userId();

        if ($userId === null) {
            Session::flash('error', 'Entre na sua conta para ver o carrinho.');
            $this->redirect('/login');
        }

        $itens = [];

        foreach ((new CartRepository(Connection::get()))->itemsForUser($userId) as $linha) {
            $imagem = (string) ($linha['image_path'] ?? '');

            $itens[] = [
                'variant_id' => (int) $linha['variant_id'],
                'name' => (string) $linha['name'],
                'slug' => (string) $linha['slug'],
                'size' => (string) $linha['size'],
                'quantity' => (int) $linha['quantity'],
                'available' => (int) $linha['available'],
                'active' => (int) $linha['is_active'] === 1,
                'price' => Price::format((int) $linha['price_cents']),
                'image' => $imagem !== '' ? $imagem : self::FALLBACK_IMAGE,
                'alt' => (string) ($linha['image_alt'] ?? ''),
            ];
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $title = 'Carrinho — Obsyrium Store';
        $limite = CartService::MAX_PER_ITEM;
        $content = $basePath . '/resources/views/cart/index.php';

        require $basePath . '/resources/views/layouts/site.php';
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
     * Volta para a página do produto de onde veio o envio (o slug chega pelo
     * formulário, reduzido a letras, números e hífen — o Referer não é usado).
     * Sem slug, a volta é o carrinho.
     */
    private function back(): string
    {
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_POST['slug'] ?? '')));

        return is_string($slug) && $slug !== '' ? '/produto/' . $slug : '/carrinho';
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
