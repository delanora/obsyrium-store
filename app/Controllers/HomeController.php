<?php

declare(strict_types=1);

namespace App\Controllers;

final class HomeController
{
    public function index(): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);

        $title = 'Obsyrium Store — camisetas com estampas autorais';
        $content = $basePath . '/resources/views/home/index.php';

        require $basePath . '/resources/views/layouts/site.php';
    }
}
