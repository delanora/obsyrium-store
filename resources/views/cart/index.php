<?php

declare(strict_types=1);

use App\Support\Cep;
use App\Support\Csrf;
use App\Support\Price;

/*
 * Página do carrinho. Recebe do CartController os itens já formatados e o
 * limite de unidades por item.
 *
 * Cada item traz o próprio formulário de quantidade (enviado sozinho quando o
 * número muda, pelo site.js) e de remoção, que volta para o carrinho.
 *
 * Abaixo da lista, o cálculo de frete por CEP: o formulário volta para
 * /carrinho?cep=... e o controller entrega as opções já com preço e prazo.
 *
 * @var list<array<string, mixed>> $itens
 * @var int $limite
 * @var string $freteCep
 * @var list<array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}> $freteOpcoes
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
// Total do carrinho: preço unitário vezes a quantidade de cada linha.
$totalCentavos = array_sum(array_map(
    static fn (array $item): int => (int) $item['price_cents'] * (int) $item['quantity'],
    $itens
));
?>
<section class="cart-page">
	<div class="container">
		<h1 class="cart-page__title">Seu carrinho</h1>


		<?php if ($itens === []): ?>			<div class="cart-empty">
				<p class="cart-empty__text">Seu carrinho está vazio.</p>
				<a class="button" href="/#lancamentos">Ver as estampas</a>
			</div>
		<?php else: ?>
			<ul class="cart-list">
				<?php foreach ($itens as $item): ?>
					<?php $id = 'quantidade-' . $item['variant_id']; ?>
					<li class="cart-item">
						<img class="cart-item__image" src="<?= $escape($item['image']) ?>" alt="<?= $escape($item['alt']) ?>" width="80" height="100" loading="lazy">

						<div class="cart-item__info">
							<a class="cart-item__name" href="/produto/<?= $escape($item['slug']) ?>"><?= $escape($item['name']) ?></a>
							<p class="cart-item__meta">Tamanho <?= $escape($item['size']) ?></p>

							<?php if (!$item['active']): ?>
								<p class="cart-item__warning">Este produto saiu do catálogo.</p>
							<?php elseif ($item['available'] === 0): ?>
								<p class="cart-item__warning">Sem estoque no momento.</p>
							<?php elseif ($item['available'] < $item['quantity']): ?>
								<p class="cart-item__warning">Restam <?= $item['available'] ?> unidade(s) deste tamanho.</p>
							<?php endif; ?>
						</div>

						<div class="cart-item__side">
							<p class="cart-item__price"><?= $escape($item['price']) ?></p>

							<form class="cart-quantity" action="/carrinho/atualizar" method="post">
								<?= Csrf::field() ?>
								<input type="hidden" name="variant_id" value="<?= $item['variant_id'] ?>">

								<label class="cart-quantity__label" for="<?= $escape($id) ?>">Quantidade</label>
								<input
									class="cart-quantity__input"
									type="number"
									id="<?= $escape($id) ?>"
									name="quantity"
									value="<?= $item['quantity'] ?>"
									min="1"
									max="<?= $limite ?>"
									required
								>
							</form>

							<form action="/carrinho/remover" method="post">
								<?= Csrf::field() ?>
								<input type="hidden" name="variant_id" value="<?= $item['variant_id'] ?>">

								<button class="cart-item__remove" type="submit">Remover</button>
							</form>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="cart-page__footer">
				<p class="cart-page__total">
					<span class="cart-page__total-label">Total</span>
					<strong class="cart-page__total-value"><?= $escape(Price::format($totalCentavos)) ?></strong>
				</p>

				<a class="button cart-page__checkout" href="/checkout">Finalizar compra</a>
			</div>

			<section class="cart-shipping" aria-labelledby="cart-frete">
				<h2 class="cart-shipping__title" id="cart-frete">Calcular frete</h2>
				<p class="cart-shipping__text">Informe o CEP de entrega para ver o frete e o prazo. O valor final aparece de novo no checkout.</p>

				<form class="cart-shipping__form" action="/carrinho" method="get">
					<label class="visually-hidden" for="frete-cep">CEP de entrega</label>
					<input
						class="field__input cart-shipping__input"
						id="frete-cep"
						name="cep"
						type="text"
						inputmode="numeric"
						autocomplete="postal-code"
						maxlength="9"
						placeholder="01234-567"
						value="<?= $escape(Cep::format($freteCep)) ?>"
						required
					>
					<button class="button button--ghost" type="submit">Calcular</button>
				</form>

				<?php if ($freteCep !== ''): ?>
					<?php if ($freteOpcoes === []): ?>
						<p class="cart-shipping__error" role="alert">Não encontramos esse CEP. Confira os oito dígitos e tente de novo.</p>
					<?php else: ?>
						<p class="cart-shipping__caption">Entrega para o CEP <?= $escape(Cep::format($freteCep)) ?></p>

						<ul class="cart-shipping__list">
							<?php foreach ($freteOpcoes as $opcao): ?>
								<li class="cart-shipping__option">
									<span class="cart-shipping__label"><?= $escape($opcao['label']) ?></span>
									<span class="cart-shipping__days"><?= $escape($opcao['days']) ?></span>
									<span class="cart-shipping__price"><?= $escape($opcao['price_label']) ?></span>
								</li>
							<?php endforeach; ?>
						</ul>

						<p class="cart-shipping__note">O frete é confirmado no checkout, com o endereço escolhido.</p>
					<?php endif; ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</div>
</section>
