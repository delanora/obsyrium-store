<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/*
 * Consultas de produtos para a vitrine.
 */
final class ProductRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Produtos ativos com categoria, estoque somado e imagem principal.
     *
     * @return list<array<string, mixed>>
     */
    public function showcase(int $limit = 8): array
    {
        $sql = 'SELECT p.id,
                       p.name,
                       p.slug,
                       p.color,
                       p.material,
                       p.price_cents,
                       p.compare_price_cents,
                       c.name AS category_name,
                       (SELECT COALESCE(SUM(s.quantity), 0)
                          FROM product_variants v
                          INNER JOIN stock s ON s.variant_id = v.id
                         WHERE v.product_id = p.id) AS stock_quantity,
                       (SELECT i.path FROM product_images i
                         WHERE i.product_id = p.id
                         ORDER BY i.position, i.id
                         LIMIT 1) AS image_path,
                       (SELECT i.alt FROM product_images i
                         WHERE i.product_id = p.id
                         ORDER BY i.position, i.id
                         LIMIT 1) AS image_alt
                  FROM products p
                  INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.is_active = 1
                 ORDER BY p.created_at DESC, p.id ASC
                 LIMIT :limit';

        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $consulta->execute();

        return $consulta->fetchAll();
    }

    /**
     * Busca o produto ativo pelo slug, com o nome e o slug da categoria.
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = 'SELECT p.id,
                       p.name,
                       p.slug,
                       p.color,
                       p.material,
                       p.description,
                       p.price_cents,
                       p.compare_price_cents,
                       c.name AS category_name,
                       c.slug AS category_slug
                  FROM products p
                  INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.slug = :slug
                   AND p.is_active = 1
                 LIMIT 1';

        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':slug', $slug);
        $consulta->execute();

        $linha = $consulta->fetch();

        return $linha === false ? null : $linha;
    }

    /**
     * Imagens do produto na ordem de exibição.
     *
     * @return list<array<string, mixed>>
     */
    public function images(int $productId): array
    {
        $sql = 'SELECT path, alt
                  FROM product_images
                 WHERE product_id = :product_id
                 ORDER BY position, id';

        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $consulta->execute();

        return $consulta->fetchAll();
    }

    /**
     * Tamanhos cadastrados com a quantidade em estoque de cada um.
     *
     * @return list<array<string, mixed>>
     */
    public function variantsWithStock(int $productId): array
    {
        $sql = "SELECT v.id,
                       v.size,
                       v.sku,
                       COALESCE(s.quantity, 0) AS quantity
                  FROM product_variants v
                  LEFT JOIN stock s ON s.variant_id = v.id
                 WHERE v.product_id = :product_id
                 ORDER BY FIELD(v.size, 'PP', 'P', 'M', 'G', 'GG', 'XG'), v.size";

        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $consulta->execute();

        return $consulta->fetchAll();
    }
}
