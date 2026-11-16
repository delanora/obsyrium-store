<?php

declare(strict_types=1);

/*
 * Aba "Compras" do perfil do cliente.
 *
 * Um cartão por pedido, do mais novo para o mais antigo, com o número, a data,
 * a situação, os itens e o endereço de entrega — o mesmo retrato gravado no
 * fechamento, mesmo que o preço do produto ou o endereço do perfil mudem
 * depois. O cartão fica em `partials/order-card.php` porque a confirmação do
 * checkout mostra o mesmo desenho.
 *
 * @var string $contaAba
 * @var list<array<string, mixed>> $contaPedidos
 */
?>
<section class="account">
	<div class="container account__inner">
		<header class="account__header">
			<p class="account__eyebrow">Minha conta</p>
			<h1 class="account__title">Compras</h1>
			<p class="account__text">Seus pedidos ficam guardados aqui, com itens, endereço de entrega e situação.</p>
		</header>

		<div class="account__layout">
			<?php require __DIR__ . '/../partials/account-nav.php'; ?>

			<?php if ($contaPedidos === []): ?>
				<div class="account__panel">
					<div class="account__empty">
						<p class="account__empty-title">Você ainda não fez compras.</p>
						<p class="account__empty-text">Quando finalizar seu primeiro pedido, ele aparece nesta lista com o status da entrega.</p>
						<a class="button" href="/">Ver as camisetas</a>
					</div>
				</div>
			<?php else: ?>
				<div class="account__panel">
					<ul class="order-list">
						<?php foreach ($contaPedidos as $pedido): ?>
							<?php require __DIR__ . '/../partials/order-card.php'; ?>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
