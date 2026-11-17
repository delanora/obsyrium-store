<?php

declare(strict_types=1);

use App\Services\StockService;
use App\Support\Csrf;

/*
 * Cadastro e edição do produto. A composição é a mesma da página do item na
 * loja (galeria à esquerda, informações à direita); no cadastro a galeria dá
 * lugar aos tamanhos e ao saldo inicial.
 *
 * O produto pode ficar em mais de uma categoria. Cada categoria raiz marcada
 * guarda a subcategoria escolhida em `subcategorias[raiz]` — sem escolha, o
 * vínculo é com a raiz. O pequeno script do painel só mostra esse seletor
 * quando a categoria está marcada; sem JavaScript ele fica visível e o
 * servidor ignora subcategoria de categoria não marcada.
 *
 * @var array<string, mixed>|null $adminProduto
 * @var string $adminModo
 * @var list<array<string, mixed>> $adminCategorias
 * @var list<int> $adminCategoriasMarcadas
 * @var array<int, int> $adminSubcategoriasMarcadas
 * @var list<string> $adminTamanhos
 * @var list<string> $adminTamanhosMarcados
 * @var list<string> $adminErros
 * @var array<string, mixed> $adminValores
 * @var array<string, mixed> $adminVoltar
 * @var array<string, mixed> $adminPrecos
 * @var string $adminLimiteFotos
 * @var int $adminMaxFotos
 * @var list<string> $adminCores
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$novo = $adminProduto === null;
$idProduto = $novo ? 0 : (int) $adminProduto['id'];

$urlDoEstoque = '/admin/estoque?' . http_build_query($adminVoltar);
$urlDoProduto = $novo ? '/admin/estoque/novo' : '/admin/estoque/produto/' . $idProduto;

$valor = static function (string $campo, string $padrao = '') use ($adminValores): string {
	return $adminValores !== [] && array_key_exists($campo, $adminValores)
		? (string) $adminValores[$campo]
		: $padrao;
};

$precoAtual = $novo ? '' : number_format($adminProduto['price_cents'] / 100, 2, ',', '');
$comparativoAtual = $novo || $adminProduto['compare_price_cents'] === null
	? ''
	: number_format($adminProduto['compare_price_cents'] / 100, 2, ',', '');

$principal = $novo ? null : ($adminProduto['images'][0] ?? null);
$estoqueTotal = 0;

if (!$novo) {
	foreach ($adminProduto['variants'] as $variacao) {
		$estoqueTotal += (int) $variacao['quantity'];
	}
}

$marcadas = $adminValores !== [] ? array_map('intval', (array) ($adminValores['categorias'] ?? [])) : $adminCategoriasMarcadas;
$subs = $adminValores !== [] ? (array) ($adminValores['subcategorias'] ?? []) : $adminSubcategoriasMarcadas;
$tamanhosMarcados = $adminValores !== [] ? (array) ($adminValores['tamanhos'] ?? []) : $adminTamanhosMarcados;

if ($novo && $tamanhosMarcados === []) {
	$tamanhosMarcados = $adminTamanhos;
}

// A cor atual entra na frente da lista de sugestões quando ainda não está nela.
$corAtual = $valor('color', $novo ? '' : (string) $adminProduto['color']);
$coresSugeridas = $adminCores;

if ($corAtual !== '' && !in_array($corAtual, $coresSugeridas, true)) {
	array_unshift($coresSugeridas, $corAtual);
}

$raizMarcada = static function (int $raiz) use ($marcadas, $subs): bool {
	if (in_array($raiz, $marcadas, true)) {
		return true;
	}

	foreach (array_keys($subs) as $pai) {
		if ((int) $pai === $raiz) {
			return true;
		}
	}

	return false;
};
?>
<p class="admin-crumb">
	<a href="<?= $escape($urlDoEstoque) ?>">Estoque</a>
	<span aria-hidden="true">/</span>
	<span aria-current="page"><?= $novo ? 'Novo modelo' : $escape((string) $adminProduto['name']) ?></span>
</p>

<header class="admin-head admin-head--row">
	<div>
		<?php if ($novo): ?>
			<p class="admin-head__eyebrow">Estoque</p>
			<h1 class="admin-head__title">Novo modelo</h1>
			<p class="admin-head__text">Cadastre a camiseta com a cor, os tamanhos, o saldo inicial e as fotos — tudo na mesma tela.</p>
		<?php else: ?>
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
		<?php endif; ?>
	</div>

	<p class="admin-head__actions">
		<?php if (!$novo && $adminProduto['is_active']): ?>
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

			<?php if ($novo): ?>
				<p class="admin-product__empty">Escolha as fotos da camiseta aqui: elas sobem junto com o modelo, ao clicar em "Criar modelo".</p>

				<div class="admin-upload">
					<label class="field__label" for="fotos">Fotos da camiseta</label>
					<input
						class="admin-upload__input"
						id="fotos"
						name="photos[]"
						type="file"
						accept="image/jpeg,image/png,image/webp"
						multiple
						form="produto-form"
					>
					<p class="field__hint">
						JPG, PNG ou WebP, até <?= $escape($adminLimiteFotos) ?>, no máximo <?= (int) $adminMaxFotos ?> fotos.
						A primeira da lista é a que aparece na vitrine.
					</p>
				</div>
			<?php else: ?>
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
			<?php endif; ?>
		</section>

	<form class="admin-product__form" id="produto-form" method="post" action="<?= $escape($urlDoProduto) ?>"<?= $novo ? ' enctype="multipart/form-data"' : '' ?>>
		<?= Csrf::field() ?>
		<?php foreach ($adminVoltar as $campo => $info): ?>
			<input type="hidden" name="<?= $escape((string) $campo) ?>" value="<?= $escape((string) $info) ?>">
		<?php endforeach; ?>

		<h2 class="admin-product__subtitle"><?= $novo ? 'Dados do modelo' : 'Dados do produto' ?></h2>

		<div class="field">
			<label class="field__label" for="name">Nome</label>
			<input class="field__input" id="name" name="name" type="text" maxlength="120" required value="<?= $escape($valor('name', $novo ? '' : (string) $adminProduto['name'])) ?>">
		</div>

		<div class="field">
			<label class="field__label" for="slug">Endereço na loja</label>
			<input class="field__input" id="slug" name="slug" type="text" maxlength="140" value="<?= $escape($valor('slug', $novo ? '' : (string) $adminProduto['slug'])) ?>">
			<p class="field__hint">Fica em /produto/<?= $escape($valor('slug', $novo ? '' : (string) $adminProduto['slug'])) ?>. Deixe vazio para gerar pelo nome.</p>
		</div>

		<fieldset class="field">
			<legend class="field__label">Categorias</legend>
			<p class="field__hint">Marque uma ou mais categorias. Se a categoria tiver subcategoria, escolha abaixo para o produto entrar nela.</p>

			<div class="admin-categories">
				<?php foreach ($adminCategorias as $categoria): ?>
					<?php
						$raiz = (int) $categoria['id'];
						$temFilhos = $categoria['children'] !== [];
						$filhoAtual = (int) ($subs[$raiz] ?? 0);
						$aberta = $raizMarcada($raiz);
					?>
					<div class="admin-category" data-category>
						<label class="admin-category__label">
							<input class="admin-category__check" type="checkbox" name="categories[]" value="<?= $raiz ?>" <?= $aberta ? 'checked' : '' ?> data-category-toggle>
							<span><?= $escape((string) $categoria['name']) ?></span>
						</label>

						<?php if ($temFilhos): ?>
							<div class="admin-category__sub" data-category-sub <?= $aberta ? '' : 'hidden' ?>>
								<label class="field__label" for="sub-<?= $raiz ?>">Subcategoria de <?= $escape((string) $categoria['name']) ?></label>
								<select class="field__input" id="sub-<?= $raiz ?>" name="subcategorias[<?= $raiz ?>]">
									<option value="">Fica na categoria</option>
									<?php foreach ($categoria['children'] as $filho): ?>
										<option value="<?= (int) $filho['id'] ?>" <?= $filhoAtual === (int) $filho['id'] ? 'selected' : '' ?>><?= $escape((string) $filho['name']) ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<div class="admin-field-row">
			<div class="field">
				<label class="field__label" for="color">Cor</label>
				<input
					class="field__input"
					id="color"
					name="color"
					type="text"
					maxlength="40"
					required
					list="cores-do-produto"
					autocomplete="off"
					data-color-input
					value="<?= $escape($corAtual) ?>"
				>
				<p class="field__hint">Escolha uma das cores da lista ou digite uma nova: ela passa a ser sugerida no campo.</p>

				<datalist id="cores-do-produto">
					<?php foreach ($coresSugeridas as $cor): ?>
						<option value="<?= $escape($cor) ?>"></option>
					<?php endforeach; ?>
				</datalist>
			</div>

			<div class="field">
				<label class="field__label" for="material">Material</label>
				<input class="field__input" id="material" name="material" type="text" maxlength="80" required value="<?= $escape($valor('material', $novo ? 'algodão 100%' : (string) $adminProduto['material'])) ?>">
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
			<textarea class="field__input admin-textarea" id="description" name="description" rows="5" maxlength="2000"><?= $escape($valor('description', $novo ? '' : (string) $adminProduto['description'])) ?></textarea>
		</div>

		<?php if ($novo): ?>
			<fieldset class="field">
				<legend class="field__label">Tamanhos</legend>
				<div class="admin-choices">
					<?php foreach ($adminTamanhos as $tamanho): ?>
						<?php $campoTamanho = 'tamanho-' . mb_strtolower($tamanho); ?>
						<input
							class="admin-choice__input"
							type="checkbox"
							id="<?= $escape($campoTamanho) ?>"
							name="sizes[]"
							value="<?= $escape($tamanho) ?>"
							<?= in_array($tamanho, $tamanhosMarcados, true) ? 'checked' : '' ?>
						>
						<label class="admin-choice" for="<?= $escape($campoTamanho) ?>"><?= $escape($tamanho) ?></label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<div class="field">
				<label class="field__label" for="initial_stock">Saldo inicial por tamanho</label>
				<input class="field__input" id="initial_stock" name="initial_stock" type="number" min="0" max="<?= StockService::MAX_QUANTITY ?>" step="1" inputmode="numeric" value="<?= $escape($valor('initial_stock', '0')) ?>">
				<p class="field__hint">Aplicado a cada tamanho marcado. Dá para ajustar tamanho por tamanho depois.</p>
			</div>

			<label class="field field--check">
				<input class="field__check" type="checkbox" name="is_active" value="1" <?= $valor('is_active', '1') === '1' ? 'checked' : '' ?>>
				<span class="field__check-label">Na loja (desmarcado já nasce fora de linha)</span>
			</label>

			<button class="button" type="submit">Criar modelo</button>
		<?php else: ?>
			<label class="field field--check">
				<input class="field__check" type="checkbox" name="is_active" value="1" <?= $valor('is_active', $adminProduto['is_active'] ? '1' : '0') === '1' ? 'checked' : '' ?>>
				<span class="field__check-label">Na loja (desmarcado deixa o produto fora de linha)</span>
			</label>

			<button class="button" type="submit">Salvar alterações</button>
		<?php endif; ?>
	</form>
</div>

<?php if (!$novo): ?>
	<section class="admin-product__variants" aria-labelledby="tamanhos-titulo">
		<h2 class="admin-product__subtitle" id="tamanhos-titulo">Tamanhos</h2>
		<p class="field__hint">Digite o saldo e salve. É o mesmo saldo da tela de estoque, tamanho por tamanho.</p>

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
						<?php $campoSaldo = 'saldo-tamanho-' . (int) $variacao['id']; ?>
						<tr>
							<td><?= $escape((string) $variacao['size']) ?></td>
							<td class="admin-table__muted admin-table__sku"><?= $escape((string) $variacao['sku']) ?></td>
							<td>
								<form class="stock-form" method="post" action="<?= $escape($urlDoProduto . '/saldo') ?>">
									<?= Csrf::field() ?>
									<input type="hidden" name="variant_id" value="<?= (int) $variacao['id'] ?>">
									<input type="hidden" name="atual" value="<?= (int) $variacao['quantity'] ?>">
									<?php foreach ($adminVoltar as $campo => $info): ?>
										<input type="hidden" name="<?= $escape((string) $campo) ?>" value="<?= $escape((string) $info) ?>">
									<?php endforeach; ?>

									<label class="admin-visually-hidden" for="<?= $escape($campoSaldo) ?>">Saldo do tamanho <?= $escape((string) $variacao['size']) ?></label>
									<input class="stock-form__input" id="<?= $escape($campoSaldo) ?>" name="quantity" type="number" min="0" max="<?= StockService::MAX_QUANTITY ?>" step="1" inputmode="numeric" value="<?= (int) $variacao['quantity'] ?>" required>

									<button class="stock-form__save" type="submit">Salvar</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
<?php endif; ?>
