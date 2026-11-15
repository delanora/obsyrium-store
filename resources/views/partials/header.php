<?php

declare(strict_types=1);

/*
 * Cabeçalho: barra de benefícios + navegação principal.
 * Os links de categoria e os botões de ação ainda não têm rota: ficam em "#"
 * até as páginas correspondentes existirem.
 */

$benefits = [
    ['icon' => 'installments', 'label' => 'Em até 3x sem juros', 'text' => 'No cartão'],
    ['icon' => 'shipping', 'label' => 'Frete grátis', 'text' => 'Acima de R$ 199'],
    ['icon' => 'service', 'label' => 'Atendimento', 'text' => 'Por WhatsApp'],
    ['icon' => 'exchange', 'label' => 'Troca', 'text' => 'A 1ª é grátis'],
    ['icon' => 'secure', 'label' => 'Site seguro', 'text' => 'Compra protegida'],
];

$menu = [
    ['label' => 'Lançamentos', 'href' => '#lancamentos', 'current' => true],
    ['label' => 'Camisetas', 'href' => '#'],
    ['label' => 'Estampas', 'href' => '#'],
    ['label' => 'Sobre', 'href' => '#'],
    ['label' => 'Contato', 'href' => '#'],
];

$icons = [
    'installments' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/></svg>',
    'shipping' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h11v8H3z"/><path d="M14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="1.6"/><circle cx="17" cy="17" r="1.6"/></svg>',
    'service' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg>',
    'exchange' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h13l-3-3"/><path d="M20 16H7l3 3"/></svg>',
    'secure' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/></svg>',
];

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<a class="skip-link" href="#conteudo">Ir para o conteúdo</a>

<div class="topbar">
	<div class="container">
		<ul class="topbar__list">
			<?php foreach ($benefits as $benefit): ?>
				<li class="topbar__item">
					<span class="topbar__icon"><?= $icons[$benefit['icon']] ?></span>
					<span>
						<span class="topbar__label"><?= $escape($benefit['label']) ?></span>
						<span class="topbar__text"><?= $escape($benefit['text']) ?></span>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>

<header class="site-header">
	<div class="container site-header__inner">
		<a class="site-header__brand" href="/">
			<img class="site-header__logo" src="/assets/images/logo-128.png" width="40" height="40" alt="">
			<span class="site-header__name">Obsyrium Store</span>
		</a>

		<nav class="site-nav" aria-label="Menu principal">
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
			<button class="icon-button" type="button" aria-label="Minha conta">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.3 3.1-6 7-6s7 2.7 7 6"/></svg>
			</button>
			<button class="icon-button" type="button" aria-label="Carrinho, 0 itens">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16l-1.5 11h-13z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
				<span class="icon-button__count" aria-hidden="true">0</span>
			</button>
		</div>
	</div>
</header>
