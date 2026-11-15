<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Cadastro do cliente. Nome, CPF, telefone e e-mail são validados no
 * AuthService; o CPF e o telefone são cifrados antes de chegar ao banco.
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
			<h1 class="auth__title">Criar conta</h1>
			<p class="auth__text">Seus dados ficam guardados para as próximas compras. CPF e telefone são cifrados.</p>

			<?php require __DIR__ . '/../partials/social-login.php'; ?>

			<form class="auth__form" method="post" action="/cadastro" novalidate>
				<?= Csrf::field() ?>

				<div class="field">
					<label class="field__label" for="name">Nome completo</label>
					<input class="field__input" id="name" name="name" type="text" autocomplete="name" required value="<?= $valor('name') ?>">
					<?php if (isset($errors['name'])): ?>
						<p class="field__error"><?= $escape($errors['name']) ?></p>
					<?php endif; ?>
				</div>

				<div class="field">
					<label class="field__label" for="cpf">CPF</label>
					<input class="field__input" id="cpf" name="cpf" type="text" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" required value="<?= $valor('cpf') ?>">
					<?php if (isset($errors['cpf'])): ?>
						<p class="field__error"><?= $escape($errors['cpf']) ?></p>
					<?php endif; ?>
				</div>

				<div class="field">
					<label class="field__label" for="phone">Telefone com DDD</label>
					<input class="field__input" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="(11) 98765-4321" required value="<?= $valor('phone') ?>">
					<?php if (isset($errors['phone'])): ?>
						<p class="field__error"><?= $escape($errors['phone']) ?></p>
					<?php endif; ?>
				</div>

				<div class="field">
					<label class="field__label" for="email">E-mail</label>
					<input class="field__input" id="email" name="email" type="email" autocomplete="email" required value="<?= $valor('email') ?>">
					<?php if (isset($errors['email'])): ?>
						<p class="field__error"><?= $escape($errors['email']) ?></p>
					<?php endif; ?>
				</div>

				<div class="field">
					<label class="field__label" for="password">Senha</label>
					<input class="field__input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
					<?php if (isset($errors['password'])): ?>
						<p class="field__error"><?= $escape($errors['password']) ?></p>
					<?php else: ?>
						<p class="field__hint">Mínimo de 8 caracteres.</p>
					<?php endif; ?>
				</div>

				<div class="field">
					<label class="field__label" for="password_confirmation">Repita a senha</label>
					<input class="field__input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
					<?php if (isset($errors['password_confirmation'])): ?>
						<p class="field__error"><?= $escape($errors['password_confirmation']) ?></p>
					<?php endif; ?>
				</div>

				<div class="field field--check">
					<input class="field__check" id="terms" name="terms" type="checkbox" value="1"<?= $valor('terms') !== '' ? ' checked' : '' ?>>
					<label class="field__check-label" for="terms">Li e aceito os termos de uso e a política de privacidade da Obsyrium Store.</label>
				</div>
				<?php if (isset($errors['terms'])): ?>
					<p class="field__error"><?= $escape($errors['terms']) ?></p>
				<?php endif; ?>

				<button class="button auth__submit" type="submit">Criar conta</button>
			</form>

			<p class="auth__switch">Já tem conta? <a href="/login">Entrar</a></p>
		</div>
	</div>
</section>
