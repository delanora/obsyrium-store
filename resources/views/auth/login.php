<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Login do cliente. A mensagem de erro é sempre genérica para não revelar
 * se o e-mail existe.
 *
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$valor = static fn (string $campo): string => $escape((string) ($old[$campo] ?? ''));
?>
<section class="auth">
	<div class="container auth__inner">
		<div class="auth__card">
			<p class="auth__eyebrow">Minha conta</p>
			<h1 class="auth__title">Entrar</h1>
			<p class="auth__text">Acesse para acompanhar pedidos e comprar mais rápido.</p>

			<?php if (isset($errors['credentials'])): ?>
				<p class="auth__alert" role="alert"><?= $escape($errors['credentials']) ?></p>
			<?php endif; ?>

			<?php require __DIR__ . '/../partials/social-login.php'; ?>

			<form class="auth__form" method="post" action="/login" novalidate>
				<?= Csrf::field() ?>

				<div class="field">
					<label class="field__label" for="email">E-mail</label>
					<input class="field__input" id="email" name="email" type="email" autocomplete="email" placeholder="seuemail@exemplo.com" required value="<?= $valor('email') ?>">
				</div>

				<div class="field">
					<label class="field__label" for="password">Senha</label>
					<input class="field__input" id="password" name="password" type="password" autocomplete="current-password" placeholder="Sua senha" required>
				</div>

				<button class="button auth__submit" type="submit">Entrar</button>
			</form>

			<p class="auth__switch">Ainda não tem conta? <a href="/cadastro">Criar conta</a></p>
		</div>
	</div>
</section>
