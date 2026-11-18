<?php

declare(strict_types=1);

use App\Support\Cep;
use App\Support\Csrf;

/*
 * Página individual do produto. Recebe de ProductController a galeria, os
 * tamanhos com disponibilidade e os textos de preço já formatados.
 *
 * O botão de compra envia variant_id (o tamanho escolhido) e o slug para
 * voltar para esta mesma página depois de gravar no carrinho.
 *
 * @var array<string, mixed> $product
 * @var list<array{id: int, label: string, chest: int|null, length: int|null}> $guia
 * @var array{cep: string, opcoes: list<array<string, mixed>>, erro: string} $productFrete
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

					<button
						class="product-gallery__zoom"
						type="button"
						data-gallery-zoom
						aria-label="Ampliar foto"
						aria-haspopup="dialog"
					>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/><path d="M8.5 11h5"/><path d="M11 8.5v5"/></svg>
					</button>
				</figure>

				<p class="visually-hidden" role="status" aria-live="polite" data-gallery-status></p>

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

				<dialog class="product-zoom" data-gallery-dialog aria-label="Foto ampliada">
					<button class="product-zoom__close" type="button" data-gallery-close aria-label="Fechar a foto ampliada">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg>
					</button>

					<img class="product-zoom__image" data-gallery-zoom-image src="<?= $escape($principal['src']) ?>" alt="<?= $escape($principal['alt']) ?>">
				</dialog>
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

					<?php if ($guia !== []): ?>
						<p class="product-info__guide-line">
							<button class="product-info__guide" type="button" data-guide-open aria-haspopup="dialog">Guia de medidas</button>
						</p>
					<?php endif; ?>

					<p class="product-info__stock product-info__stock--<?= $escape($product['stock']['state']) ?>"><?= $escape($product['stock']['label']) ?></p>

					<button class="button product-info__buy" type="submit"<?= $product['soldOut'] ? ' disabled aria-describedby="tamanho-ajuda"' : '' ?>>Adicionar ao carrinho</button>
				</form>

				<?php if ($guia !== []): ?>
					<dialog class="product-guide" data-guide-dialog aria-labelledby="guia-titulo">
						<div class="product-guide__head">
							<h2 class="product-guide__title" id="guia-titulo">Guia de medidas</h2>

							<button class="product-guide__close" type="button" data-guide-close aria-label="Fechar o guia de medidas">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg>
							</button>
						</div>

						<table class="product-guide__table">
							<caption class="visually-hidden">Medidas da camiseta em centímetros</caption>
							<thead>
								<tr>
									<th scope="col">Tamanho</th>
									<th scope="col">Largura</th>
									<th scope="col">Comprimento</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($guia as $medida): ?>
									<tr>
										<th scope="row"><?= $escape($medida['label']) ?></th>
										<td><?= $medida['chest'] !== null ? $medida['chest'] . ' cm' : '—' ?></td>
										<td><?= $medida['length'] !== null ? $medida['length'] . ' cm' : '—' ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<p class="product-guide__hint">Medidas aproximadas da peça estendida na mesa, com tolerância de 2 cm. Largura de uma axila à outra; comprimento do ombro até a barra.</p>
					</dialog>
				<?php endif; ?>

				<p class="product-info__hint" id="tamanho-ajuda">
					<?= $product['soldOut'] ? 'Todas as numerações estão esgotadas.' : 'Tamanhos riscados estão esgotados. Escolha um disponível para adicionar ao carrinho.' ?>
				</p>

				<details class="product-shipping"<?= $productFrete['cep'] !== '' ? ' open' : '' ?>>
					<summary class="product-shipping__toggle">Consultar frete</summary>

					<div class="product-shipping__body">
						<p class="product-shipping__text">Digite o CEP de entrega para ver o frete e o prazo desta peça.</p>

						<form class="product-shipping__form" action="/produto/<?= rawurlencode($product['slug']) ?>" method="get">
							<label class="visually-hidden" for="frete-cep-produto">CEP de entrega</label>
							<input
								class="field__input product-shipping__input"
								id="frete-cep-produto"
								name="cep"
								type="text"
								inputmode="numeric"
								autocomplete="postal-code"
								maxlength="9"
								placeholder="01234-567"
								value="<?= $escape(Cep::format($productFrete['cep'])) ?>"
								required
							>
							<button class="button button--ghost" type="submit">Calcular</button>
						</form>

						<?php if ($productFrete['erro'] !== ''): ?>
							<p class="product-shipping__error" role="alert"><?= $escape($productFrete['erro']) ?></p>
						<?php elseif ($productFrete['opcoes'] !== []): ?>
							<p class="product-shipping__caption">Entrega para o CEP <?= $escape(Cep::format($productFrete['cep'])) ?></p>

							<ul class="product-shipping__list">
								<?php foreach ($productFrete['opcoes'] as $opcao): ?>
									<li class="product-shipping__option">
										<span class="product-shipping__label"><?= $escape((string) $opcao['label']) ?></span>
										<span class="product-shipping__days"><?= $escape((string) $opcao['days']) ?></span>
										<span class="product-shipping__price"><?= $escape((string) $opcao['price_label']) ?></span>
									</li>
								<?php endforeach; ?>
							</ul>

							<p class="product-shipping__note">Estimativa para uma peça. O frete final é confirmado no checkout, com o endereço escolhido.</p>
						<?php endif; ?>
					</div>
				</details>

				<?php if ($product['description'] !== ''): ?>
					<div class="product-info__description">
						<h2 class="product-info__subtitle">Detalhes</h2>
						<p><?= nl2br($escape($product['description'])) ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php
		// O card da vitrine espera $product; guarda o produto aberto e devolve
		// cada sugestão no lugar dele só enquanto o partial é desenhado.
		$produtoAberto = $product;
		$sugestoesDaPagina = [
			['titulo' => 'Outras cores desta estampa', 'id' => 'cores-da-estampa', 'itens' => $produtoAberto['colorProducts']],
			['titulo' => 'Você também pode gostar', 'id' => 'produtos-relacionados', 'itens' => $produtoAberto['related']],
		];
		?>

		<?php foreach ($sugestoesDaPagina as $sugestao): ?>
			<?php if ($sugestao['itens'] === []) { continue; } ?>
			<section class="product-related" aria-labelledby="<?= $sugestao['id'] ?>">
				<h2 class="section__title product-related__title" id="<?= $sugestao['id'] ?>"><?= $escape($sugestao['titulo']) ?></h2>

				<ul class="product-grid product-related__grid">
					<?php foreach ($sugestao['itens'] as $product): ?>
						<li data-reveal>
							<?php require dirname(__DIR__) . '/partials/product-card.php'; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
	</div>
</section>
