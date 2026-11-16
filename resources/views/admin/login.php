<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Login da administração. A mensagem de erro é genérica para não revelar se o
 * e-mail existe.
 *
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$valor = static fn (string $campo): string => $escape((string) ($old[$campo] ?? ''));
?>
<section class="admin-login">
	<div class="admin-login__card">
		<p class="admin-login__eyebrow">Administração</p>
		<h1 class="admin-login__title">Entrar no painel</h1>
		<p class="admin-login__text">Acesso restrito à equipe da loja.</p>

		<?php if (isset($errors['credentials'])): ?>
			<p class="admin-login__alert" role="alert"><?= $escape($errors['credentials']) ?></p>
		<?php endif; ?>

		<form class="admin-login__form" method="post" action="/admin/login" novalidate>
			<?= Csrf::field() ?>

			<div class="field">
				<label class="field__label" for="email">E-mail</label>
				<input class="field__input" id="email" name="email" type="email" autocomplete="username" required value="<?= $valor('email') ?>">
			</div>

			<div class="field">
				<label class="field__label" for="password">Senha</label>
				<input class="field__input" id="password" name="password" type="password" autocomplete="current-password" required>
			</div>

			<button class="button" type="submit">Entrar</button>
		</form>
	</div>
</section>
