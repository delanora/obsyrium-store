<?php

declare(strict_types=1);

use App\Support\Csrf;

/*
 * Aba do frete: cotação pelo Melhor Envio ou tabela por estado, mais as
 * escolhas da loja inteira — frete grátis a partir de um valor e retirada.
 *
 * O Melhor Envio é OAuth: a loja cadastra um aplicativo no painel dele,
 * informa Client ID, Client Secret e e-mail aqui, clica em Conectar e autoriza
 * a conta. O endereço de volta precisa ser exatamente o `$adminCallback`.
 *
 * A tabela por estado nasce zerada (entrega grátis, prazo a combinar) e
 * continua sendo o plano B quando a cotação não responde.
 *
 * @var array<string, array{price_cents: int, min_days: int, max_days: int}> $adminTaxas
 * @var array<string, mixed> $adminFreteConfig
 * @var list<string> $adminEstados
 * @var list<string> $adminErros
 * @var array<string, mixed> $adminValores
 * @var array{cep: string, opcoes: list<array<string, mixed>>, erro: string}|null $adminTeste
 * @var string $adminCallback
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$reais = static function (int $centavos): string {
	return $centavos > 0 ? number_format($centavos / 100, 2, ',', '') : '';
};

$taxas = $adminTaxas;
$valores = $adminValores;
$gratis = (string) ($valores['free_from'] ?? $reais((int) $adminFreteConfig['free_from']));
$retirada = (bool) ($valores['pickup'] ?? $adminFreteConfig['pickup']);
$retiradaTexto = (string) ($valores['pickup_text'] ?? $adminFreteConfig['pickup_text']);
$comPreco = 0;

foreach ($taxas as $taxa) {
	if ($taxa['price_cents'] > 0) {
		$comPreco++;
	}
}

$modo = (string) ($valores['mode'] ?? $adminFreteConfig['mode']);
$origem = (string) ($valores['origin_postal_code'] ?? $adminFreteConfig['origin_postal_code']);
$clientId = (string) ($valores['melhorenvio_client_id'] ?? $adminFreteConfig['melhorenvio_client_id']);
$email = (string) ($valores['melhorenvio_email'] ?? $adminFreteConfig['melhorenvio_email']);
$sandbox = (bool) ($valores['melhorenvio_sandbox'] ?? $adminFreteConfig['melhorenvio_sandbox']);
$pesoPadrao = (string) ($valores['default_weight'] ?? (string) $adminFreteConfig['default_weight']);
$segredoMarcado = $adminFreteConfig['melhorenvio_client_secret_set'];
$conectado = $adminFreteConfig['melhorenvio_connected'];
$expira = (int) $adminFreteConfig['melhorenvio_expires_at'];
$melhorEnvioLigado = $modo === 'melhorenvio';
?><p class="admin-crumb">
	<a href="/admin">Painel</a>
	<span aria-hidden="true">/</span>
	<span aria-current="page">Frete</span>
</p>

<header class="admin-head admin-head--row">
	<div>
		<p class="admin-head__eyebrow">Loja</p>
		<h1 class="admin-head__title">Frete e entrega</h1>
		<p class="admin-head__text">
			Frete calculado pelo CEP do endereço: cotação no Melhor Envio ou tabela por estado, mais frete grátis e retirada na loja.
			<?= $melhorEnvioLigado
				? ($conectado
					? 'Hoje o cálculo automático está ligado e a conta está conectada.'
					: 'O cálculo automático está ligado, mas a conta do Melhor Envio ainda não foi conectada.')
				: ($comPreco > 0
					? sprintf('%d de %d estados com frete cobrado.', $comPreco, count($taxas))
					: 'Hoje todos os estados estão com frete grátis e prazo a combinar.') ?>
		</p>
	</div>

	<p class="admin-head__actions">
		<a class="admin-link-button" href="/carrinho" rel="noopener">Ver o carrinho</a>
	</p>
</header>

<?php if ($adminErros !== []): ?>
	<div class="admin-alert" role="alert">
		<p class="admin-alert__title">Confira o frete:</p>
		<ul class="admin-alert__list">
			<?php foreach ($adminErros as $erro): ?>
				<li><?= $escape($erro) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<form class="admin-frete" method="post" action="/admin/frete">
	<?= Csrf::field() ?>

	<section class="admin-panel" aria-labelledby="frete-regras">
		<h2 class="admin-panel__title" id="frete-regras">Regras da loja</h2>

		<div class="admin-frete__regras">
			<div class="field">
				<label class="field__label" for="free-from">Frete grátis a partir de</label>
				<input
					class="field__input"
					id="free-from"
					name="free_from"
					type="text"
					inputmode="decimal"
					autocomplete="off"
					placeholder="0,00"
					value="<?= $escape($gratis) ?>"
				>
				<p class="field__hint">Em reais. Deixe vazio ou zero para não ter frete grátis automático.</p>
			</div>

			<div class="field">
				<label class="field__label" for="pickup-text">Instruções de retirada</label>
				<input
					class="field__input"
					id="pickup-text"
					name="pickup_text"
					type="text"
					maxlength="160"
					autocomplete="off"
					value="<?= $escape($retiradaTexto) ?>"
				>
				<p class="field__hint">Aparece no checkout quando a retirada está liberada.</p>
			</div>
		</div>

		<label class="admin-check">
			<input type="checkbox" name="pickup" value="1"<?= $retirada ? ' checked' : '' ?>>
			<span>Oferecer retirada na loja (sem frete) no checkout</span>
		</label>
	</section>

	<section class="admin-panel" aria-labelledby="frete-calculo">
		<h2 class="admin-panel__title" id="frete-calculo">Cálculo automático (Melhor Envio)</h2>
		<p class="admin-note">
			O Melhor Envio reúne Correios, Jadlog, Loggi e outras transportadoras numa conta só.
			Crie um aplicativo no painel do Melhor Envio, cadastre o endereço de retorno abaixo e informe
			Client ID e Client Secret. Depois clique em <strong>Conectar</strong> e autorize a conta.
			O token dura 30 dias e é renovado sozinho; sem conexão, o checkout usa a tabela por estado.
		</p>
		<p class="admin-note">
			No aplicativo, o endereço de retorno precisa ser <strong>idêntico</strong> ao mostrado no fim deste bloco
			(ele sai do <code>APP_URL</code>, o domínio oficial da loja): se divergir — outro domínio, IP da rede ou http no lugar de https —
			o Melhor Envio recusa a autorização. As credenciais também têm de ser do mesmo ambiente do aplicativo marcado abaixo.
		</p>

		<div class="admin-frete__regras">
			<div class="field">
				<label class="field__label" for="mode">Como calcular o frete</label>
				<select class="field__input" id="mode" name="mode">
					<option value="estado"<?= $modo !== 'melhorenvio' ? ' selected' : '' ?>>Tabela por estado</option>
					<option value="melhorenvio"<?= $modo === 'melhorenvio' ? ' selected' : '' ?>>Melhor Envio</option>
				</select>
			</div>

			<div class="field">
				<label class="field__label" for="origin-postal-code">CEP de origem</label>
				<input class="field__input" id="origin-postal-code" name="origin_postal_code" type="text" inputmode="numeric" maxlength="9" autocomplete="off" placeholder="01234-567" value="<?= $escape($origem) ?>">
				<p class="field__hint">De onde as peças saem. Obrigatório com a cotação ligada.</p>
			</div>

			<div class="field">
				<label class="field__label" for="melhorenvio-client-id">Client ID</label>
				<input class="field__input" id="melhorenvio-client-id" name="melhorenvio_client_id" type="text" maxlength="40" autocomplete="off" value="<?= $escape($clientId) ?>">
			</div>

			<div class="field">
				<label class="field__label" for="melhorenvio-client-secret">Client Secret</label>
				<input class="field__input" id="melhorenvio-client-secret" name="melhorenvio_client_secret" type="password" maxlength="200" autocomplete="new-password" placeholder="<?= $segredoMarcado ? 'Segredo guardado — deixe vazio para manter' : 'O segredo do aplicativo' ?>">
				<p class="field__hint">Em branco mantém o segredo que já está gravado.</p>
			</div>

			<div class="field">
				<label class="field__label" for="melhorenvio-email">E-mail de contato</label>
				<input class="field__input" id="melhorenvio-email" name="melhorenvio_email" type="email" maxlength="120" autocomplete="off" value="<?= $escape($email) ?>">
				<p class="field__hint">Vai no User-Agent da requisição, como o Melhor Envio exige.</p>
			</div>

			<div class="field">
				<label class="field__label" for="default-weight">Peso padrão do pacote (g)</label>
				<input class="field__input" id="default-weight" name="default_weight" type="text" inputmode="numeric" maxlength="5" autocomplete="off" value="<?= $escape($pesoPadrao) ?>">
				<p class="field__hint">Usado quando o produto não tem peso. O mínimo das transportadoras é 300 g.</p>
			</div>
		</div>

		<label class="admin-check">
			<input type="checkbox" name="melhorenvio_sandbox" value="1"<?= $sandbox ? ' checked' : '' ?>>
			<span>Usar o ambiente de testes (sandbox) do Melhor Envio — o Client ID e o segredo precisam ser de um aplicativo criado no sandbox, não da produção</span>
		</label>

		<div class="admin-frete__conexao-bloco">
			<?php if ($conectado): ?>
				<p class="admin-frete__conexao admin-frete__conexao--ok">
					Conta conectada<?= $expira > 0 ? ' — token válido até ' . date('d/m/Y', $expira) : '' ?>.
				</p>

				<form action="/admin/frete/melhor-envio/desconectar" method="post">
					<?= Csrf::field() ?>
					<button class="button button--ghost" type="submit">Desconectar</button>
				</form>
			<?php else: ?>
				<p class="admin-frete__conexao">Conta do Melhor Envio não conectada.</p>
				<a class="button" href="/admin/frete/melhor-envio/conectar">Conectar com Melhor Envio</a>
			<?php endif; ?>

			<form action="/admin/frete/melhor-envio/testar" method="post">
				<?= Csrf::field() ?>
				<button class="button button--ghost" type="submit">Testar credenciais</button>
			</form>
		</div>

		<p class="admin-note">
			O código devolvido na autorização vale <strong>uma vez só</strong> e expira em poucos minutos:
			se a conexão falhar, clique em <strong>Conectar</strong> de novo em vez de recarregar a página de retorno.
			Se as credenciais forem recusadas, <strong>Testar credenciais</strong> confere o par Client ID/Secret sem passar pela autorização.
		</p>

		<p class="admin-note admin-frete__callback">
			Endereço de retorno para cadastrar no aplicativo: <code><?= $escape($adminCallback) ?></code>
		</p>
	</section>

	<section class="admin-panel" aria-labelledby="frete-tabela">
		<h2 class="admin-panel__title" id="frete-tabela">Valor e prazo por estado</h2>
		<p class="admin-note">Plano B do cálculo: vale quando o modo é a tabela ou quando a cotação não responde. Campo vazio é zero (frete grátis e prazo a combinar). O prazo é em dias úteis.</p>

		<div class="admin-frete__tabela">
			<div class="admin-frete__cabecalho" aria-hidden="true">
				<span>UF</span>
				<span>Frete (R$)</span>
				<span>Prazo mínimo</span>
				<span>Prazo máximo</span>
			</div>

			<?php foreach ($adminEstados as $uf): ?>
				<?php
				$taxa = $taxas[$uf] ?? ['price_cents' => 0, 'min_days' => 0, 'max_days' => 0];
				$preco = (string) ($valores['rates'][$uf]['price'] ?? $reais((int) $taxa['price_cents']));
				$minimo = (string) ($valores['rates'][$uf]['min'] ?? ((int) $taxa['min_days'] > 0 ? (string) $taxa['min_days'] : ''));
				$maximo = (string) ($valores['rates'][$uf]['max'] ?? ((int) $taxa['max_days'] > 0 ? (string) $taxa['max_days'] : ''));
				?>
				<div class="admin-frete__linha">
					<p class="admin-frete__uf"><?= $escape($uf) ?></p>

					<div class="field">
						<label class="visually-hidden" for="preco-<?= $escape($uf) ?>">Frete de <?= $escape($uf) ?></label>
						<input
							class="field__input"
							id="preco-<?= $escape($uf) ?>"
							name="price[<?= $escape($uf) ?>]"
							type="text"
							inputmode="decimal"
							autocomplete="off"
							placeholder="0,00"
							value="<?= $escape($preco) ?>"
						>
					</div>

					<div class="field">
						<label class="visually-hidden" for="min-<?= $escape($uf) ?>">Prazo mínimo de <?= $escape($uf) ?></label>
						<input
							class="field__input"
							id="min-<?= $escape($uf) ?>"
							name="min[<?= $escape($uf) ?>]"
							type="text"
							inputmode="numeric"
							pattern="[0-9]*"
							maxlength="2"
							autocomplete="off"
							placeholder="0"
							value="<?= $escape($minimo) ?>"
						>
					</div>

					<div class="field">
						<label class="visually-hidden" for="max-<?= $escape($uf) ?>">Prazo máximo de <?= $escape($uf) ?></label>
						<input
							class="field__input"
							id="max-<?= $escape($uf) ?>"
							name="max[<?= $escape($uf) ?>]"
							type="text"
							inputmode="numeric"
							pattern="[0-9]*"
							maxlength="2"
							autocomplete="off"
							placeholder="0"
							value="<?= $escape($maximo) ?>"
						>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<p class="admin-frete__salvar">
		<button class="button" type="submit">Salvar frete</button>
	</p>
</form>

<section class="admin-panel admin-frete__teste" aria-labelledby="frete-teste">
	<h2 class="admin-panel__title" id="frete-teste">Testar um CEP</h2>
	<p class="admin-note">Faz a mesma conta do carrinho, com o CEP digitado e o peso padrão do pacote. Use para conferir se a conta está conectada e se as transportadoras atendem o trecho.</p>

	<form class="admin-frete__teste-form" action="/admin/frete" method="get">
		<div class="field">
			<label class="field__label" for="teste-cep">CEP de destino</label>
			<input class="field__input" id="teste-cep" name="teste_cep" type="text" inputmode="numeric" maxlength="9" autocomplete="off" placeholder="01234-567" value="<?= $escape($adminTeste['cep'] ?? '') ?>" required>
		</div>

		<button class="button button--ghost" type="submit">Calcular</button>
	</form>

	<?php if ($adminTeste !== null): ?>
		<?php if ($adminTeste['opcoes'] === []): ?>
			<p class="admin-frete__teste-erro" role="alert">
				Não foi possível calcular para <?= $escape($adminTeste['cep']) ?>.
				<?= $adminTeste['erro'] !== '' ? $escape($adminTeste['erro']) : 'Confira o CEP.' ?>
			</p>
		<?php else: ?>
			<ul class="admin-frete__teste-lista">
				<?php foreach ($adminTeste['opcoes'] as $opcao): ?>
					<li>
						<span class="admin-frete__teste-nome"><?= $escape((string) $opcao['label']) ?></span>
						<span class="admin-frete__teste-prazo"><?= $escape((string) $opcao['days']) ?></span>
						<span class="admin-frete__teste-preco"><?= $escape((string) $opcao['price_label']) ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</section>
