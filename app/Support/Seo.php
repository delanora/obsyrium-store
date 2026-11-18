<?php

declare(strict_types=1);

namespace App\Support;

use Config\Env;

/**
 * SEO das páginas públicas: endereço canônico absoluto, descrição curta e os
 * dados estruturados entregues ao layout.
 *
 * O endereço base vem de `APP_URL` no `.env` — assim o mesmo código serve
 * `http://10.8.0.9` no desenvolvimento e o domínio em produção, sem trocar
 * nada. Sem `APP_URL`, o canônico sai relativo e continua correto para o
 * navegador.
 */
final class Seo
{
    public const SITE = 'Obsyrium Store';

    /** Quantos caracteres a descrição pode ter antes de ser cortada. */
    public const DESCRIPTION_LIMIT = 160;

    /** Áreas que o robô de busca não deve indexar. */
    public const ROBOTS_DISALLOW = [
        '/admin',
        '/carrinho',
        '/checkout',
        '/conta',
        '/pedido',
        '/login',
        '/cadastro',
    ];

    /**
     * Endereço absoluto de um caminho da loja (`/produto/x`).
     */
    public static function canonical(string $path): string
    {
        $caminho = '/' . ltrim($path, '/');
        $base = rtrim(trim((string) Env::get('APP_URL', '')), '/');

        return $base === '' ? $caminho : $base . $caminho;
    }

    /**
     * Descrição em uma linha, sem tags, cortada em `DESCRIPTION_LIMIT`. Texto
     * vazio cai no padrão da página.
     */
    public static function description(string $texto, string $padrao): string
    {
        $limpo = trim((string) preg_replace('/\s+/u', ' ', strip_tags($texto)));

        if ($limpo === '') {
            $limpo = trim((string) preg_replace('/\s+/u', ' ', $padrao));
        }

        if (mb_strlen($limpo) > self::DESCRIPTION_LIMIT) {
            $limpo = rtrim(mb_substr($limpo, 0, self::DESCRIPTION_LIMIT - 1)) . '…';
        }

        return $limpo;
    }

    /**
     * Dados estruturados de um produto (schema.org/Product), com a oferta e a
     * disponibilidade que a página mostra.
     *
     * @param list<string> $imagens caminhos públicos das fotos
     *
     * @return array<string, mixed>
     */
    public static function product(
        string $nome,
        string $descricao,
        string $url,
        array $imagens,
        int $precoCents,
        bool $emEstoque
    ): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $nome,
            'description' => $descricao,
            'image' => array_map([self::class, 'canonical'], $imagens),
            'brand' => ['@type' => 'Brand', 'name' => self::SITE],
            'offers' => [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => 'BRL',
                'price' => number_format($precoCents / 100, 2, '.', ''),
                'availability' => $emEstoque ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ];
    }
}
