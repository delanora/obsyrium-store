<?php

declare(strict_types=1);

use App\Services\OrderService;

/*
 * Lista de pedidos do painel, com filtro por situação.
 *
 * @var list<array<string, mixed>> $adminPedidos
 * @var string $adminFiltro
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<header class="admin-head">
	<div>
		<p class="admin-head__eyebrow">Painel</p>
		<h1 class="admin-head__title">Pedidos</h1>
		<p class="admin-head__text">Últimos 100 pedidos da loja. Abra um pedido para mudar a situação — cancelar devolve as peças ao estoque.</p>
	</div>
</header>

<nav class="admin-filters" aria-label="Filtrar por situação">
	<a class="admin-filter<?= $adminFiltro === '' ? ' admin-filter--ativo' : '' ?>" href="/admin/pedidos"<?= $adminFiltro === '' ? ' aria-current="true"' : '' ?>>Todos</a>

	<?php foreach (OrderService::STATUS_LABELS as $chave => $rotulo): ?>
		<a class="admin-filter<?= $adminFiltro === $chave ? ' admin-filter--ativo' : '' ?>" href="/admin/pedidos?status=<?= $escape($chave) ?>"<?= $adminFiltro === $chave ? ' aria-current="true"' : '' ?>><?= $escape($rotulo) ?></a>
	<?php endforeach; ?>
</nav>

<?php if ($adminPedidos === []): ?>
	<p class="admin-empty"><?= $adminFiltro === '' ? 'Nenhum pedido ainda.' : 'Nenhum pedido nesta situação.' ?></p>
<?php else: ?>
	<div class="admin-table-wrap admin-table-wrap--cards">
		<table class="admin-table admin-table--pedidos">
			<thead>
				<tr>
					<th scope="col">Pedido</th>
					<th scope="col">Cliente</th>
					<th scope="col">Data</th>
					<th scope="col">Peças</th>
					<th scope="col">Total</th>
					<th scope="col">Situação</th>
					<th scope="col"><span class="admin-visually-hidden">Abrir</span></th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ($adminPedidos as $pedido): ?>
					<tr>
						<td class="admin-table__number">
							<a href="/admin/pedidos/<?= $escape((string) $pedido['number']) ?>"><?= $escape((string) $pedido['number']) ?></a>
						</td>
						<td>
							<?= $escape((string) $pedido['customer']['name']) ?>
							<span class="admin-table__meta"><?= $escape((string) $pedido['customer']['email']) ?></span>
						</td>
						<td class="admin-table__muted"><?= $escape((string) $pedido['date']) ?></td>
						<td class="admin-table__muted"><?= (int) $pedido['pieces'] ?></td>
						<td class="admin-table__total"><?= $escape((string) $pedido['total']) ?></td>
						<td>
							<span class="admin-status admin-status--<?= $escape((string) $pedido['status']) ?>"><?= $escape((string) $pedido['status_label']) ?></span>
						</td>
						<td>
							<a class="admin-table__open" href="/admin/pedidos/<?= $escape((string) $pedido['number']) ?>">Abrir</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
