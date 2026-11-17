<?php

declare(strict_types=1);

/*
 * Rodapé: colunas de políticas, atendimento, redes sociais, formas de
 * pagamento e linha legal. Políticas e atendimento já têm página própria; as
 * redes sociais ainda apontam para `#` até os perfis existirem.
 */

$columns = [
    'Políticas' => [
        ['label' => 'Política de entrega', 'href' => '/entrega'],
        ['label' => 'Formas de pagamento', 'href' => '/pagamento'],
        ['label' => 'Termos de uso', 'href' => '/termos'],
        ['label' => 'Política de privacidade', 'href' => '/privacidade'],
    ],
    'Atendimento' => [
        ['label' => 'Perguntas frequentes', 'href' => '/perguntas-frequentes'],
        ['label' => 'Fale conosco', 'href' => '/contato'],
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
		<div class="site-footer__grid" data-reveal-stagger>
			<div class="site-footer__brand">
				<img class="site-footer__logo" src="/assets/images/logo-128.png" width="56" height="56" alt="">
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
