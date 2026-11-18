<?php

declare(strict_types=1);

namespace App\Support;

/*
 * CEP brasileiro: normalização, formatação e a UF de cada faixa.
 *
 * As faixas são as dos Correios e ficam aqui, fora de qualquer serviço
 * externo: o checkout descobre o estado do endereço mesmo sem internet, e o
 * frete por estado continua valendo quando a cotação dos Correios não está
 * configurada.
 */
final class Cep
{
    /** Faixas de CEP por UF, em ordem de código. */
    private const RANGES = [
        'SP' => [[1000000, 19999999]],
        'RJ' => [[20000000, 28999999]],
        'ES' => [[29000000, 29999999]],
        'MG' => [[30000000, 39999999]],
        'BA' => [[40000000, 48999999]],
        'SE' => [[49000000, 49999999]],
        'PE' => [[50000000, 56999999]],
        'AL' => [[57000000, 57999999]],
        'PB' => [[58000000, 58999999]],
        'RN' => [[59000000, 59999999]],
        'CE' => [[60000000, 63999999]],
        'PI' => [[64000000, 64999999]],
        'MA' => [[65000000, 65999999]],
        'PA' => [[66000000, 68899999]],
        'AP' => [[68900000, 68999999]],
        'AM' => [[69000000, 69299999], [69400000, 69899999]],
        'RR' => [[69300000, 69399999]],
        'AC' => [[69900000, 69999999]],
        'DF' => [[70000000, 72799999], [73000000, 73699999]],
        'GO' => [[72800000, 72999999], [73700000, 76799999]],
        'RO' => [[76800000, 76999999]],
        'TO' => [[77000000, 77999999]],
        'MT' => [[78000000, 78899999]],
        'MS' => [[79000000, 79999999]],
        'PR' => [[80000000, 87999999]],
        'SC' => [[88000000, 89999999]],
        'RS' => [[90000000, 99999999]],
    ];

    /**
     * Só os dígitos do CEP informado.
     */
    public static function normalize(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function isValid(?string $value): bool
    {
        $digitos = self::normalize($value);

        return strlen($digitos) === 8 && $digitos !== '00000000';
    }

    /**
     * "01234567" vira "01234-567"; o que não tiver oito dígitos volta como veio.
     */
    public static function format(?string $value): string
    {
        $digitos = self::normalize($value);

        return strlen($digitos) === 8
            ? substr($digitos, 0, 5) . '-' . substr($digitos, 5)
            : (string) $value;
    }

    /**
     * UF da faixa do CEP, ou null quando o CEP não é válido.
     */
    public static function stateFor(?string $value): ?string
    {
        if (!self::isValid($value)) {
            return null;
        }

        $numero = (int) self::normalize($value);

        foreach (self::RANGES as $uf => $faixas) {
            foreach ($faixas as [$inicio, $fim]) {
                if ($numero >= $inicio && $numero <= $fim) {
                    return $uf;
                }
            }
        }

        return null;
    }
}
