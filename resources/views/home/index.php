<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Obsyrium Store</title>
	<link rel="stylesheet" href="/assets/css/tokens.css">
	<link rel="stylesheet" href="/assets/css/base.css">
	<link rel="stylesheet" href="/assets/css/components.css">
	<style>
		/* Layout apenas desta prévia. A página inicial será construída na fase 4. */
		.preview {
			display: flex;
			flex-direction: column;
			gap: var(--section-space);
			padding-block: var(--space-8);
		}

		.preview__note {
			max-width: 60ch;
			color: var(--color-text-muted);
			font-size: var(--font-size-sm);
		}

		.preview__section {
			display: flex;
			flex-direction: column;
			gap: var(--space-4);
		}

		.preview__section > h2 {
			font-size: var(--font-size-lg);
			color: var(--color-text-muted);
			letter-spacing: var(--letter-spacing-wide);
		}

		.preview__max {
			max-width: 22rem;
		}
	</style>
</head>
<body>
	<main class="container preview">
		<header>
			<h1>Obsyrium Store</h1>
			<p class="preview__note">
				Prévia dos componentes base (seletor de tamanho, card de produto e faixa de confiança)
				sobre os tokens de identidade visual. A página inicial da loja será construída na fase 4.
			</p>
		</header>

		<section class="preview__section">
			<h2>Seletor de tamanho</h2>

			<fieldset class="size-picker preview__max">
				<legend class="size-picker__legend">Tamanho</legend>
				<div class="size-picker__options">
					<input class="size-option__input" type="radio" name="size" id="size-pp" value="PP">
					<label class="size-option" for="size-pp">PP</label>

					<input class="size-option__input" type="radio" name="size" id="size-p" value="P">
					<label class="size-option" for="size-p">P</label>

					<input class="size-option__input" type="radio" name="size" id="size-m" value="M" checked>
					<label class="size-option" for="size-m">M</label>

					<input class="size-option__input" type="radio" name="size" id="size-g" value="G">
					<label class="size-option" for="size-g">G</label>

					<input class="size-option__input" type="radio" name="size" id="size-gg" value="GG" disabled>
					<label class="size-option" for="size-gg">GG<span class="visually-hidden"> (esgotado)</span></label>
				</div>
				<p class="size-picker__hint">Modelagem oversized. <a href="#">Ver tabela de medidas</a></p>
			</fieldset>
		</section>

		<section class="preview__section">
			<h2>Card de produto</h2>

			<ul class="product-grid">
				<li>
					<article class="product-card">
						<figure class="product-card__media">
							<img class="product-card__image" src="/assets/images/placeholder.svg" alt="Camiseta Estampa 01, modelagem oversized, na cor preta" width="300" height="400">
							<div class="product-card__badges">
								<span class="product-card__badge product-card__badge--accent">Novo</span>
							</div>
						</figure>
						<div class="product-card__body">
							<h3 class="product-card__title"><a href="#">Camiseta Estampa 01</a></h3>
							<p class="product-card__meta">Preta · algodão 100%</p>
							<p class="product-card__price">
								<span class="product-card__price-current">R$ 129,90</span>
								<span class="product-card__price-note">ou <strong>R$ 110,42 no pix</strong></span>
								<span class="product-card__price-note">3x de R$ 43,30 sem juros</span>
							</p>
						</div>
					</article>
				</li>

				<li>
					<article class="product-card">
						<figure class="product-card__media">
							<img class="product-card__image" src="/assets/images/placeholder.svg" alt="Camiseta Estampa 02, modelagem oversized, na cor off white" width="300" height="400">
							<div class="product-card__badges">
								<span class="product-card__badge">Drop 01</span>
								<span class="product-card__badge product-card__badge--accent">-15%</span>
							</div>
						</figure>
						<div class="product-card__body">
							<h3 class="product-card__title"><a href="#">Camiseta Estampa 02</a></h3>
							<p class="product-card__meta">Off white · algodão 100%</p>
							<p class="product-card__price">
								<span class="product-card__price-old">R$ 149,90</span>
								<span class="product-card__price-current">R$ 127,90</span>
								<span class="product-card__price-note">ou <strong>R$ 108,72 no pix</strong></span>
							</p>
						</div>
					</article>
				</li>

				<li>
					<article class="product-card product-card--sold-out">
						<figure class="product-card__media">
							<img class="product-card__image" src="/assets/images/placeholder.svg" alt="Camiseta Estampa 03, modelagem oversized, na cor grafite" width="300" height="400" loading="lazy">
							<div class="product-card__badges">
								<span class="product-card__badge">Esgotado</span>
							</div>
						</figure>
						<div class="product-card__body">
							<h3 class="product-card__title"><a href="#">Camiseta Estampa 03</a></h3>
							<p class="product-card__meta">Grafite · algodão 100%</p>
							<p class="product-card__price">
								<span class="product-card__price-current">R$ 129,90</span>
								<span class="product-card__price-note">Avise-me quando voltar</span>
							</p>
						</div>
					</article>
				</li>
			</ul>
		</section>

		<section class="preview__section">
			<h2>Faixa de confiança</h2>

			<ul class="trust-bar">
				<li class="trust-bar__item">
					<span class="trust-bar__icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/></svg>
					</span>
					<span>
						<span class="trust-bar__label">Pix e cartão</span>
						<span class="trust-bar__text">Até 3x sem juros</span>
					</span>
				</li>

				<li class="trust-bar__item">
					<span class="trust-bar__icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h11v8H3z"/><path d="M14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="1.6"/><circle cx="17" cy="17" r="1.6"/></svg>
					</span>
					<span>
						<span class="trust-bar__label">Entrega para todo o Brasil</span>
						<span class="trust-bar__text">Prazo informado no carrinho</span>
					</span>
				</li>

				<li class="trust-bar__item">
					<span class="trust-bar__icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h13l-3-3"/><path d="M20 16H7l3 3"/></svg>
					</span>
					<span>
						<span class="trust-bar__label">Primeira troca grátis</span>
						<span class="trust-bar__text">Você tem 7 dias</span>
					</span>
				</li>

				<li class="trust-bar__item">
					<span class="trust-bar__icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/></svg>
					</span>
					<span>
						<span class="trust-bar__label">Compra segura</span>
						<span class="trust-bar__text">Seus dados protegidos</span>
					</span>
				</li>
			</ul>
		</section>
	</main>
</body>
</html>
