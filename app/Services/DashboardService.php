<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Price;
use PDO;

/**
 * Indicadores do painel.
 *
 * Pedido cancelado não conta como venda: nem na quantidade, nem no faturamento,
 * nem nas peças. Os meses vêm do próprio banco; os meses sem venda entram com
 * zero para o gráfico não ter buraco.
 */
final class DashboardService
{
    private const MESES_CURTOS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    private const ESTOQUE_BAIXO = 2;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{
     *     meses: list<array{rotulo: string, pedidos: int, faturamento: int, faturamento_label: string}>,
     *     pedidos: int,
     *     pecas: int,
     *     faturamento: int,
     *     faturamento_label: string,
     *     clientes: int
     * }
     */
    public function summary(int $quantidadeMeses = 6): array
    {
        $inicio = (new \DateTimeImmutable('first day of this month'))
            ->modify(sprintf('-%d months', $quantidadeMeses - 1));

        $consulta = $this->pdo->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS mes,
                    COUNT(*) AS pedidos,
                    COALESCE(SUM(total_cents), 0) AS faturamento
               FROM orders
              WHERE status <> 'cancelado'
                AND created_at >= :inicio
              GROUP BY mes"
        );

        $consulta->bindValue(':inicio', $inicio->format('Y-m-d 00:00:00'));
        $consulta->execute();

        $porMes = [];

        foreach ($consulta->fetchAll() as $linha) {
            $porMes[(string) $linha['mes']] = [
                'pedidos' => (int) $linha['pedidos'],
                'faturamento' => (int) $linha['faturamento'],
            ];
        }

        $meses = [];

        for ($i = 0; $i < $quantidadeMeses; $i++) {
            $data = $inicio->modify(sprintf('+%d months', $i));
            $chave = $data->format('Y-m');
            $venda = $porMes[$chave] ?? ['pedidos' => 0, 'faturamento' => 0];

            $meses[] = [
                'rotulo' => self::MESES_CURTOS[(int) $data->format('n') - 1],
                'pedidos' => $venda['pedidos'],
                'faturamento' => $venda['faturamento'],
                'faturamento_label' => Price::format($venda['faturamento']),
            ];
        }

        $totais = $this->pdo->query(
            "SELECT COUNT(*) AS pedidos,
                    COALESCE(SUM(pieces), 0) AS pecas,
                    COALESCE(SUM(total_cents), 0) AS faturamento
               FROM orders
              WHERE status <> 'cancelado'"
        )->fetch();

        $clientes = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND is_active = 1'
        )->fetchColumn();

        return [
            'meses' => $meses,
            'pedidos' => (int) $totais['pedidos'],
            'pecas' => (int) $totais['pecas'],
            'faturamento' => (int) $totais['faturamento'],
            'faturamento_label' => Price::format((int) $totais['faturamento']),
            'clientes' => $clientes,
        ];
    }

    /**
     * Estoque por variação, com o saldo disponível e a situação de cada uma.
     *
     * @return list<array<string, mixed>>
     */
    public function stock(): array
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
                'product' => (string) $linha['name'],
                'slug' => (string) $linha['slug'],
                'size' => (string) $linha['size'],
                'sku' => (string) $linha['sku'],
                'quantity' => (int) $linha['quantity'],
                'reserved' => (int) $linha['reserved'],
                'available' => $disponivel,
                'situation' => match (true) {
                    $disponivel === 0 => 'esgotado',
                    $disponivel <= self::ESTOQUE_BAIXO => 'baixo',
                    default => 'ok',
                },
            ];
        }

        return $linhas;
    }
}
