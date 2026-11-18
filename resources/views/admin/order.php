<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Detalhe do pedido no painel: itens, cliente, entrega, totais e a troca de
 * situação. As opções do formulário vêm do próprio serviço, então o painel não
 * oferece caminho que o servidor vá recusar.
 *
 * @var array<string, mixed> $adminPedido
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$cepLabel = static function (string $cep): string {
	$digitos = preg_replace('/\D+/', '', $cep) ?? '';

	return strlen($digitos) === 8 ? substr($digitos, 0, 5) . '-' . substr($digitos, 5) : $cep;
};

$endereco = $adminPedido['address'];
$podeCancelar = array_key_exists('cancelado', $adminPedido['next_statuses']);
?>
<header class="admin-head">
	<a class="admin-back" href="/admin/pedidos">← Pedidos</a>

	<p class="admin-head__eyebrow">Pedido</p>
	<h1 class="admin-head__title"><?= $escape((string) $adminPedido['number']) ?></h1>

	<p class="admin-head__text">
		<?= $escape((string) $adminPedido['date']) ?> · <?= (int) $adminPedido['pieces'] ?> <?= (int) $adminPedido['pieces'] === 1 ? 'peça' : 'peças' ?> ·
		<span class="admin-status admin-status--<?= $escape((string) $adminPedido['status']) ?>"><?= $escape((string) $adminPedido['status_label']) ?></span>
	</p>
</header>

<div class="admin-detail">
	<section class="admin-panel">
		<h2 class="admin-panel__title">Itens</h2>

		<ul class="admin-items">
			<?php foreach ($adminPedido['items'] as $item): ?>
				<?php $quantidade = (int) $item['quantity']; ?>

				<li class="admin-item">
					<span class="admin-item__name"><?= $escape((string) $item['name']) ?></span>
					<span class="admin-item__meta">Tamanho <?= $escape((string) $item['size']) ?> · <?= $quantidade ?> <?= $quantidade === 1 ? 'unidade' : 'unidades' ?> · <?= $escape((string) $item['price']) ?> cada</span>
					<span class="admin-item__price"><?= $escape((string) $item['line_total']) ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

		<dl class="admin-totals">
			<div class="admin-totals__row">
				<dt>Subtotal</dt>
				<dd><?= $escape((string) $adminPedido['subtotal']) ?></dd>
			</div>

			<div class="admin-totals__row">
				<dt>Frete</dt>
				<dd><?= $escape((string) $adminPedido['shipping']) ?></dd>
			</div>

			<?php if (($adminPedido['shipping_label'] ?? '') !== ''): ?>
				<div class="admin-totals__row">
					<dt>Entrega</dt>
					<dd><?= $escape((string) $adminPedido['shipping_label']) ?></dd>
				</div>
			<?php endif; ?>

			<div class="admin-totals__row admin-totals__row--total">
				<dt>Total</dt>
				<dd><?= $escape((string) $adminPedido['total']) ?></dd>
			</div>
		</dl>
	</section>

	<aside class="admin-side-columns">
		<section class="admin-panel">
			<h2 class="admin-panel__title">Situação</h2>

			<?php if ($adminPedido['next_statuses'] === []): ?>
				<p class="admin-note">Pedido finalizado — não há próxima etapa.</p>
			<?php else: ?>
				<form class="admin-status-form" method="post" action="/admin/pedidos/<?= $escape((string) $adminPedido['number']) ?>/status">
					<?= Csrf::field() ?>

					<div class="field">
						<label class="field__label" for="status">Nova situação</label>
						<select class="field__input" id="status" name="status">
							<?php foreach ($adminPedido['next_statuses'] as $chave => $rotulo): ?>
								<option value="<?= $escape($chave) ?>"><?= $escape($rotulo) ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<button class="button" type="submit">Salvar situação</button>
				</form>

				<?php if ($podeCancelar): ?>
					<p class="admin-note admin-note--warning">Cancelar devolve as peças deste pedido ao estoque.</p>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<section class="admin-panel">
			<h2 class="admin-panel__title">Rastreio</h2>

			<form class="admin-status-form" method="post" action="/admin/pedidos/<?= $escape((string) $adminPedido['number']) ?>/rastreio">
				<?= Csrf::field() ?>

				<div class="field">
					<label class="field__label" for="tracking_code">Código de rastreio</label>
					<input
						class="field__input"
						id="tracking_code"
						name="tracking_code"
						type="text"
						maxlength="60"
						autocomplete="off"
						value="<?= $escape((string) ($adminPedido['tracking_code'] ?? '')) ?>"
					>
					<p class="field__hint">Aparece na aba Compras do cliente. Deixe vazio para limpar.</p>
				</div>

				<button class="button" type="submit">Salvar código</button>
			</form>
		</section>

		<section class="admin-panel">
			<h2 class="admin-panel__title">Cliente</h2>
			<p class="admin-info">
				<?= $escape((string) $adminPedido['customer']['name']) ?><br>
				<span class="admin-info__muted"><?= $escape((string) $adminPedido['customer']['email']) ?></span>
			</p>
		</section>

		<section class="admin-panel">
			<h2 class="admin-panel__title">Entrega · <?= $escape((string) $endereco['label']) ?></h2>
			<p class="admin-info">
				<?= $escape((string) $endereco['recipient']) ?><br>
				<?= $escape((string) $endereco['street']) ?>, <?= $escape((string) $endereco['number']) ?><?= $endereco['complement'] !== '' ? ' — ' . $escape((string) $endereco['complement']) : '' ?><br>
				<?= $escape((string) $endereco['district']) ?> · <?= $escape((string) $endereco['city']) ?>/<?= $escape((string) $endereco['state']) ?><br>
				<span class="admin-info__muted">CEP <?= $escape($cepLabel((string) $endereco['postal_code'])) ?></span>
			</p>
		</section>
	</aside>
</div>
