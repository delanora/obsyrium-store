<?php

declare(strict_types=1);

/*
 * Vitrine da página inicial. Recebe de HomeController a lista de cards já
 * formatada (nome, cor, preço, imagem e selo), montada a partir do banco.
 *
 * @var list<array<string, mixed>> $products
 */
?>
<section class="hero" aria-label="Destaques da loja">
	<div class="hero__slides">
		<article class="hero__slide" id="slide-1">
			<div class="container hero__inner" data-reveal-stagger>
				<p class="hero__eyebrow">Drop 01 · nova coleção</p>
				<h1 class="hero__title">Estampas autorais com personalidade</h1>
				<p class="hero__text">
					Camisetas em algodão premium, com estampa serigráfica de alta definição
					e acabamento cuidadoso para o dia a dia.
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
			<a class="section__link" href="/catalogo">Ver todos</a>
		</div>

		<?php if ($products === []): ?>
			<p class="product-card__meta">Nenhum produto publicado ainda.</p>
		<?php endif; ?>

		<ul class="product-grid">
			<?php foreach ($products as $product): ?>
				<li data-reveal>
					<?php require dirname(__DIR__) . '/partials/product-card.php'; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<section class="section section--alt">
	<div class="container content-block" data-reveal>
		<h2>Camisetas com estampas autorais, feitas para o dia a dia</h2>
		<p>
			A <strong>Obsyrium Store</strong> trabalha com estampas próprias, em malha de algodão de gramatura alta
			e caimento oversized.
		</p>
		<p>
			Cada estampa é produzida com atenção ao detalhe, do desenho à serigrafia. As peças vão do PP ao GG,
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
