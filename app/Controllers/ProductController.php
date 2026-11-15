<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\ProductRepository;
use App\Support\Price;

final class ProductController
{
    private const PIX_DISCOUNT = 5;
    private const INSTALLMENTS = 3;
    private const FALLBACK_IMAGE = '/assets/images/placeholder.svg';
    private const LOW_STOCK = 3;

    /**
     * Página individual do produto: galeria, preço, tamanhos e estoque por
     * tamanho, tudo vindo do banco. Slug inexistente ou produto inativo cai
     * na página 404, sem revelar detalhe interno.
     *
     * @param array<string, string> $params
     */
    public function show(array $params): void
    {
        $basePath = dirname(__DIR__, 2);
        $slug = trim(rawurldecode($params['slug'] ?? ''));

        $repository = new ProductRepository(Connection::get());
        $linha = $slug !== '' ? $repository->findBySlug($slug) : null;

        if ($linha === null) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');

            require $basePath . '/resources/views/errors/404.php';

            return;
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $id = (int) $linha['id'];
        $nome = (string) $linha['name'];
        $cor = (string) $linha['color'];
        $preco = (int) $linha['price_cents'];
        $precoAntigo = $linha['compare_price_cents'] !== null ? (int) $linha['compare_price_cents'] : null;

        $imagens = array_map(
            static fn (array $imagem): array => [
                'src' => ((string) $imagem['path']) !== '' ? (string) $imagem['path'] : self::FALLBACK_IMAGE,
                'alt' => (string) $imagem['alt'],
            ],
            $repository->images($id)
        );

        if ($imagens === []) {
            $imagens[] = [
                'src' => self::FALLBACK_IMAGE,
                'alt' => sprintf('%s na cor %s', $nome, $cor),
            ];
        }

        $tamanhos = [];
        $estoqueTotal = 0;

        foreach ($repository->variantsWithStock($id) as $variacao) {
            $quantidade = (int) $variacao['quantity'];
            $estoqueTotal += $quantidade;

            $tamanhos[] = [
                'id' => (int) $variacao['id'],
                'size' => (string) $variacao['size'],
                'available' => $quantidade > 0,
            ];
        }

        $product = [
            'name' => $nome,
            'slug' => (string) $linha['slug'],
            'category' => (string) $linha['category_name'],
            'color' => $cor,
            'material' => (string) $linha['material'],
            'description' => trim((string) ($linha['description'] ?? '')),
            'images' => $imagens,
            'sizes' => $tamanhos,
            'price' => Price::format($preco),
            'oldPrice' => $precoAntigo !== null ? Price::format($precoAntigo) : null,
            'pix' => Price::withDiscount($preco, self::PIX_DISCOUNT),
            'installments' => Price::installment($preco, self::INSTALLMENTS),
            'discount' => $precoAntigo !== null ? Price::discountPercent($precoAntigo, $preco) : 0,
            'soldOut' => $estoqueTotal === 0,
            'stock' => $this->stock($estoqueTotal),
        ];

        $title = sprintf('%s — Obsyrium Store', $nome);
        $content = $basePath . '/resources/views/product/show.php';

        require $basePath . '/resources/views/layouts/site.php';
    }

    /**
     * Texto e estado do estoque para a etiqueta da página.
     *
     * @return array{label: string, state: string}
     */
    private function stock(int $quantity): array
    {
        if ($quantity === 0) {
            return ['label' => 'Esgotado', 'state' => 'out'];
        }

        if ($quantity <= self::LOW_STOCK) {
            return ['label' => sprintf('Últimas %d unidades', $quantity), 'state' => 'low'];
        }

        return ['label' => 'Em estoque', 'state' => 'in'];
    }
}
