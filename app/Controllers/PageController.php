<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\ContactService;
use App\Services\PageService;
use App\Support\Csrf;
use App\Support\Session;

/**
 * Páginas institucionais: Sobre, Contato e as do rodapé (perguntas frequentes,
 * entrega, pagamento, termos e privacidade).
 *
 * O texto das institucionais mora no banco e é editado no painel; aqui só se
 * escolhe a página pelo slug, com as ações fixas do fim de cada uma. O Contato
 * continua lendo do banco os canais configurados e gravando as mensagens do
 * formulário.
 */
final class PageController
{
    /**
     * Botões do fim de cada página, na ordem em que aparecem. O primeiro é o
     * botão cheio e os seguintes são fantasmas.
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private const ACOES = [
        'sobre' => [['Ver o catálogo', '/catalogo'], ['Falar com a loja', '/contato']],
        'perguntas-frequentes' => [['Falar com a loja', '/contato'], ['Política de entrega', '/entrega']],
        'entrega' => [['Falar com a loja', '/contato'], ['Perguntas frequentes', '/perguntas-frequentes']],
        'pagamento' => [['Falar com a loja', '/contato'], ['Política de entrega', '/entrega']],
        'termos' => [['Falar com a loja', '/contato'], ['Política de privacidade', '/privacidade']],
        'privacidade' => [['Falar com a loja', '/contato'], ['Termos de uso', '/termos']],
    ];

    public function about(): void
    {
        $this->renderPage('sobre');
    }

    public function contact(): void
    {
        $this->renderContact();
    }

    public function faq(): void
    {
        $this->renderPage('perguntas-frequentes');
    }

    public function delivery(): void
    {
        $this->renderPage('entrega');
    }

    public function payment(): void
    {
        $this->renderPage('pagamento');
    }

    public function terms(): void
    {
        $this->renderPage('termos');
    }

    public function privacy(): void
    {
        $this->renderPage('privacidade');
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

        Session::flash('success', 'Mensagem enviada. Respondemos pelo contato informado.');
        header('Location: /contato', true, 303);
        exit;
    }

    /**
     * Página institucional pelo slug, com o conteúdo que está no banco.
     */
    private function renderPage(string $slug): void
    {
        $pagina = (new PageService(Connection::get()))->findBySlug($slug);

        if ($pagina === null) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');

            require dirname(__DIR__, 2) . '/resources/views/errors/404.php';

            return;
        }

        $this->render($pagina['title'] . ' — Obsyrium Store', 'pages/page.php', [
            'pagina' => $pagina,
            'acoes' => self::ACOES[$slug] ?? [],
        ]);
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
        $paginaInstitucional = $contexto['pagina'] ?? null;
        $paginaAcoes = $contexto['acoes'] ?? [];

        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $content = $basePath . '/resources/views/' . $view;

        require $basePath . '/resources/views/layouts/site.php';
    }
}
