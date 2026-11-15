<?php

declare(strict_types=1);

use App\Services\CartService;
use App\Support\Csrf;
use App\Support\Price;

/*
 * Gaveta lateral do carrinho, aberta pelo ícone do cabeçalho no computador.
 * No celular o ícone continua levando para /carrinho.
 *
 * Recebe do cabeçalho os itens já formatados pelo CartService.
 *
 * @var list<array<string, mixed>> $itensDoCarrinho
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$voltar = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$voltar = is_string($voltar) && $voltar !== '' ? $voltar : '/';
$quantidadeTotal = array_sum(array_column($itensDoCarrinho, 'quantity'));
$limiteDaGaveta = CartService::MAX_PER_ITEM;

$totalCentavos = array_sum(array_map(
    static fn (array $item): int => (int) $item['price_cents'] * (int) $item['quantity'],
    $itensDoCarrinho
));
?>
<div class="cart-drawer" id="cart-drawer" data-cart-drawer hidden>
	<div class="cart-drawer__backdrop" data-cart-close></div>

	<aside class="cart-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title">
		<header class="cart-drawer__header">
			<h2 class="cart-drawer__title" id="cart-drawer-title">Seu carrinho</h2>
			<button class="cart-drawer__close" type="button" data-cart-close aria-label="Fechar carrinho">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg>
			</button>
		</header>

		<?php if ($itensDoCarrinho === []): ?>
			<div class="cart-drawer__empty">
				<p>Seu carrinho está vazio.</p>
				<a class="button" href="/#lancamentos">Ver as estampas</a>
			</div>
		<?php else: ?>
			<ul class="cart-drawer__list">
				<?php foreach ($itensDoCarrinho as $item): ?>
					<li class="cart-drawer__item">
						<img class="cart-drawer__image" src="<?= $escape($item['image']) ?>" alt="<?= $escape($item['alt']) ?>" width="56" height="70" loading="lazy">

						<div class="cart-drawer__info">
							<a class="cart-drawer__name" href="/produto/<?= $escape($item['slug']) ?>"><?= $escape($item['name']) ?></a>
<p class="cart-drawer__meta">Tam. <?= $escape($item['size']) ?> · <?= $escape($item['price']) ?></p>

						<form class="cart-drawer__quantity" action="/carrinho/atualizar" method="post">
							<?= Csrf::field() ?>
							<input type="hidden" name="variant_id" value="<?= (int) $item['variant_id'] ?>">
							<input type="hidden" name="quantity" value="<?= (int) $item['quantity'] ?>" data-quantity-input data-max="<?= $limiteDaGaveta ?>">

							<button class="cart-drawer__step" type="button" data-quantity-step="-1" aria-label="Diminuir quantidade"<?= (int) $item['quantity'] <= 1 ? ' disabled' : '' ?>>−</button>
							<span class="cart-drawer__quantity-value" aria-hidden="true"><?= (int) $item['quantity'] ?></span>
							<button class="cart-drawer__step" type="button" data-quantity-step="1" aria-label="Aumentar quantidade"<?= (int) $item['quantity'] >= $limiteDaGaveta ? ' disabled' : '' ?>>+</button>
						</form>

							<?php if (!$item['active']): ?>
								<p class="cart-drawer__warning">Fora do catálogo.</p>
							<?php elseif ($item['available'] === 0): ?>
								<p class="cart-drawer__warning">Sem estoque no momento.</p>
							<?php endif; ?>
						</div>

						<form action="/carrinho/remover" method="post">
							<?= Csrf::field() ?>
							<input type="hidden" name="variant_id" value="<?= (int) $item['variant_id'] ?>">
							<input type="hidden" name="voltar" value="<?= $escape($voltar) ?>">
							<button class="cart-drawer__remove" type="submit">Remover</button>
						</form>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="cart-drawer__footer">
				<p class="cart-drawer__total">
					<span>Total</span>
					<strong><?= $escape(Price::format($totalCentavos)) ?></strong>
				</p>
				<p class="cart-drawer__count"><?= $quantidadeTotal ?> <?= $quantidadeTotal === 1 ? 'item' : 'itens' ?></p>
				<a class="button cart-drawer__checkout" href="/checkout">Finalizar compra</a>
				<a class="cart-drawer__link" href="/carrinho">Ver carrinho completo</a>
			</div>
		<?php endif; ?>
	</aside>
</div>
