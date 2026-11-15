<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Página do carrinho. Recebe do CartController os itens já formatados e o
 * limite de unidades por item.
 *
 * Cada item traz o próprio formulário de quantidade e de remoção: as duas
 * ações postam nas rotas que já existem e voltam para o carrinho.
 *
 * @var list<array<string, mixed>> $itens
 * @var int $limite
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
								<button class="cart-quantity__submit" type="submit">Atualizar</button>
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
				<a class="button cart-page__checkout" href="/checkout">Finalizar compra</a>
			</div>
		<?php endif; ?>
	</div>
</section>
