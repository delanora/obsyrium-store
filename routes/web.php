<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
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

    $router->get('/carrinho', [CartController::class, 'show']);

    $router->post('/carrinho/adicionar', [CartController::class, 'add']);
    $router->post('/carrinho/atualizar', [CartController::class, 'update']);
    $router->post('/carrinho/remover', [CartController::class, 'remove']);

    $router->get('/checkout', [CheckoutController::class, 'start']);

    $router->get('/cadastro', [AuthController::class, 'showRegister']);
    $router->post('/cadastro', [AuthController::class, 'register']);
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout']);
};
