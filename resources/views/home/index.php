<?php

declare(strict_types=1);

/*
 * Vitrine da página inicial.
 *
 * Os produtos são de demonstração e serão carregados do banco na fase 5.
 */

$products = [
    ['name' => 'Camiseta Estampa 01', 'color' => 'Preta', 'price' => 'R$ 129,90', 'pix' => 'R$ 123,41', 'installments' => '3x de R$ 43,30 sem juros', 'badge' => 'Novo', 'old' => null, 'soldOut' => false],
    ['name' => 'Camiseta Estampa 02', 'color' => 'Off white', 'price' => 'R$ 127,90', 'pix' => 'R$ 121,51', 'installments' => '3x de R$ 42,63 sem juros', 'badge' => 'Drop 01', 'old' => 'R$ 149,90', 'soldOut' => false],
    ['name' => 'Camiseta Estampa 03', 'color' => 'Grafite', 'price' => 'R$ 129,90', 'pix' => 'R$ 123,41', 'installments' => '3x de R$ 43,30 sem juros', 'badge' => 'Esgotado', 'old' => null, 'soldOut' => true],
    ['name' => 'Camiseta Estampa 04', 'color' => 'Preta', 'price' => 'R$ 139,90', 'pix' => 'R$ 132,91', 'installments' => '3x de R$ 46,63 sem juros', 'badge' => null, 'old' => null, 'soldOut' => false],
    ['name' => 'Camiseta Estampa 05', 'color' => 'Marrom', 'price' => 'R$ 139,90', 'pix' => 'R$ 132,91', 'installments' => '3x de R$ 46,63 sem juros', 'badge' => null, 'old' => null, 'soldOut' => false],
    ['name' => 'Camiseta Estampa 06', 'color' => 'Off white', 'price' => 'R$ 129,90', 'pix' => 'R$ 123,41', 'installments' => '3x de R$ 43,30 sem juros', 'badge' => null, 'old' => null, 'soldOut' => false],
    ['name' => 'Camiseta Estampa 07', 'color' => 'Verde musgo', 'price' => 'R$ 149,90', 'pix' => 'R$ 142,41', 'installments' => '3x de R$ 49,97 sem juros', 'badge' => 'Novo', 'old' => null, 'soldOut' => false],
    ['name' => 'Camiseta Estampa 08', 'color' => 'Bordô', 'price' => 'R$ 129,90', 'pix' => 'R$ 123,41', 'installments' => '3x de R$ 43,30 sem juros', 'badge' => 'Esgotado', 'old' => null, 'soldOut' => true],
];

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="hero" aria-label="Destaques da loja">
	<div class="hero__slides">
		<article class="hero__slide" id="slide-1">
			<div class="container hero__inner" data-reveal-stagger>
				<p class="hero__eyebrow">Drop 01 · tiragem limitada</p>
				<h1 class="hero__title">Estampas autorais em pequenos lotes</h1>
				<p class="hero__text">
					Camisetas em algodão premium, com estampa serigráfica e tiragem limitada.
					Sem reposição depois que o lote acaba.
				</p>
				<p class="hero__actions">
					<a class="button" href="#lancamentos">Ver lançamentos</a>
					<a class="button button--ghost" href="#">Tabela de medidas</a>
				</p>
			</div>
		</article>

		<article class="hero__slide" id="slide-2">
			<div class="container hero__inner" data-reveal-stagger>
				<p class="hero__eyebrow">Algodão premium</p>
				<h2 class="hero__title">Gramatura alta, caimento oversized</h2>
				<p class="hero__text">
					Malha encorpada que não deforma na lavagem, em até 3x sem juros ou com desconto no Pix.
				</p>
				<p class="hero__actions">
					<a class="button" href="#lancamentos">Conhecer as estampas</a>
				</p>
			</div>
		</article>
	</div>

	<p class="hero__dots">
		<a class="hero__dot" href="#slide-1" aria-current="true"><span class="visually-hidden">Ir para o primeiro destaque</span></a>
		<a class="hero__dot" href="#slide-2"><span class="visually-hidden">Ir para o segundo destaque</span></a>
	</p>
</section>

<section class="section" id="lancamentos" aria-labelledby="lancamentos-titulo">
	<div class="container">
		<div class="section__head" data-reveal>
			<h2 class="section__title" id="lancamentos-titulo">Lançamentos</h2>
			<a class="section__link" href="#">Ver todos</a>
		</div>

		<ul class="product-grid">
			<?php foreach ($products as $product): ?>
				<li data-reveal>
					<article class="product-card<?= $product['soldOut'] ? ' product-card--sold-out' : '' ?>">
						<figure class="product-card__media">
							<img
								class="product-card__image"
								src="/assets/images/placeholder.svg"
								alt="Camiseta <?= $escape($product['name']) ?>, modelagem oversized, na cor <?= $escape($product['color']) ?>"
								width="300"
								height="400"
								loading="lazy"
							>
							<?php if ($product['badge'] !== null): ?>
								<div class="product-card__badges">
									<span class="product-card__badge<?= $product['badge'] === 'Novo' || $product['badge'] === 'Drop 01' ? ' product-card__badge--accent' : '' ?>"><?= $escape($product['badge']) ?></span>
								</div>
							<?php endif; ?>
						</figure>

						<div class="product-card__body">
							<h3 class="product-card__title"><a href="#"><?= $escape($product['name']) ?></a></h3>
							<p class="product-card__meta"><?= $escape($product['color']) ?> · algodão 100%</p>
							<p class="product-card__price">
								<?php if ($product['old'] !== null): ?>
									<span class="product-card__price-old"><?= $escape($product['old']) ?></span>
								<?php endif; ?>
								<span class="product-card__price-current"><?= $escape($product['price']) ?></span>
								<span class="product-card__price-note">ou <strong><?= $escape($product['pix']) ?> no pix</strong></span>
								<span class="product-card__price-note"><?= $escape($product['installments']) ?></span>
							</p>
						</div>
					</article>
				</li>
			<?php endforeach; ?>
		</ul>

		<nav class="pagination" aria-label="Paginação da vitrine">
			<span class="pagination__item" aria-current="page">1</span>
		</nav>
	</div>
</section>

<section class="section section--alt">
	<div class="container content-block" data-reveal>
		<h2>Camisetas com estampas autorais, feitas em pequenos lotes</h2>
		<p>
			A <strong>Obsyrium Store</strong> trabalha com estampas próprias, produzidas em tiragens curtas,
			em malha de algodão de gramatura alta e caimento oversized.
		</p>
		<p>
			Cada lote é estampado uma única vez: quando esgota, não volta. As peças vão do PP ao GG,
			com tabela de medidas em centímetros ao lado do seletor de tamanho em cada produto.
		</p>
	</div>
</section>

<section class="newsletter" data-reveal-stagger aria-labelledby="newsletter-titulo">
	<h2 class="newsletter__title" id="newsletter-titulo">Cadastre-se e receba os próximos drops</h2>
	<p class="newsletter__text">
		Avisamos antes de cada lançamento e abrimos a lista de espera das estampas esgotadas.
	</p>

	<div class="newsletter__form">
		<label class="visually-hidden" for="newsletter-email">Seu e-mail</label>
		<input class="newsletter__input" type="email" id="newsletter-email" name="email" placeholder="seu@email.com" autocomplete="email">
		<button class="button" type="button" disabled>Cadastrar</button>
	</div>

	<p class="newsletter__note">
		Cadastro será habilitado quando o envio de e-mails estiver implementado.
		Ao se cadastrar você concorda com a <a href="#">Política de Privacidade</a>.
	</p>
</section>
