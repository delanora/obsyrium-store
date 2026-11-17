<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Contato no painel: os dois canais que a página pública exibe e as mensagens
 * que chegaram pelo formulário.
 *
 * O WhatsApp é gravado só com dígitos (país + DDD + número) e o Instagram sem o
 * @; a página pública monta o link e o rótulo a partir disso.
 *
 * @var array{whatsapp: string, instagram: string} $adminContato
 * @var list<array{id: int, name: string, email: string, message: string, created_at: string}> $adminMensagens
 * @var list<string> $adminErros
 * @var array<string, string> $adminValores
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$data = static function (string $valor): string {
    $quando = strtotime($valor);

    return $quando === false ? $valor : date('d/m/Y H:i', $quando);
};

$whatsapp = (string) ($adminValores['whatsapp'] ?? $adminContato['whatsapp'] ?? '');
$instagram = (string) ($adminValores['instagram'] ?? $adminContato['instagram'] ?? '');
?>
<p class="admin-crumb">
	<a href="/admin">Dashboard</a>
	<span aria-hidden="true">/</span>
	<span aria-current="page">Contato</span>
</p>

<header class="admin-head">
	<div>
		<p class="admin-head__eyebrow">Painel</p>
		<h1 class="admin-head__title">Contato</h1>
		<p class="admin-head__text">Canais exibidos na página Contato e mensagens recebidas pelo formulário.</p>
	</div>
</header>

<?php if ($adminErros !== []): ?>
	<div class="admin-alert" role="alert">
		<p class="admin-alert__title">Confira o formulário:</p>
		<ul class="admin-alert__list">
			<?php foreach ($adminErros as $erro): ?>
				<li><?= $escape($erro) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<section aria-labelledby="contato-canais-titulo">
	<h2 class="admin-product__subtitle" id="contato-canais-titulo">Canais de atendimento</h2>

	<form class="admin-contact__form" action="/admin/contato" method="post">
		<?= Csrf::field() ?>

		<div class="field">
			<label class="field__label" for="contato-whatsapp">WhatsApp</label>
			<input class="field__input" id="contato-whatsapp" name="whatsapp" type="tel" maxlength="20" placeholder="11999999999" value="<?= $escape($whatsapp) ?>">
			<p class="field__hint">Só números, com DDD. O país (55) é opcional — ex.: 11999999999.</p>
		</div>

		<div class="field">
			<label class="field__label" for="contato-instagram">Instagram</label>
			<input class="field__input" id="contato-instagram" name="instagram" type="text" maxlength="31" placeholder="obsyrium.store" value="<?= $escape($instagram) ?>">
			<p class="field__hint">Só o @, sem link — ex.: obsyrium.store.</p>
		</div>

		<p class="admin-contact__actions">
			<button class="button" type="submit">Salvar canais</button>
		</p>
	</form>
</section>

<section aria-labelledby="contato-mensagens-titulo">
	<h2 class="admin-product__subtitle" id="contato-mensagens-titulo">
		Mensagens recebidas
		<span class="admin-cats__count"><?= count($adminMensagens) ?></span>
	</h2>

	<?php if ($adminMensagens === []): ?>
		<p class="admin-empty">Nenhuma mensagem recebida ainda.</p>
	<?php else: ?>
		<div class="admin-table-wrap">
			<table class="admin-table">
				<thead>
					<tr>
						<th scope="col">Quando</th>
						<th scope="col">Contato</th>
						<th scope="col">Mensagem</th>
					</tr>
				</thead>

				<tbody>
					<?php foreach ($adminMensagens as $mensagem): ?>
						<tr>
							<td class="admin-contact__date"><?= $escape($data($mensagem['created_at'])) ?></td>
							<td>
								<strong><?= $escape($mensagem['name']) ?></strong><br>
								<a href="mailto:<?= $escape($mensagem['email']) ?>"><?= $escape($mensagem['email']) ?></a>
							</td>
							<td class="admin-contact__message"><?= nl2br($escape($mensagem['message'])) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
