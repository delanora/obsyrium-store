<?php

declare(strict_types=1);

namespace App\Support;

/*
 * Formatação de valores monetários guardados em centavos.
 */
final class Price
{
    public static function format(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    public static function installment(int $cents, int $times): string
    {
        // Arredonda para cima para o total das parcelas não ficar abaixo do preço.
        $parcela = (int) ceil($cents / $times);

        return sprintf('%dx de %s sem juros', $times, self::format($parcela));
    }

    public static function withDiscount(int $cents, int $percent): string
    {
        return self::format((int) round($cents * (100 - $percent) / 100));
    }

    public static function discountPercent(int $from, int $to): int
    {
        if ($from <= 0 || $to >= $from) {
            return 0;
        }

        return (int) round((1 - $to / $from) * 100);
    }
}
