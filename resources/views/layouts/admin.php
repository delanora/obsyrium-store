<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Layout do painel de administração: barra própria, sem cabeçalho nem rodapé
 * da loja. Só entra aqui quem passou pelo login de administrador — o layout
 * apenas desenha a navegação.
 *
 * @var string $content Caminho da view de conteúdo.
 * @var string|null $title Título do documento.
 * @var array<string, mixed>|null $adminAtual
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$flash = App\Support\Session::takeFlash();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $escape($title ?? 'Administração Obsyrium') ?></title>
	<meta name="robots" content="noindex, nofollow">
	<link rel="stylesheet" href="/assets/css/tokens.css">
	<link rel="stylesheet" href="/assets/css/base.css">
	<link rel="stylesheet" href="/assets/css/components.css">
	<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
	<header class="admin-bar">
		<a class="admin-bar__brand" href="/admin/pedidos">Obsyrium <span>Admin</span></a>

		<?php if ($adminAtual !== null): ?>
			<nav class="admin-bar__nav" aria-label="Painel">
				<a class="admin-bar__link" href="/admin/pedidos" aria-current="page">Pedidos</a>
				<a class="admin-bar__link" href="/" rel="noopener">Ver loja</a>
			</nav>

			<div class="admin-bar__user">
				<span class="admin-bar__name"><?= $escape((string) $adminAtual['name']) ?></span>

				<form action="/admin/logout" method="post">
					<?= Csrf::field() ?>
					<button class="admin-bar__out" type="submit">Sair</button>
				</form>
			</div>
		<?php endif; ?>
	</header>

	<?php if ($flash !== null): ?>
		<p class="admin-flash admin-flash--<?= $escape($flash['type']) ?>" role="status"><?= $escape($flash['message']) ?></p>
	<?php endif; ?>

	<main class="admin-main" id="conteudo">
		<?php require $content; ?>
	</main>
</body>
</html>
