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
 * - `correios`: cotação real de PAC e SEDEX na API oficial, com o peso das
 *   peças do carrinho. Exige contrato e credenciais do CWS no painel; se a
 *   cotação falhar, a opção cai na tabela por estado em vez de deixar o cliente
 *   sem entrega.
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

    public const MODE_CORREIOS = 'correios';

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

    private const USER_KEY = 'shipping_correios_user';

    private const PASSWORD_KEY = 'shipping_correios_password';

    private const CONTRACT_KEY = 'shipping_correios_contract';

    private const DR_KEY = 'shipping_correios_dr';

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
     * Último motivo de a cotação dos Correios não ter sido usada (para a tela
     * de teste do painel). Vazio quando deu tudo certo ou nem foi tentado.
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
     *     correios_user: string,
     *     correios_password: string,
     *     correios_password_set: bool,
     *     correios_contract: string,
     *     correios_dr: int,
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
        $senha = $this->decifrar((string) ($valores[self::PASSWORD_KEY] ?? ''));

        return [
            'free_from' => max(0, (int) ($valores[self::FREE_FROM_KEY] ?? '0')),
            'pickup' => ($valores[self::PICKUP_KEY] ?? '0') === '1',
            'pickup_text' => (string) ($valores[self::PICKUP_TEXT_KEY] ?? ''),
            'mode' => $modo === self::MODE_CORREIOS ? self::MODE_CORREIOS : self::MODE_TABLE,
            'origin_postal_code' => Cep::normalize((string) ($valores[self::ORIGIN_KEY] ?? '')),
            'correios_user' => (string) ($valores[self::USER_KEY] ?? ''),
            'correios_password' => $senha,
            'correios_password_set' => $senha !== '',
            'correios_contract' => (string) ($valores[self::CONTRACT_KEY] ?? ''),
            'correios_dr' => max(0, (int) ($valores[self::DR_KEY] ?? '0')),
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
     * `correios` e a cotação responde, PAC e SEDEX com preço e prazo reais.
     *
     * @return list<array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}>
     */
    public function optionsForCep(
        string $postalCode,
        int $subtotalCents,
        int $weightGrams = 0,
        string $stateFallback = ''
    ): array {
        $config = $this->settings();
        $cep = Cep::normalize($postalCode);
        $uf = Cep::stateFor($cep) ?? strtoupper(trim($stateFallback));

        if (!in_array($uf, self::STATES, true)) {
            return [];
        }

        $opcoes = [];

        if ($config['mode'] === self::MODE_CORREIOS) {
            $opcoes = $this->correiosOptions($config, $cep, $subtotalCents, $weightGrams);
        }

        // Sem cotação (modo tabela, credenciais incompletas ou Correios fora do
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
     * @return array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}|null
     */
    public function optionForCep(
        string $postalCode,
        string $key,
        int $subtotalCents,
        int $weightGrams = 0,
        string $stateFallback = ''
    ): ?array {
        foreach ($this->optionsForCep($postalCode, $subtotalCents, $weightGrams, $stateFallback) as $opcao) {
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
     * Salva as taxas, as escolhas da loja e a configuração dos Correios.
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
            'correios_user' => trim((string) ($dados['correios_user'] ?? '')),
            'correios_contract' => trim((string) ($dados['correios_contract'] ?? '')),
            'correios_dr' => trim((string) ($dados['correios_dr'] ?? '')),
            'default_weight' => trim((string) ($dados['default_weight'] ?? '')),
            'password' => (string) ($dados['correios_password'] ?? ''),
            'rates' => [],
        ];

        if (!in_array($valores['mode'], [self::MODE_TABLE, self::MODE_CORREIOS], true)) {
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

        if ($valores['correios_dr'] !== '' && preg_match('/^\d{1,4}$/', $valores['correios_dr']) !== 1) {
            $erros[] = 'DR dos Correios: use só números.';
        }

        // A senha é opcional no formulário: em branco mantém a que já está
        // gravada, para a loja não precisar digitá-la de novo a cada ajuste.
        $senhaNova = (string) $valores['password'];

        if ($valores['mode'] === self::MODE_CORREIOS) {
            if (!Cep::isValid($valores['origin_postal_code'])) {
                $erros[] = 'CEP de origem: informe os oito dígitos do CEP de onde as peças saem.';
            }

            if ($valores['correios_user'] === '') {
                $erros[] = 'Usuário dos Correios: informe o usuário do CWS.';
            }

            if ($valores['correios_contract'] === '') {
                $erros[] = 'Contrato dos Correios: informe o número do contrato.';
            }

            if ($senhaNova === '' && !$atual['correios_password_set']) {
                $erros[] = 'Senha dos Correios: informe a senha do CWS.';
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
            $this->saveSetting(self::USER_KEY, $valores['correios_user']);
            $this->saveSetting(self::CONTRACT_KEY, $valores['correios_contract']);
            $this->saveSetting(self::DR_KEY, (string) (int) $valores['correios_dr']);
            $this->saveSetting(self::WEIGHT_KEY, (string) (int) $pesoPadrao);

            if ($senhaNova !== '') {
                $this->saveSetting(self::PASSWORD_KEY, Crypto::encrypt($senhaNova));
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
     * PAC e SEDEX cotados para o CEP, com frete grátis aplicado por cima.
     *
     * @param array<string, mixed> $config
     *
     * @return list<array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}>
     */
    private function correiosOptions(
        array $config,
        string $postalCode,
        int $subtotalCents,
        int $weightGrams
    ): array {
        $this->lastError = '';

        if (!Cep::isValid($postalCode)) {
            $this->lastError = 'CEP de destino inválido.';

            return [];
        }

        $peso = $weightGrams > 0 ? $weightGrams : $config['default_weight'];
        $servico = new CorreiosService($this->pdo);
        $cotacoes = $servico->quote($config, $postalCode, $peso);

        if ($cotacoes === []) {
            $this->lastError = $servico->lastError();

            return [];
        }

        $gratis = $config['free_from'] > 0 && $subtotalCents >= $config['free_from'];
        $opcoes = [];

        foreach ($cotacoes as $cotacao) {
            $preco = $gratis ? 0 : (int) $cotacao['price_cents'];

            $opcoes[] = [
                'key' => $cotacao['code'],
                'label' => $cotacao['name'],
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

    private function saveSetting(string $nome, string $valor): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO settings (name, value) VALUES (:name, :value)
             ON DUPLICATE KEY UPDATE value = :value_2'
        );

        $consulta->execute([':name' => $nome, ':value' => $valor, ':value_2' => $valor]);
    }

    /**
     * Senha dos Correios gravada cifrada; se não abrir (chave trocada, valor
     * antigo), devolve o que está lá para o painel não perder a configuração.
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
