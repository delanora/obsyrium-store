<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\AddressRepository;
use App\Repositories\UserRepository;
use App\Services\AccountService;
use App\Services\CartService;
use App\Support\Csrf;
use App\Support\Price;
use App\Support\Session;

/**
 * Finalização da compra.
 *
 * A página mostra o resumo do carrinho, o total e o endereço de entrega
 * escolhido entre os que o cliente guardou no perfil. O pagamento ainda não
 * existe: confirmar o endereço guarda a escolha na sessão para a etapa de
 * pagamento aproveitar quando ela chegar.
 */
final class CheckoutController
{
    private const TITLE = 'Finalizar compra — Obsyrium Store';

    private const SESSION_KEY = 'checkout_address';

    public function show(): void
    {
        $this->pagina($this->itens());
    }

    public function confirmAddress(): void
    {
        $itens = $this->itens();

        if (!$this->csrfOk()) {
            return;
        }

        $escolhido = $this->servico()->address((int) ($_POST['address_id'] ?? 0), (int) Session::userId());

        if ($escolhido === null) {
            Session::flash('error', 'Escolha um endereço de entrega da sua conta.');
            $this->redirect('/checkout');
        }

        Session::put(self::SESSION_KEY, (int) $escolhido['id']);
        Session::flash('success', 'Endereço de entrega confirmado. O pagamento entra na próxima etapa.');

        $this->redirect('/checkout');
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

        $basePath = dirname(__DIR__, 2);
        $title = self::TITLE;
        $checkoutItens = $itens;
        $checkoutEnderecos = $enderecos;
        $checkoutEnderecoId = $this->enderecoEscolhido($enderecos);
        $checkoutPecas = $pecas;
        $checkoutTotal = Price::format($totalCentavos);
        $content = $basePath . '/resources/views/checkout/index.php';

        require $basePath . '/resources/views/layouts/site.php';
    }

    /**
     * A escolha guardada na sessão vale enquanto o endereço existir; senão cai
     * para o principal e, na falta dele, para o primeiro da lista.
     *
     * @param array<int, array<string, mixed>> $enderecos
     */
    private function enderecoEscolhido(array $enderecos): ?int
    {
        $guardado = Session::get(self::SESSION_KEY);

        foreach ($enderecos as $endereco) {
            if (is_int($guardado) && (int) $endereco['id'] === $guardado) {
                return $guardado;
            }
        }

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

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
