<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Price;
use PDO;
use Throwable;

/**
 * Frete próprio, sem integração externa.
 *
 * A loja define uma taxa e um prazo por estado, o valor a partir do qual o
 * frete sai de graça e se a retirada está liberada. O checkout monta as opções
 * a partir do estado do endereço escolhido, e o pedido grava o que foi
 * escolhido com o preço do momento — mudar a tabela depois não reescreve o
 * que já foi vendido.
 *
 * A tabela nasce com todos os estados em zero (entrega grátis, prazo a
 * combinar): nada é inventado no lugar da loja, e o checkout continua
 * funcionando enquanto o painel não configura.
 */
final class ShippingService
{
    public const DELIVERY = 'entrega';

    public const PICKUP = 'retirada';

    /** Unidades da federação, na ordem das regiões. */
    public const STATES = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
        'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    private const FREE_FROM_KEY = 'shipping_free_from_cents';

    private const PICKUP_KEY = 'shipping_pickup_enabled';

    private const PICKUP_TEXT_KEY = 'shipping_pickup_text';

    /** Teto do frete: R$ 1.000,00. */
    private const MAX_PRICE_CENTS = 100000;

    private const MAX_DAYS = 90;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Escolhas que valem para a loja inteira.
     *
     * @return array{free_from: int, pickup: bool, pickup_text: string}
     */
    public function settings(): array
    {
        $valores = [];

        foreach ($this->pdo->query('SELECT name, value FROM settings') as $linha) {
            $valores[(string) $linha['name']] = (string) $linha['value'];
        }

        return [
            'free_from' => max(0, (int) ($valores[self::FREE_FROM_KEY] ?? '0')),
            'pickup' => ($valores[self::PICKUP_KEY] ?? '0') === '1',
            'pickup_text' => (string) ($valores[self::PICKUP_TEXT_KEY] ?? ''),
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
     * Opções de entrega para um estado, já com o frete grátis aplicado.
     *
     * @return list<array{key: string, label: string, price_cents: int, price_label: string, days: string, note: string}>
     */
    public function optionsFor(string $state, int $subtotalCents): array
    {
        $uf = strtoupper(trim($state));
        $config = $this->settings();
        $taxa = $this->rates()[$uf] ?? ['price_cents' => 0, 'min_days' => 0, 'max_days' => 0];

        $preco = max(0, (int) $taxa['price_cents']);
        $gratis = $config['free_from'] > 0 && $subtotalCents >= $config['free_from'];
        $precoFinal = $gratis ? 0 : $preco;

        $opcoes = [[
            'key' => self::DELIVERY,
            'label' => 'Entrega' . ($uf !== '' ? ' · ' . $uf : ''),
            'price_cents' => $precoFinal,
            'price_label' => $precoFinal === 0 ? 'Grátis' : Price::format($precoFinal),
            'days' => self::daysLabel((int) $taxa['min_days'], (int) $taxa['max_days']),
            'note' => $gratis && $preco > 0
                ? 'Frete grátis a partir de ' . Price::format($config['free_from']) . '.'
                : '',
        ]];

        if ($config['pickup']) {
            $opcoes[] = [
                'key' => self::PICKUP,
                'label' => 'Retirada na loja',
                'price_cents' => 0,
                'price_label' => 'Grátis',
                'days' => 'Combinada com a loja',
                'note' => $config['pickup_text'],
            ];
        }

        return $opcoes;
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
     * Salva as taxas e as escolhas da loja. Preço vazio é zero (grátis), prazo
     * vazio é zero (a combinar).
     *
     * @param array<string, mixed> $dados
     *
     * @return array{errors: list<string>, values: array<string, mixed>}
     */
    public function save(array $dados): array
    {
        $erros = [];

        $valores = [
            'free_from' => trim((string) ($dados['free_from'] ?? '')),
            'pickup' => isset($dados['pickup']),
            'pickup_text' => trim((string) ($dados['pickup_text'] ?? '')),
            'rates' => [],
        ];

        $freteGratis = Price::parse($valores['free_from']);

        if ($freteGratis === null || $freteGratis > self::MAX_PRICE_CENTS) {
            $erros[] = 'Frete grátis: informe um valor em reais ou deixe vazio para desligar.';
        }

        if (mb_strlen($valores['pickup_text']) > 160) {
            $erros[] = 'Instruções de retirada: até 160 caracteres.';
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

            $this->pdo->commit();
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }

        return ['errors' => [], 'values' => $valores];
    }

    private function saveSetting(string $nome, string $valor): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO settings (name, value) VALUES (:name, :value)
             ON DUPLICATE KEY UPDATE value = :value_2'
        );

        $consulta->execute([':name' => $nome, ':value' => $valor, ':value_2' => $valor]);
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
