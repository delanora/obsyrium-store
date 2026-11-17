<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\ContactService;
use App\Support\Csrf;
use App\Support\Session;

/**
 * Páginas institucionais: Sobre e Contato.
 *
 * O texto mora na view, sem banco: são páginas que mudam com o projeto, não com
 * a operação da loja. A exceção é o Contato, que lê do banco os canais
 * configurados no painel e grava as mensagens do formulário.
 */
final class PageController
{
    public function about(): void
    {
        $this->render('Sobre a Obsyrium — Obsyrium Store', 'pages/about.php');
    }

    public function contact(): void
    {
        $this->renderContact();
    }

    /**
     * Recebe o formulário de contato. Com erro, a página volta preenchida (422);
     * com sucesso, 303 de volta para /contato, para o navegador não reenviar.
     */
    public function sendMessage(): void
    {
        if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');

            require dirname(__DIR__, 2) . '/resources/views/errors/403.php';

            return;
        }

        $resultado = (new ContactService(Connection::get()))->send($_POST);

        if ($resultado['errors'] !== []) {
            $this->renderContact($resultado['errors'], $resultado['values'], 422);

            return;
        }

        Session::flash('success', 'Mensagem enviada. Respondemos no e-mail informado.');
        header('Location: /contato', true, 303);
        exit;
    }

    private function renderContact(array $erros = [], array $valores = [], int $status = 200): void
    {
        $this->render('Contato — Obsyrium Store', 'pages/contact.php', [
            'canais' => (new ContactService(Connection::get()))->channels(),
            'erros' => $erros,
            'valores' => $valores + ['name' => '', 'email' => '', 'phone' => '', 'message' => ''],
        ], $status);
    }

    /**
     * @param array<string, mixed> $contexto Variáveis que a view espera.
     */
    private function render(string $title, string $view, array $contexto = [], int $status = 200): void
    {
        $contatoCanais = $contexto['canais'] ?? [];
        $contatoErros = $contexto['erros'] ?? [];
        $contatoValores = $contexto['valores'] ?? [];

        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $content = $basePath . '/resources/views/' . $view;

        require $basePath . '/resources/views/layouts/site.php';
    }
}
