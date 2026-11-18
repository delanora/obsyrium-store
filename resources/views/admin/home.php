<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Página inicial no painel: as fotos do topo da loja e os textos que aparecem
 * sobre elas.
 *
 * Cada foto é um slide do carrossel da home, listado como card. O card é o
 * resumo; clicar nele abre a janela de edição (`<dialog>`), com a prévia do
 * topo no alto e os campos abaixo. O texto é opcional: com todos os campos
 * vazios, a loja mostra só a foto, sem véu escuro e sem botão. A ordem é a de
 * envio e a primeira foto ativa é a que abre a página; "Mostrar esta foto"
 * esconde o slide sem apagar o arquivo.
 *
 * @var list<array{id: int, image: string, image_alt: string, eyebrow: string, title: string, text: string, button_label: string, button_href: string, is_active: bool, position: int}> $adminHero
 * @var string $adminHeroLimite
 * @var int $adminHeroMax
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$total = count($adminHero);
?>
<p class="admin-crumb">
	<a href="/admin">Dashboard</a>
	<span aria-hidden="true">/</span>
	<span aria-current="page">Página inicial</span>
</p>

<header class="admin-head admin-head--row">
	<div>
		<p class="admin-head__eyebrow">Painel</p>
		<h1 class="admin-head__title">Página inicial</h1>
		<p class="admin-head__text">As fotos do topo da loja e o texto que aparece sobre cada uma. Sem texto, a foto aparece sozinha.</p>
	</div>

	<p class="admin-head__actions">
		<a class="admin-link-button" href="/" target="_blank" rel="noopener">Ver a loja</a>
	</p>
</header>

<section class="admin-product__form" aria-labelledby="hero-enviar-titulo">
	<h2 class="admin-product__subtitle" id="hero-enviar-titulo">Adicionar foto</h2>

	<?php if ($total >= $adminHeroMax): ?>
		<p class="admin-product__empty">O topo já tem <?= $adminHeroMax ?> fotos. Apague uma para enviar outra.</p>
	<?php else: ?>
		<form class="admin-upload" action="/admin/pagina-inicial/foto" method="post" enctype="multipart/form-data">
			<?= Csrf::field() ?>

			<label class="field__label" for="hero-foto">Foto do topo</label>
			<input class="admin-upload__input" id="hero-foto" name="imagem" type="file" accept="image/jpeg,image/png,image/webp" required>
			<p class="field__hint">JPG, PNG ou WebP, até <?= $escape($adminHeroLimite) ?>. De preferência na horizontal, com pelo menos 1600 px de largura.</p>
			<button class="button" type="submit">Enviar foto</button>
		</form>
	<?php endif; ?>
</section>

<section aria-labelledby="hero-fotos-titulo">
	<h2 class="admin-product__subtitle" id="hero-fotos-titulo">
		Fotos no topo
		<span class="admin-cats__count"><?= $total ?></span>
	</h2>

	<?php if ($adminHero === []): ?>
		<p class="admin-empty">Nenhuma foto no topo ainda. A loja mostra o fundo padrão até a primeira foto.</p>
	<?php else: ?>
		<p class="admin-hero__hint">Clique numa foto para editar o título, o texto e o botão. A prévia do topo aparece no alto da janela.</p>

		<ol class="admin-hero__cards">
			<?php foreach ($adminHero as $indice => $slide): ?>
				<?php
				$titulo = $slide['title'] !== '' ? $slide['title'] : 'Foto sem título';
				$resumo = $slide['eyebrow'] !== '' ? $slide['eyebrow'] : ($slide['text'] !== '' ? $slide['text'] : 'Sem texto sobre a foto');
				?>
				<li>
					<button class="admin-hero__card" type="button" data-hero-open="<?= $slide['id'] ?>" aria-haspopup="dialog">
						<img src="<?= $escape($slide['image']) ?>" alt="" width="320" height="180" loading="lazy">

						<span class="admin-hero__card-body">
							<span class="admin-hero__card-meta">
								Foto <?= $indice + 1 ?>
								<?php if (!$slide['is_active']): ?>
									<span class="admin-hero__card-flag">Escondida</span>
								<?php endif; ?>
							</span>
							<span class="admin-hero__card-title"><?= $escape($titulo) ?></span>
							<span class="admin-hero__card-meta"><?= $escape($resumo) ?></span>
						</span>
					</button>

					<?php
					$temTexto = $slide['eyebrow'] !== '' || $slide['title'] !== '' || $slide['text'] !== '' || $slide['button_label'] !== '';
					?>
					<dialog class="admin-hero__dialog" id="hero-editar-<?= $slide['id'] ?>" data-hero-dialog="<?= $slide['id'] ?>" aria-labelledby="hero-editar-<?= $slide['id'] ?>-titulo">
						<header class="admin-hero__dialog-head">
							<h2 class="admin-hero__dialog-title" id="hero-editar-<?= $slide['id'] ?>-titulo">Editar foto <?= $indice + 1 ?></h2>
							<button class="admin-hero__close" type="button" data-hero-close aria-label="Fechar a edição">×</button>
						</header>

						<p class="admin-hero__dialog-hint">Prévia do topo — acompanha o que você digitar.</p>

						<div class="admin-preview">
							<img class="admin-preview__image" src="<?= $escape($slide['image']) ?>" alt="">

							<span class="admin-preview__shade" data-preview-shade<?= $temTexto ? '' : ' hidden' ?>></span>

							<div class="admin-preview__inner" data-preview-inner<?= $temTexto ? '' : ' hidden' ?>>
								<p class="admin-preview__eyebrow" data-preview-eyebrow<?= $slide['eyebrow'] === '' ? ' hidden' : '' ?>><?= $escape($slide['eyebrow']) ?></p>
								<p class="admin-preview__title" data-preview-title<?= $slide['title'] === '' ? ' hidden' : '' ?>><?= $escape($slide['title']) ?></p>
								<p class="admin-preview__text" data-preview-text<?= $slide['text'] === '' ? ' hidden' : '' ?>><?= $escape($slide['text']) ?></p>
								<span class="admin-preview__button" data-preview-button<?= $slide['button_label'] === '' || $slide['button_href'] === '' ? ' hidden' : '' ?>><?= $escape($slide['button_label']) ?></span>
							</div>

							<span class="admin-preview__flag" data-preview-hidden<?= $slide['is_active'] ? ' hidden' : '' ?>>Escondida na loja</span>
						</div>

						<form class="admin-hero__form" action="/admin/pagina-inicial/<?= $slide['id'] ?>" method="post" data-hero-form>
							<?= Csrf::field() ?>

							<div class="field">
								<label class="field__label" for="hero-eyebrow-<?= $slide['id'] ?>">Linha de cima</label>
								<input class="field__input" id="hero-eyebrow-<?= $slide['id'] ?>" name="eyebrow" type="text" maxlength="80" placeholder="Drop 01 · nova coleção" value="<?= $escape($slide['eyebrow']) ?>">
							</div>

							<div class="field">
								<label class="field__label" for="hero-title-<?= $slide['id'] ?>">Título</label>
								<input class="field__input" id="hero-title-<?= $slide['id'] ?>" name="title" type="text" maxlength="160" placeholder="Estampas autorais com personalidade" value="<?= $escape($slide['title']) ?>">
							</div>

							<div class="field">
								<label class="field__label" for="hero-text-<?= $slide['id'] ?>">Texto</label>
								<textarea class="field__input admin-textarea" id="hero-text-<?= $slide['id'] ?>" name="text" maxlength="400" rows="3"><?= $escape($slide['text']) ?></textarea>
							</div>

							<div class="admin-field-row">
								<div class="field">
									<label class="field__label" for="hero-button-label-<?= $slide['id'] ?>">Texto do botão</label>
									<input class="field__input" id="hero-button-label-<?= $slide['id'] ?>" name="button_label" type="text" maxlength="60" placeholder="Ver lançamentos" value="<?= $escape($slide['button_label']) ?>">
								</div>

								<div class="field">
									<label class="field__label" for="hero-button-href-<?= $slide['id'] ?>">Link do botão</label>
									<input class="field__input" id="hero-button-href-<?= $slide['id'] ?>" name="button_href" type="text" maxlength="255" placeholder="#lancamentos" value="<?= $escape($slide['button_href']) ?>">
								</div>
							</div>
							<p class="field__hint">O botão é opcional: preencha o texto e o link, ou deixe os dois vazios.</p>

							<div class="field">
								<label class="field__label" for="hero-alt-<?= $slide['id'] ?>">Descrição da foto</label>
								<input class="field__input" id="hero-alt-<?= $slide['id'] ?>" name="image_alt" type="text" maxlength="160" placeholder="Camiseta oversized com estampa de dragão" value="<?= $escape($slide['image_alt']) ?>">
								<p class="field__hint">Lida por quem usa leitor de tela. Vazia, a loja usa o título.</p>
							</div>

							<label class="admin-hero__check">
								<input name="is_active" type="checkbox" value="1"<?= $slide['is_active'] ? ' checked' : '' ?>>
								Mostrar esta foto na loja
							</label>

							<p class="admin-hero__actions">
								<button class="button" type="submit">Salvar textos</button>
							</p>
						</form>

						<div class="admin-hero__footer">
							<form action="/admin/pagina-inicial/<?= $slide['id'] ?>/remover" method="post" data-confirm="Apagar esta foto do topo da loja?">
								<?= Csrf::field() ?>
								<button class="admin-link-button admin-link-button--danger" type="submit">Apagar foto</button>
							</form>
						</div>
					</dialog>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</section>
