<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Categorias do painel: criar categoria ou subcategoria e ver quais modelos
 * estão em cada uma. Cada produto aparece onde foi colocado — na categoria ou
 * na subcategoria escolhida —, por isso a contagem de uma categoria não soma
 * as filhas.
 *
 * @var list<array<string, mixed>> $adminCategoriasArvore
 * @var list<array<string, mixed>> $adminCategoriasRaiz
 * @var list<string> $adminErros
 * @var array<string, string> $adminValores
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$total = 0;

foreach ($adminCategoriasArvore as $categoria) {
	$total += count($categoria['products']);

	foreach ($categoria['children'] as $filha) {
		$total += count($filha['products']);
	}
}
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

<div class="admin-cats">
	<section class="admin-cats__form" aria-labelledby="nova-categoria-titulo">
		<h2 class="admin-product__subtitle" id="nova-categoria-titulo">Nova categoria</h2>

		<form class="admin-cats__novo" method="post" action="/admin/categorias">
			<?= Csrf::field() ?>

			<div class="field">
				<label class="field__label" for="cat-name">Nome</label>
				<input class="field__input" id="cat-name" name="name" type="text" maxlength="80" required value="<?= $escape((string) ($adminValores['name'] ?? '')) ?>">
			</div>

			<div class="field">
				<label class="field__label" for="cat-parent">Subcategoria de</label>
				<select class="field__input" id="cat-parent" name="parent_id">
					<option value="">Categoria principal</option>
					<?php foreach ($adminCategoriasRaiz as $raiz): ?>
						<option value="<?= (int) $raiz['id'] ?>" <?= (string) ($adminValores['parent_id'] ?? '') === (string) (int) $raiz['id'] ? 'selected' : '' ?>><?= $escape((string) $raiz['name']) ?></option>
					<?php endforeach; ?>
				</select>
				<p class="field__hint">Deixe em "Categoria principal" para criar uma categoria raiz. Uma subcategoria não pode ter subcategoria.</p>
			</div>

			<button class="button" type="submit">Criar categoria</button>
		</form>
	</section>

	<section class="admin-cats__list" aria-label="Categorias existentes">
		<?php if ($adminCategoriasArvore === []): ?>
			<p class="admin-empty">Nenhuma categoria cadastrada.</p>
		<?php endif; ?>

		<?php foreach ($adminCategoriasArvore as $categoria): ?>
			<article class="admin-cat-card">
				<header class="admin-cat-card__head">
					<h2 class="admin-cat-card__title"><?= $escape((string) $categoria['name']) ?></h2>
					<span class="admin-cat-card__count">
						<?= count($categoria['products']) ?> produto<?= count($categoria['products']) === 1 ? '' : 's' ?>
					</span>
				</header>

				<?php if ($categoria['products'] === []): ?>
					<p class="admin-cat-card__empty">Nenhum modelo nesta categoria.</p>
				<?php else: ?>
					<ul class="admin-cat-card__products">
						<?php foreach ($categoria['products'] as $produto): ?>
							<li>
								<a href="/admin/estoque/produto/<?= (int) $produto['id'] ?>"><?= $escape((string) $produto['name']) ?></a>
								<?php if (!$produto['is_active']): ?>
									<span class="admin-status admin-status--fora">Fora de linha</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ($categoria['children'] !== []): ?>
					<div class="admin-cat-card__children">
						<?php foreach ($categoria['children'] as $filha): ?>
							<div class="admin-cat-card__child">
								<h3 class="admin-cat-card__subtitle">
									<?= $escape((string) $filha['name']) ?>
									<span class="admin-cat-card__count">
										<?= count($filha['products']) ?> produto<?= count($filha['products']) === 1 ? '' : 's' ?>
									</span>
								</h3>

								<?php if ($filha['products'] === []): ?>
									<p class="admin-cat-card__empty">Nenhum modelo nesta subcategoria.</p>
								<?php else: ?>
									<ul class="admin-cat-card__products">
										<?php foreach ($filha['products'] as $produto): ?>
											<li>
												<a href="/admin/estoque/produto/<?= (int) $produto['id'] ?>"><?= $escape((string) $produto['name']) ?></a>
												<?php if (!$produto['is_active']): ?>
													<span class="admin-status admin-status--fora">Fora de linha</span>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</section>
</div>
