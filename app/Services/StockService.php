<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

/**
 * Estoque por variação: leitura para o painel e ajuste manual do saldo.
 *
 * O ajuste é absoluto (o administrador digita o saldo novo, não a diferença) e
 * obedece a duas travas: nunca fica abaixo das peças já reservadas — a tabela
 * também tem esse CHECK — e a gravação só vale se o saldo lido na tela ainda
 * for o saldo do banco, para uma compra não ser sobrescrita por uma página
 * aberta há minutos.
 */
final class StockService
{
    public const MAX_QUANTITY = 99999;

    private const LOW_STOCK = 2;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Estoque por variação, com o saldo disponível e a situação de cada uma.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $consulta = $this->pdo->query(
            'SELECT p.name,
                    p.slug,
                    v.id AS variant_id,
                    v.size,
                    v.sku,
                    COALESCE(s.quantity, 0) AS quantity,
                    COALESCE(s.reserved, 0) AS reserved
               FROM product_variants v
               INNER JOIN products p ON p.id = v.product_id
               LEFT JOIN stock s ON s.variant_id = v.id
              ORDER BY p.name, v.id'
        );

        $linhas = [];

        foreach ($consulta->fetchAll() as $linha) {
            $disponivel = max(0, (int) $linha['quantity'] - (int) $linha['reserved']);

            $linhas[] = [
                'variant_id' => (int) $linha['variant_id'],
                'product' => (string) $linha['name'],
                'slug' => (string) $linha['slug'],
                'size' => (string) $linha['size'],
                'sku' => (string) $linha['sku'],
                'quantity' => (int) $linha['quantity'],
                'reserved' => (int) $linha['reserved'],
                'available' => $disponivel,
                'situation' => match (true) {
                    $disponivel === 0 => 'esgotado',
                    $disponivel <= self::LOW_STOCK => 'baixo',
                    default => 'ok',
                },
            ];
        }

        return $linhas;
    }

    /**
     * Grava o saldo novo de uma variação.
     *
     * @param int $esperado saldo que estava na tela quando o formulário abriu
     *
     * @return string|null mensagem de erro, ou null quando gravou
     */
    public function update(int $variantId, int $quantity, int $esperado): ?string
    {
        if ($variantId <= 0) {
            return 'Variação não encontrada.';
        }

        if ($quantity < 0) {
            return 'O saldo não pode ser negativo.';
        }

        if ($quantity > self::MAX_QUANTITY) {
            return sprintf('O saldo máximo é %d peças.', self::MAX_QUANTITY);
        }

        $this->pdo->beginTransaction();

        try {
            $consulta = $this->pdo->prepare(
                'SELECT v.id,
                        v.size,
                        p.name,
                        COALESCE(s.quantity, 0) AS quantity,
                        COALESCE(s.reserved, 0) AS reserved
                   FROM product_variants v
                   INNER JOIN products p ON p.id = v.product_id
                   LEFT JOIN stock s ON s.variant_id = v.id
                  WHERE v.id = :variant
                  LIMIT 1
                    FOR UPDATE'
            );

            $consulta->bindValue(':variant', $variantId, PDO::PARAM_INT);
            $consulta->execute();

            $variacao = $consulta->fetch();

            if ($variacao === false) {
                $this->pdo->rollBack();

                return 'Variação não encontrada.';
            }

            $reservado = (int) $variacao['reserved'];

            if ($quantity < $reservado) {
                $this->pdo->rollBack();

                return sprintf(
                    'O tamanho %s de %s tem %d %s reservada%s: o saldo não pode ficar abaixo disso.',
                    (string) $variacao['size'],
                    (string) $variacao['name'],
                    $reservado,
                    $reservado === 1 ? 'peça' : 'peças',
                    $reservado === 1 ? '' : 's'
                );
            }

            if ((int) $variacao['quantity'] !== $esperado) {
                $this->pdo->rollBack();

                return 'O saldo mudou enquanto você editava. Recarregue a página e tente de novo.';
            }

            $atualizar = $this->pdo->prepare(
                'INSERT INTO stock (variant_id, quantity)
                 VALUES (:variant, :quantity)
                 ON DUPLICATE KEY UPDATE quantity = :nova'
            );

            $atualizar->bindValue(':variant', $variantId, PDO::PARAM_INT);
            $atualizar->bindValue(':quantity', $quantity, PDO::PARAM_INT);
            $atualizar->bindValue(':nova', $quantity, PDO::PARAM_INT);
            $atualizar->execute();

            $this->pdo->commit();

            return null;
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }
    }
}
