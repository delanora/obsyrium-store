<?php

declare(strict_types=1);

/*
 * Layout base do site: documento, cabeçalho, conteúdo e rodapé.
 *
 * @var string $content Caminho da view de conteúdo.
 * @var string|null $title Título do documento.
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// Lido antes do cabeçalho: o aviso de item adicionado aparece no próprio
// cabeçalho, junto do ícone do carrinho, e não como faixa no topo.
$flash = App\Support\Session::takeFlash();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $escape($title ?? 'Obsyrium Store') ?></title>
	<link rel="stylesheet" href="/assets/css/tokens.css">
	<link rel="stylesheet" href="/assets/css/base.css">
	<link rel="stylesheet" href="/assets/css/components.css">
	<link rel="stylesheet" href="/assets/css/layout.css">
	<link rel="stylesheet" href="/assets/css/product.css">
	<link rel="stylesheet" href="/assets/css/cart.css">
	<link rel="stylesheet" href="/assets/css/account.css">
	<link rel="stylesheet" href="/assets/css/checkout.css">
	<link rel="stylesheet" href="/assets/css/auth.css">
	<link rel="stylesheet" href="/assets/css/animations.css">
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

	<script src="/assets/js/site.js" defer></script>
</body>
</html>
