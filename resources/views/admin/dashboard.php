<?php

declare(strict_types=1);

/*
 * Dashboard do painel: cards numéricos de peças e clientes, barras de vendas
 * por mês e linha do faturamento mensal.
 *
 * Os gráficos são SVG montado aqui, sem biblioteca: alturas e pontos saem do
 * próprio dado e acompanham a largura do cartão (viewBox fixo).
 *
 * @var array<string, mixed> $adminResumo
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$meses = $adminResumo['meses'];
$totalMeses = max(1, count($meses));

$maxPedidos = 1;
$maxFaturamento = 1;

foreach ($meses as $mes) {
    $maxPedidos = max($maxPedidos, (int) $mes['pedidos']);
    $maxFaturamento = max($maxFaturamento, (int) $mes['faturamento']);
}

// Barras: 600 de largura útil, base em 170 e topo em 30.
$larguraBarra = 600 / $totalMeses;
$barras = [];

foreach ($meses as $indice => $mes) {
    $altura = ((int) $mes['pedidos'] / $maxPedidos) * 140;
    $x = $indice * $larguraBarra + $larguraBarra * 0.2;
    $largura = $larguraBarra * 0.6;

    $barras[] = [
        'x' => round($x, 1),
        'largura' => round($largura, 1),
        'y' => round(170 - max(2, $altura), 1),
        'altura' => round(max(2, $altura), 1),
        'centro' => round($x + $largura / 2, 1),
        'rotulo' => (string) $mes['rotulo'],
        'valor' => (int) $mes['pedidos'],
    ];
}

// Linha: pontos de 30 a 570, com 20 de folga no topo.
$passo = $totalMeses > 1 ? 540 / ($totalMeses - 1) : 0;
$pontos = [];

foreach ($meses as $indice => $mes) {
    $x = 30 + $indice * $passo;
    $y = 170 - ((int) $mes['faturamento'] / $maxFaturamento) * 150;

    $pontos[] = [
        'x' => round($x, 1),
        'y' => round($y, 1),
        'rotulo' => (string) $mes['rotulo'],
        'valor' => (string) $mes['faturamento_label'],
        'faturamento' => (int) $mes['faturamento'],
    ];
}

$linha = implode(' ', array_map(static fn (array $p): string => $p['x'] . ',' . $p['y'], $pontos));
$area = sprintf('30,170 %s 570,170', $linha);
?>
<header class="admin-head">
	<p class="admin-head__eyebrow">Painel</p>
	<h1 class="admin-head__title">Dashboard</h1>
	<p class="admin-head__text">Vendas dos últimos seis meses, peças e clientes. Pedido cancelado não entra na conta.</p>
</header>

<section class="admin-stats" aria-label="Números da loja">
	<article class="admin-stat">
		<p class="admin-stat__label">Peças vendidas</p>
		<p class="admin-stat__value"><?= (int) $adminResumo['pecas'] ?></p>
		<p class="admin-stat__note">Somando <?= (int) $adminResumo['pedidos'] ?> <?= (int) $adminResumo['pedidos'] === 1 ? 'pedido' : 'pedidos' ?></p>
	</article>

	<article class="admin-stat">
		<p class="admin-stat__label">Clientes</p>
		<p class="admin-stat__value"><?= (int) $adminResumo['clientes'] ?></p>
		<p class="admin-stat__note">Contas ativas na loja</p>
	</article>

	<article class="admin-stat admin-stat--wide">
		<p class="admin-stat__label">Faturamento acumulado</p>
		<p class="admin-stat__value admin-stat__value--money"><?= $escape((string) $adminResumo['faturamento_label']) ?></p>
		<p class="admin-stat__note">Sem frete e sem pedidos cancelados</p>
	</article>
</section>

<section class="admin-charts">
	<article class="admin-panel">
		<div class="admin-panel__head">
			<h2 class="admin-panel__title">Vendas por mês</h2>
			<p class="admin-panel__text">Pedidos fechados em cada mês.</p>
		</div>

		<svg class="chart" viewBox="0 0 600 210" role="img" aria-label="Pedidos fechados por mês nos últimos seis meses">
			<title>Pedidos fechados por mês</title>

			<line class="chart__axis" x1="0" y1="170" x2="600" y2="170"></line>

			<?php foreach ($barras as $barra): ?>
				<rect class="chart__bar" x="<?= $barra['x'] ?>" y="<?= $barra['y'] ?>" width="<?= $barra['largura'] ?>" height="<?= $barra['altura'] ?>" rx="3"></rect>
				<text class="chart__value" x="<?= $barra['centro'] ?>" y="<?= round(max(16, (float) $barra['y'] - 8), 1) ?>" text-anchor="middle"><?= $barra['valor'] ?></text>
				<text class="chart__label" x="<?= $barra['centro'] ?>" y="196" text-anchor="middle"><?= $escape($barra['rotulo']) ?></text>
			<?php endforeach; ?>
		</svg>
	</article>

	<article class="admin-panel">
		<div class="admin-panel__head">
			<h2 class="admin-panel__title">Faturamento mensal</h2>
			<p class="admin-panel__text">Crescimento da loja, mês a mês.</p>
		</div>

		<svg class="chart" viewBox="0 0 600 210" role="img" aria-label="Faturamento por mês nos últimos seis meses">
			<title>Faturamento por mês</title>

			<?php foreach ([170, 132.5, 95, 57.5, 20] as $y): ?>
				<line class="chart__grid" x1="0" y1="<?= $y ?>" x2="600" y2="<?= $y ?>"></line>
			<?php endforeach; ?>

			<polygon class="chart__area" points="<?= $area ?>"></polygon>
			<polyline class="chart__line" points="<?= $linha ?>"></polyline>

			<?php foreach ($pontos as $ponto): ?>
				<circle class="chart__dot" cx="<?= $ponto['x'] ?>" cy="<?= $ponto['y'] ?>" r="4"></circle>
				<text class="chart__label" x="<?= $ponto['x'] ?>" y="196" text-anchor="middle"><?= $escape($ponto['rotulo']) ?></text>
			<?php endforeach; ?>
		</svg>

		<ul class="chart-legend">
			<?php foreach ($pontos as $ponto): ?>
				<li class="chart-legend__item">
					<span class="chart-legend__month"><?= $escape($ponto['rotulo']) ?></span>
					<span class="chart-legend__value"><?= $escape($ponto['valor']) ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</article>
</section>
