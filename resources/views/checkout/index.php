<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Price;

/*
 * Página de finalização da compra.
 *
 * Resumo dos itens à esquerda, escolha do endereço de entrega entre os que o
 * cliente guardou no perfil e o total à direita. O pagamento entra na próxima
 * etapa: por enquanto o botão confirma o endereço e guarda a escolha.
 *
 * @var array<int, array<string, mixed>> $checkoutItens
 * @var array<int, array<string, mixed>> $checkoutEnderecos
 * @var int|null $checkoutEnderecoId
 * @var int $checkoutPecas
 * @var string $checkoutTotal
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$cepLabel = static function (string $cep): string {
	$digitos = preg_replace('/\D+/', '', $cep) ?? '';

	return strlen($digitos) === 8 ? substr($digitos, 0, 5) . '-' . substr($digitos, 5) : $cep;
};
?>
<section class="checkout">
	<div class="container checkout__inner">
		<header class="checkout__header">
			<p class="checkout__eyebrow">Checkout</p>
			<h1 class="checkout__title">Finalizar compra</h1>
			<p class="checkout__text">Confira os itens, escolha o endereço de entrega e pronto. O pagamento entra na próxima etapa.</p>
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
					<h2 class="checkout__panel-title">Endereço de entrega</h2>

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
									<?php $marcado = (int) $endereco['id'] === $checkoutEnderecoId; ?>
									<li>
										<label class="checkout-address<?= $marcado ? ' checkout-address--escolhido' : '' ?>">
											<input class="checkout-address__radio" type="radio" name="address_id" value="<?= (int) $endereco['id'] ?>"<?= $marcado ? ' checked' : '' ?>>

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
									</li>
								<?php endforeach; ?>
							</ul>

							<div class="checkout__actions">
								<button class="button" type="submit">Confirmar endereço</button>
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
						<dd>A calcular</dd>
					</div>

					<div class="checkout-summary__row checkout-summary__row--total">
						<dt>Total</dt>
						<dd><strong><?= $escape($checkoutTotal) ?></strong></dd>
					</div>
				</dl>

				<p class="checkout__note">O pagamento entra na próxima etapa — nada é cobrado agora.</p>

				<a class="button button--ghost checkout__back" href="/carrinho">Voltar ao carrinho</a>
			</aside>
		</div>
	</div>
</section>
