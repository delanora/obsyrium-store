<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\CatalogService;
use App\Support\Navegacao;
use App\Support\ProductCard;

final class CatalogController
{
    /**
     * Catálogo: todos os produtos ativos, com filtros, ordenação e paginação.
     * O estado mora na URL, então o link é compartilhável e o voltar do
     * navegador devolve os mesmos filtros.
     */
    public function index(): void
    {
        Navegacao::registrar('/catalogo');

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $servico = new CatalogService(Connection::get());

        $estado = $servico->state($_GET);
        $resultado = $servico->results($estado);

        $produtos = array_map(
            static fn (array $linha): array => ProductCard::fromRow($linha),
            $resultado['items']
        );

        $categorias = $servico->categories();
        $tamanhos = $servico->sizes();
        $totalDaLoja = $servico->totalProducts();

        $title = 'Catálogo — Obsyrium Store';
        $content = $basePath . '/resources/views/catalog/index.php';

        require $basePath . '/resources/views/layouts/site.php';
    }
}
