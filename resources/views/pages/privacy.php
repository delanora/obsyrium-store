<?php

declare(strict_types=1);

/*
 * Política de privacidade: o que a loja guarda, por quê, por quanto tempo e como
 * a pessoa pede acesso ou exclusão. O texto descreve o que o código realmente
 * faz (senha com hash, CPF/telefone/endereço cifrados, IP só como hash no
 * limite anti-spam) e não promete recurso que ainda não existe, como e-mail
 * transacional.
 */
?>
<section class="page">
	<div class="container page__inner">
		<header class="page__header">
			<p class="page__eyebrow">Políticas</p>
			<h1 class="page__title">Política de privacidade</h1>
			<p class="page__text">Como a Obsyrium Store trata os seus dados pessoais, conforme a Lei Geral de Proteção de Dados (Lei 13.709/2018).</p>
		</header>

		<div class="page__blocks">
			<article class="page__block">
				<h2 class="page__block-title">Dados que coletamos</h2>
				<ul>
					<li>Cadastro: nome, e-mail, senha, CPF e telefone.</li>
					<li>Endereço de entrega: destinatário, rua, número, complemento, bairro, cidade, UF e CEP.</li>
					<li>Pedidos: itens, valores, situação e datas.</li>
					<li>Contato: nome, e-mail, telefone e o texto da mensagem enviada pelo formulário.</li>
					<li>Técnicos: cookie de sessão (login e carrinho) e o endereço IP, guardado apenas como hash, usado no limite de envios do contato e no bloqueio de tentativas de login.</li>
				</ul>
			</article>

			<article class="page__block">
				<h2 class="page__block-title">Para que usamos</h2>
				<ul>
					<li>Concluir a compra, separar e entregar o pedido.</li>
					<li>Responder mensagens e resolver trocas, devoluções e dúvidas.</li>
					<li>Cumprir obrigações fiscais e legais ligadas à venda.</li>
					<li>Proteger a loja e quem compra: evitar spam, fraude e acesso indevido ao painel.</li>
				</ul>
			</article>

			<article class="page__block">
				<h2 class="page__block-title">Base legal</h2>
				<p>Tratamos os dados para executar o contrato de compra, cumprir obrigação legal ou regulatória e atender interesses legítimos de segurança da loja. Quando a lei exigir consentimento, ele é pedido de forma clara e pode ser retirado a qualquer momento.</p>
			</article>

			<article class="page__block">
				<h2 class="page__block-title">Com quem compartilhamos</h2>
				<p>Com a transportadora, para entregar o pedido (nome, endereço e telefone); com o meio de pagamento, quando ele entrar no ar, para processar a cobrança; e com os provedores de infraestrutura que hospedam o site e o banco de dados.</p>
				<p>Não vendemos nem cedemos dados pessoais para publicidade.</p>
			</article>

			<article class="page__block">
				<h2 class="page__block-title">Como protegemos</h2>
				<p>A senha é guardada apenas como hash com Argon2id. CPF, telefone e dados de endereço ficam cifrados no banco com AES-256-GCM, e o CPF usa um índice cego para conferir duplicidade sem guardá-lo em texto puro. O acesso ao painel é restrito às contas administrativas.</p>
			</article>

			<article class="page__block">
				<h2 class="page__block-title">Por quanto tempo guardamos</h2>
				<p>Os dados da conta ficam enquanto ela existir. Registros de pedidos são mantidos pelos prazos fiscais e legais, mesmo depois de uma exclusão, e nesse caso ficam sem os dados de contato do cliente. Mensagens do formulário são apagadas quando o assunto é resolvido.</p>
			</article>

			<article class="page__block">
				<h2 class="page__block-title">Seus direitos</h2>
				<p>Você pode pedir confirmação, acesso, correção, portabilidade, anonimização, bloqueio ou exclusão dos seus dados, além de informações sobre com quem eles foram compartilhados. O pedido é feito pelo formulário de contato, com o e-mail usado na conta, e é respondido em até 15 dias.</p>
				<p>
					<a class="page__link" href="/contato">Fazer um pedido sobre meus dados</a>
				</p>
			</article>

			<article class="page__block">
				<h2 class="page__block-title">Cookies</h2>
				<p>O site usa apenas o cookie de sessão, que mantém o carrinho e o login funcionando enquanto você navega. Não há cookie de publicidade nem rastreamento de terceiros. Sem ele, o carrinho e a conta não funcionam.</p>
			</article>

			<article class="page__block">
				<h2 class="page__block-title">Mudanças nesta política</h2>
				<p>Se o tratamento de dados mudar, esta página é atualizada e a data de revisão é trocada. Mudanças que afetem direitos são avisadas também no site.</p>
			</article>
		</div>

		<p class="page__note">Revisão de 17 de novembro de 2026. O contato do controlador e do encarregado de dados é publicado aqui antes da abertura da loja; até lá, pedidos sobre dados pessoais são recebidos pelo formulário de contato.</p>

		<div class="page__actions">
			<a class="button" href="/contato">Falar com a loja</a>
			<a class="button button--ghost" href="/termos">Termos de uso</a>
		</div>
	</div>
</section>
