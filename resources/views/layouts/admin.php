<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Layout do painel: barra no topo (marca e saída) e navegação lateral com
 * dashboard, pedidos e estoque. No celular a lateral vira uma faixa de abas
 * que rola na horizontal.
 *
 * @var string $content Caminho da view de conteúdo.
 * @var string|null $title Título do documento.
 * @var array<string, mixed>|null $adminAtual
 * @var string $adminAba 'dashboard', 'pedidos', 'estoque', 'categorias', 'contato' ou 'mensagens'
 * @var int $adminMensagensNaoLidas Quantidade exibida ao lado da aba Mensagens
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// Cada arquivo estático entra na página com a data de modificação na URL: mudou
// o arquivo, muda o endereço e o navegador não fica com CSS/JS velho no cache.
$asset = static function (string $caminho): string {
    $arquivo = dirname(__DIR__, 3) . '/public' . $caminho;
    $versao = is_file($arquivo) ? (string) filemtime($arquivo) : '';

    return $caminho . ($versao !== '' ? '?v=' . $versao : '');
};

$flash = App\Support\Session::takeFlash();

$adminAba = $adminAba ?? 'dashboard';
$adminMensagensNaoLidas = (int) ($adminMensagensNaoLidas ?? 0);

$adminItens = [
	'dashboard' => ['rotulo' => 'Dashboard', 'href' => '/admin'],
	'home' => ['rotulo' => 'Página inicial', 'href' => '/admin/pagina-inicial'],
	'pedidos' => ['rotulo' => 'Pedidos', 'href' => '/admin/pedidos'],
	'estoque' => ['rotulo' => 'Estoque', 'href' => '/admin/estoque'],
	'categorias' => ['rotulo' => 'Categorias', 'href' => '/admin/categorias'],
	'paginas' => ['rotulo' => 'Páginas', 'href' => '/admin/paginas'],
	'contato' => ['rotulo' => 'Contato', 'href' => '/admin/contato'],
	'mensagens' => ['rotulo' => 'Mensagens', 'href' => '/admin/mensagens'],
];
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $escape($title ?? 'Administração Obsyrium') ?></title>
	<meta name="robots" content="noindex, nofollow">
	<link rel="icon" href="/favicon.ico" sizes="any">
	<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16.png">
	<link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
	<link rel="stylesheet" href="<?= $asset('/assets/css/tokens.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/base.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/components.css') ?>">
	<link rel="stylesheet" href="<?= $asset('/assets/css/admin.css') ?>">
</head>
<body class="admin-body">
	<div class="admin-shell">
		<header class="admin-bar">
			<a class="admin-bar__brand" href="/admin">Obsyrium <span>Admin</span></a>

			<?php if ($adminAtual !== null): ?>
				<div class="admin-bar__user">
					<span class="admin-bar__name"><?= $escape((string) $adminAtual['name']) ?></span>

					<form action="/admin/logout" method="post">
						<?= Csrf::field() ?>
						<button class="admin-bar__out" type="submit">Sair</button>
					</form>
				</div>
			<?php endif; ?>
		</header>

		<?php if ($adminAtual !== null): ?>
			<aside class="admin-side">
				<nav class="admin-nav" aria-label="Seções do painel">
					<?php foreach ($adminItens as $chave => $item): ?>
						<a class="admin-nav__link" href="<?= $escape($item['href']) ?>"<?= $adminAba === $chave ? ' aria-current="page"' : '' ?>>
							<?= $escape($item['rotulo']) ?><?php if ($chave === 'mensagens' && $adminMensagensNaoLidas > 0): ?><span class="admin-nav__count"><?= $adminMensagensNaoLidas ?></span><?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<a class="admin-side__shop" href="/" rel="noopener">Ver loja</a>
			</aside>
		<?php endif; ?>

		<main class="admin-main" id="conteudo">
			<?php if ($flash !== null): ?>
				<p class="admin-flash admin-flash--<?= $escape($flash['type']) ?>" role="status"><?= $escape($flash['message']) ?></p>
			<?php endif; ?>

			<?php require $content; ?>
		</main>

		<script src="<?= $asset('/assets/js/admin.js') ?>" defer></script>
	</div>
</body>
</html>
