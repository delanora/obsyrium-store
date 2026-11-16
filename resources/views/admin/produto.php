<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Edição do produto: a mesma composição da página do item na loja (galeria à
 * esquerda, informações à direita), só que com tudo editável e as fotos sendo
 * enviadas por aqui.
 *
 * A aba, a coluna, a direção e a página do estoque viajam em campos ocultos
 * para o "Voltar" e o pós-salvar caírem na mesma lista de onde o lápis saiu.
 *
 * @var array<string, mixed> $adminProduto
 * @var list<array<string, mixed>> $adminCategorias
 * @var list<string> $adminErros
 * @var array<string, string> $adminValores
 * @var array<string, mixed> $adminVoltar
 * @var array<string, mixed> $adminPrecos
 * @var string $adminLimiteFotos
 * @var int $adminMaxFotos
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$urlDoEstoque = '/admin/estoque?' . http_build_query($adminVoltar);
$urlDoProduto = '/admin/estoque/produto/' . (int) $adminProduto['id'];

$valor = static function (string $campo, string $padrao = '') use ($adminValores): string {
	return $adminValores !== [] && array_key_exists($campo, $adminValores)
		? (string) $adminValores[$campo]
		: $padrao;
};

$precoAtual = number_format($adminProduto['price_cents'] / 100, 2, ',', '');
$comparativoAtual = $adminProduto['compare_price_cents'] !== null
	? number_format($adminProduto['compare_price_cents'] / 100, 2, ',', '')
	: '';

$principal = $adminProduto['images'][0] ?? null;
$estoqueTotal = 0;

foreach ($adminProduto['variants'] as $variacao) {
	$estoqueTotal += (int) $variacao['quantity'];
}
?>
<p class="admin-crumb">
	<a href="<?= $escape($urlDoEstoque) ?>">Estoque</a>
	<span aria-hidden="true">/</span>
	<span aria-current="page"><?= $escape((string) $adminProduto['name']) ?></span>
</p>

<header class="admin-head admin-head--row">
	<div>
		<p class="admin-head__eyebrow"><?= $escape((string) $adminProduto['category']) ?></p>
		<h1 class="admin-head__title"><?= $escape((string) $adminProduto['name']) ?></h1>
		<p class="admin-head__text">
			<?= $escape((string) $adminPrecos['price']) ?>
			<?php if ($adminPrecos['old'] !== null): ?>
				<span class="admin-product__old"><?= $escape((string) $adminPrecos['old']) ?></span>
				<span class="admin-table__off">-<?= (int) $adminPrecos['discount'] ?>%</span>
			<?php endif; ?>
			· <?= (int) $estoqueTotal ?> peça<?= $estoqueTotal === 1 ? '' : 's' ?> em estoque
			· <?= $adminProduto['is_active'] ? 'na loja' : 'fora de linha' ?>
		</p>
	</div>

	<p class="admin-head__actions">
		<?php if ($adminProduto['is_active']): ?>
			<a class="admin-link-button" href="/produto/<?= $escape((string) $adminProduto['slug']) ?>" target="_blank" rel="noopener">Ver na loja</a>
		<?php endif; ?>
		<a class="admin-link-button" href="<?= $escape($urlDoEstoque) ?>">Voltar ao estoque</a>
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

<div class="admin-product">
	<section class="admin-product__gallery" aria-labelledby="fotos-titulo">
		<h2 class="admin-product__subtitle" id="fotos-titulo">Fotos do item</h2>

		<?php if ($principal === null): ?>
			<p class="admin-product__empty">Nenhuma foto enviada ainda. A loja mostra a imagem padrão até a primeira foto.</p>
		<?php else: ?>
			<figure class="admin-product__main">
				<img src="<?= $escape((string) $principal['path']) ?>" alt="<?= $escape((string) $principal['alt']) ?>" width="600" height="800">
				<figcaption class="admin-product__caption">Foto principal</figcaption>
			</figure>

			<ul class="admin-photos">
				<?php foreach ($adminProduto['images'] as $indice => $foto): ?>
					<li class="admin-photos__item">
						<img src="<?= $escape((string) $foto['path']) ?>" alt="<?= $escape((string) $foto['alt']) ?>" width="80" height="100" loading="lazy">

						<form method="post" action="<?= $escape($urlDoProduto . '/foto/remover') ?>">
							<?= Csrf::field() ?>
							<input type="hidden" name="image_id" value="<?= (int) $foto['id'] ?>">
							<?php foreach ($adminVoltar as $campo => $info): ?>
								<input type="hidden" name="<?= $escape((string) $campo) ?>" value="<?= $escape((string) $info) ?>">
							<?php endforeach; ?>

							<button
								class="admin-icon-button admin-icon-button--danger"
								type="submit"
								aria-label="<?= $indice === 0 ? 'Apagar a foto principal' : 'Apagar esta foto' ?>"
								title="Apagar foto"
							>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/><path d="M9.5 7V5h5v2"/><path d="M6.5 7l1 13h9l1-13"/><path d="M10.5 11v5.5"/><path d="M13.5 11v5.5"/></svg>
							</button>
						</form>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<form class="admin-upload" method="post" action="<?= $escape($urlDoProduto . '/foto') ?>" enctype="multipart/form-data">
			<?= Csrf::field() ?>
			<?php foreach ($adminVoltar as $campo => $info): ?>
				<input type="hidden" name="<?= $escape((string) $campo) ?>" value="<?= $escape((string) $info) ?>">
			<?php endforeach; ?>

			<label class="field__label" for="foto">Enviar foto</label>
			<input
				class="admin-upload__input"
				id="foto"
				name="imagem"
				type="file"
				accept="image/jpeg,image/png,image/webp"
				required
			>
			<p class="field__hint">
				JPG, PNG ou WebP, até <?= $escape($adminLimiteFotos) ?>, no máximo <?= (int) $adminMaxFotos ?> fotos.
				A primeira da lista é a que aparece na vitrine.
			</p>
			<button class="button" type="submit">Enviar foto</button>
		</form>
	</section>

	<form class="admin-product__form" method="post" action="<?= $escape($urlDoProduto) ?>">
		<?= Csrf::field() ?>
		<?php foreach ($adminVoltar as $campo => $info): ?>
			<input type="hidden" name="<?= $escape((string) $campo) ?>" value="<?= $escape((string) $info) ?>">
		<?php endforeach; ?>

		<h2 class="admin-product__subtitle">Dados do produto</h2>

		<div class="field">
			<label class="field__label" for="name">Nome</label>
			<input class="field__input" id="name" name="name" type="text" maxlength="120" required value="<?= $escape($valor('name', (string) $adminProduto['name'])) ?>">
		</div>

		<div class="field">
			<label class="field__label" for="slug">Endereço na loja</label>
			<input class="field__input" id="slug" name="slug" type="text" maxlength="140" value="<?= $escape($valor('slug', (string) $adminProduto['slug'])) ?>">
			<p class="field__hint">Fica em /produto/<?= $escape($valor('slug', (string) $adminProduto['slug'])) ?>. Deixe vazio para gerar pelo nome.</p>
		</div>

		<fieldset class="field">
			<legend class="field__label">Categoria</legend>
			<div class="admin-choices">
				<?php foreach ($adminCategorias as $categoria): ?>
					<?php $idCampo = 'categoria-' . (int) $categoria['id']; ?>
					<input
						class="admin-choice__input"
						type="radio"
						id="<?= $escape($idCampo) ?>"
						name="category_id"
						value="<?= (int) $categoria['id'] ?>"
						<?= $valor('category_id', (string) $adminProduto['category_id']) === (string) (int) $categoria['id'] ? 'checked' : '' ?>
						required
					>
					<label class="admin-choice" for="<?= $escape($idCampo) ?>"><?= $escape((string) $categoria['name']) ?></label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<div class="admin-field-row">
			<div class="field">
				<label class="field__label" for="color">Cor</label>
				<input class="field__input" id="color" name="color" type="text" maxlength="40" required value="<?= $escape($valor('color', (string) $adminProduto['color'])) ?>">
			</div>

			<div class="field">
				<label class="field__label" for="material">Material</label>
				<input class="field__input" id="material" name="material" type="text" maxlength="80" required value="<?= $escape($valor('material', (string) $adminProduto['material'])) ?>">
			</div>
		</div>

		<div class="admin-field-row">
			<div class="field">
				<label class="field__label" for="price">Preço promocional</label>
				<input class="field__input" id="price" name="price" type="text" inputmode="decimal" required value="<?= $escape($valor('price', $precoAtual)) ?>">
				<p class="field__hint">É o valor cobrado, com vírgula: 129,90.</p>
			</div>

			<div class="field">
				<label class="field__label" for="compare_price">Preço cheio</label>
				<input class="field__input" id="compare_price" name="compare_price" type="text" inputmode="decimal" value="<?= $escape($valor('compare_price', $comparativoAtual)) ?>">
				<p class="field__hint">Opcional. Só entra quando for maior e vira o "de".</p>
			</div>
		</div>

		<div class="field">
			<label class="field__label" for="description">Descrição</label>
			<textarea class="field__input admin-textarea" id="description" name="description" rows="5" maxlength="2000"><?= $escape($valor('description', (string) $adminProduto['description'])) ?></textarea>
		</div>

		<label class="field field--check">
			<input class="field__check" type="checkbox" name="is_active" value="1" <?= $valor('is_active', $adminProduto['is_active'] ? '1' : '0') === '1' ? 'checked' : '' ?>>
			<span class="field__check-label">Na loja (desmarcado deixa o produto fora de linha)</span>
		</label>

		<button class="button" type="submit">Salvar alterações</button>
	</form>
</div>

<section class="admin-product__variants" aria-labelledby="tamanhos-titulo">
	<h2 class="admin-product__subtitle" id="tamanhos-titulo">Tamanhos</h2>
	<p class="field__hint">O saldo de cada tamanho continua sendo ajustado na tela de estoque.</p>

	<div class="admin-table-wrap">
		<table class="admin-table">
			<thead>
				<tr>
					<th scope="col">Tamanho</th>
					<th scope="col">SKU</th>
					<th scope="col">Saldo</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($adminProduto['variants'] as $variacao): ?>
					<tr>
						<td><?= $escape((string) $variacao['size']) ?></td>
						<td class="admin-table__muted admin-table__sku"><?= $escape((string) $variacao['sku']) ?></td>
						<td class="admin-table__total"><?= (int) $variacao['quantity'] ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
