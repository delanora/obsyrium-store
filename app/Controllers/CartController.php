<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\CartService;
use App\Support\Csrf;
use App\Support\Price;
use App\Support\Session;

/**
 * Carrinho: ver os itens, adicionar, alterar quantidade e remover.
 *
 * O visitante monta o carrinho sem conta (guardado na sessão do navegador) e o
 * login só é exigido na hora da compra; ao entrar, os itens são levados para o
 * carrinho do banco. Toda escrita exige token CSRF e responde 303 voltando
 * para a página de origem — o formulário manda o slug junto.
 */
final class CartController
{
    private const FALLBACK_IMAGE = '/assets/images/placeholder.svg';

    public function add(): void
    {
        $this->requireCsrf();

        $erro = $this->service()->add(
            (int) ($_POST['variant_id'] ?? 0),
            (int) ($_POST['quantity'] ?? 1)
        );

        $this->flash($erro, 'Item adicionado ao carrinho.');
        $this->redirect($this->back());
    }

    public function update(): void
    {
        $this->requireCsrf();

        $erro = $this->service()->update(
            (int) ($_POST['variant_id'] ?? 0),
            (int) ($_POST['quantity'] ?? 0)
        );

        $this->flash($erro, 'Quantidade atualizada.');
        $this->redirect($this->back());
    }

    public function remove(): void
    {
        $this->requireCsrf();

        $erro = $this->service()->remove((int) ($_POST['variant_id'] ?? 0));

        $this->flash($erro, 'Item removido do carrinho.');
        $this->redirect($this->back());
    }

    /**
     * Página do carrinho, aberta para visitante e para cliente autenticado.
     */
    public function show(): void
    {
        $itens = [];

        foreach ($this->service()->items() as $linha) {
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
        $visitante = Session::userId() === null;
        $content = $basePath . '/resources/views/cart/index.php';

        require $basePath . '/resources/views/layouts/site.php';
    }

    private function requireCsrf(): void
    {
        if (Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
            return;
        }

        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');

        require dirname(__DIR__, 2) . '/resources/views/errors/403.php';

        exit;
    }

    private function service(): CartService
    {
        return CartService::forCurrentUser(Connection::get());
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
