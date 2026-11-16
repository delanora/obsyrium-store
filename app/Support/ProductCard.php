<?php

declare(strict_types=1);

namespace App\Support;

/*
 * Card da vitrine: recebe a linha do banco (produto, estoque somado e imagem
 * principal) e devolve os campos que a view do card usa.
 *
 * Fica aqui, e não em cada controller, para a home e o catálogo mostrarem
 * exatamente o mesmo card — preço, selo, imagem e link.
 */
final class ProductCard
{
    public const PIX_DISCOUNT = 5;
    public const INSTALLMENTS = 3;
    public const FALLBACK_IMAGE = '/assets/images/placeholder.svg';

    /**
     * @param array<string, mixed> $linha
     *
     * @return array<string, mixed>
     */
    public static function fromRow(array $linha): array
    {
        $preco = (int) $linha['price_cents'];
        $precoAntigo = $linha['compare_price_cents'] !== null ? (int) $linha['compare_price_cents'] : null;
        $estoque = (int) $linha['stock_quantity'];
        $desconto = $precoAntigo !== null ? Price::discountPercent($precoAntigo, $preco) : 0;
        $imagem = (string) ($linha['image_path'] ?? '');

        $selo = null;
        $seloAcento = false;

        if ($estoque === 0) {
            $selo = 'Esgotado';
        } elseif ($desconto > 0) {
            $selo = '-' . $desconto . '%';
            $seloAcento = true;
        }

        return [
            'name' => (string) $linha['name'],
            'href' => '/produto/' . rawurlencode((string) $linha['slug']),
            'color' => (string) $linha['color'],
            'material' => (string) $linha['material'],
            'image' => $imagem !== '' ? $imagem : self::FALLBACK_IMAGE,
            'imageAlt' => (string) ($linha['image_alt'] ?? ''),
            'price' => Price::format($preco),
            'oldPrice' => $precoAntigo !== null ? Price::format($precoAntigo) : null,
            'pix' => Price::withDiscount($preco, self::PIX_DISCOUNT),
            'installments' => Price::installment($preco, self::INSTALLMENTS),
            'badge' => $selo,
            'badgeAccent' => $seloAcento,
            'soldOut' => $estoque === 0,
        ];
    }
}
