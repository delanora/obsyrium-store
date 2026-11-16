<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Price;
use PDO;
use Throwable;

/**
 * Estoque por variação: leitura com abas, ordenação e paginação, ajuste do
 * saldo e saída/volta de linha do produto.
 *
 * "Fora de linha" é `products.is_active = 0` — o mesmo interruptor que tira o
 * produto da loja. Por isso a linha da tabela é da variação, mas a lixeira age
 * no modelo: as outras abas só mostram produtos ativos, e o carrinho da aba
 * fora de linha devolve o produto à loja (que reaparece esgotado se não tiver
 * saldo).
 */
final class StockService
{
    public const MAX_QUANTITY = 99999;

    public const PER_PAGE = 10;

    /**
     * Abas do estoque, na ordem em que aparecem.
     */
    public const TABS = [
        'estoque' => 'Em estoque',
        'esgotados' => 'Esgotados',
        'fora' => 'Fora de linha',
        'todos' => 'Todos',
    ];

    /**
     * Colunas ordenáveis e a expressão SQL de cada uma. É a lista branca: o que
     * não está aqui vira a ordenação padrão, nunca SQL vindo da URL.
     */
    public const SORTABLE = [
        'produto' => 'p.name',
        'tamanho' => "FIELD(v.size, 'PP', 'P', 'M', 'G', 'GG', 'XG', 'XGG')",
        'sku' => 'v.sku',
        'preco' => 'COALESCE(p.compare_price_cents, p.price_cents)',
        'promocional' => 'p.price_cents',
        'reservado' => 'COALESCE(s.reserved, 0)',
        'saldo' => 'COALESCE(s.quantity, 0)',
        'disponivel' => '(COALESCE(s.quantity, 0) - COALESCE(s.reserved, 0))',
    ];

    private const LOW_STOCK = 2;

    private const DISPONIVEL = '(COALESCE(s.quantity, 0) - COALESCE(s.reserved, 0))';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Uma página do estoque, já filtrada pela aba e ordenada pela coluna pedida.
     *
     * @return array{
     *     itens: list<array<string, mixed>>,
     *     total: int,
     *     pagina: int,
     *     paginas: int,
     *     porPagina: int,
     *     inicio: int,
     *     fim: int,
     *     aba: string,
     *     ordem: string,
     *     direcao: string,
     *     contagens: array<string, int>
     * }
     */
    public function page(string $aba = 'todos', string $ordem = 'produto', string $direcao = 'asc', int $pagina = 1): array
    {
        $aba = isset(self::TABS[$aba]) ? $aba : 'todos';
        $ordem = isset(self::SORTABLE[$ordem]) ? $ordem : 'produto';
        $direcao = strtolower($direcao) === 'desc' ? 'desc' : 'asc';
        $pagina = max(1, $pagina);

        $where = $this->where($aba);
        $base = 'FROM product_variants v
                 INNER JOIN products p ON p.id = v.product_id
                 LEFT JOIN stock s ON s.variant_id = v.id';

        $consulta = $this->pdo->prepare("SELECT COUNT(*) {$base} WHERE {$where}");
        $consulta->execute();
        $total = (int) $consulta->fetchColumn();

        $paginas = max(1, (int) ceil($total / self::PER_PAGE));
        $pagina = min($pagina, $paginas);
        $offset = ($pagina - 1) * self::PER_PAGE;

        $ordemSql = self::SORTABLE[$ordem] . ' ' . $direcao . ', p.name ASC, v.id ASC';

        $consulta = $this->pdo->prepare(
            "SELECT p.id AS product_id,
                    p.name,
                    p.is_active,
                    p.price_cents,
                    p.compare_price_cents,
                    v.id AS variant_id,
                    v.size,
                    v.sku,
                    COALESCE(s.quantity, 0) AS quantity,
                    COALESCE(s.reserved, 0) AS reserved
               {$base}
              WHERE {$where}
              ORDER BY {$ordemSql}
              LIMIT :limite OFFSET :offset"
        );

        $consulta->bindValue(':limite', self::PER_PAGE, PDO::PARAM_INT);
        $consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
        $consulta->execute();

        $itens = [];

        foreach ($consulta->fetchAll() as $linha) {
            $disponivel = max(0, (int) $linha['quantity'] - (int) $linha['reserved']);
            $promocional = (int) $linha['price_cents'];
            $cheio = $linha['compare_price_cents'] !== null ? (int) $linha['compare_price_cents'] : $promocional;

            $itens[] = [
                'product_id' => (int) $linha['product_id'],
                'variant_id' => (int) $linha['variant_id'],
                'product' => (string) $linha['name'],
                'size' => (string) $linha['size'],
                'sku' => (string) $linha['sku'],
                'price' => Price::format($cheio),
                'promo' => Price::format($promocional),
                'discount' => Price::discountPercent($cheio, $promocional),
                'reserved' => (int) $linha['reserved'],
                'quantity' => (int) $linha['quantity'],
                'available' => $disponivel,
                'situation' => match (true) {
                    $disponivel === 0 => 'esgotado',
                    $disponivel <= self::LOW_STOCK => 'baixo',
                    default => 'ok',
                },
            ];
        }

        return [
            'itens' => $itens,
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'porPagina' => self::PER_PAGE,
            'inicio' => $total === 0 ? 0 : $offset + 1,
            'fim' => min($offset + self::PER_PAGE, $total),
            'aba' => $aba,
            'ordem' => $ordem,
            'direcao' => $direcao,
            'contagens' => $this->counts(),
        ];
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

    /**
     * Tira o produto de linha (lixeira) ou devolve ele à loja (carrinho na aba
     * fora de linha). O estoque não muda: voltar sem saldo deixa o produto
     * esgotado na loja.
     *
     * @return array{product?: string, error?: string}
     */
    public function setLine(int $productId, bool $active): array
    {
        if ($productId <= 0) {
            return ['error' => 'Produto não encontrado.'];
        }

        $consulta = $this->pdo->prepare('SELECT name, is_active FROM products WHERE id = :id LIMIT 1');
        $consulta->bindValue(':id', $productId, PDO::PARAM_INT);
        $consulta->execute();

        $produto = $consulta->fetch();

        if ($produto === false) {
            return ['error' => 'Produto não encontrado.'];
        }

        $nome = (string) $produto['name'];

        if ((int) $produto['is_active'] === ($active ? 1 : 0)) {
            return [
                'error' => $active
                    ? sprintf('%s já está na loja.', $nome)
                    : sprintf('%s já está fora de linha.', $nome),
            ];
        }

        $atualizar = $this->pdo->prepare('UPDATE products SET is_active = :ativo WHERE id = :id');
        $atualizar->bindValue(':ativo', $active ? 1 : 0, PDO::PARAM_INT);
        $atualizar->bindValue(':id', $productId, PDO::PARAM_INT);
        $atualizar->execute();

        return ['product' => $nome];
    }

    private function where(string $aba): string
    {
        return match ($aba) {
            'estoque' => 'p.is_active = 1 AND ' . self::DISPONIVEL . ' > 0',
            'esgotados' => 'p.is_active = 1 AND ' . self::DISPONIVEL . ' <= 0',
            'fora' => 'p.is_active = 0',
            default => 'p.is_active = 1',
        };
    }

    /**
     * Quantidade de variações em cada aba, para o contador dos botões.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        $consulta = $this->pdo->query(
            'SELECT SUM(p.is_active = 1) AS todos,
                    SUM(p.is_active = 1 AND ' . self::DISPONIVEL . ' > 0) AS estoque,
                    SUM(p.is_active = 1 AND ' . self::DISPONIVEL . ' <= 0) AS esgotados,
                    SUM(p.is_active = 0) AS fora
               FROM product_variants v
               INNER JOIN products p ON p.id = v.product_id
               LEFT JOIN stock s ON s.variant_id = v.id'
        );

        $linha = $consulta->fetch();

        return [
            'todos' => (int) ($linha['todos'] ?? 0),
            'estoque' => (int) ($linha['estoque'] ?? 0),
            'esgotados' => (int) ($linha['esgotados'] ?? 0),
            'fora' => (int) ($linha['fora'] ?? 0),
        ];
    }
}
