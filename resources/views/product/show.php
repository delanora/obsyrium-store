<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Página individual do produto. Recebe de ProductController a galeria, os
 * tamanhos com disponibilidade e os textos de preço já formatados.
 *
 * O botão de compra envia variant_id (o tamanho escolhido) e o slug para
 * voltar para esta mesma página depois de gravar no carrinho.
 *
 * @var array<string, mixed> $product
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$principal = $product['images'][0];
?>
<section class="product-page">
	<div class="container">
		<nav class="product-breadcrumb" aria-label="Você está em">
			<a href="/">Home</a>
			<span aria-hidden="true">/</span>
			<span aria-current="page"><?= $escape($product['name']) ?></span>
		</nav>

		<div class="product-page__grid">
			<div class="product-heading" data-reveal>
				<p class="product-info__eyebrow"><?= $escape($product['category']) ?></p>
				<h1 class="product-info__title"><?= $escape($product['name']) ?></h1>
			</div>

			<div class="product-gallery" data-reveal>
				<figure class="product-gallery__main">
					<img
						class="product-gallery__image"
						data-gallery-main
						src="<?= $escape($principal['src']) ?>"
						alt="<?= $escape($principal['alt']) ?>"
						width="600"
						height="800"
					>
					<?php if ($product['discount'] > 0): ?>
						<span class="product-gallery__badge">-<?= $product['discount'] ?>%</span>
					<?php endif; ?>
				</figure>

				<?php if (count($product['images']) > 1): ?>
					<ul class="product-gallery__thumbs">
						<?php foreach ($product['images'] as $indice => $imagem): ?>
							<li>
								<button
									class="product-gallery__thumb"
									type="button"
									data-gallery-src="<?= $escape($imagem['src']) ?>"
									data-gallery-alt="<?= $escape($imagem['alt']) ?>"
									aria-pressed="<?= $indice === 0 ? 'true' : 'false' ?>"
									aria-label="Ver a foto <?= $indice + 1 ?>: <?= $escape($imagem['alt']) ?>"
								>
									<img src="<?= $escape($imagem['src']) ?>" alt="" width="64" height="80" loading="lazy">
								</button>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="product-info" data-reveal>
				<p class="product-info__meta"><?= $escape($product['color']) ?> · <?= $escape($product['material']) ?></p>

				<p class="product-info__price">
					<?php if ($product['oldPrice'] !== null): ?>
						<span class="product-info__price-old"><?= $escape($product['oldPrice']) ?></span>
					<?php endif; ?>
					<span class="product-info__price-value"><?= $escape($product['price']) ?></span>
				</p>
				<p class="product-info__pix"><?= $escape($product['pix']) ?> no pix <span class="product-info__pix-off">(-5%)</span></p>
				<p class="product-info__installments"><?= $escape($product['installments']) ?></p>

				<form class="product-form" action="/carrinho/adicionar" method="post">
					<?= Csrf::field() ?>
					<input type="hidden" name="slug" value="<?= $escape($product['slug']) ?>">
					<input type="hidden" name="voltar" value="<?= $escape($product['voltar']) ?>">

					<fieldset class="size-picker product-info__sizes">
						<legend class="size-picker__legend">Tamanho</legend>
						<div class="size-picker__options">
							<?php foreach ($product['sizes'] as $tamanho): ?>
								<?php $id = 'tamanho-' . strtolower($tamanho['size']); ?>
								<input
									class="size-option__input"
									type="radio"
									name="variant_id"
									id="<?= $escape($id) ?>"
									value="<?= $tamanho['id'] ?>"
									required
									<?= $tamanho['available'] ? '' : 'disabled' ?>
								>
								<label class="size-option" for="<?= $escape($id) ?>"><?= $escape($tamanho['size']) ?></label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<p class="product-info__stock product-info__stock--<?= $escape($product['stock']['state']) ?>"><?= $escape($product['stock']['label']) ?></p>

					<button class="button product-info__buy" type="submit"<?= $product['soldOut'] ? ' disabled aria-describedby="tamanho-ajuda"' : '' ?>>Adicionar ao carrinho</button>
				</form>

				<p class="product-info__hint" id="tamanho-ajuda">
					<?= $product['soldOut'] ? 'Todas as numerações estão esgotadas.' : 'Tamanhos riscados estão esgotados. Escolha um disponível para adicionar ao carrinho.' ?>
				</p>

				<?php if ($product['description'] !== ''): ?>
					<div class="product-info__description">
						<h2 class="product-info__subtitle">Detalhes</h2>
						<p><?= nl2br($escape($product['description'])) ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
