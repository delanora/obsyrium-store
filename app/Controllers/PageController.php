<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\ContactService;
use App\Support\Csrf;
use App\Support\Session;

/**
 * Páginas institucionais: Sobre, Contato e as do rodapé (perguntas frequentes,
 * entrega, pagamento, termos e privacidade).
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

    public function faq(): void
    {
        $this->render('Perguntas frequentes — Obsyrium Store', 'pages/faq.php');
    }

    public function delivery(): void
    {
        $this->render('Política de entrega — Obsyrium Store', 'pages/delivery.php');
    }

    public function payment(): void
    {
        $this->render('Formas de pagamento — Obsyrium Store', 'pages/payment.php');
    }

    public function terms(): void
    {
        $this->render('Termos de uso — Obsyrium Store', 'pages/terms.php');
    }

    public function privacy(): void
    {
        $this->render('Política de privacidade — Obsyrium Store', 'pages/privacy.php');
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

        $resultado = (new ContactService(Connection::get()))->send(
            $_POST,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );

        // Robô que preencheu o campo armadilha recebe a mesma resposta de
        // sucesso, mas nada é gravado.
        if (!$resultado['spam'] && $resultado['errors'] !== []) {
            $this->renderContact(
                $resultado['errors'],
                $resultado['values'],
                $resultado['throttled'] ? 429 : 422
            );

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
