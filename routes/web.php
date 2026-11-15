<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\ProductController;
use App\Routing\Router;

/*
 * Registro das rotas da aplicação.
 *
 * Retorna um callable que recebe o dispatcher; o front controller em
 * public/index.php executa esse callable antes do despacho.
 */

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/produto/{slug}', [ProductController::class, 'show']);
};
