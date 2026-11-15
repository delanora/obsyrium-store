<?php

declare(strict_types=1);

/*
 * Seed de demonstração: categorias, 8 estampas, tamanhos, estoque e imagem.
 *
 *   php database/seed.php
 *
 * Recusa rodar com APP_ENV=production. Limpa as tabelas da vitrine antes de
 * inserir, então serve para ambiente local, nunca para dados reais.
 */

use App\Database\Connection;
use Config\Env;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

if (Env::get('APP_ENV', 'local') === 'production') {
    fwrite(STDERR, 'Seeder bloqueado: APP_ENV=production.' . PHP_EOL);
    exit(1);
}

$categorias = [
    ['Camisetas', 'camisetas', 1],
    ['Lançamentos', 'lancamentos', 2],
];

// nome, slug, cor, preço (centavos), preço "de" (centavos|null), categoria, estoque por tamanho
$produtos = [
    ['Camiseta Estampa 01', 'camiseta-estampa-01', 'Preta', 12990, null, 'lancamentos', 5],
    ['Camiseta Estampa 02', 'camiseta-estampa-02', 'Off white', 12790, 14990, 'lancamentos', 4],
    ['Camiseta Estampa 03', 'camiseta-estampa-03', 'Grafite', 12990, null, 'camisetas', 0],
    ['Camiseta Estampa 04', 'camiseta-estampa-04', 'Preta', 13990, null, 'camisetas', 6],
    ['Camiseta Estampa 05', 'camiseta-estampa-05', 'Marrom', 13990, null, 'camisetas', 3],
    ['Camiseta Estampa 06', 'camiseta-estampa-06', 'Off white', 12990, null, 'camisetas', 7],
    ['Camiseta Estampa 07', 'camiseta-estampa-07', 'Verde musgo', 14990, null, 'camisetas', 5],
    ['Camiseta Estampa 08', 'camiseta-estampa-08', 'Bordô', 12990, null, 'camisetas', 0],
];

$tamanhos = ['PP', 'P', 'M', 'G', 'GG'];

$pdo = Connection::get();

try {
    $pdo->beginTransaction();

    $pdo->exec('DELETE FROM product_images');
    $pdo->exec('DELETE FROM stock');
    $pdo->exec('DELETE FROM product_variants');
    $pdo->exec('DELETE FROM products');
    $pdo->exec('DELETE FROM categories');

    $inserirCategoria = $pdo->prepare('INSERT INTO categories (name, slug, position) VALUES (:name, :slug, :position)');
    $idsCategorias = [];

    foreach ($categorias as [$nome, $slug, $posicao]) {
        $inserirCategoria->execute(['name' => $nome, 'slug' => $slug, 'position' => $posicao]);
        $idsCategorias[$slug] = (int) $pdo->lastInsertId();
    }

    $inserirProduto = $pdo->prepare(
        'INSERT INTO products (category_id, name, slug, color, price_cents, compare_price_cents, description)
         VALUES (:category_id, :name, :slug, :color, :price, :compare_price, :description)'
    );
    $inserirVariacao = $pdo->prepare('INSERT INTO product_variants (product_id, size, sku) VALUES (:product_id, :size, :sku)');
    $inserirEstoque = $pdo->prepare('INSERT INTO stock (variant_id, quantity) VALUES (:variant_id, :quantity)');
    $inserirImagem = $pdo->prepare(
        'INSERT INTO product_images (product_id, path, alt, position) VALUES (:product_id, :path, :alt, 0)'
    );

    foreach ($produtos as [$nome, $slug, $cor, $preco, $precoAntigo, $categoria, $quantidade]) {
        $inserirProduto->execute([
            'category_id' => $idsCategorias[$categoria],
            'name' => $nome,
            'slug' => $slug,
            'color' => $cor,
            'price' => $preco,
            'compare_price' => $precoAntigo,
            'description' => 'Camiseta em algodão de gramatura alta, modelagem oversized e estampa serigráfica.',
        ]);

        $produtoId = (int) $pdo->lastInsertId();

        foreach ($tamanhos as $tamanho) {
            $inserirVariacao->execute([
                'product_id' => $produtoId,
                'size' => $tamanho,
                'sku' => strtoupper($slug) . '-' . $tamanho,
            ]);

            $inserirEstoque->execute([
                'variant_id' => (int) $pdo->lastInsertId(),
                'quantity' => $quantidade,
            ]);
        }

        $inserirImagem->execute([
            'product_id' => $produtoId,
            'path' => '/assets/images/placeholder.svg',
            'alt' => sprintf('Camiseta %s, modelagem oversized, na cor %s', $nome, $cor),
        ]);
    }

    $pdo->commit();
} catch (Throwable $erro) {
    $pdo->rollBack();
    fwrite(STDERR, 'Falha no seed: ' . $erro->getMessage() . PHP_EOL);
    exit(1);
}

printf(
    '  Seed concluído: %d categorias, %d produtos, %d variações, %d linhas de estoque.' . PHP_EOL,
    count($categorias),
    count($produtos),
    count($produtos) * count($tamanhos),
    count($produtos) * count($tamanhos)
);
