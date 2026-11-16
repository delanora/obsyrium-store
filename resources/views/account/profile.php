<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Aba "Meus dados" do perfil do cliente.
 *
 * Nome e telefone são editáveis; e-mail e CPF aparecem só para conferência —
 * o CPF chega mascarado do controller e o e-mail é o login, que não muda por
 * aqui.
 *
 * @var array<string, mixed> $contaUsuario
 * @var string $contaAba
 * @var string $contaCpf
 * @var string $contaTelefone
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$valor = static fn (string $campo, string $padrao = ''): string => $escape((string) ($old[$campo] ?? $padrao));
$primeiroNome = explode(' ', trim((string) $contaUsuario['name']))[0];
?>
<section class="account">
	<div class="container account__inner">
		<header class="account__header">
			<p class="account__eyebrow">Minha conta</p>
			<h1 class="account__title">Meus dados</h1>
			<p class="account__text">Olá, <?= $escape($primeiroNome) ?>. Confira seus dados de contato e mantenha o telefone atualizado.</p>
		</header>

		<div class="account__layout">
			<?php require __DIR__ . '/../partials/account-nav.php'; ?>

			<div class="account__panel">
				<form class="account__form" method="post" action="/conta" novalidate>
					<?= Csrf::field() ?>

					<div class="field">
						<label class="field__label" for="name">Nome completo</label>
						<input class="field__input" id="name" name="name" type="text" autocomplete="name" required value="<?= $valor('name', (string) $contaUsuario['name']) ?>">
						<?php if (isset($errors['name'])): ?>
							<p class="field__error"><?= $escape($errors['name']) ?></p>
						<?php endif; ?>
					</div>

					<div class="field">
						<label class="field__label" for="phone">Telefone</label>
						<input class="field__input" id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="(11) 91234-5678" value="<?= $valor('phone', $contaTelefone) ?>">
						<p class="field__hint">Com DDD. Serve para avisos sobre a entrega e fica cifrado no banco.</p>
						<?php if (isset($errors['phone'])): ?>
							<p class="field__error"><?= $escape($errors['phone']) ?></p>
						<?php endif; ?>
					</div>

					<div class="account__info">
						<p class="account__info-item">
							<span class="account__info-label">E-mail de acesso</span>
							<strong class="account__info-value"><?= $escape((string) $contaUsuario['email']) ?></strong>
						</p>
						<p class="account__info-item">
							<span class="account__info-label">CPF</span>
							<strong class="account__info-value"><?= $escape($contaCpf) ?></strong>
						</p>
						<p class="account__info-note">O e-mail é o seu login e não muda por aqui.</p>
					</div>

					<div class="account__actions">
						<button class="button" type="submit">Salvar alterações</button>
						<a class="button button--ghost" href="/conta/enderecos">Meus endereços</a>
					</div>
				</form>
			</div>
		</div>
	</div>
</section>
