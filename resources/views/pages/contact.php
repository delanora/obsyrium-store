<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Página Contato: os três caminhos de atendimento — formulário, WhatsApp e
 * Instagram — e os atalhos que já funcionam dentro da loja.
 *
 * O WhatsApp e o @ do Instagram vêm do painel. Enquanto não forem
 * configurados, o cartão do canal explica que ele ainda não está publicado e o
 * formulário continua funcionando: a mensagem fica guardada para a loja.
 *
 * @var array{whatsapp: string|null, whatsappLabel: string, instagram: string|null, instagramLabel: string} $contatoCanais
 * @var list<string> $contatoErros
 * @var array{name: string, email: string, phone: string, message: string} $contatoValores
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$canais = $contatoCanais;
$erros = $contatoErros ?? [];
$valores = $contatoValores ?? ['name' => '', 'email' => '', 'phone' => '', 'message' => ''];
?>
<section class="page">
	<div class="container page__inner">
		<header class="page__header">
			<p class="page__eyebrow">Atendimento</p>
			<h1 class="page__title">Contato</h1>
			<p class="page__text">Atendemos de segunda a sexta, das 9h às 18h. Escolha o canal que preferir: mensagem por aqui, WhatsApp ou Instagram.</p>
		</header>

		<div class="contact__channels">
			<article class="contact__channel">
				<h2 class="page__block-title">WhatsApp</h2>

				<?php if ($canais['whatsapp'] !== null): ?>
					<p class="contact__channel-text"><?= $escape($canais['whatsappLabel']) ?></p>
					<a class="button" href="<?= $escape($canais['whatsapp']) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a>
				<?php else: ?>
					<p class="contact__channel-text">O número ainda não foi publicado. Enquanto isso, use o formulário abaixo.</p>
				<?php endif; ?>
			</article>

			<article class="contact__channel">
				<h2 class="page__block-title">Instagram</h2>

				<?php if ($canais['instagram'] !== null): ?>
					<p class="contact__channel-text"><?= $escape($canais['instagramLabel']) ?></p>
					<a class="button button--ghost" href="<?= $escape($canais['instagram']) ?>" target="_blank" rel="noopener">Abrir o Instagram</a>
				<?php else: ?>
					<p class="contact__channel-text">O perfil ainda não foi publicado. Enquanto isso, use o formulário abaixo.</p>
				<?php endif; ?>
			</article>
		</div>

		<section class="contact__form" aria-labelledby="contato-form-titulo">
			<h2 class="page__block-title" id="contato-form-titulo">Enviar mensagem</h2>
			<p class="page__text">Conte o que você precisa e respondemos no e-mail informado.</p>

			<?php if ($erros !== []): ?>
				<div class="contact__alert" role="alert">
					<ul>
						<?php foreach ($erros as $erro): ?>
							<li><?= $escape($erro) ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<form class="contact__fields" action="/contato" method="post">
				<?= Csrf::field() ?>

				<div class="field">
					<label class="field__label" for="contato-nome">Nome</label>
					<input class="field__input" id="contato-nome" name="name" type="text" maxlength="<?= App\Services\ContactService::MAX_NAME ?>" autocomplete="name" required value="<?= $escape($valores['name']) ?>">
				</div>

				<div class="field">
					<label class="field__label" for="contato-email">E-mail</label>
					<input class="field__input" id="contato-email" name="email" type="email" maxlength="<?= App\Services\ContactService::MAX_EMAIL ?>" autocomplete="email" required value="<?= $escape($valores['email']) ?>">
				</div>

				<div class="field">
					<label class="field__label" for="contato-telefone">Telefone</label>
					<input class="field__input" id="contato-telefone" name="phone" type="tel" maxlength="<?= App\Services\ContactService::MAX_PHONE ?>" autocomplete="tel" inputmode="tel" placeholder="(11) 99999-9999" required value="<?= $escape($valores['phone']) ?>">
				</div>

				<div class="field contact__field--wide">
					<label class="field__label" for="contato-mensagem">Mensagem</label>
					<textarea class="field__input field__input--textarea" id="contato-mensagem" name="message" maxlength="<?= App\Services\ContactService::MAX_MESSAGE ?>" rows="6" required><?= $escape($valores['message']) ?></textarea>
				</div>

				<div class="contact__submit contact__field--wide">
					<button class="button" type="submit">Enviar mensagem</button>
				</div>
			</form>
		</section>

		<div class="page__blocks">
			<article class="page__block">
				<h2 class="page__block-title">Pedidos</h2>
				<p>O histórico de compras mostra os itens, o endereço de entrega e a situação de cada pedido, do fechamento até a entrega.</p>
				<p><a class="page__link" href="/conta/compras">Abrir Compras</a></p>
			</article>

			<article class="page__block">
				<h2 class="page__block-title">Endereço de entrega</h2>
				<p>Antes de finalizar a compra, confira o endereço escolhido. Você pode cadastrar outro, trocar o principal ou corrigir um endereço a qualquer momento.</p>
				<p><a class="page__link" href="/conta/enderecos">Gerenciar endereços</a></p>
			</article>
		</div>

		<div class="page__actions">
			<a class="button" href="/catalogo">Ver o catálogo</a>
			<a class="button button--ghost" href="/sobre">Conhecer a marca</a>
		</div>
	</div>
</section>
