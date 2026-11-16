<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Páginas institucionais: Sobre e Contato.
 *
 * O texto mora na view, sem banco: são páginas que mudam com o projeto, não com
 * a operação da loja. Ambas usam o layout do site, com cabeçalho, carrinho e
 * rodapé iguais aos do resto da loja.
 */
final class PageController
{
    public function about(): void
    {
        $this->render('Sobre a Obsyrium — Obsyrium Store', 'pages/about.php');
    }

    public function contact(): void
    {
        $this->render('Contato — Obsyrium Store', 'pages/contact.php');
    }

    private function render(string $title, string $view): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $content = $basePath . '/resources/views/' . $view;

        require $basePath . '/resources/views/layouts/site.php';
    }
}
