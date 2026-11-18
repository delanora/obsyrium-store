<?php

declare(strict_types=1);

use App\Services\ContactService;
use App\Support\Csrf;

/*
 * Mensagens do formulário de contato, numa aba própria.
 *
 * As abas separam a caixa de entrada (todas e não lidas) da lixeira. Cada linha
 * carrega o formulário da ação: marcar como lida ou não lida, mover para a
 * lixeira e, na lixeira, restaurar ou apagar de vez. Toda ação exige CSRF e
 * volta para a mesma aba (`voltar`).
 *
 * O texto alternativo do botão da lixeira não existe: o rótulo é o próprio
 * texto, então a ação é legível sem ícone.
 *
 * @var list<array{id: int, name: string, email: string, phone: string, source: string, message: string, is_read: bool, created_at: string, deleted_at: string|null}> $adminMensagens
 * @var string $adminMensagensFiltro
 * @var array{todas: int, nao_lidas: int, lixeira: int} $adminMensagensContagem
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$data = static function (string $valor): string {
    $quando = strtotime($valor);

    return $quando === false ? $valor : date('d/m/Y H:i', $quando);
};

$abas = [
    ContactService::FILTER_ALL => ['rotulo' => 'Todas', 'href' => '/admin/mensagens', 'total' => (int) ($adminMensagensContagem['todas'] ?? 0)],
    ContactService::FILTER_UNREAD => ['rotulo' => 'Não lidas', 'href' => '/admin/mensagens?filtro=nao_lidas', 'total' => (int) ($adminMensagensContagem['nao_lidas'] ?? 0)],
    ContactService::FILTER_TRASH => ['rotulo' => 'Lixeira', 'href' => '/admin/mensagens?filtro=lixeira', 'total' => (int) ($adminMensagensContagem['lixeira'] ?? 0)],
];

$naLixeira = $adminMensagensFiltro === ContactService::FILTER_TRASH;
?>
<p class="admin-crumb">
	<a href="/admin">Dashboard</a>
	<span aria-hidden="true">/</span>
	<span aria-current="page">Mensagens</span>
</p>

<header class="admin-head admin-head--row">
	<div>
		<p class="admin-head__eyebrow">Painel</p>
		<h1 class="admin-head__title">Mensagens</h1>
		<p class="admin-head__text">Mensagens do formulário de contato e ideias de estampa enviadas pela home.</p>
	</div>

	<p class="admin-head__actions">
		<a class="admin-link-button" href="/admin/contato">Canais de contato</a>
	</p>
</header>

<nav class="admin-tabs" aria-label="Situação das mensagens">
	<?php foreach ($abas as $chave => $aba): ?>
		<a class="admin-tab<?= $adminMensagensFiltro === $chave ? ' admin-tab--ativo' : '' ?>" href="<?= $escape($aba['href']) ?>"<?= $adminMensagensFiltro === $chave ? ' aria-current="page"' : '' ?>>
			<?= $escape($aba['rotulo']) ?>
			<span class="admin-tab__count"><?= $aba['total'] ?></span>
		</a>
	<?php endforeach; ?>
</nav>

<?php if ($adminMensagens === []): ?>
	<p class="admin-empty">
		<?= $naLixeira ? 'A lixeira está vazia.' : 'Nenhuma mensagem recebida ainda.' ?>
	</p>
<?php else: ?>
	<div class="admin-table-wrap">
		<table class="admin-table admin-table--mensagens">
			<thead>
				<tr>
					<th scope="col">Contato</th>
					<th scope="col">Mensagem</th>
					<th scope="col">Recebida em</th>
					<th scope="col"><span class="admin-visually-hidden">Ações</span></th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ($adminMensagens as $mensagem): ?>
					<?php $resposta = ContactService::replyLinks($mensagem['name'], $mensagem['email'], $mensagem['phone'], $mensagem['message']); ?>
					<tr class="<?= $mensagem['is_read'] || $naLixeira ? '' : 'admin-message--nova' ?>">
						<td>
							<span class="admin-message__name">
								<?= $escape($mensagem['name']) ?>
								<?php if (!$mensagem['is_read'] && !$naLixeira): ?>
									<span class="admin-message__badge">Nova</span>
								<?php endif; ?>
								<?php if ($mensagem['source'] === ContactService::SOURCE_IDEA): ?>
									<span class="admin-message__badge">Ideia de estampa</span>
								<?php endif; ?>
							</span>
							<a class="admin-message__meta" href="mailto:<?= $escape($mensagem['email']) ?>"><?= $escape($mensagem['email']) ?></a>
							<span class="admin-message__meta"><?= $escape(ContactService::phoneLabel($mensagem['phone'])) ?></span>
						</td>

						<td class="admin-message__text"><?= nl2br($escape($mensagem['message'])) ?></td>

						<td class="admin-message__date">
							<?= $escape($data($mensagem['created_at'])) ?>
							<?php if ($mensagem['deleted_at'] !== null): ?>
								<span class="admin-message__meta">Na lixeira desde <?= $escape($data($mensagem['deleted_at'])) ?></span>
							<?php endif; ?>
						</td>

						<td>
							<div class="admin-message__reply">
								<a class="admin-link-button" href="<?= $escape($resposta['mail']) ?>">Responder por e-mail</a>

								<?php if ($resposta['whatsapp'] !== null): ?>
									<a class="admin-link-button" href="<?= $escape($resposta['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a>
								<?php endif; ?>
							</div>

							<div class="admin-message__actions">
								<?php if ($naLixeira): ?>
									<form action="/admin/mensagens/restaurar" method="post">
										<?= Csrf::field() ?>
										<input type="hidden" name="mensagem" value="<?= $mensagem['id'] ?>">
										<input type="hidden" name="voltar" value="<?= $adminMensagensFiltro ?>">
										<button class="admin-link-button" type="submit">Restaurar</button>
									</form>

									<form action="/admin/mensagens/apagar" method="post" data-confirm="Apagar a mensagem de <?= $escape($mensagem['name']) ?> de vez? Não dá para recuperar.">
										<?= Csrf::field() ?>
										<input type="hidden" name="mensagem" value="<?= $mensagem['id'] ?>">
										<input type="hidden" name="voltar" value="<?= $adminMensagensFiltro ?>">
										<button class="admin-link-button admin-link-button--danger" type="submit">Apagar de vez</button>
									</form>
								<?php else: ?>
									<form action="/admin/mensagens/ler" method="post">
										<?= Csrf::field() ?>
										<input type="hidden" name="mensagem" value="<?= $mensagem['id'] ?>">
										<input type="hidden" name="lida" value="<?= $mensagem['is_read'] ? '0' : '1' ?>">
										<input type="hidden" name="voltar" value="<?= $adminMensagensFiltro ?>">
										<button class="admin-link-button" type="submit"><?= $mensagem['is_read'] ? 'Marcar como não lida' : 'Marcar como lida' ?></button>
									</form>

									<form action="/admin/mensagens/lixeira" method="post" data-confirm="Mover a mensagem de <?= $escape($mensagem['name']) ?> para a lixeira?">
										<?= Csrf::field() ?>
										<input type="hidden" name="mensagem" value="<?= $mensagem['id'] ?>">
										<input type="hidden" name="voltar" value="<?= $adminMensagensFiltro ?>">
										<button class="admin-link-button admin-link-button--danger" type="submit">Lixeira</button>
									</form>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
