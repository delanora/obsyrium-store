<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lembra a página em que o cliente está, para que ações que trocam de tela
 * (como adicionar ao carrinho) devolvam o cliente ao ponto de onde veio, sem
 * depender do cabeçalho Referer.
 *
 * Só entram caminhos conhecidos: `/`, `/catalogo`, `/carrinho` e
 * `/produto/{slug}`. O catálogo entra sem os filtros — voltar para ele é
 * voltar para a lista inteira.
 */
final class Navegacao
{
    private const ATUAL = 'pagina_atual';

    public static function registrar(string $caminho): void
    {
        $caminho = self::permitido($caminho);

        if ($caminho !== null) {
            Session::put(self::ATUAL, $caminho);
        }
    }

    /**
     * Página em que o cliente estava antes de abrir `$caminhoAtual`.
     *
     * Sem histórico — ou quando a página atual é a própria `$caminhoAtual` — a
     * resposta é a home, que é o ponto natural para seguir comprando.
     */
    public static function origemDe(string $caminhoAtual): string
    {
        $atual = Session::get(self::ATUAL);

        if (!is_string($atual) || $atual === '' || $atual === $caminhoAtual) {
            return '/';
        }

        return self::permitido($atual) ?? '/';
    }

    private static function permitido(string $caminho): ?string
    {
        $caminho = (string) parse_url($caminho, PHP_URL_PATH);
        $caminho = preg_replace('#[^a-z0-9/-]#', '', strtolower($caminho));

        if (!is_string($caminho) || $caminho === '') {
            return null;
        }

        return preg_match('#^/(carrinho|catalogo|produto/[a-z0-9-]+)?$#', $caminho) === 1 ? $caminho : null;
    }
}
