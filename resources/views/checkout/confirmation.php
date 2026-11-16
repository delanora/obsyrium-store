<?php

declare(strict_types=1);

/*
 * Confirmação do pedido fechado.
 *
 * O cliente chega aqui logo depois de finalizar a compra. A página mostra o
 * número do pedido, o mesmo cartão que aparece em Compras e o que acontece a
 * partir de agora, com o atalho para o histórico e para voltar ao catálogo.
 *
 * @var array<string, mixed> $confirmacaoPedido
 */

$pedidoConfirmado = $confirmacaoPedido;
?>
<section class="confirm">
	<div class="container confirm__inner">
		<header class="confirm__header">
			<span class="confirm__badge" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
			</span>

			<p class="confirm__eyebrow">Pedido confirmado</p>
			<h1 class="confirm__title">Recebemos seu pedido</h1>
			<p class="confirm__text">
				Guardamos as peças escolhidas e o endereço de entrega. O número do pedido é
				<strong class="confirm__number"><?= $escape((string) $pedidoConfirmado['number']) ?></strong> — ele aparece em Compras e em qualquer contato com a loja.
			</p>
		</header>

		<div class="confirm__layout">
			<div class="confirm__main">
				<section class="confirm__panel">
					<h2 class="confirm__panel-title">Resumo do pedido</h2>

					<ul class="order-list">
						<?php require __DIR__ . '/../partials/order-card.php'; ?>
					</ul>
				</section>
			</div>

			<aside class="confirm__aside" aria-label="Próximos passos">
				<section class="confirm__steps">
					<h2 class="confirm__steps-title">Próximos passos</h2>

					<ol class="confirm__steps-list">
						<li>
							<span class="confirm__step-title">Pagamento</span>
							O pedido está como aguardando pagamento. O pagamento entra na próxima etapa da loja e nada foi cobrado agora.
						</li>
						<li>
							<span class="confirm__step-title">Separação e envio</span>
							Conferimos as peças e o endereço antes de postar. O prazo e o código de rastreio passam a aparecer no próprio pedido quando ele for enviado.
						</li>
						<li>
							<span class="confirm__step-title">Acompanhe em Compras</span>
							O histórico fica na sua conta, com itens, endereço e situação sempre atualizados.
						</li>
					</ol>
				</section>

				<div class="confirm__actions">
					<a class="button" href="/conta/compras">Ir para Compras</a>
					<a class="button button--ghost" href="/catalogo">Continuar comprando</a>
				</div>
			</aside>
		</div>
	</div>
</section>
