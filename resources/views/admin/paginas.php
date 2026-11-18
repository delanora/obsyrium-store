<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Páginas institucionais: as abas escolhem a página, o primeiro formulário
 * edita o cabeçalho (linha de cima, título, introdução e nota do fim) e cada
 * bloco tem o seu próprio formulário, com setas de ordem e o botão de apagar.
 *
 * O texto do bloco é texto puro: linha em branco separa parágrafos, linha
 * começando com "- " vira item de lista e [rótulo](/caminho) vira link interno.
 *
 * @var list<array<string, mixed>> $adminPaginas
 * @var array<string, mixed>|null $adminPagina
 * @var list<string> $adminErros
 * @var array<string, string> $adminValores
 * @var int $adminBlocoErro
 * @var array<string, string> $adminValoresBloco
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$link = static fn (int $id): string => '/admin/paginas?' . http_build_query(['pagina' => $id]);

$pagina = $adminPagina;
$idPagina = (int) ($pagina['id'] ?? 0);
$blocos = $pagina['blocks'] ?? [];

$valores = $adminValores !== []
	? $adminValores
	: [
		'eyebrow' => (string) ($pagina['eyebrow'] ?? ''),
		'title' => (string) ($pagina['title'] ?? ''),
		'intro' => (string) ($pagina['intro'] ?? ''),
		'note' => (string) ($pagina['note'] ?? ''),
	];

$endereco = [
	'sobre' => '/sobre',
	'perguntas-frequentes' => '/perguntas-frequentes',
	'entrega' => '/entrega',
	'pagamento' => '/pagamento',
	'termos' => '/termos',
	'privacidade' => '/privacidade',
];
?>
<p class="admin-crumb">
	<a href="/admin">Painel</a>
	<span aria-hidden="true">/</span>
	<span aria-current="page">Páginas</span>
</p>

<header class="admin-head admin-head--row">
	<div>
		<p class="admin-head__eyebrow">Conteúdo</p>
		<h1 class="admin-head__title">Páginas</h1>
		<p class="admin-head__text">O texto de Sobre, Perguntas frequentes e das políticas do rodapé, editado aqui e publicado na hora.</p>
	</div>

	<?php if ($pagina !== null): ?>
		<p class="admin-head__actions">
			<a class="admin-link-button" href="<?= $escape($endereco[(string) $pagina['slug']] ?? '/') ?>" rel="noopener">Ver na loja</a>
		</p>
	<?php endif; ?>
</header>

<?php if ($adminErros !== []): ?>
	<div class="admin-alert" role="alert">
		<p class="admin-alert__title">Confira o formulário:</p>
		<ul class="admin-alert__list">
			<?php foreach ($adminErros as $erro): ?>
				<li><?= $escape($erro) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ($adminPaginas === []): ?>
	<p class="admin-empty">Nenhuma página cadastrada.</p>
<?php else: ?>
	<nav class="admin-tabs" aria-label="Páginas">
		<?php foreach ($adminPaginas as $item): ?>
			<?php $ativa = (int) $item['id'] === $idPagina; ?>
			<a class="admin-tab<?= $ativa ? ' admin-tab--ativo' : '' ?>" href="<?= $escape($link((int) $item['id'])) ?>"<?= $ativa ? ' aria-current="page"' : '' ?>>
				<?= $escape((string) $item['title']) ?>
				<span class="admin-tab__count"><?= (int) $item['blocks'] ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ($pagina !== null): ?>
		<section class="admin-pages__cabecalho" aria-labelledby="pagina-cabecalho-titulo">
			<h2 class="admin-product__subtitle" id="pagina-cabecalho-titulo">Cabeçalho</h2>
			<p class="admin-pages__slug">/<?= $escape((string) $pagina['slug']) ?> · <?= count($blocos) ?> bloco<?= count($blocos) === 1 ? '' : 's' ?></p>

			<form class="admin-pages__form" method="post" action="/admin/paginas/<?= $idPagina ?>">
				<?= Csrf::field() ?>

				<div class="admin-field-row">
					<div class="field">
						<label class="field__label" for="pagina-eyebrow">Linha de cima</label>
						<input class="field__input" id="pagina-eyebrow" name="eyebrow" type="text" maxlength="60" value="<?= $escape($valores['eyebrow']) ?>">
						<p class="field__hint">Opcional. Aparece em dourado acima do título.</p>
					</div>

					<div class="field">
						<label class="field__label" for="pagina-title">Título</label>
						<input class="field__input" id="pagina-title" name="title" type="text" maxlength="140" required value="<?= $escape($valores['title']) ?>">
					</div>
				</div>

				<div class="field">
					<label class="field__label" for="pagina-intro">Introdução</label>
					<textarea class="field__input" id="pagina-intro" name="intro" rows="3" maxlength="400"><?= $escape($valores['intro']) ?></textarea>
				</div>

				<div class="field">
					<label class="field__label" for="pagina-note">Nota do fim</label>
					<textarea class="field__input" id="pagina-note" name="note" rows="2" maxlength="500"><?= $escape($valores['note']) ?></textarea>
					<p class="field__hint">Opcional. Pequeno aviso em destaque depois dos blocos.</p>
				</div>

				<p class="admin-pages__salvar">
					<button class="button" type="submit">Salvar página</button>
				</p>
			</form>
		</section>

		<section aria-labelledby="pagina-blocos-titulo">
			<div class="admin-pages__titulo-linha">
				<h2 class="admin-product__subtitle" id="pagina-blocos-titulo">Blocos</h2>

				<form action="/admin/paginas/<?= $idPagina ?>/blocos" method="post">
					<?= Csrf::field() ?>
					<button class="admin-link-button" type="submit">Novo bloco</button>
				</form>
			</div>

			<?php if ($blocos === []): ?>
				<p class="admin-empty">Nenhum bloco nesta página ainda.</p>
			<?php endif; ?>

			<?php foreach ($blocos as $indice => $bloco): ?>
				<?php
				$blocoId = (int) $bloco['id'];
				$comErro = $blocoId === $adminBlocoErro;
				$campos = $comErro
					? $adminValoresBloco
					: [
						'heading' => (string) $bloco['heading'],
						'body' => (string) $bloco['body'],
						'link_label' => (string) $bloco['link_label'],
						'link_href' => (string) $bloco['link_href'],
					];
				?>
				<article class="admin-pages__bloco<?= $comErro ? ' admin-pages__bloco--erro' : '' ?>">
					<header class="admin-pages__bloco-head">
						<h3 class="admin-pages__bloco-titulo">Bloco <?= $indice + 1 ?></h3>

						<div class="admin-pages__acoes">
							<form action="/admin/paginas/<?= $idPagina ?>/blocos/<?= $blocoId ?>/subir" method="post">
								<?= Csrf::field() ?>
								<button class="admin-cats__arrow" type="submit" aria-label="Subir o bloco <?= $indice + 1 ?>" title="Subir"<?= $indice === 0 ? ' disabled' : '' ?>>↑</button>
							</form>

							<form action="/admin/paginas/<?= $idPagina ?>/blocos/<?= $blocoId ?>/descer" method="post">
								<?= Csrf::field() ?>
								<button class="admin-cats__arrow" type="submit" aria-label="Descer o bloco <?= $indice + 1 ?>" title="Descer"<?= $indice === count($blocos) - 1 ? ' disabled' : '' ?>>↓</button>
							</form>

							<form action="/admin/paginas/<?= $idPagina ?>/blocos/<?= $blocoId ?>/remover" method="post" data-confirm="Apagar o bloco <?= $indice + 1 ?>?">
								<?= Csrf::field() ?>
								<button class="admin-link-button admin-link-button--danger admin-pages__apagar" type="submit">Apagar</button>
							</form>
						</div>
					</header>

					<form class="admin-pages__form" method="post" action="/admin/paginas/<?= $idPagina ?>/blocos/<?= $blocoId ?>">
						<?= Csrf::field() ?>

						<div class="field">
							<label class="field__label" for="bloco-<?= $blocoId ?>-heading">Título do bloco</label>
							<input class="field__input" id="bloco-<?= $blocoId ?>-heading" name="heading" type="text" maxlength="160" value="<?= $escape((string) ($campos['heading'] ?? '')) ?>">
						</div>

						<div class="field">
							<label class="field__label" for="bloco-<?= $blocoId ?>-body">Texto</label>
							<textarea class="field__input admin-pages__texto" id="bloco-<?= $blocoId ?>-body" name="body" rows="5" maxlength="6000"><?= $escape((string) ($campos['body'] ?? '')) ?></textarea>
							<p class="field__hint">Linha em branco separa parágrafos, "- " no começo vira item de lista e [rótulo](/caminho) vira link.</p>
						</div>

						<div class="admin-field-row">
							<div class="field">
								<label class="field__label" for="bloco-<?= $blocoId ?>-link-label">Texto do link</label>
								<input class="field__input" id="bloco-<?= $blocoId ?>-link-label" name="link_label" type="text" maxlength="80" value="<?= $escape((string) ($campos['link_label'] ?? '')) ?>">
							</div>

							<div class="field">
								<label class="field__label" for="bloco-<?= $blocoId ?>-link-href">Endereço do link</label>
								<input class="field__input" id="bloco-<?= $blocoId ?>-link-href" name="link_href" type="text" maxlength="255" value="<?= $escape((string) ($campos['link_href'] ?? '')) ?>">
								<p class="field__hint">Opcional. Deixe os dois vazios para o bloco ficar sem link.</p>
							</div>
						</div>

						<p class="admin-pages__salvar">
							<button class="button" type="submit">Salvar bloco</button>
						</p>
					</form>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>
<?php endif; ?>
