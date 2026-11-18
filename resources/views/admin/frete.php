<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Aba do frete: uma linha por estado com valor e prazo, mais as escolhas da
 * loja inteira — frete grátis a partir de um valor e retirada na loja.
 *
 * A tabela nasce zerada (entrega grátis, prazo a combinar); enquanto a loja
 * não preenche, o checkout continua funcionando com frete zero. Campo vazio
 * vale zero, então dá para deixar só o prazo preenchido.
 *
 * @var array<string, array{price_cents: int, min_days: int, max_days: int}> $adminTaxas
 * @var array{free_from: int, pickup: bool, pickup_text: string} $adminFreteConfig
 * @var list<string> $adminEstados
 * @var list<string> $adminErros
 * @var array<string, mixed> $adminValores
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$reais = static function (int $centavos): string {
	return $centavos > 0 ? number_format($centavos / 100, 2, ',', '') : '';
};

$taxas = $adminTaxas;
$valores = $adminValores;
$gratis = (string) ($valores['free_from'] ?? $reais((int) $adminFreteConfig['free_from']));
$retirada = (bool) ($valores['pickup'] ?? $adminFreteConfig['pickup']);
$retiradaTexto = (string) ($valores['pickup_text'] ?? $adminFreteConfig['pickup_text']);
$comPreco = 0;

foreach ($taxas as $taxa) {
	if ($taxa['price_cents'] > 0) {
		$comPreco++;
	}
}
?><p class="admin-crumb">
	<a href="/admin">Painel</a>
	<span aria-hidden="true">/</span>
	<span aria-current="page">Frete</span>
</p>

<header class="admin-head admin-head--row">
	<div>
		<p class="admin-head__eyebrow">Loja</p>
		<h1 class="admin-head__title">Frete e entrega</h1>
		<p class="admin-head__text">
			Valor e prazo por estado, frete grátis a partir de um valor e retirada na loja.
			<?= $comPreco > 0
				? sprintf('%d de %d estados com frete cobrado.', $comPreco, count($taxas))
				: 'Hoje todos os estados estão com frete grátis e prazo a combinar.' ?>
		</p>
	</div>

	<p class="admin-head__actions">
		<a class="admin-link-button" href="/carrinho" rel="noopener">Ver o carrinho</a>
	</p>
</header>

<?php if ($adminErros !== []): ?>
	<div class="admin-alert" role="alert">
		<p class="admin-alert__title">Confira o frete:</p>
		<ul class="admin-alert__list">
			<?php foreach ($adminErros as $erro): ?>
				<li><?= $escape($erro) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<form class="admin-frete" method="post" action="/admin/frete">
	<?= Csrf::field() ?>

	<section class="admin-panel" aria-labelledby="frete-regras">
		<h2 class="admin-panel__title" id="frete-regras">Regras da loja</h2>

		<div class="admin-frete__regras">
			<div class="field">
				<label class="field__label" for="free-from">Frete grátis a partir de</label>
				<input
					class="field__input"
					id="free-from"
					name="free_from"
					type="text"
					inputmode="decimal"
					autocomplete="off"
					placeholder="0,00"
					value="<?= $escape($gratis) ?>"
				>
				<p class="field__hint">Em reais. Deixe vazio ou zero para não ter frete grátis automático.</p>
			</div>

			<div class="field">
				<label class="field__label" for="pickup-text">Instruções de retirada</label>
				<input
					class="field__input"
					id="pickup-text"
					name="pickup_text"
					type="text"
					maxlength="160"
					autocomplete="off"
					value="<?= $escape($retiradaTexto) ?>"
				>
				<p class="field__hint">Aparece no checkout quando a retirada está liberada.</p>
			</div>
		</div>

		<label class="admin-check">
			<input type="checkbox" name="pickup" value="1"<?= $retirada ? ' checked' : '' ?>>
			<span>Oferecer retirada na loja (sem frete) no checkout</span>
		</label>
	</section>

	<section class="admin-panel" aria-labelledby="frete-tabela">
		<h2 class="admin-panel__title" id="frete-tabela">Valor e prazo por estado</h2>
		<p class="admin-note">Campo vazio é zero: frete grátis e prazo a combinar. O prazo é em dias úteis.</p>

		<div class="admin-frete__tabela">
			<div class="admin-frete__cabecalho" aria-hidden="true">
				<span>UF</span>
				<span>Frete (R$)</span>
				<span>Prazo mínimo</span>
				<span>Prazo máximo</span>
			</div>

			<?php foreach ($adminEstados as $uf): ?>
				<?php
				$taxa = $taxas[$uf] ?? ['price_cents' => 0, 'min_days' => 0, 'max_days' => 0];
				$preco = (string) ($valores['rates'][$uf]['price'] ?? $reais((int) $taxa['price_cents']));
				$minimo = (string) ($valores['rates'][$uf]['min'] ?? ((int) $taxa['min_days'] > 0 ? (string) $taxa['min_days'] : ''));
				$maximo = (string) ($valores['rates'][$uf]['max'] ?? ((int) $taxa['max_days'] > 0 ? (string) $taxa['max_days'] : ''));
				?>
				<div class="admin-frete__linha">
					<p class="admin-frete__uf"><?= $escape($uf) ?></p>

					<div class="field">
						<label class="visually-hidden" for="preco-<?= $escape($uf) ?>">Frete de <?= $escape($uf) ?></label>
						<input
							class="field__input"
							id="preco-<?= $escape($uf) ?>"
							name="price[<?= $escape($uf) ?>]"
							type="text"
							inputmode="decimal"
							autocomplete="off"
							placeholder="0,00"
							value="<?= $escape($preco) ?>"
						>
					</div>

					<div class="field">
						<label class="visually-hidden" for="min-<?= $escape($uf) ?>">Prazo mínimo de <?= $escape($uf) ?></label>
						<input
							class="field__input"
							id="min-<?= $escape($uf) ?>"
							name="min[<?= $escape($uf) ?>]"
							type="text"
							inputmode="numeric"
							pattern="[0-9]*"
							maxlength="2"
							autocomplete="off"
							placeholder="0"
							value="<?= $escape($minimo) ?>"
						>
					</div>

					<div class="field">
						<label class="visually-hidden" for="max-<?= $escape($uf) ?>">Prazo máximo de <?= $escape($uf) ?></label>
						<input
							class="field__input"
							id="max-<?= $escape($uf) ?>"
							name="max[<?= $escape($uf) ?>]"
							type="text"
							inputmode="numeric"
							pattern="[0-9]*"
							maxlength="2"
							autocomplete="off"
							placeholder="0"
							value="<?= $escape($maximo) ?>"
						>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<p class="admin-frete__salvar">
		<button class="button" type="submit">Salvar frete</button>
	</p>
</form>
