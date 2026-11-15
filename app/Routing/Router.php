<?php

declare(strict_types=1);

namespace App\Routing;

use RuntimeException;

final class Router
{
    /**
     * @var array<string, array<string, array{class-string, string}>>
     */
    private array $routes = [];

    /**
     * @param array{class-string, string} $handler
     */
    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * @param array{class-string, string} $handler
     */
    public function add(string $method, string $path, array $handler): void
    {
        $this->routes[strtoupper($method)][$this->normalize($path)] = $handler;
    }

    /**
     * Despacha a requisição para o handler da rota correspondente.
     *
     * @return bool true quando havia rota registrada para método e caminho.
     */
    public function dispatch(string $method, string $uri): bool
    {
        $path = $this->normalize((string) parse_url($uri, PHP_URL_PATH));
        $handler = $this->routes[strtoupper($method)][$path] ?? null;

        if ($handler === null) {
            return false;
        }

        [$controllerClass, $action] = $handler;

        if (!class_exists($controllerClass) || !method_exists($controllerClass, $action)) {
            throw new RuntimeException(
                sprintf('Rota com handler inválido: %s::%s', $controllerClass, $action)
            );
        }

        (new $controllerClass())->{$action}();

        return true;
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
