<?php

declare(strict_types=1);

/*
 * Perguntas frequentes: cada pergunta é um <details>, que abre e fecha sem
 * JavaScript e continua funcionando pelo teclado. As respostas apontam para as
 * páginas com o texto completo (entrega, pagamento, privacidade) em vez de
 * repetir as regras aqui dentro.
 */

$perguntas = [
    [
        'pergunta' => 'Como faço um pedido?',
        'resposta' => [
            'Escolha a camiseta no catálogo, selecione o tamanho disponível e adicione ao carrinho. No fechamento você entra na conta (ou cria uma em segundos), confere o endereço de entrega e conclui o pedido.',
            'Depois disso, a compra aparece em Minha conta → Compras, com os itens, o endereço e a situação do pedido.',
        ],
        'link' => ['label' => 'Ver o catálogo', 'href' => '/catalogo'],
    ],
    [
        'pergunta' => 'Preciso de conta para comprar?',
        'resposta' => [
            'Não. O carrinho funciona sem login e você pode montá-lo com calma. A conta só é pedida na hora de finalizar, porque é nela que o pedido e o endereço de entrega ficam guardados.',
        ],
        'link' => ['label' => 'Entrar ou criar conta', 'href' => '/login'],
    ],
    [
        'pergunta' => 'Como sei se o tamanho está disponível?',
        'resposta' => [
            'A loja trabalha com estoque real. Quando um tamanho acaba, ele sai do seletor da página do produto até a próxima remessa — não vendemos o que não está no estoque.',
        ],
        'link' => null,
    ],
    [
        'pergunta' => 'Quais são as formas de pagamento?',
        'resposta' => [
            'Pix, cartão de crédito e boleto são os meios previstos. O pagamento entra no ar junto com a abertura da loja, e cada meio é confirmado na página de formas de pagamento.',
        ],
        'link' => ['label' => 'Ver formas de pagamento', 'href' => '/pagamento'],
    ],
    [
        'pergunta' => 'Qual é o prazo de entrega?',
        'resposta' => [
            'O pedido é separado em lotes pequenos e cada peça é conferida antes de sair. O prazo depende do endereço e da transportadora, e é confirmado antes do envio.',
        ],
        'link' => ['label' => 'Ver política de entrega', 'href' => '/entrega'],
    ],
    [
        'pergunta' => 'Posso trocar ou devolver a peça?',
        'resposta' => [
            'Sim. Compras pela internet podem ser desfeitas em até 7 dias corridos depois do recebimento, sem precisar justificar. Peça com defeito também é trocada.',
            'Basta falar com a loja pelo formulário de contato, informando o número do pedido. A peça volta sem uso e com a etiqueta.',
        ],
        'link' => ['label' => 'Falar com a loja', 'href' => '/contato'],
    ],
    [
        'pergunta' => 'Como cuido da estampa?',
        'resposta' => [
            'Lave do avesso, em água fria, com sabão neutro. Nada de alvejante e nada de ferro em cima da estampa: seque à sombra e passe pelo lado de dentro.',
        ],
        'link' => null,
    ],
    [
        'pergunta' => 'Como acompanho o meu pedido?',
        'resposta' => [
            'Em Minha conta → Compras você vê a situação de cada pedido, do fechamento até a entrega, com os itens e o endereço escolhido.',
        ],
        'link' => ['label' => 'Ver minhas compras', 'href' => '/conta/compras'],
    ],
    [
        'pergunta' => 'Meus dados ficam seguros?',
        'resposta' => [
            'Sim. A senha é guardada só como hash, e CPF, telefone e endereço ficam cifrados no banco. O site inteiro trafega por HTTPS quando publicado.',
        ],
        'link' => ['label' => 'Ver política de privacidade', 'href' => '/privacidade'],
    ],
    [
        'pergunta' => 'Como falo com a loja?',
        'resposta' => [
            'Pelo formulário da página de contato, pelo WhatsApp ou pelo Instagram. Atendemos de segunda a sexta, das 9h às 18h.',
        ],
        'link' => ['label' => 'Abrir o contato', 'href' => '/contato'],
    ],
];

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="page">
	<div class="container page__inner">
		<header class="page__header">
			<p class="page__eyebrow">Atendimento</p>
			<h1 class="page__title">Perguntas frequentes</h1>
			<p class="page__text">As dúvidas que mais chegam por aqui: pedido, conta, tamanho, pagamento, entrega e troca. Se a sua não estiver na lista, fale com a loja.</p>
		</header>

		<div class="faq">
			<?php foreach ($perguntas as $indice => $pergunta): ?>
				<details class="faq__item"<?= $indice === 0 ? ' open' : '' ?>>
					<summary class="faq__question"><?= $escape($pergunta['pergunta']) ?></summary>

					<div class="faq__answer">
						<?php foreach ($pergunta['resposta'] as $paragrafo): ?>
							<p><?= $escape($paragrafo) ?></p>
						<?php endforeach; ?>

						<?php if ($pergunta['link'] !== null): ?>
							<p><a class="page__link" href="<?= $escape($pergunta['link']['href']) ?>"><?= $escape($pergunta['link']['label']) ?></a></p>
						<?php endif; ?>
					</div>
				</details>
			<?php endforeach; ?>
		</div>

		<div class="page__actions">
			<a class="button" href="/contato">Falar com a loja</a>
			<a class="button button--ghost" href="/entrega">Política de entrega</a>
		</div>
	</div>
</section>
