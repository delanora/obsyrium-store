<?php

declare(strict_types=1);

/*
 * Aba "Compras" do perfil do cliente.
 *
 * A lista de pedidos entra aqui quando as tabelas de pedido existirem (Fase 3
 * do roadmap). Até lá a aba mostra o estado vazio — ela já faz parte do menu
 * para o cliente saber que o histórico vai morar neste lugar.
 *
 * @var string $contaAba
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

			<div class="account__panel">
				<div class="account__empty">
					<p class="account__empty-title">Você ainda não fez compras.</p>
					<p class="account__empty-text">Quando finalizar seu primeiro pedido, ele aparece nesta lista com o status da entrega.</p>
					<a class="button" href="/">Ver as camisetas</a>
				</div>
			</div>
		</div>
	</div>
</section>
