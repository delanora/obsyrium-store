<?php

declare(strict_types=1);

/*
 * Card de produto da vitrine e do catálogo. Recebe $product já formatado por
 * App\Support\ProductCard.
 *
 * @var array<string, mixed> $product
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<article class="product-card<?= $product['soldOut'] ? ' product-card--sold-out' : '' ?>">
	<figure class="product-card__media">
		<img
			class="product-card__image"
			src="<?= $escape((string) $product['image']) ?>"
			alt="<?= $escape((string) $product['imageAlt']) ?>"
			width="300"
			height="400"
			loading="lazy"
		>
		<?php if ($product['badge'] !== null): ?>
			<div class="product-card__badges">
				<span class="product-card__badge<?= $product['badgeAccent'] ? ' product-card__badge--accent' : '' ?>"><?= $escape((string) $product['badge']) ?></span>
			</div>
		<?php endif; ?>
	</figure>

	<div class="product-card__body">
		<h3 class="product-card__title"><a href="<?= $escape((string) $product['href']) ?>"><?= $escape((string) $product['name']) ?></a></h3>
		<p class="product-card__meta"><?= $escape((string) $product['color']) ?> · <?= $escape((string) $product['material']) ?></p>
		<p class="product-card__price">
			<?php if ($product['oldPrice'] !== null): ?>
				<span class="product-card__price-old"><?= $escape((string) $product['oldPrice']) ?></span>
			<?php endif; ?>
			<span class="product-card__price-current"><?= $escape((string) $product['price']) ?></span>
			<span class="product-card__price-note">ou <strong><?= $escape((string) $product['pix']) ?> no pix</strong></span>
			<span class="product-card__price-note"><?= $escape((string) $product['installments']) ?></span>
		</p>
	</div>
</article>
