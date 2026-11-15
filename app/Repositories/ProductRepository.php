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
}
