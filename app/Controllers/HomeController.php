<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\ProductRepository;
use App\Support\Navegacao;
use App\Support\Price;

final class HomeController
{
    private const SHOWCASE_LIMIT = 8;
    private const PIX_DISCOUNT = 5;
    private const INSTALLMENTS = 3;
    private const FALLBACK_IMAGE = '/assets/images/placeholder.svg';

    public function index(): void
    {
        Navegacao::registrar('/');

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);

        $repository = new ProductRepository(Connection::get());
        $products = array_map(
            fn (array $linha): array => $this->toCard($linha),
            $repository->showcase(self::SHOWCASE_LIMIT)
        );

        $title = 'Obsyrium Store — camisetas com estampas autorais';
        $content = $basePath . '/resources/views/home/index.php';

        require $basePath . '/resources/views/layouts/site.php';
    }

    /**
     * Converte a linha do banco no formato que o card da vitrine consome.
     *
     * @param array<string, mixed> $linha
     * @return array<string, mixed>
     */
    private function toCard(array $linha): array
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
