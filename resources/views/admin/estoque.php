<?php

declare(strict_types=1);

use App\Services\StockService;
use App\Support\Csrf;

/*
 * Estoque por variação, editável: cada linha traz o saldo num campo com o
 * botão de salvar. O envio manda também o saldo que estava na tela, para o
 * servidor recusar se alguém comprou nesse meio-tempo.
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
	<p class="admin-head__text">Digite o saldo novo de cada tamanho e salve. O valor nunca fica abaixo das peças reservadas.</p>
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
					<th scope="col">Reservado</th>
					<th scope="col">Saldo</th>
					<th scope="col">Disponível</th>
					<th scope="col">Situação</th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ($adminEstoque as $linha): ?>
					<?php $campoId = 'saldo-' . (int) $linha['variant_id']; ?>

					<tr>
						<td><?= $escape((string) $linha['product']) ?></td>
						<td class="admin-table__muted"><?= $escape((string) $linha['size']) ?></td>

						<td class="admin-table__muted admin-table__sku">
							<?= $escape((string) $linha['sku']) ?>
							<span class="admin-visually-hidden">, <?= $escape((string) $linha['product']) ?> tamanho <?= $escape((string) $linha['size']) ?></span>
						</td>

						<td class="admin-table__muted"><?= (int) $linha['reserved'] ?></td>

						<td>
							<form class="stock-form" method="post" action="/admin/estoque/saldo">
								<?= Csrf::field() ?>
								<input type="hidden" name="variant_id" value="<?= (int) $linha['variant_id'] ?>">
								<input type="hidden" name="atual" value="<?= (int) $linha['quantity'] ?>">

								<label class="admin-visually-hidden" for="<?= $escape($campoId) ?>">Saldo de <?= $escape((string) $linha['product']) ?> tamanho <?= $escape((string) $linha['size']) ?></label>
								<input class="stock-form__input" id="<?= $escape($campoId) ?>" name="quantity" type="number" min="0" max="<?= StockService::MAX_QUANTITY ?>" step="1" inputmode="numeric" value="<?= (int) $linha['quantity'] ?>" required>

								<button class="stock-form__save" type="submit">Salvar</button>
							</form>
						</td>

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
