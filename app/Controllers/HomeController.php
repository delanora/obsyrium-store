<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\ProductRepository;
use App\Services\ContactService;
use App\Support\Csrf;
use App\Support\Navegacao;
use App\Support\ProductCard;
use App\Support\Session;

final class HomeController
{
    private const SHOWCASE_LIMIT = 8;

    public function index(): void
    {
        $this->renderHome();
    }

    /**
     * Recebe o formulário "Tem alguma ideia?" da home. A ideia entra na mesma
     * caixa de mensagens do painel, marcada como pedido de estampa, e passa
     * pelo CSRF, pela armadilha de robô e pelo limite por IP do contato.
     */
    public function requestIdea(): void
    {
        if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');

            require dirname(__DIR__, 2) . '/resources/views/errors/403.php';

            return;
        }

        $resultado = (new ContactService(Connection::get()))->send(
            $_POST,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            ContactService::SOURCE_IDEA
        );

        // Robô que preencheu o campo armadilha recebe a mesma resposta de
        // sucesso, mas nada é gravado.
        if (!$resultado['spam'] && $resultado['errors'] !== []) {
            $this->renderHome(
                $resultado['errors'],
                $resultado['values'],
                $resultado['throttled'] ? 429 : 422
            );

            return;
        }

        Session::flash('success', 'Ideia recebida. Se ela virar estampa, avisamos no e-mail informado.');
        header('Location: /#ideia', true, 303);
        exit;
    }

    /**
     * @param list<string> $errors
     * @param array{name: string, email: string, phone: string, message: string} $values
     */
    private function renderHome(array $errors = [], array $values = [], int $status = 200): void
    {
        Navegacao::registrar('/');

        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);

        $repository = new ProductRepository(Connection::get());
        $products = array_map(
            static fn (array $linha): array => ProductCard::fromRow($linha),
            $repository->showcase(self::SHOWCASE_LIMIT)
        );

        $ideiaErros = $errors;
        $ideiaValores = $values + ['name' => '', 'email' => '', 'phone' => '', 'message' => ''];

        $title = 'Obsyrium Store — camisetas com estampas autorais';
        $content = $basePath . '/resources/views/home/index.php';

        require $basePath . '/resources/views/layouts/site.php';
    }
}
