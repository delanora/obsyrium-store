<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\AddressRepository;
use App\Repositories\UserRepository;
use App\Services\AccountService;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\ShippingService;
use App\Support\Csrf;
use App\Support\Price;
use App\Support\Session;

/**
 * Finalização da compra.
 *
 * A página mostra o resumo do carrinho, o total e o endereço de entrega
 * escolhido entre os que o cliente guardou no perfil. Confirmar grava o pedido
 * (OrderService), esvazia o carrinho e leva para a tela de confirmação, com o
 * número, o resumo gravado e os próximos passos. O pagamento entra na próxima
 * etapa: o pedido nasce aguardando pagamento.
 */
final class CheckoutController
{
    private const TITLE = 'Finalizar compra — Obsyrium Store';

    public function show(): void
    {
        $this->pagina($this->itens());
    }

    public function place(): void
    {
        $this->itens();

        if (!$this->csrfOk()) {
            return;
        }

        $pedido = (new OrderService(Connection::get()))->place(
            (int) Session::userId(),
            (int) ($_POST['address_id'] ?? 0),
            (string) ($_POST['shipping'] ?? '')
        );

        if (isset($pedido['error'])) {
            Session::flash('error', $pedido['error']);
            $this->redirect('/checkout');
        }

        $this->redirect('/pedido/' . $pedido['number'] . '/confirmacao');
    }

    /**
     * Confirmação do pedido recém-fechado: resumo do que ficou gravado, o que
     * acontece agora e o atalho para Compras. Só abre para o dono do pedido.
     *
     * @param array<string, string> $params
     */
    public function confirmation(array $params): void
    {
        if (Session::userId() === null) {
            Session::flash('error', 'Entre na sua conta para ver o pedido.');
            $this->redirect('/login');
        }

        $numero = (string) ($params['number'] ?? '');
        $pedido = (new OrderService(Connection::get()))
            ->orderForUser((int) Session::userId(), $numero);

        if ($pedido === null) {
            $this->naoEncontrado();
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $title = sprintf('Pedido %s confirmado — Obsyrium Store', $numero);
        $confirmacaoPedido = $pedido;
        $content = $basePath . '/resources/views/checkout/confirmation.php';

        $metaRobots = 'noindex, follow';

        require $basePath . '/resources/views/layouts/site.php';
    }

    /**
     * Carrinho vazio volta para /carrinho e visitante sem conta vai para o
     * login — comprar exige conta.
     *
     * @return array<int, array<string, mixed>>
     */
    private function itens(): array
    {
        $itens = CartService::forCurrentUser(Connection::get())->itemsForDisplay();

        if ($itens === []) {
            Session::flash('error', 'Seu carrinho está vazio.');
            $this->redirect('/carrinho');
        }

        if (Session::userId() === null) {
            Session::flash('error', 'Entre na sua conta para finalizar a compra.');
            $this->redirect('/login');
        }

        return $itens;
    }

    /**
     * @param array<int, array<string, mixed>> $itens
     */
    private function pagina(array $itens): void
    {
        $usuarioId = (int) Session::userId();
        $enderecos = $this->servico()->addressesFor($usuarioId);

        $totalCentavos = 0;
        $pecas = 0;

        foreach ($itens as $item) {
            $totalCentavos += (int) $item['price_cents'] * (int) $item['quantity'];
            $pecas += (int) $item['quantity'];
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $enderecoId = $this->enderecoEscolhido($enderecos);
        $servicoFrete = new ShippingService(Connection::get());

        // O frete depende do estado do endereço escolhido: cada endereço leva
        // as próprias opções, e o formulário troca o total ao trocar a escolha.
        $checkoutFrete = [];
        $checkoutEntrega = '';

        foreach ($enderecos as $endereco) {
            $opcoes = $servicoFrete->optionsFor((string) $endereco['state'], $totalCentavos);
            $checkoutFrete[(int) $endereco['id']] = $opcoes;

            if ((int) $endereco['id'] === $enderecoId) {
                $checkoutEntrega = (string) ($opcoes[0]['key'] ?? '');
            }
        }

        $basePath = dirname(__DIR__, 2);
        $title = self::TITLE;
        $checkoutItens = $itens;
        $checkoutEnderecos = $enderecos;
        $checkoutEnderecoId = $enderecoId;
        $checkoutPecas = $pecas;
        $checkoutTotal = Price::format($totalCentavos);
        $checkoutSubtotalCents = $totalCentavos;
        $content = $basePath . '/resources/views/checkout/index.php';

        $metaRobots = 'noindex, follow';

        require $basePath . '/resources/views/layouts/site.php';
    }

    /**
     * Começa marcado o endereço principal e, na falta dele, o primeiro da
     * lista. O cliente pode trocar a escolha no próprio formulário.
     *
     * @param array<int, array<string, mixed>> $enderecos
     */
    private function enderecoEscolhido(array $enderecos): ?int
    {
        foreach ($enderecos as $endereco) {
            if ($endereco['is_default']) {
                return (int) $endereco['id'];
            }
        }

        return isset($enderecos[0]) ? (int) $enderecos[0]['id'] : null;
    }

    private function servico(): AccountService
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

    private function naoEncontrado(): never
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');

        require dirname(__DIR__, 2) . '/resources/views/errors/404.php';

        exit;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
