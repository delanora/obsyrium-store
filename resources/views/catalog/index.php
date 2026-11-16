<?php

declare(strict_types=1);

use App\Services\CatalogService;

/*
 * Catálogo: filtros (categoria, subcategoria, tamanho, disponibilidade e faixa
 * de preço), ordenação e paginação. Todo o estado vem da URL e cada opção é um
 * link que devolve o estado novo — sem JavaScript o catálogo funciona igual.
 *
 * No celular a barra de filtros nasce recolhida (site.js) e abre pelo resumo;
 * com filtro ativo ela já vem aberta, para dar para ajustar o que está ligado.
 *
 * @var list<array<string, mixed>> $produtos
 * @var array<string, mixed> $estado
 * @var array<string, mixed> $resultado
 * @var list<array<string, mixed>> $categorias
 * @var list<string> $tamanhos
 * @var int $totalDaLoja
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// Monta o link do catálogo com o estado atual trocado pelo que vier aqui.
// Valores padrão ficam fora da URL: /catalogo limpo é o catálogo inteiro.
$url = static function (array $mudancas = []) use ($estado): string {
    $query = [];

    foreach (['categoria', 'sub', 'tamanho', 'disponibilidade', 'preco', 'ordem', 'pagina'] as $chave) {
        $valor = array_key_exists($chave, $mudancas) ? $mudancas[$chave] : ($estado[$chave] ?? '');

        if ($valor === '' || $valor === null) {
            continue;
        }

        if ($chave === 'ordem' && $valor === 'novidades') {
            continue;
        }

        if ($chave === 'pagina' && (int) $valor === 1) {
            continue;
        }

        $query[$chave] = $valor;
    }

    return '/catalogo' . ($query === [] ? '' : '?' . http_build_query($query));
};

$filtrosAtivos = 0;

foreach (['categoria', 'sub', 'tamanho', 'disponibilidade', 'preco'] as $chave) {
    if (($estado[$chave] ?? '') !== '') {
        $filtrosAtivos++;
    }
}

$categoriaAtual = null;

foreach ($categorias as $categoria) {
    if ($categoria['slug'] === $estado['categoria']) {
        $categoriaAtual = $categoria;
    }
}

// Paginação: até 7 páginas aparecem todas; acima disso entra a primeira, a
// atual, a última e as vizinhas, com reticências no lugar do resto.
$paginasVisiveis = [];

if ($resultado['paginas'] <= 7) {
    $paginasVisiveis = range(1, (int) $resultado['paginas']);
} else {
    $paginasVisiveis[] = 1;

    if ($resultado['pagina'] > 3) {
        $paginasVisiveis[] = null;
    }

    for ($numero = max(2, (int) $resultado['pagina'] - 1); $numero <= min((int) $resultado['paginas'] - 1, (int) $resultado['pagina'] + 1); $numero++) {
        $paginasVisiveis[] = $numero;
    }

    if ($resultado['pagina'] < (int) $resultado['paginas'] - 2) {
        $paginasVisiveis[] = null;
    }

    $paginasVisiveis[] = (int) $resultado['paginas'];
}
?>
<section class="section catalog">
	<div class="container">
		<header class="catalog__head">
			<p class="catalog__eyebrow">Loja</p>
			<h1 class="catalog__title">Catálogo</h1>
			<p class="catalog__lead">
				<?= $totalDaLoja ?> camiseta<?= $totalDaLoja === 1 ? '' : 's' ?> em linha, em tiragem limitada.
				Escolha por categoria, tamanho e preço.
			</p>
		</header>

		<details class="catalog-filters" data-catalog-filters open<?= $filtrosAtivos > 0 ? ' data-active' : '' ?>>
			<summary class="catalog-filters__summary">
				<span class="catalog-filters__summary-label">Filtros</span>

				<?php if ($filtrosAtivos > 0): ?>
					<span class="catalog-filters__badge"><?= $filtrosAtivos ?></span>
				<?php endif; ?>

				<span class="catalog-filters__chevron" aria-hidden="true"></span>
			</summary>

			<div class="catalog-filters__body">
				<div class="catalog-filters__group">
					<p class="catalog-filters__title" id="filtro-categoria">Categoria</p>
					<ul class="catalog-filters__list" aria-labelledby="filtro-categoria">
						<li>
							<a class="filter-chip<?= $estado['categoria'] === '' ? ' filter-chip--active' : '' ?>" href="<?= $escape($url(['categoria' => '', 'sub' => '', 'pagina' => 1])) ?>"<?= $estado['categoria'] === '' ? ' aria-current="true"' : '' ?>>
								Todas
								<span class="filter-chip__count"><?= $totalDaLoja ?></span>
							</a>
						</li>

						<?php foreach ($categorias as $categoria): ?>
							<li>
								<a class="filter-chip<?= $categoria['slug'] === $estado['categoria'] ? ' filter-chip--active' : '' ?>" href="<?= $escape($url(['categoria' => (string) $categoria['slug'], 'sub' => '', 'pagina' => 1])) ?>"<?= $categoria['slug'] === $estado['categoria'] ? ' aria-current="true"' : '' ?>>
									<?= $escape((string) $categoria['name']) ?>
									<span class="filter-chip__count"><?= (int) $categoria['total'] ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>

					<?php if ($categoriaAtual !== null && $categoriaAtual['children'] !== []): ?>
						<p class="catalog-filters__title catalog-filters__title--sub" id="filtro-subcategoria">Subcategoria</p>
						<ul class="catalog-filters__list" aria-labelledby="filtro-subcategoria">
							<li>
								<a class="filter-chip filter-chip--small<?= $estado['sub'] === '' ? ' filter-chip--active' : '' ?>" href="<?= $escape($url(['sub' => '', 'pagina' => 1])) ?>"<?= $estado['sub'] === '' ? ' aria-current="true"' : '' ?>>
									Todas de <?= $escape((string) $categoriaAtual['name']) ?>
								</a>
							</li>

							<?php foreach ($categoriaAtual['children'] as $filha): ?>
								<li>
									<a class="filter-chip filter-chip--small<?= $filha['slug'] === $estado['sub'] ? ' filter-chip--active' : '' ?>" href="<?= $escape($url(['sub' => (string) $filha['slug'], 'pagina' => 1])) ?>"<?= $filha['slug'] === $estado['sub'] ? ' aria-current="true"' : '' ?>>
										<?= $escape((string) $filha['name']) ?>
										<span class="filter-chip__count"><?= (int) $filha['total'] ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<?php if ($tamanhos !== []): ?>
					<div class="catalog-filters__group">
						<p class="catalog-filters__title" id="filtro-tamanho">Tamanho</p>
						<ul class="catalog-filters__list" aria-labelledby="filtro-tamanho">
							<?php foreach ($tamanhos as $tamanho): ?>
								<li>
									<a class="filter-chip filter-chip--size<?= $tamanho === $estado['tamanho'] ? ' filter-chip--active' : '' ?>" href="<?= $escape($url(['tamanho' => $tamanho === $estado['tamanho'] ? '' : $tamanho, 'pagina' => 1])) ?>"<?= $tamanho === $estado['tamanho'] ? ' aria-current="true"' : '' ?>>
										<?= $escape($tamanho) ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<div class="catalog-filters__group">
					<p class="catalog-filters__title" id="filtro-disponibilidade">Disponibilidade</p>
					<ul class="catalog-filters__list" aria-labelledby="filtro-disponibilidade">
						<?php foreach (CatalogService::AVAILABILITY as $chave => $rotulo): ?>
							<li>
								<a class="filter-chip<?= $chave === $estado['disponibilidade'] ? ' filter-chip--active' : '' ?>" href="<?= $escape($url(['disponibilidade' => $chave === $estado['disponibilidade'] ? '' : $chave, 'pagina' => 1])) ?>"<?= $chave === $estado['disponibilidade'] ? ' aria-current="true"' : '' ?>>
									<?= $escape($rotulo) ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="catalog-filters__group">
					<p class="catalog-filters__title" id="filtro-preco">Faixa de preço</p>
					<ul class="catalog-filters__list" aria-labelledby="filtro-preco">
						<?php foreach (CatalogService::PRICE_RANGES as $chave => $faixa): ?>
							<li>
								<a class="filter-chip<?= $chave === $estado['preco'] ? ' filter-chip--active' : '' ?>" href="<?= $escape($url(['preco' => $chave === $estado['preco'] ? '' : $chave, 'pagina' => 1])) ?>"<?= $chave === $estado['preco'] ? ' aria-current="true"' : '' ?>>
									<?= $escape($faixa['label']) ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<?php if ($filtrosAtivos > 0): ?>
					<p class="catalog-filters__clear">
						<a class="section__link" href="/catalogo">Limpar filtros</a>
					</p>
				<?php endif; ?>
			</div>
		</details>

		<div class="catalog__toolbar">
			<p class="catalog__count" role="status">
				<?php if ($resultado['total'] === 0): ?>
					Nenhum produto
				<?php elseif ($resultado['total'] === 1): ?>
					1 produto
				<?php else: ?>
					<?= (int) $resultado['total'] ?> produtos
				<?php endif; ?>

				<?php if ($resultado['paginas'] > 1): ?>
					<span class="catalog__range">· <?= (int) $resultado['inicio'] ?>–<?= (int) $resultado['fim'] ?></span>
				<?php endif; ?>
			</p>

			<nav class="catalog-sort" aria-label="Ordenar o catálogo">
				<span class="catalog-sort__label">Ordenar</span>

				<?php foreach (CatalogService::SORTS as $chave => $rotulo): ?>
					<a class="catalog-sort__link<?= $chave === $estado['ordem'] ? ' catalog-sort__link--active' : '' ?>" href="<?= $escape($url(['ordem' => $chave, 'pagina' => 1])) ?>"<?= $chave === $estado['ordem'] ? ' aria-current="true"' : '' ?>>
						<?= $escape($rotulo) ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>

		<?php if ($produtos === []): ?>
			<p class="catalog__empty">
				Nenhum produto com esses filtros.
				<a class="catalog__empty-link" href="/catalogo">Ver o catálogo inteiro</a>
			</p>
		<?php else: ?>
			<ul class="product-grid">
				<?php foreach ($produtos as $product): ?>
					<li data-reveal>
						<?php require dirname(__DIR__) . '/partials/product-card.php'; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ($resultado['paginas'] > 1): ?>
				<nav class="pagination" aria-label="Paginação do catálogo">
					<?php if ($resultado['pagina'] > 1): ?>
						<a class="pagination__item pagination__item--wide" href="<?= $escape($url(['pagina' => (int) $resultado['pagina'] - 1])) ?>" rel="prev">Anterior</a>
					<?php else: ?>
						<span class="pagination__item pagination__item--wide pagination__item--off">Anterior</span>
					<?php endif; ?>

					<?php foreach ($paginasVisiveis as $numero): ?>
						<?php if ($numero === null): ?>
							<span class="pagination__gap" aria-hidden="true">…</span>
						<?php elseif ($numero === (int) $resultado['pagina']): ?>
							<span class="pagination__item" aria-current="page"><?= $numero ?></span>
						<?php else: ?>
							<a class="pagination__item" href="<?= $escape($url(['pagina' => $numero])) ?>"><?= $numero ?></a>
						<?php endif; ?>
					<?php endforeach; ?>

					<?php if ($resultado['pagina'] < (int) $resultado['paginas']): ?>
						<a class="pagination__item pagination__item--wide" href="<?= $escape($url(['pagina' => (int) $resultado['pagina'] + 1])) ?>" rel="next">Próxima</a>
					<?php else: ?>
						<span class="pagination__item pagination__item--wide pagination__item--off">Próxima</span>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
