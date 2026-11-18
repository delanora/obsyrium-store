<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Endereços que entram no `sitemap.xml`: as páginas fixas da loja, as
 * institucionais que estão no banco, as categorias ativas (com as filhas no
 * caminho da mãe) e os produtos ativos.
 *
 * Categoria desativada e produto fora de linha ficam de fora: o sitemap só
 * aponta para o que a loja realmente abre.
 */
final class SitemapService
{
    /** Páginas do banco e o endereço público de cada uma. */
    private const PAGE_ROUTES = [
        'sobre' => '/sobre',
        'perguntas-frequentes' => '/perguntas-frequentes',
        'entrega' => '/entrega',
        'pagamento' => '/pagamento',
        'termos' => '/termos',
        'privacidade' => '/privacidade',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    public function urls(): array
    {
        $urls = [
            ['loc' => '/', 'lastmod' => null],
            ['loc' => '/catalogo', 'lastmod' => null],
            ['loc' => '/contato', 'lastmod' => null],
        ];

        foreach ($this->pdo->query('SELECT slug, updated_at FROM pages ORDER BY id') as $pagina) {
            $rota = self::PAGE_ROUTES[(string) $pagina['slug']] ?? null;

            if ($rota !== null) {
                $urls[] = ['loc' => $rota, 'lastmod' => (string) $pagina['updated_at']];
            }
        }

        $categorias = $this->pdo->query(
            'SELECT id, slug, parent_id, is_active, updated_at FROM categories ORDER BY position, id'
        )->fetchAll();

        $porId = [];

        foreach ($categorias as $categoria) {
            $porId[(int) $categoria['id']] = $categoria;
        }

        foreach ($categorias as $categoria) {
            if ((int) $categoria['is_active'] !== 1) {
                continue;
            }

            $lastmod = (string) $categoria['updated_at'];

            if ($categoria['parent_id'] === null) {
                $urls[] = ['loc' => '/categoria/' . $categoria['slug'], 'lastmod' => $lastmod];

                continue;
            }

            $mae = $porId[(int) $categoria['parent_id']] ?? null;

            if ($mae === null || (int) $mae['is_active'] !== 1) {
                continue;
            }

            $urls[] = [
                'loc' => '/categoria/' . $mae['slug'] . '/' . $categoria['slug'],
                'lastmod' => $lastmod,
            ];
        }

        foreach ($this->pdo->query('SELECT slug, updated_at FROM products WHERE is_active = 1 ORDER BY id') as $produto) {
            $urls[] = [
                'loc' => '/produto/' . rawurlencode((string) $produto['slug']),
                'lastmod' => (string) $produto['updated_at'],
            ];
        }

        return $urls;
    }
}
