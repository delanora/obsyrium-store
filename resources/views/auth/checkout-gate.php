<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Porta do checkout: o visitante que clica em "Finalizar compra" cai aqui, sem
 * mensagem de erro, com o login e o cadastro lado a lado. Os dois formulários
 * voltam com `?compra=1` para, depois de entrar, irem direto ao checkout.
 *
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$valor = static fn (string $campo): string => $escape((string) ($old[$campo] ?? ''));

// Depois de uma tentativa recusada o cadastro já abre inteiro, para o cliente
// ver o campo que precisa corrigir em vez de reabrir o formulário na mão.
$camposAbertos = array_diff(array_keys($errors), ['credentials']) !== [];
?>
<section class="auth auth--gate">
	<div class="container auth__inner">
		<header class="auth-gate__head">
			<p class="auth__eyebrow">Finalizar compra</p>
			<h1 class="auth-gate__title">Para prosseguir com a compra você deve fazer o login ou cadastrar-se</h1>
		</header>

		<div class="auth-gate__cards">
			<article class="auth__card auth-gate__card auth-gate__card--login">
				<p class="auth__eyebrow">Já compro aqui</p>
				<h2 class="auth-gate__card-title">Entrar</h2>
				<p class="auth__text">Use o e-mail e a senha da sua conta para ir direto ao checkout.</p>

				<?php if (isset($errors['credentials'])): ?>
					<p class="auth__alert" role="alert"><?= $escape($errors['credentials']) ?></p>
				<?php endif; ?>

				<?php
				$socialSuffix = '-gate-login';
				$socialCompact = true;
				require __DIR__ . '/../partials/social-login.php';
				?>

				<form class="auth__form" method="post" action="/login?compra=1" novalidate>
					<?= Csrf::field() ?>

					<div class="field">
						<label class="field__label" for="gate-login-email">E-mail</label>
						<input class="field__input" id="gate-login-email" name="email" type="email" autocomplete="email" placeholder="seuemail@exemplo.com" required value="<?= $valor('email') ?>">
					</div>

					<div class="field">
						<label class="field__label" for="gate-login-password">Senha</label>
						<input class="field__input" id="gate-login-password" name="password" type="password" autocomplete="current-password" placeholder="Sua senha" required>
					</div>

					<button class="button auth__submit" type="submit">Entrar e continuar</button>
				</form>
			</article>

			<article class="auth__card auth-gate__card auth-gate__card--register">
				<p class="auth__eyebrow">Primeira compra</p>
				<h2 class="auth-gate__card-title">Criar conta</h2>
				<p class="auth__text">Leva um minuto. CPF e telefone ficam cifrados e os dados servem para as próximas compras.</p>

				<?php
				$socialSuffix = '-gate-register';
				$socialCompact = true;
				require __DIR__ . '/../partials/social-login.php';
				?>

				<form class="auth__form" method="post" action="/cadastro?compra=1" novalidate>
					<?= Csrf::field() ?>

					<div class="field auth-gate__field--full">
						<label class="field__label" for="gate-name">Nome completo</label>
						<input class="field__input" id="gate-name" name="name" type="text" autocomplete="name" placeholder="Nome e sobrenome" required value="<?= $valor('name') ?>">
						<?php if (isset($errors['name'])): ?>
							<p class="field__error"><?= $escape($errors['name']) ?></p>
						<?php endif; ?>
					</div>

					<div class="auth-gate__more<?= $camposAbertos ? ' is-open' : '' ?>" data-gate-more<?= $camposAbertos ? ' data-open="1"' : '' ?>>
					<div class="field">
						<label class="field__label" for="gate-cpf">CPF</label>
						<input class="field__input" id="gate-cpf" name="cpf" type="text" inputmode="numeric" autocomplete="off" maxlength="14" placeholder="000.000.000-00" data-cpf-mask required value="<?= $valor('cpf') ?>">
						<?php if (isset($errors['cpf'])): ?>
							<p class="field__error"><?= $escape($errors['cpf']) ?></p>
						<?php endif; ?>
					</div>

					<div class="field">
						<label class="field__label" for="gate-phone">Telefone com DDD</label>
						<input class="field__input" id="gate-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="15" placeholder="(11) 98765-4321" data-phone-mask required value="<?= $valor('phone') ?>">
						<?php if (isset($errors['phone'])): ?>
							<p class="field__error"><?= $escape($errors['phone']) ?></p>
						<?php endif; ?>
					</div>

					<div class="field auth-gate__field--full">
						<label class="field__label" for="gate-email">E-mail</label>
						<input class="field__input" id="gate-email" name="email" type="email" autocomplete="email" placeholder="seuemail@exemplo.com" required value="<?= $valor('email') ?>">
						<?php if (isset($errors['email'])): ?>
							<p class="field__error"><?= $escape($errors['email']) ?></p>
						<?php endif; ?>
					</div>

					<div class="field">
						<label class="field__label" for="gate-password">Senha</label>
						<input class="field__input" id="gate-password" name="password" type="password" autocomplete="new-password" placeholder="Sua senha" minlength="8" required>
						<?php if (isset($errors['password'])): ?>
							<p class="field__error"><?= $escape($errors['password']) ?></p>
						<?php endif; ?>
					</div>

					<div class="field">
						<label class="field__label" for="gate-password-confirmation">Repita a senha</label>
						<input class="field__input" id="gate-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repita a senha" minlength="8" required>
						<?php if (isset($errors['password_confirmation'])): ?>
							<p class="field__error"><?= $escape($errors['password_confirmation']) ?></p>
						<?php endif; ?>
					</div>

					<?php if (!isset($errors['password'])): ?>
						<p class="field__hint auth-gate__field--full">Mínimo de 8 caracteres.</p>
					<?php endif; ?>
					</div>

					<div class="field field--check auth-gate__field--full">
						<input class="field__check" id="gate-terms" name="terms" type="checkbox" value="1"<?= $valor('terms') !== '' ? ' checked' : '' ?>>
						<label class="field__check-label" for="gate-terms">Li e aceito os <a href="/termos">termos de uso</a> e a <a href="/privacidade">política de privacidade</a> da Obsyrium Store.</label>
					</div>
					<?php if (isset($errors['terms'])): ?>
						<p class="field__error auth-gate__field--full"><?= $escape($errors['terms']) ?></p>
					<?php endif; ?>

					<button class="button auth__submit" type="submit">Criar conta e continuar</button>
				</form>
			</article>
		</div>
	</div>
</section>
