<?php

declare(strict_types=1);

/*
 * Formas de pagamento: os meios previstos para a loja. O gateway ainda não
 * entrou (o pagamento está fora da ordem de trabalho atual), então a página diz
 * isso com clareza em vez de prometer cobrança que não existe.
 */

$meios = [
    [
        'nome' => 'Pix',
        'texto' => 'Pagamento à vista, com confirmação rápida. O pedido é separado assim que o valor cai.',
    ],
    [
        'nome' => 'Cartão de crédito',
        'texto' => 'Visa, Mastercard, Elo e Hipercard, com parcelamento informado no checkout no momento da compra.',
    ],
    [
        'nome' => 'Boleto bancário',
        'texto' => 'Emitido no fechamento do pedido, com prazo de vencimento mostrado antes da confirmação.',
    ],
];

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="page">
	<div class="container page__inner">
		<header class="page__header">
			<p class="page__eyebrow">Pagamento</p>
			<h1 class="page__title">Formas de pagamento</h1>
			<p class="page__text">Pix, cartão e boleto são os meios previstos para a loja. Todos os preços do site são em reais e já incluem impostos — nenhuma taxa é somada no fechamento.</p>
		</header>

		<div class="page__blocks">
			<?php foreach ($meios as $meio): ?>
				<article class="page__block">
					<h2 class="page__block-title"><?= $escape($meio['nome']) ?></h2>
					<p><?= $escape($meio['texto']) ?></p>
				</article>
			<?php endforeach; ?>

			<article class="page__block">
				<h2 class="page__block-title">Confirmação do pedido</h2>
				<p>O pedido só é separado e enviado depois que o pagamento é confirmado. Pix e cartão confirmam na hora; boleto pode levar até dois dias úteis depois do pagamento, prazo do próprio banco.</p>
			</article>
		</div>

		<div class="page__blocks">
			<article class="page__block">
				<h2 class="page__block-title">Segurança</h2>
				<p>Os dados do cartão são digitados no ambiente do meio de pagamento e não passam pelo servidor da loja: a Obsyrium não guarda número de cartão nem código de segurança. CPF, telefone e endereço ficam cifrados no banco.</p>
			</article>

			<article class="page__block">
				<h2 class="page__block-title">Preços e disponibilidade</h2>
				<p>Os preços valem enquanto durarem os estoques. Se um pedido for feito com erro evidente de preço ou com peça que acabou de esgotar, a loja avisa pelo canal de atendimento e devolve integralmente o valor pago, sem cobrar nada pelo cancelamento.</p>
			</article>
		</div>

		<p class="page__note">A loja ainda não está processando pagamentos: os meios acima são os previstos e cada um é confirmado nesta página quando entrar no ar. Nada é cobrado antes disso.</p>

		<div class="page__actions">
			<a class="button" href="/contato">Falar com a loja</a>
			<a class="button button--ghost" href="/entrega">Política de entrega</a>
		</div>
	</div>
</section>
