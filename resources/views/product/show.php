<?php

declare(strict_types=1);

/*
 * Página individual do produto. Recebe de ProductController a galeria, os
 * tamanhos com disponibilidade e os textos de preço já formatados.
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
			<div class="product-gallery" data-reveal>
				<figure class="product-gallery__main">
					<img
						class="product-gallery__image"
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
						<?php foreach ($product['images'] as $imagem): ?>
							<li>
								<img src="<?= $escape($imagem['src']) ?>" alt="<?= $escape($imagem['alt']) ?>" width="64" height="80" loading="lazy">
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="product-info" data-reveal>
				<p class="product-info__eyebrow"><?= $escape($product['category']) ?></p>
				<h1 class="product-info__title"><?= $escape($product['name']) ?></h1>
				<p class="product-info__meta"><?= $escape($product['color']) ?> · <?= $escape($product['material']) ?></p>

				<p class="product-info__price">
					<?php if ($product['oldPrice'] !== null): ?>
						<span class="product-info__price-old"><?= $escape($product['oldPrice']) ?></span>
					<?php endif; ?>
					<span class="product-info__price-value"><?= $escape($product['price']) ?></span>
				</p>
				<p class="product-info__pix"><?= $escape($product['pix']) ?> no pix <span class="product-info__pix-off">(-5%)</span></p>
				<p class="product-info__installments"><?= $escape($product['installments']) ?></p>

				<fieldset class="size-picker product-info__sizes">
					<legend class="size-picker__legend">Tamanho</legend>
					<div class="size-picker__options">
						<?php foreach ($product['sizes'] as $tamanho): ?>
							<?php $id = 'tamanho-' . strtolower($tamanho['size']); ?>
							<input
								class="size-option__input"
								type="radio"
								name="size"
								id="<?= $escape($id) ?>"
								value="<?= $escape($tamanho['size']) ?>"
								<?= $tamanho['available'] ? '' : 'disabled' ?>
							>
							<label class="size-option" for="<?= $escape($id) ?>"><?= $escape($tamanho['size']) ?></label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<p class="product-info__stock product-info__stock--<?= $escape($product['stock']['state']) ?>"><?= $escape($product['stock']['label']) ?></p>

				<button class="button product-info__buy" type="button" disabled<?= $product['soldOut'] ? ' aria-describedby="tamanho-ajuda"' : '' ?>>Adicionar ao carrinho</button>
				<p class="product-info__hint" id="tamanho-ajuda">
					<?= $product['soldOut'] ? 'Todas as numerações estão esgotadas.' : 'Tamanhos riscados estão esgotados. Carrinho e pagamento chegam na próxima fase.' ?>
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
