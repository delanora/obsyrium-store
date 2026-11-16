<?php

declare(strict_types=1);

/*
 * Aba "Compras" do perfil do cliente.
 *
 * Um cartão por pedido, do mais novo para o mais antigo, com o número, a data,
 * a situação, os itens e o endereço de entrega — o mesmo retrato gravado no
 * fechamento, mesmo que o preço do produto ou o endereço do perfil mudem
 * depois.
 *
 * @var string $contaAba
 * @var list<array<string, mixed>> $contaPedidos
 */

$cepLabel = static function (string $cep): string {
	$digitos = preg_replace('/\D+/', '', $cep) ?? '';

	return strlen($digitos) === 8 ? substr($digitos, 0, 5) . '-' . substr($digitos, 5) : $cep;
};
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
							<?php $pecas = (int) $pedido['pieces']; ?>

							<li class="order">
								<header class="order__header">
									<div>
										<p class="order__number">Pedido <?= $escape((string) $pedido['number']) ?></p>
										<p class="order__date"><?= $escape((string) $pedido['date']) ?> · <?= $pecas ?> <?= $pecas === 1 ? 'peça' : 'peças' ?></p>
									</div>

									<span class="order__status order__status--<?= $escape((string) $pedido['status']) ?>"><?= $escape((string) $pedido['status_label']) ?></span>
								</header>

								<ul class="order__items">
									<?php foreach ($pedido['items'] as $item): ?>
										<?php $quantidade = (int) $item['quantity']; ?>

										<li class="order__item">
											<a class="order__item-name" href="/produto/<?= $escape((string) $item['slug']) ?>"><?= $escape((string) $item['name']) ?></a>
											<span class="order__item-meta">Tamanho <?= $escape((string) $item['size']) ?> · <?= $quantidade ?> <?= $quantidade === 1 ? 'unidade' : 'unidades' ?> · <?= $escape((string) $item['price']) ?> cada</span>
											<span class="order__item-price"><?= $escape((string) $item['line_total']) ?></span>
										</li>
									<?php endforeach; ?>
								</ul>

								<footer class="order__footer">
									<p class="order__address">
										<span class="order__address-label">Entrega · <?= $escape((string) $pedido['address']['label']) ?></span><br>
										<?= $escape((string) $pedido['address']['recipient']) ?><br>
										<?= $escape((string) $pedido['address']['street']) ?>, <?= $escape((string) $pedido['address']['number']) ?><?= $pedido['address']['complement'] !== '' ? ' — ' . $escape((string) $pedido['address']['complement']) : '' ?><br>
										<?= $escape((string) $pedido['address']['district']) ?> · <?= $escape((string) $pedido['address']['city']) ?>/<?= $escape((string) $pedido['address']['state']) ?> · CEP <?= $escape($cepLabel((string) $pedido['address']['postal_code'])) ?>
									</p>

									<dl class="order__totals">
										<div class="order__total-row">
											<dt>Subtotal</dt>
											<dd><?= $escape((string) $pedido['subtotal']) ?></dd>
										</div>

										<div class="order__total-row">
											<dt>Frete</dt>
											<dd><?= $escape((string) $pedido['shipping']) ?></dd>
										</div>

										<div class="order__total-row order__total-row--total">
											<dt>Total</dt>
											<dd><strong><?= $escape((string) $pedido['total']) ?></strong></dd>
										</div>
									</dl>
								</footer>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
