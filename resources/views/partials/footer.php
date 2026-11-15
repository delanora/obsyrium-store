<?php

declare(strict_types=1);

/*
 * Rodapé: colunas de políticas, atendimento, redes sociais, formas de
 * pagamento e linha legal. Links institucionais entram quando as páginas
 * existirem.
 */

$columns = [
    'Políticas' => [
        ['label' => 'Política de entrega', 'href' => '#'],
        ['label' => 'Trocas e devoluções', 'href' => '#'],
        ['label' => 'Termos de privacidade', 'href' => '#'],
        ['label' => 'Formas de pagamento', 'href' => '#'],
    ],
    'Atendimento' => [
        ['label' => 'Perguntas frequentes', 'href' => '#'],
        ['label' => 'Fale conosco', 'href' => '#'],
        ['label' => 'Segunda a sexta, 9h às 18h', 'href' => null],
    ],
    'Redes sociais' => [
        ['label' => 'Instagram', 'href' => '#'],
        ['label' => 'Tik Tok', 'href' => '#'],
        ['label' => 'Facebook', 'href' => '#'],
    ],
];

$payments = ['Pix', 'Visa', 'Master', 'Elo', 'Hipercard', 'Boleto'];

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<footer class="site-footer">
	<div class="container">
		<div class="site-footer__grid">
			<div class="site-footer__brand">
				<img class="site-footer__logo" src="/assets/images/logo-128.png" width="56" height="56" alt="">
				<p class="site-footer__about">
					Obsyrium Store — camisetas com estampas autorais, produzidas em pequenos lotes.
				</p>
			</div>

			<?php foreach ($columns as $title => $links): ?>
				<div>
					<h2 class="site-footer__title"><?= $escape($title) ?></h2>
					<ul class="site-footer__list">
						<?php foreach ($links as $link): ?>
							<li>
								<?php if ($link['href'] === null): ?>
									<?= $escape($link['label']) ?>
								<?php else: ?>
									<a href="<?= $escape($link['href']) ?>"><?= $escape($link['label']) ?></a>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>

			<div>
				<h2 class="site-footer__title">Formas de pagamento</h2>
				<ul class="site-footer__payments">
					<?php foreach ($payments as $payment): ?>
						<li class="site-footer__payment"><?= $escape($payment) ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>

		<div class="site-footer__legal">
			<p>Obsyrium Store · Todos os direitos reservados.</p>
			<p>Preços e condições válidos enquanto durarem os estoques.</p>
		</div>
	</div>
</footer>
