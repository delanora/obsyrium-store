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
        $cores = $servico->colors();
        $precos = $servico->priceBounds();
        $totalDaLoja = $servico->totalProducts();

        $title = 'Catálogo — Obsyrium Store';
        $content = $basePath . '/resources/views/catalog/index.php';

        require $basePath . '/resources/views/layouts/site.php';
    }

    /**
     * Página de uma categoria principal: `/categoria/{slug}`.
     *
     * @param array<string, string> $params
     */
    public function category(array $params): void
    {
        $this->showCategory(trim(rawurldecode($params['slug'] ?? '')), null);
    }

    /**
     * Página de uma subcategoria: `/categoria/{categoria}/{sub}`.
     *
     * @param array<string, string> $params
     */
    public function subcategory(array $params): void
    {
        // O caminho é `/categoria/{mãe}/{filha}`: a primeira parte é a mãe.
        $this->showCategory(
            trim(rawurldecode($params['sub'] ?? '')),
            trim(rawurldecode($params['categoria'] ?? ''))
        );
    }

    /**
     * Categoria e subcategoria usam a mesma listagem do catálogo: filtros,
     * ordenação, busca e paginação continuam funcionando, só que presos à
     * categoria do caminho. O caminho canônico da subcategoria inclui a mãe —
     * pedir só a filha redireciona para lá.
     */
    private function showCategory(string $slug, ?string $parentSlug): void
    {
        $basePath = dirname(__DIR__, 2);
        $servico = new CatalogService(Connection::get());
        $categoria = $slug !== '' ? $servico->categoryWithChildren($slug) : null;

        // A subcategoria precisa da mãe certa no caminho; sem ela, o caminho
        // completo é o canônico.
        if ($categoria !== null && $parentSlug === null && $categoria['parent'] !== null) {
            header('Location: /categoria/' . $categoria['parent']['slug'] . '/' . $categoria['slug'], true, 301);

            return;
        }

        if ($categoria !== null && $parentSlug !== null
            && ($categoria['parent'] === null || $categoria['parent']['slug'] !== $parentSlug)) {
            $categoria = null;
        }

        if ($categoria === null) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');

            require $basePath . '/resources/views/errors/404.php';

            return;
        }

        Navegacao::registrar('/categoria/' . ($categoria['parent'] !== null ? $categoria['parent']['slug'] . '/' : '') . $categoria['slug']);

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $estado = $servico->stateForCategory($_GET, $categoria);
        $resultado = $servico->results($estado);

        $produtos = array_map(
            static fn (array $linha): array => ProductCard::fromRow($linha),
            $resultado['items']
        );

        $categorias = $servico->categories();
        $tamanhos = $servico->sizes();
        $cores = $servico->colors();
        $precos = $servico->priceBounds();
        $totalDaLoja = $servico->totalProducts();

        $urlBase = '/categoria/' . ($categoria['parent'] !== null ? $categoria['parent']['slug'] . '/' : '') . $categoria['slug'];
        $categoriaPagina = $categoria;

        $title = sprintf('%s — Obsyrium Store', (string) $categoria['name']);
        $content = $basePath . '/resources/views/catalog/index.php';

        require $basePath . '/resources/views/layouts/site.php';
    }
}
