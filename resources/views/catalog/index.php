<?php

declare(strict_types=1);

use App\Services\CatalogService;

/*
 * Catálogo: cabeçalho centrado, filtros numa coluna à esquerda no computador e
 * a grade à direita.
 *
 * Os filtros são um formulário GET: categoria, subcategoria e cor em
 * `select`, tamanho e disponibilidade em botões de rádio, preço digitado em
 * dois campos. O botão "Aplicar filtros" funciona sem JavaScript; com JS, o
 * formulário se envia sozinho quando um campo muda (site.js).
 *
 * O estado continua na URL — cada filtro ativo aparece numa faixa de chips
 * removíveis, e a ordenação é um menu recolhido que também é um link.
 *
 * @var list<array<string, mixed>> $produtos
 * @var array<string, mixed> $estado
 * @var array<string, mixed> $resultado
 * @var list<array<string, mixed>> $categorias
 * @var list<string> $tamanhos
 * @var list<array{name: string, total: int}> $cores
 * @var array{min: int, max: int} $precos
 * @var int $totalDaLoja
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// Monta o link do catálogo com o estado atual trocado pelo que vier aqui.
// Valores padrão ficam fora da URL: /catalogo limpo é o catálogo inteiro.
$url = static function (array $mudancas = []) use ($estado): string {
    $query = [];

    foreach (['categoria', 'sub', 'tamanho', 'cor', 'disponibilidade', 'preco_min', 'preco_max', 'ordem', 'pagina'] as $chave) {
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

        // O estado guarda centavos; a URL mostra reais.
        if ($chave === 'preco_min' || $chave === 'preco_max') {
            $query[$chave] = number_format((int) $valor / 100, 2, '.', '');
            continue;
        }

        $query[$chave] = $valor;
    }

    return '/catalogo' . ($query === [] ? '' : '?' . http_build_query($query));
};

$categoriaAtual = null;

foreach ($categorias as $categoria) {
    if ($categoria['slug'] === $estado['categoria']) {
        $categoriaAtual = $categoria;
    }
}

// Chips dos filtros ligados: cada um leva de volta ao catálogo sem ele.
$ativos = [];

if ($categoriaAtual !== null) {
    $ativos[] = [
        'rotulo' => (string) $categoriaAtual['name'],
        'href' => $url(['categoria' => '', 'sub' => '', 'pagina' => 1]),
    ];
}

if ($categoriaAtual !== null && $estado['sub'] !== '') {
    foreach ($categoriaAtual['children'] as $filha) {
        if ($filha['slug'] === $estado['sub']) {
            $ativos[] = [
                'rotulo' => (string) $filha['name'],
                'href' => $url(['sub' => '', 'pagina' => 1]),
            ];
        }
    }
}

if ($estado['tamanho'] !== '') {
    $ativos[] = ['rotulo' => 'Tamanho ' . $estado['tamanho'], 'href' => $url(['tamanho' => '', 'pagina' => 1])];
}

if ($estado['cor'] !== '') {
    $ativos[] = ['rotulo' => (string) $estado['cor'], 'href' => $url(['cor' => '', 'pagina' => 1])];
}

if ($estado['disponibilidade'] !== '') {
    $ativos[] = [
        'rotulo' => CatalogService::AVAILABILITY[(string) $estado['disponibilidade']],
        'href' => $url(['disponibilidade' => '', 'pagina' => 1]),
    ];
}

if ($estado['preco_min'] !== '' || $estado['preco_max'] !== '') {
    $de = (int) ($estado['preco_min'] ?: 0);
    $ate = (int) ($estado['preco_max'] ?: 0);

    // Centavos aparecem só quando existem: "R$ 130" e "R$ 129,90".
    $reais = static fn (int $centavos): string => number_format($centavos / 100, $centavos % 100 === 0 ? 0 : 2, ',', '.');

    if ($de > 0 && $ate > 0) {
        $rotulo = sprintf('R$ %s a %s', $reais($de), $reais($ate));
    } elseif ($de > 0) {
        $rotulo = sprintf('A partir de R$ %s', $reais($de));
    } else {
        $rotulo = sprintf('Até R$ %s', $reais($ate));
    }

    $ativos[] = ['rotulo' => $rotulo, 'href' => $url(['preco_min' => '', 'preco_max' => '', 'pagina' => 1])];
}

$filtrosAtivos = count($ativos);

// Preço digitado volta para o campo em reais, com ponto decimal.
$valorPreco = static fn (mixed $centavos): string => $centavos === '' || $centavos === null
    ? ''
    : number_format((int) $centavos / 100, 2, '.', '');

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
			<h1 class="catalog__title">Catálogo</h1>
			<p class="catalog__lead">
				Camisetas de estampa autoral, feitas em lotes pequenos e conferidas peça a peça antes de sair.
				Nada de catálogo infinito: quando o lote acaba, o tamanho sai do ar.
			</p>
		</header>

		<div class="catalog__layout">
			<details class="catalog-filters" data-catalog-filters open<?= $filtrosAtivos > 0 ? ' data-active' : '' ?>>
				<summary class="catalog-filters__summary">
					<span class="catalog-filters__summary-label">Filtros</span>

					<?php if ($filtrosAtivos > 0): ?>
						<span class="catalog-filters__badge"><?= $filtrosAtivos ?></span>
					<?php endif; ?>

					<span class="catalog-filters__chevron" aria-hidden="true"></span>
				</summary>

				<form class="catalog-filters__body" action="/catalogo" method="get" data-filter-form>
					<?php if ($estado['ordem'] !== 'novidades'): ?>
						<input type="hidden" name="ordem" value="<?= $escape((string) $estado['ordem']) ?>">
					<?php endif; ?>

					<div class="catalog-field">
						<label class="catalog-field__label" for="filtro-categoria">Categoria</label>

						<select class="catalog-field__select" id="filtro-categoria" name="categoria">
							<option value="">Todas as camisetas (<?= $totalDaLoja ?>)</option>

							<?php foreach ($categorias as $categoria): ?>
								<option value="<?= $escape((string) $categoria['slug']) ?>"<?= $categoria['slug'] === $estado['categoria'] ? ' selected' : '' ?>>
									<?= $escape((string) $categoria['name']) ?> (<?= (int) $categoria['total'] ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<?php if ($categoriaAtual !== null && $categoriaAtual['children'] !== []): ?>
						<div class="catalog-field">
							<label class="catalog-field__label" for="filtro-subcategoria">Subcategoria</label>

							<select class="catalog-field__select" id="filtro-subcategoria" name="sub">
								<option value="">Todas de <?= $escape((string) $categoriaAtual['name']) ?></option>

								<?php foreach ($categoriaAtual['children'] as $filha): ?>
									<option value="<?= $escape((string) $filha['slug']) ?>"<?= $filha['slug'] === $estado['sub'] ? ' selected' : '' ?>>
										<?= $escape((string) $filha['name']) ?> (<?= (int) $filha['total'] ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<?php if ($cores !== []): ?>
						<div class="catalog-field">
							<label class="catalog-field__label" for="filtro-cor">Cor</label>

							<select class="catalog-field__select" id="filtro-cor" name="cor">
								<option value="">Todas as cores</option>

								<?php foreach ($cores as $cor): ?>
									<option value="<?= $escape($cor['name']) ?>"<?= $cor['name'] === $estado['cor'] ? ' selected' : '' ?>>
										<?= $escape($cor['name']) ?> (<?= (int) $cor['total'] ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<?php if ($tamanhos !== []): ?>
						<fieldset class="catalog-field catalog-field--chips">
							<legend class="catalog-field__label">Tamanho</legend>

							<div class="catalog-chips">
								<label class="catalog-chip">
									<input type="radio" name="tamanho" value=""<?= $estado['tamanho'] === '' ? ' checked' : '' ?>>
									<span class="catalog-chip__text">Todos</span>
								</label>

								<?php foreach ($tamanhos as $tamanho): ?>
									<label class="catalog-chip">
										<input type="radio" name="tamanho" value="<?= $escape($tamanho) ?>"<?= $tamanho === $estado['tamanho'] ? ' checked' : '' ?>>
										<span class="catalog-chip__text"><?= $escape($tamanho) ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</fieldset>
					<?php endif; ?>

					<fieldset class="catalog-field catalog-field--chips">
						<legend class="catalog-field__label">Disponibilidade</legend>

						<div class="catalog-chips">
							<label class="catalog-chip">
								<input type="radio" name="disponibilidade" value=""<?= $estado['disponibilidade'] === '' ? ' checked' : '' ?>>
								<span class="catalog-chip__text">Todos</span>
							</label>

							<?php foreach (CatalogService::AVAILABILITY as $chave => $rotulo): ?>
								<label class="catalog-chip">
									<input type="radio" name="disponibilidade" value="<?= $escape($chave) ?>"<?= $chave === $estado['disponibilidade'] ? ' checked' : '' ?>>
									<span class="catalog-chip__text"><?= $escape($rotulo) ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<div class="catalog-field">
						<span class="catalog-field__label" id="filtro-preco">Faixa de preço</span>

						<div class="catalog-price" role="group" aria-labelledby="filtro-preco">
							<label class="catalog-price__field">
								<span class="catalog-price__text">De</span>
								<input class="catalog-price__input" type="text" name="preco_min" inputmode="decimal" autocomplete="off" spellcheck="false" placeholder="<?= $precos['min'] > 0 ? number_format($precos['min'] / 100, 0, ',', '.') : '0' ?>" value="<?= $escape($valorPreco($estado['preco_min'])) ?>">
							</label>

							<label class="catalog-price__field">
								<span class="catalog-price__text">Até</span>
								<input class="catalog-price__input" type="text" name="preco_max" inputmode="decimal" autocomplete="off" spellcheck="false" placeholder="<?= $precos['max'] > 0 ? number_format($precos['max'] / 100, 0, ',', '.') : '0' ?>" value="<?= $escape($valorPreco($estado['preco_max'])) ?>">
							</label>
						</div>

						<p class="catalog-field__hint">Em reais. Deixe um dos campos vazio para não limitar.</p>
					</div>

					<div class="catalog-filters__actions">
						<button class="button catalog-filters__apply" type="submit">Aplicar filtros</button>

						<?php if ($filtrosAtivos > 0): ?>
							<a class="catalog-filters__clear" href="/catalogo">Limpar tudo</a>
						<?php endif; ?>
					</div>
				</form>
			</details>

			<div class="catalog__main">
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

					<details class="catalog-sort" data-menu>
						<summary class="catalog-sort__toggle">
							<svg class="catalog-sort__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4v16"/><path d="M4 17l3 3 3-3"/><path d="M17 20V4"/><path d="M14 7l3-3 3 3"/></svg>
							<span class="catalog-sort__label">Ordenar</span>
							<span class="catalog-sort__current"><?= $escape(CatalogService::SORTS[(string) $estado['ordem']]) ?></span>
							<span class="catalog-sort__chevron" aria-hidden="true"></span>
						</summary>

						<div class="catalog-sort__menu">
							<p class="catalog-sort__title">Ordenar por</p>

							<ul class="catalog-sort__list">
								<?php foreach (CatalogService::SORTS as $chave => $rotulo): ?>
									<?php $atual = $chave === $estado['ordem']; ?>

									<li>
										<a class="catalog-sort__option<?= $atual ? ' catalog-sort__option--active' : '' ?>" href="<?= $escape($url(['ordem' => $chave, 'pagina' => 1])) ?>"<?= $atual ? ' aria-current="true"' : '' ?>>
											<span><?= $escape($rotulo) ?></span>

											<?php if ($atual): ?>
												<svg class="catalog-sort__check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
											<?php endif; ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</details>
				</div>

				<?php if ($ativos !== []): ?>
					<ul class="catalog-active" aria-label="Filtros aplicados">
						<?php foreach ($ativos as $ativo): ?>
							<li>
								<a class="catalog-active__chip" href="<?= $escape($ativo['href']) ?>">
									<?= $escape($ativo['rotulo']) ?>
									<span class="catalog-active__remove" aria-hidden="true">×</span>
									<span class="visually-hidden">Remover filtro</span>
								</a>
							</li>
						<?php endforeach; ?>

						<li>
							<a class="catalog-active__clear" href="/catalogo">Limpar tudo</a>
						</li>
					</ul>
				<?php endif; ?>

				<?php if ($produtos === []): ?>
					<div class="catalog__empty">
						<p class="catalog__empty-title">Nenhuma camiseta com esses filtros.</p>
						<p class="catalog__empty-text">Tente uma faixa de preço mais ampla ou veja o catálogo inteiro.</p>
						<a class="button button--ghost" href="/catalogo">Ver o catálogo inteiro</a>
					</div>
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
		</div>
	</div>
</section>
