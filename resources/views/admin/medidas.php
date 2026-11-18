<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Guia de medidas: uma linha por tamanho, com largura e comprimento em
 * centímetros. As medidas são da peça real — o formulário nasce vazio e a loja
 * só mostra o guia na página do produto quando houver alguma medida salva.
 *
 * @var list<array<string, mixed>> $adminMedidas
 * @var list<string> $adminErros
 * @var array<int, array{chest: string, length: string}> $adminValores
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$valores = $adminValores;
$preenchidas = 0;

foreach ($adminMedidas as $medida) {
	if ($medida['chest'] !== null || $medida['length'] !== null) {
		$preenchidas++;
	}
}
?>
<p class="admin-crumb">
	<a href="/admin">Painel</a>
	<span aria-hidden="true">/</span>
	<span aria-current="page">Guia de medidas</span>
</p>

<header class="admin-head admin-head--row">
	<div>
		<p class="admin-head__eyebrow">Conteúdo</p>
		<h1 class="admin-head__title">Guia de medidas</h1>
		<p class="admin-head__text">
			Largura e comprimento de cada tamanho, em centímetros.
			<?= $preenchidas > 0 ? 'O guia aparece na página do produto.' : 'Enquanto nenhuma medida estiver preenchida, o guia não aparece na loja.' ?>
		</p>
	</div>

	<p class="admin-head__actions">
		<a class="admin-link-button" href="/catalogo" rel="noopener">Ver a loja</a>
	</p>
</header>

<?php if ($adminErros !== []): ?>
	<div class="admin-alert" role="alert">
		<p class="admin-alert__title">Confira as medidas:</p>
		<ul class="admin-alert__list">
			<?php foreach ($adminErros as $erro): ?>
				<li><?= $escape($erro) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<section class="admin-guide" aria-labelledby="guia-form-titulo">
	<h2 class="admin-product__subtitle" id="guia-form-titulo">Medidas da camiseta</h2>

	<form class="admin-guide__form" method="post" action="/admin/guia-de-medidas">
		<?= Csrf::field() ?>

		<?php foreach ($adminMedidas as $medida): ?>
			<?php
			$id = (int) $medida['id'];
			$largura = $valores[$id]['chest'] ?? ($medida['chest'] !== null ? (string) $medida['chest'] : '');
			$comprimento = $valores[$id]['length'] ?? ($medida['length'] !== null ? (string) $medida['length'] : '');
			?>
			<div class="admin-guide__linha">
				<p class="admin-guide__tamanho"><?= $escape((string) $medida['label']) ?></p>

				<div class="field">
					<label class="field__label" for="chest-<?= $id ?>">Largura (cm)</label>
					<input
						class="field__input"
						id="chest-<?= $id ?>"
						name="chest[<?= $id ?>]"
						type="text"
						inputmode="numeric"
						pattern="[0-9]*"
						maxlength="3"
						autocomplete="off"
						value="<?= $escape($largura) ?>"
					>
				</div>

				<div class="field">
					<label class="field__label" for="length-<?= $id ?>">Comprimento (cm)</label>
					<input
						class="field__input"
						id="length-<?= $id ?>"
						name="length[<?= $id ?>]"
						type="text"
						inputmode="numeric"
						pattern="[0-9]*"
						maxlength="3"
						autocomplete="off"
						value="<?= $escape($comprimento) ?>"
					>
				</div>
			</div>
		<?php endforeach; ?>

		<p class="field__hint">Medida da peça estendida na mesa, com tolerância de 2 cm. Largura de uma axila à outra; comprimento do ombro até a barra. Pode preencher só a largura, só o comprimento ou as duas.</p>

		<p class="admin-guide__salvar">
			<button class="button" type="submit">Salvar medidas</button>
		</p>
	</form>
</section>
