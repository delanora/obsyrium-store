<?php

declare(strict_types=1);

namespace App\Routing;

use RuntimeException;

final class Router
{
    /**
     * @var array<string, list<array{regex: string, params: list<string>, handler: array{class-string, string}}>>
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
    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * Registra a rota. Segmentos entre chaves, como `/produto/{slug}`, viram
     * parâmetros capturados do caminho e chegam ao handler num array nomeado.
     *
     * @param array{class-string, string} $handler
     */
    public function add(string $method, string $path, array $handler): void
    {
        [$regex, $params] = $this->compile($this->normalize($path));

        $this->routes[strtoupper($method)][] = [
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
        ];
    }

    /**
     * Despacha a requisição para o handler da primeira rota que casar.
     *
     * @return bool true quando havia rota registrada para método e caminho.
     */
    public function dispatch(string $method, string $uri): bool
    {
        $path = $this->normalize((string) parse_url($uri, PHP_URL_PATH));

        foreach ($this->routes[strtoupper($method)] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];

            foreach ($route['params'] as $index => $name) {
                $params[$name] = $matches[$index + 1];
            }

            [$controllerClass, $action] = $route['handler'];

            if (!class_exists($controllerClass) || !method_exists($controllerClass, $action)) {
                throw new RuntimeException(
                    sprintf('Rota com handler inválido: %s::%s', $controllerClass, $action)
                );
            }

            $controller = new $controllerClass();

            if ($params === []) {
                $controller->{$action}();
            } else {
                $controller->{$action}($params);
            }

            return true;
        }

        return false;
    }

    /**
     * Converte o caminho em regex ancorada e coleta os nomes dos parâmetros.
     *
     * @return array{string, list<string>}
     */
    private function compile(string $path): array
    {
        $regex = '';
        $params = [];
        $offset = 0;
        $length = strlen($path);

        while ($offset < $length) {
            $open = strpos($path, '{', $offset);

            if ($open === false) {
                $regex .= preg_quote(substr($path, $offset), '#');
                break;
            }

            $close = strpos($path, '}', $open);

            if ($close === false) {
                throw new RuntimeException(sprintf('Rota com parâmetro não fechado: %s', $path));
            }

            $regex .= preg_quote(substr($path, $offset, $open - $offset), '#');
            $regex .= '([^/]+)';
            $params[] = substr($path, $open + 1, $close - $open - 1);
            $offset = $close + 1;
        }

        return ['#^' . $regex . '$#u', $params];
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
