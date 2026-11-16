<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AdminController;
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
    $router->post('/checkout', [CheckoutController::class, 'place']);

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

    $router->get('/admin', [AdminController::class, 'dashboard']);
    $router->get('/admin/login', [AdminController::class, 'showLogin']);
    $router->post('/admin/login', [AdminController::class, 'login']);
    $router->post('/admin/logout', [AdminController::class, 'logout']);
    $router->get('/admin/pedidos', [AdminController::class, 'orders']);
    $router->get('/admin/pedidos/{number}', [AdminController::class, 'order']);
    $router->post('/admin/pedidos/{number}/status', [AdminController::class, 'changeStatus']);
    $router->get('/admin/estoque', [AdminController::class, 'stock']);
    $router->post('/admin/estoque/saldo', [AdminController::class, 'saveStock']);
    $router->post('/admin/estoque/fora-de-linha', [AdminController::class, 'discontinue']);
    $router->post('/admin/estoque/voltar', [AdminController::class, 'restore']);
    $router->get('/admin/estoque/produto/{id}', [AdminController::class, 'product']);
    $router->post('/admin/estoque/produto/{id}', [AdminController::class, 'saveProduct']);
    $router->post('/admin/estoque/produto/{id}/saldo', [AdminController::class, 'saveProductStock']);
    $router->post('/admin/estoque/produto/{id}/foto', [AdminController::class, 'uploadPhoto']);
    $router->post('/admin/estoque/produto/{id}/foto/remover', [AdminController::class, 'removePhoto']);
};
