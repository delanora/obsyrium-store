<?php

declare(strict_types=1);

/*
 * Front controller da aplicação.
 *
 * Único arquivo PHP acessível diretamente pelo servidor web. Nenhum detalhe
 * interno (stack trace, caminho de arquivo, SQL, credencial) é enviado ao
 * usuário: os detalhes vão para storage/logs/app.log e o cliente recebe
 * apenas a página genérica de erro.
 */

use App\Routing\Router;
use App\Support\Session;
use Config\Env;

$basePath = dirname(__DIR__);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $basePath . '/storage/logs/app.log');
error_reporting(E_ALL);

$renderError = static function (int $status) use ($basePath): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');

    $view = $basePath . '/resources/views/errors/' . $status . '.php';

    if (is_file($view)) {
        require $view;
    }
};

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception) use ($renderError): void {
    error_log(sprintf(
        "[%s] %s: %s em %s:%d%s%s",
        date('c'),
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        PHP_EOL,
        $exception->getTraceAsString()
    ));

    $renderError(500);
});

register_shutdown_function(static function () use ($renderError): void {
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    if (!in_array($error['type'], $fatal, true)) {
        return;
    }

    error_log(sprintf(
        '[%s] Erro fatal: %s em %s:%d',
        date('c'),
        $error['message'],
        $error['file'],
        $error['line']
    ));

    if (!headers_sent()) {
        $renderError(500);
    }
});

require $basePath . '/vendor/autoload.php';

Env::load($basePath . '/.env');

// A sessão começa aqui: cabeçalho, CSRF e mensagens dependem dela.
Session::start();

$router = new Router();

(require $basePath . '/routes/web.php')($router);

$matched = $router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/'
);

if (!$matched) {
    $renderError(404);
}
