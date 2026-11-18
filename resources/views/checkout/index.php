<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Price;

/*
 * Página de finalização da compra.
 *
 * Resumo dos itens à esquerda, escolha do endereço de entrega e da forma de
 * entrega (entrega pelo estado do endereço ou retirada na loja) e o total à
 * direita. O frete de cada endereço já vem calculado pelo ShippingService; os
 * botões de entrega carregam o preço em `data-*` e o script do fim só atualiza
 * o total na tela. Quem fecha o pedido é o servidor, que confere o frete de
 * novo — a conta que vale é a do banco, não a do navegador.
 *
 * @var array<int, array<string, mixed>> $checkoutItens
 * @var array<int, array<string, mixed>> $checkoutEnderecos
 * @var int|null $checkoutEnderecoId
 * @var int $checkoutPecas
 * @var string $checkoutTotal
 * @var int $checkoutSubtotalCents
 * @var string $checkoutEntrega
 * @var array<int, list<array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}>> $checkoutFrete
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$cepLabel = static function (string $cep): string {
	$digitos = preg_replace('/\D+/', '', $cep) ?? '';

	return strlen($digitos) === 8 ? substr($digitos, 0, 5) . '-' . substr($digitos, 5) : $cep;
};

$opcoesPadrao = $checkoutEnderecoId !== null ? ($checkoutFrete[$checkoutEnderecoId] ?? []) : [];
$freteAtual = null;

foreach ($opcoesPadrao as $opcao) {
	if ($opcao['key'] === $checkoutEntrega) {
		$freteAtual = $opcao;
	}
}

$freteAtual ??= $opcoesPadrao[0] ?? null;
$freteCentavos = $freteAtual['price_cents'] ?? 0;
?>
<section class="checkout">
	<div class="container checkout__inner">
		<header class="checkout__header">
			<p class="checkout__eyebrow">Checkout</p>
			<h1 class="checkout__title">Finalizar compra</h1>
			<p class="checkout__text">Confira os itens, escolha o endereço e a forma de entrega e finalize. O pedido fica registrado em Compras e o pagamento entra na próxima etapa.</p>
		</header>

		<div class="checkout__layout">
			<div class="checkout__main">
				<section class="checkout__panel">
					<h2 class="checkout__panel-title">Resumo do pedido</h2>

					<ul class="checkout-list">
						<?php foreach ($checkoutItens as $item): ?>
							<li class="checkout-item">
								<img class="checkout-item__image" src="<?= $escape((string) $item['image']) ?>" alt="<?= $escape((string) $item['alt']) ?>" width="64" height="80" loading="lazy">

								<div class="checkout-item__info">
									<a class="checkout-item__name" href="/produto/<?= $escape((string) $item['slug']) ?>"><?= $escape((string) $item['name']) ?></a>
									<p class="checkout-item__meta">Tamanho <?= $escape((string) $item['size']) ?> · <?= (int) $item['quantity'] ?> <?= (int) $item['quantity'] === 1 ? 'unidade' : 'unidades' ?></p>
								</div>

								<p class="checkout-item__price"><?= $escape(Price::format((int) $item['price_cents'] * (int) $item['quantity'])) ?></p>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>

				<section class="checkout__panel">
					<h2 class="checkout__panel-title">Endereço e entrega</h2>

					<?php if ($checkoutEnderecos === []): ?>
						<div class="checkout__empty">
							<p class="checkout__empty-title">Nenhum endereço cadastrado.</p>
							<p class="checkout__empty-text">Cadastre um endereço de entrega no seu perfil para continuar.</p>
							<a class="button" href="/conta/enderecos">Cadastrar endereço</a>
						</div>
					<?php else: ?>
						<form class="checkout-addresses" action="/checkout" method="post">
							<?= Csrf::field() ?>

							<ul class="checkout-addresses__list">
								<?php foreach ($checkoutEnderecos as $endereco): ?>
									<?php
									$idEndereco = (int) $endereco['id'];
									$marcado = $idEndereco === $checkoutEnderecoId;
									$opcoes = $checkoutFrete[$idEndereco] ?? [];
									?>
									<li>
										<label class="checkout-address<?= $marcado ? ' checkout-address--escolhido' : '' ?>">
											<input class="checkout-address__radio" type="radio" name="address_id" value="<?= $idEndereco ?>"<?= $marcado ? ' checked' : '' ?>>

											<span class="checkout-address__body">
												<span class="checkout-address__top">
													<span class="checkout-address__label"><?= $escape((string) $endereco['label']) ?></span>
													<?php if ($endereco['is_default']): ?>
														<span class="checkout-address__badge">Principal</span>
													<?php endif; ?>
												</span>

												<span class="checkout-address__text">
													<?= $escape((string) $endereco['recipient']) ?><br>
													<?= $escape((string) $endereco['street']) ?>, <?= $escape((string) $endereco['number']) ?><?= $endereco['complement'] !== '' ? ' — ' . $escape((string) $endereco['complement']) : '' ?><br>
													<?= $escape((string) $endereco['district']) ?> · <?= $escape((string) $endereco['city']) ?>/<?= $escape((string) $endereco['state']) ?> · CEP <?= $escape($cepLabel((string) $endereco['postal_code'])) ?>
												</span>
											</span>
										</label>

										<fieldset class="checkout-shipping" data-address="<?= $idEndereco ?>">
											<legend class="checkout-shipping__legend">Entrega</legend>

											<?php foreach ($opcoes as $opcao): ?>
												<?php $escolhida = $marcado && $opcao['key'] === $checkoutEntrega; ?>
												<label class="checkout-shipping__option">
													<input
														class="checkout-shipping__radio"
														type="radio"
														name="shipping"
														value="<?= $escape($opcao['key']) ?>"
														data-address="<?= $idEndereco ?>"
														data-price="<?= (int) $opcao['price_cents'] ?>"
														data-price-label="<?= $escape($opcao['price_label']) ?>"
														<?= $escolhida ? ' checked' : '' ?>
													>

													<span class="checkout-shipping__body">
														<span class="checkout-shipping__top">
															<span class="checkout-shipping__label"><?= $escape($opcao['label']) ?></span>
															<span class="checkout-shipping__price"><?= $escape($opcao['price_label']) ?></span>
														</span>

														<span class="checkout-shipping__days"><?= $escape($opcao['days']) ?></span>

														<?php if ($opcao['note'] !== ''): ?>
															<span class="checkout-shipping__note"><?= $escape($opcao['note']) ?></span>
														<?php endif; ?>
													</span>
												</label>
											<?php endforeach; ?>
										</fieldset>
									</li>
								<?php endforeach; ?>
							</ul>

							<div class="checkout__actions">
								<button class="button" type="submit">Finalizar pedido</button>
								<a class="button button--ghost" href="/conta/enderecos">Gerenciar endereços</a>
							</div>
						</form>
					<?php endif; ?>
				</section>
			</div>

			<aside class="checkout__summary" aria-label="Resumo de valores">
				<h2 class="checkout__panel-title">Total</h2>

				<dl class="checkout-summary">
					<div class="checkout-summary__row">
						<dt>Peças</dt>
						<dd><?= $checkoutPecas ?></dd>
					</div>

					<div class="checkout-summary__row">
						<dt>Subtotal</dt>
						<dd><?= $escape($checkoutTotal) ?></dd>
					</div>

					<div class="checkout-summary__row">
						<dt>Frete</dt>
						<dd id="checkout-shipping-value">
							<?= $freteAtual !== null ? $escape($freteAtual['price_label']) : 'Escolha a entrega' ?>
						</dd>
					</div>

					<?php if ($freteAtual !== null && $freteAtual['note'] !== ''): ?>
						<div class="checkout-summary__row checkout-summary__row--note">
							<dt class="visually-hidden">Observação do frete</dt>
							<dd id="checkout-shipping-note"><?= $escape($freteAtual['note']) ?></dd>
						</div>
					<?php endif; ?>

					<div class="checkout-summary__row checkout-summary__row--total">
						<dt>Total</dt>
						<dd><strong id="checkout-total-value"><?= $escape(Price::format($checkoutSubtotalCents + $freteCentavos)) ?></strong></dd>
					</div>
				</dl>

				<p class="checkout__note">O pedido fica como aguardando pagamento — nada é cobrado agora.</p>

				<a class="button button--ghost checkout__back" href="/carrinho">Voltar ao carrinho</a>
			</aside>
		</div>
	</div>
</section>

<script>
(function () {
	const subtotal = <?= (int) $checkoutSubtotalCents ?>;
	const freteValor = document.getElementById('checkout-shipping-value');
	const totalValor = document.getElementById('checkout-total-value');

	if (freteValor === null || totalValor === null) {
		return;
	}

	const formatar = function (centavos) {
		const reais = (centavos / 100).toFixed(2).replace('.', ',');
		return 'R$ ' + reais.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	};

	const entregas = Array.prototype.slice.call(document.querySelectorAll('.checkout-shipping__radio'));
	const enderecos = Array.prototype.slice.call(document.querySelectorAll('.checkout-address__radio'));

	const pintar = function () {
		const escolhida = entregas.filter(function (radio) { return radio.checked; })[0];

		if (!escolhida) {
			freteValor.textContent = 'Escolha a entrega';
			totalValor.textContent = formatar(subtotal);
			return;
		}

		freteValor.textContent = escolhida.dataset.priceLabel;
		totalValor.textContent = formatar(subtotal + Number(escolhida.dataset.price));

		const bloco = escolhida.closest('.checkout-shipping');
		const nota = document.getElementById('checkout-shipping-note');

		if (nota !== null) {
			const texto = bloco !== null ? bloco.querySelector('.checkout-shipping__note') : null;
			nota.textContent = texto !== null ? texto.textContent : '';
			nota.parentElement.hidden = nota.textContent === '';
		}
	};

	enderecos.forEach(function (endereco) {
		endereco.addEventListener('change', function () {
			entregas.forEach(function (radio) { radio.checked = false; });

			const primeira = document.querySelector(
				'.checkout-shipping[data-address="' + endereco.value + '"] .checkout-shipping__radio'
			);

			if (primeira !== null) {
				primeira.checked = true;
			}

			document.querySelectorAll('.checkout-address').forEach(function (cartao) {
				cartao.classList.toggle(
					'checkout-address--escolhido',
					cartao.querySelector('.checkout-address__radio') === endereco
				);
			});

			pintar();
		});
	});

	entregas.forEach(function (entrega) {
		entrega.addEventListener('change', function () {
			const endereco = document.querySelector(
				'.checkout-address__radio[value="' + entrega.dataset.address + '"]'
			);

			if (endereco !== null) {
				endereco.checked = true;
			}

			pintar();
		});
	});

	pintar();
})();
</script>
