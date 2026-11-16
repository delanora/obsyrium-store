<?php

declare(strict_types=1);

/*
 * Estoque por variação, em leitura: nome do produto, tamanho, SKU, saldo e
 * quantas peças já estão reservadas. A situação (esgotado, baixo, ok) é
 * calculada no serviço; a cor do selo só reforça o texto.
 *
 * @var list<array<string, mixed>> $adminEstoque
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$situacoes = [
	'ok' => 'Disponível',
	'baixo' => 'Estoque baixo',
	'esgotado' => 'Esgotado',
];
?>
<header class="admin-head">
	<p class="admin-head__eyebrow">Painel</p>
	<h1 class="admin-head__title">Estoque</h1>
	<p class="admin-head__text">Saldo por tamanho. A edição de estoque entra com o cadastro de produtos no painel.</p>
</header>

<?php if ($adminEstoque === []): ?>
	<p class="admin-empty">Nenhuma variação cadastrada.</p>
<?php else: ?>
	<div class="admin-table-wrap">
		<table class="admin-table">
			<thead>
				<tr>
					<th scope="col">Produto</th>
					<th scope="col">Tamanho</th>
					<th scope="col">SKU</th>
					<th scope="col">Saldo</th>
					<th scope="col">Reservado</th>
					<th scope="col">Disponível</th>
					<th scope="col">Situação</th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ($adminEstoque as $linha): ?>
					<tr>
						<td><?= $escape((string) $linha['product']) ?></td>
						<td class="admin-table__muted"><?= $escape((string) $linha['size']) ?></td>
						<td class="admin-table__muted admin-table__sku"><?= $escape((string) $linha['sku']) ?></td>
						<td class="admin-table__muted"><?= (int) $linha['quantity'] ?></td>
						<td class="admin-table__muted"><?= (int) $linha['reserved'] ?></td>
						<td class="admin-table__total"><?= (int) $linha['available'] ?></td>
						<td>
							<span class="admin-status admin-status--estoque-<?= $escape((string) $linha['situation']) ?>"><?= $escape($situacoes[(string) $linha['situation']] ?? 'Disponível') ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
