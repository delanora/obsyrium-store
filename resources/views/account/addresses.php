<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Aba "Endereços" do perfil do cliente.
 *
 * A lista chega de AddressRepository já decifrada. O formulário serve para
 * cadastrar e para editar: quando $contaEdicao não é null ele abre preenchido e
 * manda o address_id junto, e o controller confere o dono antes de gravar.
 *
 * @var array<string, mixed> $contaUsuario
 * @var string $contaAba
 * @var array<int, array<string, mixed>> $contaEnderecos
 * @var array<int, string> $contaUfs
 * @var array<string, mixed>|null $contaEdicao
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$emEdicao = $contaEdicao !== null;

$valor = static function (string $campo) use ($old, $contaEdicao, $escape): string {
	if (isset($old[$campo])) {
		return $escape((string) $old[$campo]);
	}

	return $escape((string) ($contaEdicao[$campo] ?? ''));
};

$ufSelecionada = strtoupper((string) ($old['state'] ?? ($contaEdicao['state'] ?? '')));

$marcadoPrincipal = $old !== []
	? isset($old['is_default'])
	: (($contaEdicao['is_default'] ?? false) || $contaEnderecos === []);

$cepLabel = static function (string $cep): string {
	$digitos = preg_replace('/\D+/', '', $cep) ?? '';

	return strlen($digitos) === 8 ? substr($digitos, 0, 5) . '-' . substr($digitos, 5) : $cep;
};
?>
<section class="account">
	<div class="container account__inner">
		<header class="account__header">
			<p class="account__eyebrow">Minha conta</p>
			<h1 class="account__title">Endereços</h1>
			<p class="account__text">Guarde aqui seus endereços de entrega. O principal é o primeiro a aparecer no checkout.</p>
		</header>

		<div class="account__layout">
			<?php require __DIR__ . '/../partials/account-nav.php'; ?>

			<div class="account__panel">
				<section class="account__section">
					<h2 class="account__section-title">Endereços salvos</h2>

					<?php if ($contaEnderecos === []): ?>
						<p class="account__empty-text">Você ainda não tem endereço cadastrado. Use o formulário abaixo para guardar o primeiro.</p>
					<?php else: ?>
						<ul class="address-list">
							<?php foreach ($contaEnderecos as $endereco): ?>
								<li class="address-card<?= $endereco['is_default'] ? ' address-card--principal' : '' ?>">
									<div class="address-card__top">
										<h3 class="address-card__label"><?= $escape((string) $endereco['label']) ?></h3>
										<?php if ($endereco['is_default']): ?>
											<span class="address-card__badge">Principal</span>
										<?php endif; ?>
									</div>

									<p class="address-card__text">
										<?= $escape((string) $endereco['recipient']) ?><br>
										<?= $escape((string) $endereco['street']) ?>, <?= $escape((string) $endereco['number']) ?><?= $endereco['complement'] !== '' ? ' — ' . $escape((string) $endereco['complement']) : '' ?><br>
										<?= $escape((string) $endereco['district']) ?> · <?= $escape((string) $endereco['city']) ?>/<?= $escape((string) $endereco['state']) ?><br>
										CEP <?= $escape($cepLabel((string) $endereco['postal_code'])) ?>
									</p>

									<div class="address-card__actions">
										<?php if (!$endereco['is_default']): ?>
											<form action="/conta/enderecos/padrao" method="post">
												<?= Csrf::field() ?>
												<input type="hidden" name="address_id" value="<?= (int) $endereco['id'] ?>">
												<button class="link-button" type="submit">Tornar principal</button>
											</form>
										<?php endif; ?>

										<a class="link-button" href="/conta/enderecos/<?= (int) $endereco['id'] ?>">Editar</a>

										<form action="/conta/enderecos/remover" method="post">
											<?= Csrf::field() ?>
											<input type="hidden" name="address_id" value="<?= (int) $endereco['id'] ?>">
											<button class="link-button link-button--danger" type="submit">Remover</button>
										</form>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>

				<section class="account__section account__section--form">
					<h2 class="account__section-title"><?= $emEdicao ? 'Editar endereço' : 'Adicionar endereço' ?></h2>

					<form class="account__form" method="post" action="/conta/enderecos/salvar" novalidate>
						<?= Csrf::field() ?>

						<?php if ($emEdicao): ?>
							<input type="hidden" name="address_id" value="<?= (int) $contaEdicao['id'] ?>">
						<?php endif; ?>

						<?php if (isset($errors['address'])): ?>
							<p class="field__error" role="alert"><?= $escape($errors['address']) ?></p>
						<?php endif; ?>

						<div class="field">
							<label class="field__label" for="label">Nome do endereço</label>
							<input class="field__input" id="label" name="label" type="text" maxlength="40" placeholder="Casa, Trabalho…" value="<?= $valor('label') ?>">
							<?php if (isset($errors['label'])): ?>
								<p class="field__error"><?= $escape($errors['label']) ?></p>
							<?php endif; ?>
						</div>

						<div class="field">
							<label class="field__label" for="recipient">Quem recebe</label>
							<input class="field__input" id="recipient" name="recipient" type="text" autocomplete="name" required value="<?= $valor('recipient') ?>">
							<?php if (isset($errors['recipient'])): ?>
								<p class="field__error"><?= $escape($errors['recipient']) ?></p>
							<?php endif; ?>
						</div>

						<div class="field">
							<label class="field__label" for="postal_code">CEP</label>
							<input class="field__input" id="postal_code" name="postal_code" type="text" inputmode="numeric" autocomplete="postal-code" maxlength="9" placeholder="01234-567" required value="<?= $valor('postal_code') ?>">
							<?php if (isset($errors['postal_code'])): ?>
								<p class="field__error"><?= $escape($errors['postal_code']) ?></p>
							<?php endif; ?>
						</div>

						<div class="field">
							<label class="field__label" for="street">Rua</label>
							<input class="field__input" id="street" name="street" type="text" autocomplete="address-line1" required value="<?= $valor('street') ?>">
							<?php if (isset($errors['street'])): ?>
								<p class="field__error"><?= $escape($errors['street']) ?></p>
							<?php endif; ?>
						</div>

						<div class="account__row">
							<div class="field">
								<label class="field__label" for="number">Número</label>
								<input class="field__input" id="number" name="number" type="text" maxlength="20" required value="<?= $valor('number') ?>">
								<?php if (isset($errors['number'])): ?>
									<p class="field__error"><?= $escape($errors['number']) ?></p>
								<?php endif; ?>
							</div>

							<div class="field">
								<label class="field__label" for="complement">Complemento</label>
								<input class="field__input" id="complement" name="complement" type="text" maxlength="120" placeholder="Apto, bloco" value="<?= $valor('complement') ?>">
								<?php if (isset($errors['complement'])): ?>
									<p class="field__error"><?= $escape($errors['complement']) ?></p>
								<?php endif; ?>
							</div>
						</div>

						<div class="field">
							<label class="field__label" for="district">Bairro</label>
							<input class="field__input" id="district" name="district" type="text" required value="<?= $valor('district') ?>">
							<?php if (isset($errors['district'])): ?>
								<p class="field__error"><?= $escape($errors['district']) ?></p>
							<?php endif; ?>
						</div>

						<div class="account__row">
							<div class="field">
								<label class="field__label" for="city">Cidade</label>
								<input class="field__input" id="city" name="city" type="text" autocomplete="address-level2" required value="<?= $valor('city') ?>">
								<?php if (isset($errors['city'])): ?>
									<p class="field__error"><?= $escape($errors['city']) ?></p>
								<?php endif; ?>
							</div>

							<div class="field">
								<label class="field__label" for="state">UF</label>
								<select class="field__input" id="state" name="state" required>
									<option value="">—</option>
									<?php foreach ($contaUfs as $uf): ?>
										<option value="<?= $escape($uf) ?>"<?= $ufSelecionada === $uf ? ' selected' : '' ?>><?= $escape($uf) ?></option>
									<?php endforeach; ?>
								</select>
								<?php if (isset($errors['state'])): ?>
									<p class="field__error"><?= $escape($errors['state']) ?></p>
								<?php endif; ?>
							</div>
						</div>

						<div class="field field--check">
							<input class="field__check" id="is_default" name="is_default" type="checkbox" value="1"<?= $marcadoPrincipal ? ' checked' : '' ?>>
							<label class="field__check-label" for="is_default">Usar como endereço principal</label>
						</div>

						<div class="account__actions">
							<button class="button" type="submit"><?= $emEdicao ? 'Salvar endereço' : 'Cadastrar endereço' ?></button>
							<?php if ($emEdicao): ?>
								<a class="button button--ghost" href="/conta/enderecos">Cancelar</a>
							<?php endif; ?>
						</div>
					</form>
				</section>
			</div>
		</div>
	</div>
</section>
