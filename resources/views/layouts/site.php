<?php

declare(strict_types=1);

/*
 * Layout base do site: documento, cabeçalho, conteúdo e rodapé.
 *
 * @var string $content Caminho da view de conteúdo.
 * @var string|null $title Título do documento.
 * @var string|null $metaDescription Descrição para o resultado de busca.
 * @var string|null $canonical Endereço canônico absoluto da página.
 * @var string|null $metaRobots `noindex` nas telas que não devem ser indexadas.
 * @var list<array<string, mixed>>|null $structuredData Blocos JSON-LD (schema.org).
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// Cada arquivo estático entra na página com a data de modificação na URL: mudou
// o arquivo, muda o endereço e o navegador não fica com CSS/JS velho no cache.
$asset = static function (string $caminho): string {
    $arquivo = dirname(__DIR__, 3) . '/public' . $caminho;
    $versao = is_file($arquivo) ? (string) filemtime($arquivo) : '';

    return $caminho . ($versao !== '' ? '?v=' . $versao : '');
};

// Lido antes do cabeçalho: o aviso de item adicionado aparece no próprio
// cabeçalho, junto do ícone do carrinho, e não como faixa no topo.
$flash = App\Support\Session::takeFlash();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $escape($title ?? 'Obsyrium Store') ?></title>
	<?php if (($metaDescription ?? '') !== ''): ?>
		<meta name="description" content="<?= $escape($metaDescription) ?>">
	<?php endif; ?>
	<?php if (($canonical ?? '') !== ''): ?>
		<link rel="canonical" href="<?= $escape($canonical) ?>">
	<?php endif; ?>
	<?php if (($metaRobots ?? '') !== ''): ?>
		<meta name="robots" content="<?= $escape($metaRobots) ?>">
	<?php endif; ?>
	<?php foreach (($structuredData ?? []) as $dados): ?>
		<script type="application/ld+json"><?= json_encode($dados, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
	<?php endforeach; ?>
	<link rel="icon" href="/favicon.ico" sizes="any">
	<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16.png">
	<link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
	<link rel="stylesheet" href="<?= $asset('/assets/css/tokens.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/base.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/components.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/layout.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/product.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/catalog.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/cart.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/account.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/checkout.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/pages.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/auth.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/animations.css') ?>">
	<script>
		document.documentElement.classList.add("js");
		window.addEventListener("load", function () {
			if (!window.__obsyriumReady) {
				document.documentElement.classList.remove("js");
			}
		});
	</script>
</head>
<body>
	<?php require __DIR__ . '/../partials/header.php'; ?>

	<?php require __DIR__ . '/../partials/cart-drawer.php'; ?>

	<?php if ($flash !== null && $flash['type'] !== 'cart'): ?>
		<div class="container">
			<p class="flash flash--<?= $escape($flash['type']) ?>" role="status"><?= $escape($flash['message']) ?></p>
		</div>
	<?php endif; ?>

	<main id="conteudo">
		<?php require $content; ?>
	</main>

	<?php require __DIR__ . '/../partials/footer.php'; ?>

	<script src="<?= $asset('/assets/js/site.js') ?>" defer></script>
</body>
</html>
