<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Catálogo da loja: lê o estado dos filtros da URL, devolve a página de
 * produtos e as contagens que a barra de filtros mostra.
 *
 * O vínculo do produto com a categoria vem de `product_categories` — a mesma
 * tabela do painel —, então escolher uma categoria traz também o que está nas
 * subcategorias dela. Preço e estoque saem do banco: nada é confiado à URL.
 */
final class CatalogService
{
    public const PER_PAGE = 12;

    /**
     * Faixas de preço em centavos. O teto vazio é a última faixa.
     *
     * @var array<string, array{label: string, min: int, max: int|null}>
     */
    public const PRICE_RANGES = [
        'ate-99' => ['label' => 'Até R$ 99', 'min' => 0, 'max' => 9999],
        '100-149' => ['label' => 'R$ 100 a 149', 'min' => 10000, 'max' => 14999],
        '150-199' => ['label' => 'R$ 150 a 199', 'min' => 15000, 'max' => 19999],
        '200-mais' => ['label' => 'R$ 200 ou mais', 'min' => 20000, 'max' => null],
    ];

    /** @var array<string, string> */
    public const SORTS = [
        'novidades' => 'Novidades',
        'preco-asc' => 'Menor preço',
        'preco-desc' => 'Maior preço',
        'nome-asc' => 'Nome de A a Z',
    ];

    /** @var array<string, string> */
    public const AVAILABILITY = [
        'disponivel' => 'Disponível',
        'esgotado' => 'Esgotado',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Estado dos filtros: aceita só o que existe no banco (categoria, tamanho,
     * faixa de preço e ordenação) e cai no padrão quando vem coisa estranha.
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function state(array $query): array
    {
        $estado = [
            'categoria' => '',
            'categoria_id' => null,
            'sub' => '',
            'sub_id' => null,
            'tamanho' => '',
            'disponibilidade' => '',
            'preco' => '',
            'ordem' => 'novidades',
            'pagina' => 1,
        ];

        $categoria = $this->findCategory($this->texto($query['categoria'] ?? ''));
        $sub = $this->findCategory($this->texto($query['sub'] ?? ''));

        if ($sub !== null && $sub['parent_id'] === null) {
            $sub = null;
        }

        if ($sub !== null) {
            if ($categoria !== null && (int) $categoria['id'] !== (int) $sub['parent_id']) {
                $sub = null;
            } else {
                $categoria = $this->findCategoryById((int) $sub['parent_id']);
            }
        }

        if ($categoria !== null && $categoria['parent_id'] !== null) {
            $categoria = null;
        }

        if ($categoria !== null) {
            $estado['categoria'] = (string) $categoria['slug'];
            $estado['categoria_id'] = (int) $categoria['id'];
        }

        if ($sub !== null) {
            $estado['sub'] = (string) $sub['slug'];
            $estado['sub_id'] = (int) $sub['id'];
        }

        $tamanho = mb_strtoupper($this->texto($query['tamanho'] ?? ''), 'UTF-8');

        if (in_array($tamanho, $this->sizes(), true)) {
            $estado['tamanho'] = $tamanho;
        }

        $disponibilidade = $this->texto($query['disponibilidade'] ?? '');

        if (array_key_exists($disponibilidade, self::AVAILABILITY)) {
            $estado['disponibilidade'] = $disponibilidade;
        }

        $preco = $this->texto($query['preco'] ?? '');

        if (array_key_exists($preco, self::PRICE_RANGES)) {
            $estado['preco'] = $preco;
        }

        $ordem = $this->texto($query['ordem'] ?? '');

        if (array_key_exists($ordem, self::SORTS) && $ordem !== '') {
            $estado['ordem'] = $ordem;
        }

        $estado['pagina'] = max(1, (int) ($query['pagina'] ?? 1));

        return $estado;
    }

    /**
     * Produtos da página pedida, com o total e as páginas que existem.
     *
     * @param array<string, mixed> $estado
     *
     * @return array{items: list<array<string, mixed>>, total: int, pagina: int, paginas: int, inicio: int, fim: int}
     */
    public function results(array $estado): array
    {
        [$where, $params] = $this->conditions($estado);
        $filtro = ' WHERE ' . implode(' AND ', $where);

        $contagem = $this->pdo->prepare('SELECT COUNT(*) FROM products p' . $filtro);
        $this->bindAll($contagem, $params);
        $contagem->execute();

        $total = (int) $contagem->fetchColumn();
        $paginas = max(1, (int) ceil($total / self::PER_PAGE));
        $pagina = min((int) $estado['pagina'], $paginas);
        $deslocamento = ($pagina - 1) * self::PER_PAGE;

        $sql = 'SELECT p.id,
                       p.name,
                       p.slug,
                       p.color,
                       p.material,
                       p.price_cents,
                       p.compare_price_cents,
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
                  FROM products p'
            . $filtro
            . ' ORDER BY ' . $this->order($estado)
            . ' LIMIT :limite OFFSET :deslocamento';

        $consulta = $this->pdo->prepare($sql);
        $this->bindAll($consulta, $params);
        $consulta->bindValue(':limite', self::PER_PAGE, PDO::PARAM_INT);
        $consulta->bindValue(':deslocamento', $deslocamento, PDO::PARAM_INT);
        $consulta->execute();

        return [
            'items' => $consulta->fetchAll(),
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'inicio' => $total === 0 ? 0 : $deslocamento + 1,
            'fim' => min($total, $deslocamento + self::PER_PAGE),
        ];
    }

    /**
     * Categorias principais com as subcategorias e quantos produtos ativos há
     * em cada uma. A contagem da principal soma as filhas, porque o filtro por
     * categoria também traz o que está dentro delas.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        $categorias = $this->pdo->query(
            'SELECT id, name, slug, parent_id FROM categories ORDER BY position, name'
        )->fetchAll();

        $contagens = [];

        foreach ($this->pdo->query(
            'SELECT pc.category_id, COUNT(DISTINCT pc.product_id) AS total
               FROM product_categories pc
               INNER JOIN products p ON p.id = pc.product_id AND p.is_active = 1
              GROUP BY pc.category_id'
        )->fetchAll() as $linha) {
            $contagens[(int) $linha['category_id']] = (int) $linha['total'];
        }

        $filhas = [];

        foreach ($categorias as $categoria) {
            if ($categoria['parent_id'] !== null) {
                $filhas[(int) $categoria['parent_id']][] = [
                    'id' => (int) $categoria['id'],
                    'name' => (string) $categoria['name'],
                    'slug' => (string) $categoria['slug'],
                    'total' => $contagens[(int) $categoria['id']] ?? 0,
                ];
            }
        }

        $arvore = [];

        foreach ($categorias as $categoria) {
            if ($categoria['parent_id'] !== null) {
                continue;
            }

            $id = (int) $categoria['id'];
            $lista = $filhas[$id] ?? [];
            $total = $contagens[$id] ?? 0;

            foreach ($lista as $filha) {
                $total += $filha['total'];
            }

            $arvore[] = [
                'id' => $id,
                'name' => (string) $categoria['name'],
                'slug' => (string) $categoria['slug'],
                'total' => $total,
                'children' => $lista,
            ];
        }

        return $arvore;
    }

    /**
     * Tamanhos que existem em produtos ativos, na ordem do PP ao GG.
     *
     * @return list<string>
     */
    public function sizes(): array
    {
        $linhas = $this->pdo->query(
            "SELECT DISTINCT v.size
               FROM product_variants v
               INNER JOIN products p ON p.id = v.product_id
              WHERE p.is_active = 1
              ORDER BY FIELD(v.size, 'PP', 'P', 'M', 'G', 'GG', 'XG'), v.size"
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_map('strval', $linhas));
    }

    /**
     * Quantos produtos ativos a loja tem ao todo.
     */
    public function totalProducts(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
    }

    /**
     * @param array<string, mixed> $estado
     *
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private function conditions(array $estado): array
    {
        $where = ['p.is_active = 1'];
        $params = [];

        $estoque = '(SELECT COALESCE(SUM(sv.quantity), 0)
                       FROM product_variants pv
                       INNER JOIN stock sv ON sv.variant_id = pv.id
                      WHERE pv.product_id = p.id)';

        if ($estado['categoria_id'] !== null) {
            $where[] = 'EXISTS (SELECT 1
                                  FROM product_categories pc
                                 WHERE pc.product_id = p.id
                                   AND (pc.category_id = :categoria
                                        OR pc.category_id IN (SELECT f.id FROM categories f WHERE f.parent_id = :categoria_filhas)))';
            $params[':categoria'] = (int) $estado['categoria_id'];
            $params[':categoria_filhas'] = (int) $estado['categoria_id'];
        }

        if ($estado['sub_id'] !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM product_categories pcs WHERE pcs.product_id = p.id AND pcs.category_id = :subcategoria)';
            $params[':subcategoria'] = (int) $estado['sub_id'];
        }

        if ($estado['tamanho'] !== '') {
            $where[] = 'EXISTS (SELECT 1
                                  FROM product_variants vt
                                  INNER JOIN stock st ON st.variant_id = vt.id
                                 WHERE vt.product_id = p.id
                                   AND vt.size = :tamanho
                                   AND st.quantity > 0)';
            $params[':tamanho'] = (string) $estado['tamanho'];
        }

        if ($estado['disponibilidade'] === 'disponivel') {
            $where[] = $estoque . ' > 0';
        } elseif ($estado['disponibilidade'] === 'esgotado') {
            $where[] = $estoque . ' = 0';
        }

        if ($estado['preco'] !== '') {
            $faixa = self::PRICE_RANGES[(string) $estado['preco']];

            $where[] = 'p.price_cents >= :preco_min';
            $params[':preco_min'] = (int) $faixa['min'];

            if ($faixa['max'] !== null) {
                $where[] = 'p.price_cents <= :preco_max';
                $params[':preco_max'] = (int) $faixa['max'];
            }
        }

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $estado
     */
    private function order(array $estado): string
    {
        return match ((string) $estado['ordem']) {
            'preco-asc' => 'p.price_cents ASC, p.name ASC, p.id ASC',
            'preco-desc' => 'p.price_cents DESC, p.name ASC, p.id ASC',
            'nome-asc' => 'p.name ASC, p.id ASC',
            default => 'p.created_at DESC, p.id ASC',
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function bindAll(\PDOStatement $consulta, array $params): void
    {
        foreach ($params as $chave => $valor) {
            $consulta->bindValue($chave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    /**
     * @return array{id: int, name: string, slug: string, parent_id: int|null}|null
     */
    private function findCategory(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        $consulta = $this->pdo->prepare('SELECT id, name, slug, parent_id FROM categories WHERE slug = :slug LIMIT 1');
        $consulta->bindValue(':slug', $slug);
        $consulta->execute();

        return $this->categoryRow($consulta->fetch());
    }

    /**
     * @return array{id: int, name: string, slug: string, parent_id: int|null}|null
     */
    private function findCategoryById(int $id): ?array
    {
        $consulta = $this->pdo->prepare('SELECT id, name, slug, parent_id FROM categories WHERE id = :id LIMIT 1');
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();

        return $this->categoryRow($consulta->fetch());
    }

    /**
     * @param mixed $linha
     *
     * @return array{id: int, name: string, slug: string, parent_id: int|null}|null
     */
    private function categoryRow(mixed $linha): ?array
    {
        if (!is_array($linha)) {
            return null;
        }

        return [
            'id' => (int) $linha['id'],
            'name' => (string) $linha['name'],
            'slug' => (string) $linha['slug'],
            'parent_id' => $linha['parent_id'] !== null ? (int) $linha['parent_id'] : null,
        ];
    }

    private function texto(mixed $valor): string
    {
        if (!is_string($valor)) {
            return '';
        }

        return mb_substr(trim($valor), 0, 60, 'UTF-8');
    }
}
