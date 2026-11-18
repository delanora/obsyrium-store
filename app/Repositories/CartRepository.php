<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/*
 * Leitura e escrita do carrinho.
 *
 * Um cliente tem no máximo um carrinho aberto (chave única em user_id) e cada
 * variação aparece uma vez em cart_items, com a quantidade somada.
 */
final class CartRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Devolve o carrinho do cliente, criando na primeira vez.
     *
     * O INSERT com ON DUPLICATE KEY deixa a criação atômica: dois cliques ao
     * mesmo tempo não geram dois carrinhos.
     */
    public function openForUser(int $userId): int
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO carts (user_id) VALUES (:user_id)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );

        $consulta->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $consulta->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Carrinho existente do cliente, sem criar um novo.
     */
    public function cartIdForUser(int $userId): ?int
    {
        $consulta = $this->pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id LIMIT 1');
        $consulta->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $consulta->execute();

        $linha = $consulta->fetch();

        return $linha === false ? null : (int) $linha['id'];
    }

    public function itemQuantity(int $cartId, int $variantId): int
    {
        $consulta = $this->pdo->prepare(
            'SELECT quantity FROM cart_items WHERE cart_id = :cart_id AND variant_id = :variant_id LIMIT 1'
        );

        $consulta->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
        $consulta->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $consulta->execute();

        $linha = $consulta->fetch();

        return $linha === false ? 0 : (int) $linha['quantity'];
    }

    /**
     * Grava a quantidade absoluta da variação no carrinho.
     */
    public function setItem(int $cartId, int $variantId, int $quantity): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO cart_items (cart_id, variant_id, quantity)
             VALUES (:cart_id, :variant_id, :quantity)
             ON DUPLICATE KEY UPDATE quantity = :nova'
        );

        $consulta->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
        $consulta->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $consulta->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $consulta->bindValue(':nova', $quantity, PDO::PARAM_INT);
        $consulta->execute();
    }

    public function removeItem(int $cartId, int $variantId): void
    {
        $consulta = $this->pdo->prepare(
            'DELETE FROM cart_items WHERE cart_id = :cart_id AND variant_id = :variant_id'
        );

        $consulta->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
        $consulta->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $consulta->execute();
    }

    /**
     * Quantidade por variação no carrinho do cliente, na ordem de inclusão.
     *
     * @return array<int, int>
     */
    public function quantitiesForUser(int $userId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT ci.variant_id, ci.quantity
               FROM cart_items ci
               INNER JOIN carts c ON c.id = ci.cart_id
              WHERE c.user_id = :user_id
              ORDER BY ci.created_at, ci.id'
        );

        $consulta->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $consulta->execute();

        $quantidades = [];

        foreach ($consulta->fetchAll() as $linha) {
            $quantidades[(int) $linha['variant_id']] = (int) $linha['quantity'];
        }

        return $quantidades;
    }

    /**
     * Dados de exibição das variações pedidas: nome, tamanho, preço, estoque
     * disponível e imagem principal.
     *
     * Os marcadores do IN são gerados aqui (só caracteres "?"); os valores vão
     * presos ao prepared statement.
     *
     * @param list<int> $variantIds
     * @return list<array<string, mixed>>
     */
    public function itemsForVariants(array $variantIds): array
    {
        $ids = [];

        foreach ($variantIds as $variantId) {
            if ($variantId > 0 && !in_array($variantId, $ids, true)) {
                $ids[] = $variantId;
            }
        }

        if ($ids === []) {
            return [];
        }

        $marcadores = implode(', ', array_fill(0, count($ids), '?'));

        $consulta = $this->pdo->prepare(
            "SELECT v.id AS variant_id,
                    v.size,
                    p.name,
                    p.slug,
                    p.price_cents,
                    p.weight_grams,
                    p.is_active,
                    COALESCE(s.quantity, 0) - COALESCE(s.reserved, 0) AS available,
                    (SELECT i.path FROM product_images i
                      WHERE i.product_id = p.id
                      ORDER BY i.position, i.id
                      LIMIT 1) AS image_path,
                    (SELECT i.alt FROM product_images i
                      WHERE i.product_id = p.id
                      ORDER BY i.position, i.id
                      LIMIT 1) AS image_alt
               FROM product_variants v
               INNER JOIN products p ON p.id = v.product_id
               LEFT JOIN stock s ON s.variant_id = v.id
              WHERE v.id IN ({$marcadores})
              ORDER BY p.name, v.id"
        );

        $consulta->execute($ids);

        return $consulta->fetchAll();
    }
    /**
     * Variação com o estoque travado para conferência.
     *
     * Precisa ser chamada dentro de uma transação: o FOR UPDATE impede que
     * duas requisições leiam o mesmo saldo e passem as duas pela validação.
     *
     * @return array<string, mixed>|null
     */
    public function lockedVariant(int $variantId): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT v.id,
                    v.size,
                    p.name,
                    p.slug,
                    p.is_active,
                    COALESCE(s.quantity, 0) AS quantity,
                    COALESCE(s.reserved, 0) AS reserved
               FROM product_variants v
               INNER JOIN products p ON p.id = v.product_id
               LEFT JOIN stock s ON s.variant_id = v.id
              WHERE v.id = :variant_id
              LIMIT 1
                FOR UPDATE'
        );

        $consulta->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $consulta->execute();

        $linha = $consulta->fetch();

        return $linha === false ? null : $linha;
    }
}
