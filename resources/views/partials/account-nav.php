<?php

declare(strict_types=1);

/*
 * Menu da área do cliente.
 *
 * No computador é uma coluna fixa à esquerda (padrão de loja grande: todas as
 * seções à vista, sem esconder nada atrás de um clique). No celular a mesma
 * lista vira uma faixa de abas que rola na horizontal — media query em
 * account.css.
 *
 * Quando as avaliações de produto entrarem, basta acrescentar aqui uma quarta
 * entrada: a lista alimenta as três telas e o estado marcado é o `$contaAba`.
 *
 * @var string $contaAba 'dados', 'enderecos' ou 'compras'
 */

$itensDaConta = [
    'dados' => ['rotulo' => 'Meus dados', 'href' => '/conta'],
    'enderecos' => ['rotulo' => 'Endereços', 'href' => '/conta/enderecos'],
    'compras' => ['rotulo' => 'Compras', 'href' => '/conta/compras'],
];
?>
<nav class="account-nav" aria-label="Área do cliente">
	<ul class="account-nav__list">
		<?php foreach ($itensDaConta as $chave => $item): ?>
			<li class="account-nav__item">
				<a class="account-nav__link" href="<?= $escape($item['href']) ?>"<?= $contaAba === $chave ? ' aria-current="page"' : '' ?>><?= $escape($item['rotulo']) ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
