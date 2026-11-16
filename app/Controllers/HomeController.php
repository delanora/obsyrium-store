<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\ProductRepository;
use App\Support\Navegacao;
use App\Support\ProductCard;

final class HomeController
{
    private const SHOWCASE_LIMIT = 8;

    public function index(): void
    {
        Navegacao::registrar('/');

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);

        $repository = new ProductRepository(Connection::get());
        $products = array_map(
            static fn (array $linha): array => ProductCard::fromRow($linha),
            $repository->showcase(self::SHOWCASE_LIMIT)
        );

        $title = 'Obsyrium Store — camisetas com estampas autorais';
        $content = $basePath . '/resources/views/home/index.php';

        require $basePath . '/resources/views/layouts/site.php';
    }
}
