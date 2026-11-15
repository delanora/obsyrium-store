<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Session;

/*
 * Cabeçalho: marca, navegação principal e ações.
 * No celular o menu é recolhido no botão .nav-toggle; sem JavaScript ele
 * aparece em duas linhas.
 */

$menu = [
    ['label' => 'Lançamentos', 'href' => '#lancamentos', 'current' => true],
    ['label' => 'Camisetas', 'href' => '#'],
    ['label' => 'Sobre', 'href' => '#'],
    ['label' => 'Contato', 'href' => '#'],
];

$conectado = Session::userId() !== null;

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
				<form class="site-header__account-form" action="/logout" method="post">
					<?= Csrf::field() ?>
					<button class="icon-button icon-button--active" type="submit" aria-label="Sair da conta">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.3 3.1-6 7-6s7 2.7 7 6"/></svg>
					</button>
				</form>
			<?php else: ?>
				<a class="icon-button" href="/login" aria-label="Entrar ou criar conta">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.3 3.1-6 7-6s7 2.7 7 6"/></svg>
				</a>
			<?php endif; ?>

			<button class="icon-button" type="button" aria-label="Carrinho, 0 itens">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16l-1.5 11h-13z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
				<span class="icon-button__count" aria-hidden="true">0</span>
			</button>
		</div>
	</div>
</header>
