<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Session;

/*
 * Cabeçalho: marca, navegação principal e ações.
 * No celular o menu é recolhido no botão .nav-toggle; sem JavaScript ele
 * aparece em duas linhas. O ícone do carrinho abre a gaveta lateral no
 * computador (site.js) e navega para /carrinho no celular.
 */

$menu = [
    ['label' => 'Lançamentos', 'href' => '#lancamentos', 'current' => true],
    ['label' => 'Camisetas', 'href' => '#'],
    ['label' => 'Sobre', 'href' => '#'],
    ['label' => 'Contato', 'href' => '#'],
];

$conectado = Session::userId() !== null;

// O ícone de pessoa fica marcado enquanto o cliente está na área dele.
$naConta = str_starts_with((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'), '/conta');

$carrinhoDoCliente = App\Services\CartService::forCurrentUser(App\Database\Connection::get());
$itensDoCarrinho = $carrinhoDoCliente->itemsForDisplay();
$itensNoCarrinho = array_sum(array_column($itensDoCarrinho, 'quantity'));

// A confirmação de item adicionado vira um pop-up junto do ícone do carrinho,
// não uma faixa no topo: o layout deixa esse tipo de mensagem para cá.
$avisoDoCarrinho = (($flash ?? [])['type'] ?? null) === 'cart' ? $flash : null;

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<a class="skip-link" href="#conteudo">Ir para o conteúdo</a>

<header class="site-header" data-menu="closed">
	<div class="site-header__inner">
		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-principal" aria-label="Abrir menu">
			<span class="nav-toggle__icon" aria-hidden="true"></span>
		</button>

		<a class="site-header__brand" href="/">
			<img class="site-header__logo" src="/assets/images/logo-128.png" width="40" height="40" alt="">
			<span class="site-header__name">Obsyrium</span>
		</a>

		<nav class="site-nav" id="menu-principal" aria-label="Menu principal">
			<ul class="site-nav__list">
				<?php foreach ($menu as $item): ?>
					<li>
						<a class="site-nav__link" href="<?= $escape($item['href']) ?>"<?= !empty($item['current']) ? ' aria-current="page"' : '' ?>><?= $escape($item['label']) ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="site-header__actions">
			<button class="icon-button" type="button" aria-label="Buscar">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="M20 20l-3.5-3.5"/></svg>
			</button>

			<?php if ($conectado): ?>
				<a class="icon-button<?= $naConta ? ' icon-button--active' : '' ?>" href="/conta" aria-label="Minha conta">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.3 3.1-6 7-6s7 2.7 7 6"/></svg>
				</a>

				<form class="site-header__account-form" action="/logout" method="post">
					<?= Csrf::field() ?>
					<button class="icon-button" type="submit" aria-label="Sair da conta">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v8"/><path d="M8.4 7.2a6 6 0 1 0 7.2 0"/></svg>
					</button>
				</form>
			<?php else: ?>
				<a class="icon-button" href="/login" aria-label="Entrar ou criar conta">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.3 3.1-6 7-6s7 2.7 7 6"/></svg>
				</a>
			<?php endif; ?>

			<div class="site-header__cart">
				<a class="icon-button" href="/carrinho" data-cart-open aria-controls="cart-drawer" aria-expanded="false" aria-label="Carrinho, <?= $itensNoCarrinho ?> <?= $itensNoCarrinho === 1 ? 'item' : 'itens' ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16l-1.5 11h-13z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
					<span class="icon-button__count" aria-hidden="true"><?= $itensNoCarrinho ?></span>
				</a>

				<?php if ($avisoDoCarrinho !== null): ?>
					<div class="cart-toast" role="status" data-toast>
						<span class="cart-toast__check" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
						</span>

						<div class="cart-toast__body">
							<p class="cart-toast__text"><?= $escape($avisoDoCarrinho['message']) ?></p>
							<a class="cart-toast__link" href="/carrinho">Ver carrinho</a>
						</div>

						<button class="cart-toast__close" type="button" data-toast-close aria-label="Fechar aviso">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</header>
