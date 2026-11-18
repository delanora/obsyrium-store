<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Cep;
use App\Support\Crypto;
use App\Support\Price;
use PDO;
use Throwable;

/**
 * Frete da loja, por CEP do endereço.
 *
 * Dois modos, escolhidos no painel:
 *
 * - `estado`: a tabela própria, uma taxa e um prazo por UF. O CEP do endereço
 *   descobre a UF (faixas dos Correios, sem internet) e a opção sai da tabela.
 *   Nasce toda em zero, ou seja, entrega grátis com prazo a combinar, para o
 *   checkout fechar pedido antes de a loja configurar qualquer preço.
 * - `melhorenvio`: cotação real no Melhor Envio, que agrega Correios, Jadlog,
 *   Loggi e outras transportadoras na mesma conta. Usa o peso das peças do
 *   carrinho e exige a conta conectada por OAuth no painel; se a cotação
 *   falhar, a opção cai na tabela por estado em vez de deixar o cliente sem
 *   entrega.
 *
 * Além disso, a loja define a partir de quanto o frete sai de graça e se a
 * retirada está liberada. O pedido grava o que foi escolhido com o preço do
 * momento — mudar a tabela depois não reescreve o que já foi vendido.
 */
final class ShippingService
{
    public const DELIVERY = 'entrega';

    public const PICKUP = 'retirada';

    public const MODE_TABLE = 'estado';

    public const MODE_MELHOR_ENVIO = 'melhorenvio';

    /** Unidades da federação, na ordem das regiões. */
    public const STATES = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
        'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    private const FREE_FROM_KEY = 'shipping_free_from_cents';

    private const PICKUP_KEY = 'shipping_pickup_enabled';

    private const PICKUP_TEXT_KEY = 'shipping_pickup_text';

    private const MODE_KEY = 'shipping_mode';

    private const ORIGIN_KEY = 'shipping_origin_postal_code';

    private const CLIENT_ID_KEY = 'shipping_melhorenvio_client_id';

    private const CLIENT_SECRET_KEY = 'shipping_melhorenvio_client_secret';

    private const EMAIL_KEY = 'shipping_melhorenvio_email';

    private const SANDBOX_KEY = 'shipping_melhorenvio_sandbox';

    private const WEIGHT_KEY = 'shipping_default_weight_grams';

    /** Teto do frete: R$ 1.000,00. */
    private const MAX_PRICE_CENTS = 100000;

    private const MAX_DAYS = 90;

    private const MAX_WEIGHT_GRAMS = 30000;

    private string $lastError = '';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Último motivo de a cotação do Melhor Envio não ter sido usada (para a
     * tela de teste do painel). Vazio quando deu tudo certo ou nem foi tentado.
     */
    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * Escolhas que valem para a loja inteira.
     *
     * @return array{
     *     free_from: int,
     *     pickup: bool,
     *     pickup_text: string,
     *     mode: string,
     *     origin_postal_code: string,
     *     melhorenvio_client_id: string,
     *     melhorenvio_client_secret: string,
     *     melhorenvio_client_secret_set: bool,
     *     melhorenvio_email: string,
     *     melhorenvio_sandbox: bool,
     *     melhorenvio_connected: bool,
     *     melhorenvio_expires_at: int,
     *     default_weight: int
     * }
     */
    public function settings(): array
    {
        $valores = [];

        foreach ($this->pdo->query('SELECT name, value FROM settings') as $linha) {
            $valores[(string) $linha['name']] = (string) $linha['value'];
        }

        $modo = (string) ($valores[self::MODE_KEY] ?? self::MODE_TABLE);
        $segredo = $this->decifrar((string) ($valores[self::CLIENT_SECRET_KEY] ?? ''));

        return [
            'free_from' => max(0, (int) ($valores[self::FREE_FROM_KEY] ?? '0')),
            'pickup' => ($valores[self::PICKUP_KEY] ?? '0') === '1',
            'pickup_text' => (string) ($valores[self::PICKUP_TEXT_KEY] ?? ''),
            'mode' => $modo === self::MODE_MELHOR_ENVIO ? self::MODE_MELHOR_ENVIO : self::MODE_TABLE,
            'origin_postal_code' => Cep::normalize((string) ($valores[self::ORIGIN_KEY] ?? '')),
            'melhorenvio_client_id' => (string) ($valores[self::CLIENT_ID_KEY] ?? ''),
            'melhorenvio_client_secret' => $segredo,
            'melhorenvio_client_secret_set' => $segredo !== '',
            'melhorenvio_email' => (string) ($valores[self::EMAIL_KEY] ?? ''),
            'melhorenvio_sandbox' => ($valores[self::SANDBOX_KEY] ?? '0') === '1',
            'melhorenvio_connected' => $this->melhorEnvio()->hasToken(),
            'melhorenvio_expires_at' => $this->melhorEnvio()->expiresAt(),
            'default_weight' => max(1, (int) ($valores[self::WEIGHT_KEY] ?? '300')),
        ];
    }

    /**
     * Taxa e prazo de cada estado, sempre com as 27 linhas.
     *
     * @return array<string, array{price_cents: int, min_days: int, max_days: int}>
     */
    public function rates(): array
    {
        $taxas = [];

        foreach (self::STATES as $uf) {
            $taxas[$uf] = ['price_cents' => 0, 'min_days' => 0, 'max_days' => 0];
        }

        $consulta = $this->pdo->query('SELECT state, price_cents, min_days, max_days FROM shipping_rates');

        foreach ($consulta as $linha) {
            $uf = strtoupper((string) $linha['state']);

            if (!isset($taxas[$uf])) {
                continue;
            }

            $taxas[$uf] = [
                'price_cents' => (int) $linha['price_cents'],
                'min_days' => (int) $linha['min_days'],
                'max_days' => (int) $linha['max_days'],
            ];
        }

        return $taxas;
    }

    /**
     * Opções para um estado, direto da tabela (sem cotação externa).
     *
     * @return list<array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}>
     */
    public function optionsFor(string $state, int $subtotalCents): array
    {
        return $this->tableOptions(
            strtoupper(trim($state)),
            $subtotalCents,
            $this->settings()
        );
    }

    /**
     * Opções para um CEP: UF pelas faixas dos Correios e, quando o modo é
     * `melhorenvio` e a conta está conectada, as transportadoras cotadas com o
     * peso e as medidas das peças do carrinho.
     *
     * @param list<array<string, mixed>> $items linhas com `weight_grams`, `quantity` e `price_cents`
     *
     * @return list<array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}>
     */
    public function optionsForCep(
        string $postalCode,
        int $subtotalCents,
        array $items = [],
        string $stateFallback = ''
    ): array {
        $config = $this->settings();
        $cep = Cep::normalize($postalCode);
        $uf = Cep::stateFor($cep) ?? strtoupper(trim($stateFallback));

        if (!in_array($uf, self::STATES, true)) {
            return [];
        }

        $opcoes = [];

        if ($config['mode'] === self::MODE_MELHOR_ENVIO) {
            $opcoes = $this->melhorEnvioOptions($config, $cep, $subtotalCents, $items);
        }

        // Sem cotação (modo tabela, conta não conectada ou Melhor Envio fora do
        // ar), a tabela por estado é o plano B — o cliente nunca fica sem
        // entrega por causa de uma chamada externa.
        if ($opcoes === []) {
            $opcoes = $this->tableOptions($uf, $subtotalCents, $config);
        } else {
            $opcoes = array_merge($opcoes, $this->pickupOptions($config));
        }

        return $opcoes;
    }

    /**
     * A opção escolhida, se ela existir para o CEP e o subtotal informados.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}|null
     */
    public function optionForCep(
        string $postalCode,
        string $key,
        int $subtotalCents,
        array $items = [],
        string $stateFallback = ''
    ): ?array {
        foreach ($this->optionsForCep($postalCode, $subtotalCents, $items, $stateFallback) as $opcao) {
            if ($opcao['key'] === $key) {
                return $opcao;
            }
        }

        return null;
    }

    /**
     * A opção escolhida, se ela existir para o estado e o subtotal informados.
     *
     * @return array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}|null
     */
    public function option(string $state, string $key, int $subtotalCents): ?array
    {
        foreach ($this->optionsFor($state, $subtotalCents) as $opcao) {
            if ($opcao['key'] === $key) {
                return $opcao;
            }
        }

        return null;
    }

    /**
     * Peso do carrinho em gramas: peso da peça cadastrado no produto vezes a
     * quantidade. Sem peso cadastrado, vale o pacote mínimo configurado.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function weightForItems(array $items): int
    {
        $total = 0;

        foreach ($items as $item) {
            $peso = (int) ($item['weight_grams'] ?? 0);
            $quantidade = max(1, (int) ($item['quantity'] ?? 1));
            $total += max(0, $peso) * $quantidade;
        }

        if ($total > 0) {
            return $total;
        }

        return max(300, $this->settings()['default_weight']);
    }

    /**
     * Salva as taxas, as escolhas da loja e a configuração do Melhor Envio.
     * Preço vazio é zero (grátis), prazo vazio é zero (a combinar).
     *
     * @param array<string, mixed> $dados
     *
     * @return array{errors: list<string>, values: array<string, mixed>}
     */
    public function save(array $dados): array
    {
        $erros = [];
        $atual = $this->settings();

        $valores = [
            'free_from' => trim((string) ($dados['free_from'] ?? '')),
            'pickup' => isset($dados['pickup']),
            'pickup_text' => trim((string) ($dados['pickup_text'] ?? '')),
            'mode' => (string) ($dados['mode'] ?? self::MODE_TABLE),
            'origin_postal_code' => Cep::normalize((string) ($dados['origin_postal_code'] ?? '')),
            'melhorenvio_client_id' => trim((string) ($dados['melhorenvio_client_id'] ?? '')),
            'melhorenvio_email' => trim((string) ($dados['melhorenvio_email'] ?? '')),
            'melhorenvio_sandbox' => isset($dados['melhorenvio_sandbox']),
            'default_weight' => trim((string) ($dados['default_weight'] ?? '')),
            'secret' => (string) ($dados['melhorenvio_client_secret'] ?? ''),
            'rates' => [],
        ];

        if (!in_array($valores['mode'], [self::MODE_TABLE, self::MODE_MELHOR_ENVIO], true)) {
            $valores['mode'] = self::MODE_TABLE;
        }

        $freteGratis = Price::parse($valores['free_from']);

        if ($freteGratis === null || $freteGratis > self::MAX_PRICE_CENTS) {
            $erros[] = 'Frete grátis: informe um valor em reais ou deixe vazio para desligar.';
        }

        if (mb_strlen($valores['pickup_text']) > 160) {
            $erros[] = 'Instruções de retirada: até 160 caracteres.';
        }

        $pesoPadrao = filter_var(
            $valores['default_weight'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => self::MAX_WEIGHT_GRAMS]]
        );

        if ($pesoPadrao === false) {
            $erros[] = sprintf(
                'Peso padrão do pacote: use gramas, de 1 a %d.',
                self::MAX_WEIGHT_GRAMS
            );
        }

        // O segredo é opcional no formulário: em branco mantém o que já está
        // gravado, para a loja não precisar digitá-lo de novo a cada ajuste.
        $segredoNovo = (string) $valores['secret'];

        if ($valores['mode'] === self::MODE_MELHOR_ENVIO) {
            if (!Cep::isValid($valores['origin_postal_code'])) {
                $erros[] = 'CEP de origem: informe os oito dígitos do CEP de onde as peças saem.';
            }

            if ($valores['melhorenvio_client_id'] === '') {
                $erros[] = 'Client ID: copie o identificador do aplicativo criado no Melhor Envio.';
            }

            if ($segredoNovo === '' && !$atual['melhorenvio_client_secret_set']) {
                $erros[] = 'Client Secret: copie o segredo do aplicativo criado no Melhor Envio.';
            }

            if ($valores['melhorenvio_email'] === ''
                || filter_var($valores['melhorenvio_email'], FILTER_VALIDATE_EMAIL) === false
            ) {
                $erros[] = 'E-mail de contato: o Melhor Envio exige um e-mail no User-Agent da requisição.';
            }
        }

        $taxas = [];

        foreach (self::STATES as $uf) {
            $precoBruto = trim((string) ($dados['price'][$uf] ?? ''));
            $minBruto = trim((string) ($dados['min'][$uf] ?? ''));
            $maxBruto = trim((string) ($dados['max'][$uf] ?? ''));
            $valores['rates'][$uf] = ['price' => $precoBruto, 'min' => $minBruto, 'max' => $maxBruto];

            $preco = Price::parse($precoBruto);

            if ($preco === null || $preco > self::MAX_PRICE_CENTS) {
                $erros[] = sprintf('Frete de %s: informe um valor em reais.', $uf);

                continue;
            }

            $minimo = $minBruto === ''
                ? 0
                : filter_var($minBruto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::MAX_DAYS]]);

            $maximo = $maxBruto === ''
                ? 0
                : filter_var($maxBruto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::MAX_DAYS]]);

            if ($minimo === false || $maximo === false) {
                $erros[] = sprintf('Prazo de %s: use dias inteiros entre 0 e %d.', $uf, self::MAX_DAYS);

                continue;
            }

            if ($maximo > 0 && $minimo > $maximo) {
                $erros[] = sprintf('Prazo de %s: o máximo não pode ser menor que o mínimo.', $uf);

                continue;
            }

            $taxas[$uf] = [
                'price_cents' => $preco,
                'min_days' => (int) $minimo,
                'max_days' => (int) $maximo,
            ];
        }

        if ($erros !== []) {
            return ['errors' => $erros, 'values' => $valores];
        }

        $this->pdo->beginTransaction();

        try {
            $garantir = $this->pdo->prepare('INSERT IGNORE INTO shipping_rates (state) VALUES (:uf)');
            $atualizar = $this->pdo->prepare(
                'UPDATE shipping_rates
                    SET price_cents = :preco, min_days = :minimo, max_days = :maximo
                  WHERE state = :uf'
            );

            foreach ($taxas as $uf => $taxa) {
                $garantir->execute([':uf' => $uf]);
                $atualizar->execute([
                    ':preco' => $taxa['price_cents'],
                    ':minimo' => $taxa['min_days'],
                    ':maximo' => $taxa['max_days'],
                    ':uf' => $uf,
                ]);
            }

            $this->saveSetting(self::FREE_FROM_KEY, (string) $freteGratis);
            $this->saveSetting(self::PICKUP_KEY, $valores['pickup'] ? '1' : '0');
            $this->saveSetting(self::PICKUP_TEXT_KEY, $valores['pickup_text']);
            $this->saveSetting(self::MODE_KEY, $valores['mode']);
            $this->saveSetting(self::ORIGIN_KEY, $valores['origin_postal_code']);
            $this->saveSetting(self::CLIENT_ID_KEY, $valores['melhorenvio_client_id']);
            $this->saveSetting(self::EMAIL_KEY, $valores['melhorenvio_email']);
            $this->saveSetting(self::SANDBOX_KEY, $valores['melhorenvio_sandbox'] ? '1' : '0');
            $this->saveSetting(self::WEIGHT_KEY, (string) (int) $pesoPadrao);

            if ($segredoNovo !== '') {
                $this->saveSetting(self::CLIENT_SECRET_KEY, Crypto::encrypt($segredoNovo));
            }

            $this->pdo->commit();
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }

        return ['errors' => [], 'values' => $valores];
    }

    /**
     * Transportadoras cotadas no Melhor Envio, com frete grátis por cima.
     *
     * @param array<string, mixed>       $config
     * @param list<array<string, mixed>> $items
     *
     * @return list<array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}>
     */
    private function melhorEnvioOptions(
        array $config,
        string $postalCode,
        int $subtotalCents,
        array $items
    ): array {
        $this->lastError = '';

        if (!Cep::isValid($postalCode)) {
            $this->lastError = 'CEP de destino inválido.';

            return [];
        }

        // Cada peça vira uma linha com peso e valor unitários; o Melhor Envio
        // multiplica pela quantidade e soma o volume do envio.
        $produtos = [];

        foreach ($items as $indice => $item) {
            $linha = MelhorEnvioService::productLine($item, $config['default_weight']);
            $linha['id'] = (string) ($item['slug'] ?? $item['id'] ?? 'peca-' . $indice);
            $produtos[] = $linha;
        }

        if ($produtos === []) {
            $produtos[] = MelhorEnvioService::productLine([
                'id' => 'pacote',
                'weight_grams' => $config['default_weight'],
                'price_cents' => $subtotalCents,
                'quantity' => 1,
            ], $config['default_weight']);
        }

        $servico = $this->melhorEnvio();
        $cotacoes = $servico->quote($config, $postalCode, $produtos, $subtotalCents);

        if ($cotacoes === []) {
            $this->lastError = $servico->lastError();

            return [];
        }

        $gratis = $config['free_from'] > 0 && $subtotalCents >= $config['free_from'];
        $opcoes = [];

        foreach ($cotacoes as $cotacao) {
            $preco = $gratis ? 0 : (int) $cotacao['price_cents'];
            $empresa = (string) ($cotacao['company'] ?? '');

            $opcoes[] = [
                'key' => (string) $cotacao['code'],
                'label' => $empresa !== ''
                    ? sprintf('%s · %s', $cotacao['name'], $empresa)
                    : (string) $cotacao['name'],
                'price_cents' => $preco,
                'price_label' => $this->priceLabel($preco),
                'days' => self::daysLabel((int) $cotacao['days'], (int) $cotacao['days']),
                'note' => $gratis
                    ? 'Frete grátis a partir de ' . Price::format($config['free_from']) . '.'
                    : '',
            ];
        }

        return $opcoes;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}>
     */
    private function tableOptions(string $state, int $subtotalCents, array $config): array
    {
        $taxa = $this->rates()[$state] ?? ['price_cents' => 0, 'min_days' => 0, 'max_days' => 0];
        $preco = max(0, (int) $taxa['price_cents']);
        $gratis = $config['free_from'] > 0 && $subtotalCents >= $config['free_from'];
        $precoFinal = $gratis ? 0 : $preco;

        $opcoes = [[
            'key' => self::DELIVERY,
            'label' => 'Entrega' . ($state !== '' ? ' · ' . $state : ''),
            'price_cents' => $precoFinal,
            'price_label' => $this->priceLabel($precoFinal),
            'days' => self::daysLabel((int) $taxa['min_days'], (int) $taxa['max_days']),
            'note' => $gratis && $preco > 0
                ? 'Frete grátis a partir de ' . Price::format($config['free_from']) . '.'
                : '',
        ]];

        return array_merge($opcoes, $this->pickupOptions($config));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}>
     */
    private function pickupOptions(array $config): array
    {
        if (!$config['pickup']) {
            return [];
        }

        return [[
            'key' => self::PICKUP,
            'label' => 'Retirada na loja',
            'price_cents' => 0,
            'price_label' => 'Grátis',
            'days' => 'Combinada com a loja',
            'note' => $config['pickup_text'],
        ]];
    }

    private function priceLabel(int $cents): string
    {
        return $cents === 0 ? 'Grátis' : Price::format($cents);
    }

    private function melhorEnvio(): MelhorEnvioService
    {
        return new MelhorEnvioService($this->pdo);
    }

    private function saveSetting(string $nome, string $valor): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO settings (name, value) VALUES (:name, :value)
             ON DUPLICATE KEY UPDATE value = :value_2'
        );

        $consulta->execute([':name' => $nome, ':value' => $valor, ':value_2' => $valor]);
    }

    /**
     * Segredo do aplicativo gravado cifrado; se não abrir (chave trocada,
     * valor antigo), devolve o que está lá para não perder a configuração.
     */
    private function decifrar(string $valor): string
    {
        if ($valor === '') {
            return '';
        }

        try {
            return Crypto::decrypt($valor);
        } catch (Throwable) {
            return $valor;
        }
    }

    private static function daysLabel(int $min, int $max): string
    {
        if ($min <= 0 && $max <= 0) {
            return 'Prazo a combinar';
        }

        if ($max <= 0) {
            return sprintf('%d dias úteis', $min);
        }

        if ($min <= 0) {
            return sprintf('Até %d dias úteis', $max);
        }

        if ($min === $max) {
            return sprintf('%d dias úteis', $min);
        }

        return sprintf('%d a %d dias úteis', $min, $max);
    }
}
