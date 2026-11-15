<?php

declare(strict_types=1);

/*
 * Front controller da aplicação.
 *
 * O document root aponta para public/, portanto este é o único arquivo PHP
 * acessível diretamente pelo servidor web.
 */

use App\Routing\Router;

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

$router = new Router();

(require $basePath . '/routes/web.php')($router);

$matched = $router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/'
);

if (!$matched) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');

    require $basePath . '/resources/views/errors/404.php';
}
