<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\SitemapService;
use App\Support\Seo;

/**
 * `robots.txt` e `sitemap.xml` saem do PHP, e não de arquivos soltos, para o
 * endereço do sitemap sair inteiro a partir de `APP_URL` — o mesmo código serve
 * o desenvolvimento e o domínio em produção.
 *
 * Nenhum dos dois é uma página: a resposta é texto puro (`robots.txt`) e XML.
 */
final class SitemapController
{
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');

        echo "User-agent: *\n";
        echo "Allow: /\n";

        foreach (Seo::ROBOTS_DISALLOW as $caminho) {
            echo 'Disallow: ', $caminho, "\n";
        }

        echo "\n";
        echo 'Sitemap: ', Seo::canonical('/sitemap.xml'), "\n";
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";

        foreach ((new SitemapService(Connection::get()))->urls() as $url) {
            echo "\t<url>\n";
            echo "\t\t<loc>", $this->escapeXml(Seo::canonical($url['loc'])), "</loc>\n";

            if ($url['lastmod'] !== null && $url['lastmod'] !== '') {
                echo "\t\t<lastmod>", $this->escapeXml(substr($url['lastmod'], 0, 10)), "</lastmod>\n";
            }

            echo "\t</url>\n";
        }

        echo "</urlset>\n";
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
