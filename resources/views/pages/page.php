<?php

declare(strict_types=1);

/**
 * Página institucional editável: o conteúdo vem de `pages`/`page_blocks`, pelo
 * PageService. O texto dos blocos já chega pronto em `html` (escapado, com
 * listas e links internos montados); aqui só se decide o desenho — layout
 * `faq` usa `<details>` para as perguntas, o resto usa blocos de leitura.
 *
 * @var array{id: int, slug: string, eyebrow: string, title: string, intro: string, note: string|null, layout: string, blocks: list<array<string, string|int>>} $paginaInstitucional
 * @var list<array{0: string, 1: string}> $paginaAcoes
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="page">
	<div class="container page__inner">
		<header class="page__header">
			<?php if ($paginaInstitucional['eyebrow'] !== ''): ?>
				<p class="page__eyebrow"><?= $escape($paginaInstitucional['eyebrow']) ?></p>
			<?php endif; ?>
			<h1 class="page__title"><?= $escape($paginaInstitucional['title']) ?></h1>
			<?php if ($paginaInstitucional['intro'] !== ''): ?>
				<p class="page__text"><?= $escape($paginaInstitucional['intro']) ?></p>
			<?php endif; ?>
		</header>

		<?php if ($paginaInstitucional['layout'] === 'faq'): ?>
			<div class="faq">
				<?php foreach ($paginaInstitucional['blocks'] as $indice => $bloco): ?>
					<details class="faq__item"<?= $indice === 0 ? ' open' : '' ?>>
						<summary class="faq__question"><?= $escape((string) $bloco['heading']) ?></summary>

						<div class="faq__answer">
							<?= $bloco['html'] ?>

							<?php if ((string) $bloco['link_href'] !== ''): ?>
								<p><a class="page__link" href="<?= $escape((string) $bloco['link_href']) ?>"><?= $escape((string) $bloco['link_label']) ?></a></p>
							<?php endif; ?>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="page__blocks">
				<?php foreach ($paginaInstitucional['blocks'] as $bloco): ?>
					<article class="page__block">
						<?php if ((string) $bloco['heading'] !== ''): ?>
							<h2 class="page__block-title"><?= $escape((string) $bloco['heading']) ?></h2>
						<?php endif; ?>

						<?= $bloco['html'] ?>

						<?php if ((string) $bloco['link_href'] !== ''): ?>
							<p><a class="page__link" href="<?= $escape((string) $bloco['link_href']) ?>"><?= $escape((string) $bloco['link_label']) ?></a></p>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ($paginaInstitucional['note'] !== null && $paginaInstitucional['note'] !== ''): ?>
			<p class="page__note"><?= $escape($paginaInstitucional['note']) ?></p>
		<?php endif; ?>

		<?php if ($paginaAcoes !== []): ?>
			<div class="page__actions">
				<?php foreach ($paginaAcoes as $indice => $acao): ?>
					<a class="button<?= $indice > 0 ? ' button--ghost' : '' ?>" href="<?= $escape($acao[1]) ?>"><?= $escape($acao[0]) ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
