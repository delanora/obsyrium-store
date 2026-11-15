<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\CartService;
use App\Support\Session;

/**
 * Início da finalização da compra.
 *
 * O pagamento ainda não existe: por enquanto o botão “Finalizar compra”
 * confere o carrinho e, para quem está sem conta, manda para o login/cadastro
 * — comprar exige conta. Cliente autenticado volta ao carrinho com o aviso de
 * que o pagamento entra em breve.
 */
final class CheckoutController
{
    public function start(): void
    {
        $carrinho = CartService::forCurrentUser(Connection::get());

        if ($carrinho->total() === 0) {
            Session::flash('error', 'Seu carrinho está vazio.');
            $this->redirect('/carrinho');
        }

        if (Session::userId() === null) {
            Session::flash('error', 'Entre na sua conta para finalizar a compra.');
            $this->redirect('/login');
        }

        Session::flash('success', 'Falta pouco: o pagamento será liberado em breve.');
        $this->redirect('/carrinho');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
