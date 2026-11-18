<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Categorias do painel: abas por categoria, os modelos da categoria escolhida
 * em tabela, o formulário de nova categoria e o editor da categoria aberta
 * (nome, categoria principal, visibilidade e ordem).
 *
 * Cada produto aparece onde foi colocado — na categoria ou na subcategoria
 * escolhida —, por isso a contagem de uma categoria não soma as filhas.
 *
 * Desativada, a categoria sai da loja (catálogo, filtros e `/categoria/...`),
 * mas continua aqui com seus produtos e subcategorias.
 *
 * @var list<array<string, mixed>> $adminCategoriasArvore
 * @var list<array<string, mixed>> $adminCategoriasRaiz
 * @var array<string, mixed>|null $adminCategoria
 * @var array<string, mixed>|null $adminCategoriaRaiz
 * @var list<string> $adminErros
 * @var array<string, string> $adminValores
 * @var array<string, string> $adminValoresEdicao
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$link = static fn (int $id): string => '/admin/categorias?' . http_build_query(['categoria' => $id]);

$total = 0;

foreach ($adminCategoriasArvore as $categoria) {
	$total += count($categoria['products']);

	foreach ($categoria['children'] as $filha) {
		$total += count($filha['products']);
	}
}

$selecionada = $adminCategoria;
$mae = $adminCategoriaRaiz;
$idSelecionado = (int) ($selecionada['id'] ?? 0);
$ehSubcategoria = $selecionada !== null && $mae !== null && (int) $selecionada['id'] !== (int) $mae['id'];
$produtos = $selecionada['products'] ?? [];

$marcouSubcategoria = (string) ($adminValores['is_subcategory'] ?? '') === '1';
$paiEscolhido = (string) ($adminValores['parent_id'] ?? '');

$valoresEdicao = $adminValoresEdicao ?? [];
$temFilhas = $selecionada !== null && ($selecionada['children'] ?? []) !== [];
$nomeEdicao = (string) ($valoresEdicao['name'] ?? $selecionada['name'] ?? '');
$paiEdicao = array_key_exists('parent_id', $valoresEdicao)
	? (string) $valoresEdicao['parent_id']
	: ($selecionada !== null && $selecionada['parent_id'] !== null ? (string) $selecionada['parent_id'] : '');
$ativaEdicao = array_key_exists('is_active', $valoresEdicao)
	? $valoresEdicao['is_active'] === '1'
	: (bool) ($selecionada['is_active'] ?? true);

// Irmãs da categoria aberta: as subcategorias da mesma mãe ou as categorias
// principais. É dentro dessa lista que as setas sobem e descem.
$irmas = $selecionada === null ? [] : ($ehSubcategoria ? ($mae['children'] ?? []) : $adminCategoriasArvore);
$indiceIrma = 0;

foreach ($irmas as $posicao => $irma) {
	if ((int) $irma['id'] === $idSelecionado) {
		$indiceIrma = $posicao;
	}
}

$totalIrmas = count($irmas);

// Categoria não pode ficar dentro dela mesma: ela sai do seletor de mãe.
$outrasRaizes = array_values(array_filter(
	$adminCategoriasRaiz,
	static fn (array $raiz): bool => (int) $raiz['id'] !== $idSelecionado
));
?>
<p class="admin-crumb">
	<a href="/admin/estoque">Estoque</a>
	<span aria-hidden="true">/</span>
	<span aria-current="page">Categorias</span>
</p>

<header class="admin-head admin-head--row">
	<div>
		<p class="admin-head__eyebrow">Painel</p>
		<h1 class="admin-head__title">Categorias</h1>
		<p class="admin-head__text">
			<?= count($adminCategoriasArvore) ?> categoria<?= count($adminCategoriasArvore) === 1 ? '' : 's' ?>
			· <?= $total ?> vínculo<?= $total === 1 ? '' : 's' ?> de produto
		</p>
	</div>

	<p class="admin-head__actions">
		<a class="admin-link-button" href="/admin/estoque/novo">Novo modelo</a>
		<a class="admin-link-button" href="/admin/estoque">Estoque</a>
	</p>
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

<section class="admin-cats__form" aria-labelledby="nova-categoria-titulo">
	<h2 class="admin-product__subtitle" id="nova-categoria-titulo">Nova categoria</h2>

	<form class="admin-cats__novo" method="post" action="/admin/categorias">
		<?= Csrf::field() ?>

		<div class="field">
			<label class="field__label" for="cat-name">Nome</label>
			<input class="field__input" id="cat-name" name="name" type="text" maxlength="80" required value="<?= $escape((string) ($adminValores['name'] ?? '')) ?>">
		</div>

		<label class="admin-cats__check">
			<input type="checkbox" name="is_subcategory" value="1" data-subcategory-toggle <?= $marcouSubcategoria ? 'checked' : '' ?>>
			<span>Subcategoria</span>
		</label>

		<button class="button" type="submit">Criar categoria</button>

		<div class="field admin-cats__pai" data-subcategory-field <?= $marcouSubcategoria ? '' : 'hidden' ?>>
			<label class="field__label" for="cat-parent">Subcategoria de</label>
			<select class="field__input" id="cat-parent" name="parent_id">
				<?php if ($adminCategoriasRaiz === []): ?>
					<option value="">Nenhuma categoria principal ainda</option>
				<?php endif; ?>

				<?php foreach ($adminCategoriasRaiz as $raiz): ?>
					<option value="<?= (int) $raiz['id'] ?>" <?= $paiEscolhido === (string) (int) $raiz['id'] ? 'selected' : '' ?>><?= $escape((string) $raiz['name']) ?><?= $raiz['is_active'] ? '' : ' (desativada)' ?></option>
				<?php endforeach; ?>
			</select>
			<p class="field__hint">Só categorias principais aparecem aqui — subcategoria não pode ter subcategoria.</p>
		</div>
	</form>
</section>

<?php if ($adminCategoriasArvore === []): ?>
	<p class="admin-empty">Nenhuma categoria cadastrada.</p>
<?php else: ?>
	<nav class="admin-tabs" aria-label="Categorias">
		<?php foreach ($adminCategoriasArvore as $categoria): ?>
			<?php $ativa = (int) $categoria['id'] === $idSelecionado || ($mae !== null && (int) $mae['id'] === (int) $categoria['id']); ?>
			<?php $escondida = !$categoria['is_active']; ?>
			<a class="admin-tab<?= $ativa ? ' admin-tab--ativo' : '' ?><?= $escondida ? ' admin-tab--off' : '' ?>" href="<?= $escape($link((int) $categoria['id'])) ?>"<?= $ativa ? ' aria-current="page"' : '' ?>>
				<?= $escape((string) $categoria['name']) ?>
				<?php if ($escondida): ?>
					<span class="admin-cats__off">fora</span>
				<?php endif; ?>
				<span class="admin-tab__count"><?= count($categoria['products']) ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ($mae !== null && $mae['children'] !== []): ?>
		<nav class="admin-cats__subnav" aria-label="Subcategorias de <?= $escape((string) $mae['name']) ?>">
			<a class="admin-tab<?= (int) $mae['id'] === $idSelecionado ? ' admin-tab--ativo' : '' ?>" href="<?= $escape($link((int) $mae['id'])) ?>">
				Na categoria
				<span class="admin-tab__count"><?= count($mae['products']) ?></span>
			</a>

			<?php foreach ($mae['children'] as $filha): ?>
				<a class="admin-tab<?= (int) $filha['id'] === $idSelecionado ? ' admin-tab--ativo' : '' ?><?= $filha['is_active'] ? '' : ' admin-tab--off' ?>" href="<?= $escape($link((int) $filha['id'])) ?>">
					<?= $escape((string) $filha['name']) ?>
					<?php if (!$filha['is_active']): ?>
						<span class="admin-cats__off">fora</span>
					<?php endif; ?>
					<span class="admin-tab__count"><?= count($filha['products']) ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ($selecionada !== null): ?>
		<section class="admin-cats__editar" aria-labelledby="editar-categoria-titulo">
			<div class="admin-cats__editar-head">
				<div>
					<h2 class="admin-product__subtitle" id="editar-categoria-titulo">
						Editar categoria
						<?php if (!$selecionada['is_active']): ?>
							<span class="admin-cats__off">Desativada na loja</span>
						<?php endif; ?>
					</h2>
					<p class="admin-cats__slug">
						/categoria/<strong><?= $escape((string) $selecionada['slug']) ?></strong><?= $ehSubcategoria && $mae !== null ? ' · dentro de ' . $escape((string) $mae['name']) : '' ?>
					</p>
				</div>

				<div class="admin-cats__ordem" aria-label="Ordem da categoria">
					<form action="/admin/categorias/<?= $idSelecionado ?>/subir" method="post">
						<?= Csrf::field() ?>
						<button class="admin-cats__arrow" type="submit" aria-label="Subir a categoria" title="Subir"<?= $indiceIrma === 0 ? ' disabled' : '' ?>>↑</button>
					</form>

					<form action="/admin/categorias/<?= $idSelecionado ?>/descer" method="post">
						<?= Csrf::field() ?>
						<button class="admin-cats__arrow" type="submit" aria-label="Descer a categoria" title="Descer"<?= $indiceIrma === $totalIrmas - 1 ? ' disabled' : '' ?>>↓</button>
					</form>
				</div>
			</div>

			<form class="admin-cats__formulario" method="post" action="/admin/categorias/<?= $idSelecionado ?>">
				<?= Csrf::field() ?>

				<div class="admin-field-row">
					<div class="field">
						<label class="field__label" for="cat-edit-name">Nome</label>
						<input class="field__input" id="cat-edit-name" name="name" type="text" maxlength="80" required value="<?= $escape($nomeEdicao) ?>">
						<p class="field__hint">Renomear troca também o endereço da categoria na loja.</p>
					</div>

					<?php if ($temFilhas): ?>
						<p class="field__hint admin-cats__hint">Esta categoria tem subcategorias e continua sendo principal.</p>
					<?php else: ?>
						<div class="field">
							<label class="field__label" for="cat-edit-parent">Dentro de</label>
							<select class="field__input" id="cat-edit-parent" name="parent_id">
								<option value=""<?= $paiEdicao === '' ? ' selected' : '' ?>>Categoria principal</option>

								<?php foreach ($outrasRaizes as $raiz): ?>
									<option value="<?= (int) $raiz['id'] ?>"<?= $paiEdicao === (string) (int) $raiz['id'] ? ' selected' : '' ?>><?= $escape((string) $raiz['name']) ?><?= $raiz['is_active'] ? '' : ' (desativada)' ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
				</div>

				<label class="admin-cats__check">
					<input name="is_active" type="checkbox" value="1"<?= $ativaEdicao ? ' checked' : '' ?>>
					<span>Mostrar na loja</span>
				</label>
				<p class="field__hint">Desmarcada, a categoria sai do catálogo, dos filtros e da página dela — os produtos continuam guardados onde estão.</p>

				<p class="admin-cats__salvar">
					<button class="button" type="submit">Salvar categoria</button>
				</p>
			</form>

			<div class="admin-cats__apagar">
				<form action="/admin/categorias/apagar" method="post" data-confirm="Apagar a categoria &quot;<?= $escape((string) $selecionada['name']) ?>&quot;?">
					<?= Csrf::field() ?>
					<input type="hidden" name="categoria_apagar" value="<?= $idSelecionado ?>">
					<button class="admin-link-button admin-link-button--danger" type="submit">Apagar categoria</button>
				</form>
				<p class="field__hint">Só sai se estiver vazia: sem subcategoria e sem produto ligado a ela.</p>
			</div>
		</section>

		<section aria-labelledby="modelos-categoria-titulo">
			<h2 class="admin-product__subtitle" id="modelos-categoria-titulo">
				Modelos em <?= $escape((string) $selecionada['name']) ?>
				<span class="admin-cats__count"><?= count($produtos) ?> produto<?= count($produtos) === 1 ? '' : 's' ?></span>
			</h2>

			<?php if ($produtos === []): ?>
				<p class="admin-empty">Nenhum modelo nesta <?= $ehSubcategoria ? 'subcategoria' : 'categoria' ?>.</p>
			<?php else: ?>
				<div class="admin-table-wrap">
					<table class="admin-table admin-table--cats">
						<thead>
							<tr>
								<th scope="col">Produto</th>
								<th scope="col">Situação</th>
								<th scope="col"><span class="admin-visually-hidden">Ação</span></th>
							</tr>
						</thead>

						<tbody>
							<?php foreach ($produtos as $produto): ?>
								<tr>
									<td><?= $escape((string) $produto['name']) ?></td>
									<td>
										<span class="admin-status <?= $produto['is_active'] ? 'admin-status--estoque-ok' : 'admin-status--fora' ?>">
											<?= $produto['is_active'] ? 'Disponível' : 'Fora de linha' ?>
										</span>
									</td>
									<td>
										<a class="admin-icon-button" href="/admin/estoque/produto/<?= (int) $produto['id'] ?>" aria-label="Editar <?= $escape((string) $produto['name']) ?>" title="Editar produto">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3Z"/><path d="M13.5 6.5l3 3"/></svg>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
<?php endif; ?>
