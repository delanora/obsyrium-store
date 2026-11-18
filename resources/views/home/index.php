<?php

declare(strict_types=1);

use App\Services\ContactService;
use App\Support\Csrf;

/*
 * Vitrine da página inicial. Recebe de HomeController a lista de cards já
 * formatada (nome, cor, preço, imagem e selo), montada a partir do banco.
 *
 * O bloco "Tem alguma ideia?" pede estampas para a loja: a mensagem entra na
 * mesma caixa do contato, marcada como ideia, e os erros voltam para cá.
 *
 * @var list<array<string, mixed>> $products
 * @var list<string> $ideiaErros
 * @var array{name: string, email: string, phone: string, message: string} $ideiaValores
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$errosIdeia = $ideiaErros ?? [];
$valoresIdeia = $ideiaValores ?? ['name' => '', 'email' => '', 'phone' => '', 'message' => ''];
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

<section class="idea" id="ideia" data-reveal-stagger aria-labelledby="ideia-titulo">
	<h2 class="idea__title" id="ideia-titulo">Tem alguma ideia?</h2>
	<p class="idea__text">
		Quer uma estampa que ainda não está na loja? Escreva qual é: lemos cada ideia
		e respondemos no e-mail informado.
	</p>

	<?php if ($errosIdeia !== []): ?>
		<div class="idea__alert" role="alert">
			<ul>
				<?php foreach ($errosIdeia as $erro): ?>
					<li><?= $escape($erro) ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form class="idea__form" action="/ideia" method="post">
		<?= Csrf::field() ?>

		<?php /* Armadilha para robô: gente não vê, robô preenche. */ ?>
		<div class="idea__honeypot" aria-hidden="true">
			<label for="ideia-website">Não preencha este campo</label>
			<input id="ideia-website" name="website" type="text" tabindex="-1" autocomplete="off">
		</div>

		<div class="field">
			<label class="field__label" for="ideia-nome">Nome</label>
			<input class="field__input" id="ideia-nome" name="name" type="text" maxlength="<?= ContactService::MAX_NAME ?>" autocomplete="name" required value="<?= $escape($valoresIdeia['name']) ?>">
		</div>

		<div class="field">
			<label class="field__label" for="ideia-email">E-mail</label>
			<input class="field__input" id="ideia-email" name="email" type="email" maxlength="<?= ContactService::MAX_EMAIL ?>" autocomplete="email" required value="<?= $escape($valoresIdeia['email']) ?>">
		</div>

		<div class="field">
			<label class="field__label" for="ideia-telefone">Telefone</label>
			<input class="field__input" id="ideia-telefone" name="phone" type="tel" maxlength="15" autocomplete="tel" inputmode="tel" placeholder="(11) 99999-9999" data-phone-mask required value="<?= $escape($valoresIdeia['phone']) ?>">
		</div>

		<div class="field idea__field--wide">
			<label class="field__label" for="ideia-mensagem">Qual estampa você quer ver?</label>
			<textarea class="field__input field__input--textarea" id="ideia-mensagem" name="message" maxlength="<?= ContactService::MAX_MESSAGE ?>" rows="5" placeholder="Conte o tema, a referência ou a frase que você imaginou." required><?= $escape($valoresIdeia['message']) ?></textarea>
		</div>

		<div class="idea__submit idea__field--wide">
			<button class="button" type="submit">Enviar ideia</button>
		</div>
	</form>

	<p class="idea__note">
		Sua ideia vai direto para a loja. Se ela virar estampa, avisamos no e-mail informado.
	</p>
</section>
