<?php

declare(strict_types=1);

/*
 * Cartão de um pedido: número, data, situação, itens, endereço de entrega e
 * valores. Usado na aba Compras e na confirmação do checkout, sempre a partir
 * do retrato gravado no fechamento — preço, nome e endereço do pedido não
 * mudam quando o produto ou o perfil do cliente mudam depois.
 *
 * @var array<string, mixed> $pedido
 */

$pecasDoPedido = (int) $pedido['pieces'];

$cepDoPedido = static function (string $cep): string {
	$digitos = preg_replace('/\D+/', '', $cep) ?? '';

	return strlen($digitos) === 8 ? substr($digitos, 0, 5) . '-' . substr($digitos, 5) : $cep;
};
?>
<li class="order">
	<header class="order__header">
		<div>
			<p class="order__number">Pedido <?= $escape((string) $pedido['number']) ?></p>
			<p class="order__date"><?= $escape((string) $pedido['date']) ?> · <?= $pecasDoPedido ?> <?= $pecasDoPedido === 1 ? 'peça' : 'peças' ?></p>
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
			<?= $escape((string) $pedido['address']['district']) ?> · <?= $escape((string) $pedido['address']['city']) ?>/<?= $escape((string) $pedido['address']['state']) ?> · CEP <?= $escape($cepDoPedido((string) $pedido['address']['postal_code'])) ?>
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
