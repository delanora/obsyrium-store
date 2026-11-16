<?php

declare(strict_types=1);

use App\Controllers\AccountController;
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

    $router->get('/checkout', [CheckoutController::class, 'show']);
    $router->post('/checkout', [CheckoutController::class, 'confirmAddress']);

    $router->get('/conta', [AccountController::class, 'show']);
    $router->post('/conta', [AccountController::class, 'saveProfile']);

    $router->get('/conta/enderecos', [AccountController::class, 'addresses']);
    $router->post('/conta/enderecos/salvar', [AccountController::class, 'saveAddress']);
    $router->post('/conta/enderecos/remover', [AccountController::class, 'removeAddress']);
    $router->post('/conta/enderecos/padrao', [AccountController::class, 'defaultAddress']);
    $router->get('/conta/enderecos/{id}', [AccountController::class, 'editAddress']);

    $router->get('/conta/compras', [AccountController::class, 'orders']);

    $router->get('/cadastro', [AuthController::class, 'showRegister']);
    $router->post('/cadastro', [AuthController::class, 'register']);
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout']);
};
