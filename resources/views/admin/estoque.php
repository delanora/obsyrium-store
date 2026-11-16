<?php

declare(strict_types=1);

use App\Services\StockService;
use App\Support\Csrf;

/*
 * Estoque do painel: abas por situação, colunas ordenáveis, paginação de 10 e
 * o saldo editável em cada linha.
 *
 * A lixeira tira o modelo de linha (products.is_active = 0) e some com ele das
 * abas de produtos ativos; na aba fora de linha o ícone vira carrinho e devolve
 * o produto à loja — que reaparece esgotado se não tiver saldo.
 *
 * @var array<string, mixed> $adminEstoque
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$situacoes = [
	'ok' => 'Disponível',
	'baixo' => 'Estoque baixo',
	'esgotado' => 'Esgotado',
];

$aba = (string) $adminEstoque['aba'];
$ordem = (string) $adminEstoque['ordem'];
$direcao = (string) $adminEstoque['direcao'];
$pagina = (int) $adminEstoque['pagina'];

$linkDaAba = static function (string $chave) use ($ordem, $direcao): string {
	return '/admin/estoque?' . http_build_query(['aba' => $chave, 'ordem' => $ordem, 'direcao' => $direcao, 'pagina' => 1]);
};

$linkDaPagina = static function (int $numero) use ($aba, $ordem, $direcao): string {
	return '/admin/estoque?' . http_build_query(['aba' => $aba, 'ordem' => $ordem, 'direcao' => $direcao, 'pagina' => $numero]);
};

$coluna = static function (string $chave) use ($aba, $ordem, $direcao): array {
	$atual = $ordem === $chave;
	$novaDirecao = $atual && $direcao === 'asc' ? 'desc' : 'asc';

	return [
		'url' => '/admin/estoque?' . http_build_query(['aba' => $aba, 'ordem' => $chave, 'direcao' => $novaDirecao, 'pagina' => 1]),
		'aria' => $atual ? ($direcao === 'asc' ? 'ascending' : 'descending') : 'none',
		'sinal' => $atual ? ($direcao === 'asc' ? '↑' : '↓') : '',
	];
};

// Números da paginação: até 7 páginas aparecem todas; acima disso entra
// primeira, atual, última e vizinhas, com reticências no lugar do resto.
$paginasVisiveis = [];

if ($adminEstoque['paginas'] <= 7) {
	$paginasVisiveis = range(1, (int) $adminEstoque['paginas']);
} else {
	$paginasVisiveis = [1];

	if ($pagina > 3) {
		$paginasVisiveis[] = null;
	}

	for ($numero = max(2, $pagina - 1); $numero <= min((int) $adminEstoque['paginas'] - 1, $pagina + 1); $numero++) {
		$paginasVisiveis[] = $numero;
	}

	if ($pagina < (int) $adminEstoque['paginas'] - 2) {
		$paginasVisiveis[] = null;
	}

	$paginasVisiveis[] = (int) $adminEstoque['paginas'];
}

$voltar = ['aba' => $aba, 'ordem' => $ordem, 'direcao' => $direcao, 'pagina' => $pagina];
?>
<header class="admin-head admin-head--row">
	<div>
		<p class="admin-head__eyebrow">Painel</p>
		<h1 class="admin-head__title">Estoque</h1>
		<p class="admin-head__text">Digite o saldo e salve. A lixeira tira o modelo de linha; o carrinho na aba fora de linha devolve ele à loja.</p>
	</div>

	<p class="admin-head__actions">
		<a class="admin-link-button" href="/admin/estoque/novo">Novo modelo</a>
		<a class="admin-link-button" href="/admin/categorias">Categorias</a>
	</p>
</header>

<nav class="admin-tabs" aria-label="Situação do estoque">
	<?php foreach (StockService::TABS as $chave => $rotulo): ?>
		<a class="admin-tab<?= $aba === $chave ? ' admin-tab--ativo' : '' ?>" href="<?= $escape($linkDaAba($chave)) ?>"<?= $aba === $chave ? ' aria-current="page"' : '' ?>>
			<?= $escape($rotulo) ?>
			<span class="admin-tab__count"><?= (int) ($adminEstoque['contagens'][$chave] ?? 0) ?></span>
		</a>
	<?php endforeach; ?>
</nav>

<?php if ($adminEstoque['itens'] === []): ?>
	<p class="admin-empty">
		<?= $aba === 'fora' ? 'Nenhum produto fora de linha.' : 'Nenhuma variação nesta situação.' ?>
	</p>
<?php else: ?>
	<div class="admin-table-wrap">
		<table class="admin-table admin-table--estoque">
			<thead>
				<tr>
					<?php foreach ([
						'produto' => 'Produto',
						'tamanho' => 'Tamanho',
						'sku' => 'SKU',
						'preco' => 'Preço',
						'promocional' => 'Promocional',
						'reservado' => 'Reservado',
						'saldo' => 'Saldo',
						'disponivel' => 'Disponível',
					] as $chave => $rotulo): ?>
						<?php $info = $coluna($chave); ?>
						<th scope="col" aria-sort="<?= $info['aria'] ?>">
							<a class="admin-sort" href="<?= $escape($info['url']) ?>">
								<?= $escape($rotulo) ?> <span class="admin-sort__sinal" aria-hidden="true"><?= $info['sinal'] ?></span>
							</a>
						</th>
					<?php endforeach; ?>
					<th scope="col">Situação</th>
					<th scope="col"><span class="admin-visually-hidden">Ação</span></th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ($adminEstoque['itens'] as $linha): ?>
					<?php $campoId = 'saldo-' . (int) $linha['variant_id']; ?>

					<tr>
						<td><?= $escape((string) $linha['product']) ?></td>
						<td class="admin-table__muted"><?= $escape((string) $linha['size']) ?></td>
						<td class="admin-table__muted admin-table__sku"><?= $escape((string) $linha['sku']) ?></td>
						<td class="admin-table__money"><?= $escape((string) $linha['price']) ?></td>
						<td class="admin-table__money">
							<?= $escape((string) $linha['promo']) ?>
							<?php if ((int) $linha['discount'] > 0): ?>
								<span class="admin-table__off">-<?= (int) $linha['discount'] ?>%</span>
							<?php endif; ?>
						</td>
						<td class="admin-table__muted"><?= (int) $linha['reserved'] ?></td>

						<td>
							<form class="stock-form" method="post" action="/admin/estoque/saldo">
								<?= Csrf::field() ?>
								<input type="hidden" name="variant_id" value="<?= (int) $linha['variant_id'] ?>">
								<input type="hidden" name="atual" value="<?= (int) $linha['quantity'] ?>">
								<?php foreach ($voltar as $campo => $valor): ?>
									<input type="hidden" name="<?= $escape($campo) ?>" value="<?= $escape((string) $valor) ?>">
								<?php endforeach; ?>

								<label class="admin-visually-hidden" for="<?= $escape($campoId) ?>">Saldo de <?= $escape((string) $linha['product']) ?> tamanho <?= $escape((string) $linha['size']) ?></label>
								<input class="stock-form__input" id="<?= $escape($campoId) ?>" name="quantity" type="number" min="0" max="<?= StockService::MAX_QUANTITY ?>" step="1" inputmode="numeric" value="<?= (int) $linha['quantity'] ?>" required>

								<button class="stock-form__save" type="submit">Salvar</button>
							</form>
						</td>

						<td class="admin-table__total"><?= (int) $linha['available'] ?></td>

						<td>
							<span class="admin-status admin-status--estoque-<?= $escape((string) $linha['situation']) ?>"><?= $escape($situacoes[(string) $linha['situation']] ?? 'Disponível') ?></span>
						</td>

						<td>
							<div class="admin-actions">
							<a class="admin-icon-button" href="/admin/estoque/produto/<?= (int) $linha['product_id'] ?>?<?= $escape(http_build_query($voltar)) ?>" aria-label="Editar <?= $escape((string) $linha['product']) ?>" title="Editar produto">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3Z"/><path d="M13.5 6.5l3 3"/></svg>
							</a>

							<?php if ($aba === 'fora'): ?>
								<form method="post" action="/admin/estoque/voltar">
									<?= Csrf::field() ?>
									<input type="hidden" name="product_id" value="<?= (int) $linha['product_id'] ?>">
									<?php foreach ($voltar as $campo => $valor): ?>
										<input type="hidden" name="<?= $escape($campo) ?>" value="<?= $escape((string) $valor) ?>">
									<?php endforeach; ?>

									<button class="admin-icon-button" type="submit" aria-label="Voltar <?= $escape((string) $linha['product']) ?> para a loja" title="Voltar para a loja">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M3 4h2l2.4 11h11.2l1.9-8H6.2"/></svg>
									</button>
								</form>
							<?php else: ?>
								<form method="post" action="/admin/estoque/fora-de-linha">
									<?= Csrf::field() ?>
									<input type="hidden" name="product_id" value="<?= (int) $linha['product_id'] ?>">
									<?php foreach ($voltar as $campo => $valor): ?>
										<input type="hidden" name="<?= $escape($campo) ?>" value="<?= $escape((string) $valor) ?>">
									<?php endforeach; ?>

									<button class="admin-icon-button admin-icon-button--danger" type="submit" aria-label="Tirar <?= $escape((string) $linha['product']) ?> de linha" title="Tirar de linha">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/><path d="M9.5 7V5h5v2"/><path d="M6.5 7l1 13h9l1-13"/><path d="M10.5 11v5.5"/><path d="M13.5 11v5.5"/></svg>
									</button>
								</form>
							<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<nav class="admin-pagination" aria-label="Paginação do estoque">
		<p class="admin-pagination__info">
			Mostrando <?= (int) $adminEstoque['inicio'] ?>–<?= (int) $adminEstoque['fim'] ?> de <?= (int) $adminEstoque['total'] ?>
		</p>

		<?php if ((int) $adminEstoque['paginas'] > 1): ?>
			<ul class="admin-pagination__list">
				<li>
					<?php if ($pagina > 1): ?>
						<a class="admin-pagination__link" href="<?= $escape($linkDaPagina($pagina - 1)) ?>" rel="prev">Anterior</a>
					<?php else: ?>
						<span class="admin-pagination__link admin-pagination__link--off">Anterior</span>
					<?php endif; ?>
				</li>

				<?php foreach ($paginasVisiveis as $numero): ?>
					<li>
						<?php if ($numero === null): ?>
							<span class="admin-pagination__gap" aria-hidden="true">…</span>
						<?php elseif ($numero === $pagina): ?>
							<span class="admin-pagination__link admin-pagination__link--atual" aria-current="page"><?= $numero ?></span>
						<?php else: ?>
							<a class="admin-pagination__link" href="<?= $escape($linkDaPagina($numero)) ?>"><?= $numero ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>

				<li>
					<?php if ($pagina < (int) $adminEstoque['paginas']): ?>
						<a class="admin-pagination__link" href="<?= $escape($linkDaPagina($pagina + 1)) ?>" rel="next">Próxima</a>
					<?php else: ?>
						<span class="admin-pagination__link admin-pagination__link--off">Próxima</span>
					<?php endif; ?>
				</li>
			</ul>
		<?php endif; ?>
	</nav>
<?php endif; ?>
