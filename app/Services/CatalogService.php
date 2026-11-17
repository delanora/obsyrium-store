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
 * subcategorias dela. Cor, preço e estoque saem do banco: nada é confiado à
 * URL. O preço vem do que o cliente digita, em reais, e é convertido para
 * centavos aqui — cada ponta é opcional.
 *
 * A busca (`q`) procura no nome, na cor, no material, na descrição e no nome
 * das categorias do produto. Os curingas do LIKE são escapados, então digitar
 * `%` ou `_` procura esses caracteres e não vira curinga.
 */
final class CatalogService
{
    public const PER_PAGE = 12;

    /**
     * Teto de sanidade para o preço digitado (R$ 100.000), para uma URL
     * estranha não virar consulta absurda.
     */
    private const PRECO_MAXIMO = 10000000;

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
     * cor, preço e ordenação) e cai no padrão quando vem coisa estranha.
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function state(array $query): array
    {
        $estado = [
            'q' => '',
            'categoria' => '',
            'categoria_id' => null,
            'sub' => '',
            'sub_id' => null,
            'tamanho' => '',
            'cor' => '',
            'disponibilidade' => '',
            'preco_min' => '',
            'preco_max' => '',
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

        $cor = $this->texto($query['cor'] ?? '');

        if ($cor !== '') {
            foreach ($this->colors() as $disponivel) {
                if (mb_strtolower($disponivel['name'], 'UTF-8') === mb_strtolower($cor, 'UTF-8')) {
                    $estado['cor'] = $disponivel['name'];
                    break;
                }
            }
        }

        $minimo = $this->dinheiro($query['preco_min'] ?? null);
        $maximo = $this->dinheiro($query['preco_max'] ?? null);

        if ($minimo !== null && $maximo !== null && $minimo > $maximo) {
            [$minimo, $maximo] = [$maximo, $minimo];
        }

        $estado['preco_min'] = $minimo ?? '';
        $estado['preco_max'] = $maximo ?? '';

        $ordem = $this->texto($query['ordem'] ?? '');

        if (array_key_exists($ordem, self::SORTS) && $ordem !== '') {
            $estado['ordem'] = $ordem;
        }

        $estado['q'] = $this->texto($query['q'] ?? '');
        $estado['pagina'] = max(1, (int) ($query['pagina'] ?? 1));

        return $estado;
    }

    /**
     * Estado do catálogo preso a uma categoria: a categoria vem do caminho, não
     * da URL, e o resto dos filtros (cor, tamanho, preço, ordenação, busca)
     * continua valendo dentro dela. Categoria principal traz também as filhas;
     * subcategoria traz só o que está nela.
     *
     * @param array<string, mixed> $query
     * @param array{id: int, name: string, slug: string, parent_id: int|null} $categoria
     *
     * @return array<string, mixed>
     */
    public function stateForCategory(array $query, array $categoria): array
    {
        $estado = $this->state($query);

        $estado['categoria'] = '';
        $estado['categoria_id'] = null;
        $estado['sub'] = '';
        $estado['sub_id'] = null;

        if ($categoria['parent_id'] === null) {
            $estado['categoria'] = (string) $categoria['slug'];
            $estado['categoria_id'] = (int) $categoria['id'];
        } else {
            $estado['sub'] = (string) $categoria['slug'];
            $estado['sub_id'] = (int) $categoria['id'];
        }

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
     * em cada uma. A contagem da principal já inclui as filhas, porque o filtro
     * por categoria também traz o que está dentro delas — e usa DISTINCT, então
     * um produto ligado à categoria e a uma filha conta uma vez só.
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
            'SELECT c.id,
                    (SELECT COUNT(DISTINCT pc.product_id)
                       FROM product_categories pc
                       INNER JOIN products p ON p.id = pc.product_id AND p.is_active = 1
                      WHERE pc.category_id = c.id
                         OR pc.category_id IN (SELECT f.id FROM categories f WHERE f.parent_id = c.id)) AS total
               FROM categories c'
        )->fetchAll() as $linha) {
            $contagens[(int) $linha['id']] = (int) $linha['total'];
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

            $arvore[] = [
                'id' => $id,
                'name' => (string) $categoria['name'],
                'slug' => (string) $categoria['slug'],
                'total' => $contagens[$id] ?? 0,
                'children' => $filhas[$id] ?? [],
            ];
        }

        return $arvore;
    }

    /**
     * Categoria para a página da loja: ela mesma, a mãe (quando é filha), as
     * filhas (quando é principal) e a contagem de produtos de cada uma. Slug
     * que não existe devolve `null` — a página responde 404.
     *
     * @return array{id: int, name: string, slug: string, parent_id: int|null, parent: array{id: int, name: string, slug: string}|null, children: list<array{id: int, name: string, slug: string, total: int}>}|null
     */
    public function categoryWithChildren(string $slug): ?array
    {
        $categoria = $this->findCategory($slug);

        if ($categoria === null) {
            return null;
        }

        $resultado = $categoria + ['parent' => null, 'children' => []];

        foreach ($this->categories() as $principal) {
            if ($principal['id'] === $categoria['id']) {
                $resultado['children'] = $principal['children'];

                return $resultado;
            }

            foreach ($principal['children'] as $filha) {
                if ($filha['id'] === $categoria['id']) {
                    $resultado['parent'] = [
                        'id' => (int) $principal['id'],
                        'name' => (string) $principal['name'],
                        'slug' => (string) $principal['slug'],
                    ];

                    return $resultado;
                }
            }
        }

        return $resultado;
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
     * Cores que existem em produtos ativos, em ordem alfabética, com quantos
     * produtos há em cada uma. A cor é uma coluna de `products`, preenchida no
     * painel.
     *
     * @return list<array{name: string, total: int}>
     */
    public function colors(): array
    {
        $linhas = $this->pdo->query(
            "SELECT p.color, COUNT(*) AS total
               FROM products p
              WHERE p.is_active = 1
                AND p.color <> ''
              GROUP BY p.color
              ORDER BY p.color"
        )->fetchAll();

        return array_map(
            static fn (array $linha): array => [
                'name' => (string) $linha['color'],
                'total' => (int) $linha['total'],
            ],
            $linhas
        );
    }

    /**
     * Menor e maior preço entre os produtos ativos, para os exemplos dos
     * campos de preço do filtro.
     *
     * @return array{min: int, max: int}
     */
    public function priceBounds(): array
    {
        $linha = $this->pdo->query(
            'SELECT MIN(price_cents) AS minimo, MAX(price_cents) AS maximo
               FROM products
              WHERE is_active = 1'
        )->fetch();

        return [
            'min' => (int) ($linha['minimo'] ?? 0),
            'max' => (int) ($linha['maximo'] ?? 0),
        ];
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

        if ($estado['cor'] !== '') {
            $where[] = 'p.color = :cor';
            $params[':cor'] = (string) $estado['cor'];
        }

        if ($estado['preco_min'] !== '') {
            $where[] = 'p.price_cents >= :preco_min';
            $params[':preco_min'] = (int) $estado['preco_min'];
        }

        if ($estado['preco_max'] !== '') {
            $where[] = 'p.price_cents <= :preco_max';
            $params[':preco_max'] = (int) $estado['preco_max'];
        }

        // Cada termo usa o seu próprio parâmetro: o MySQL não aceita o mesmo
        // nome repetido na consulta quando o prepare é nativo.
        if ($estado['q'] !== '') {
            $busca = '%' . $this->like((string) $estado['q']) . '%';

            $where[] = '(p.name LIKE :busca_nome
                          OR p.color LIKE :busca_cor
                          OR p.material LIKE :busca_material
                          OR p.description LIKE :busca_descricao
                          OR EXISTS (SELECT 1
                                       FROM product_categories pcb
                                       INNER JOIN categories catb ON catb.id = pcb.category_id
                                      WHERE pcb.product_id = p.id
                                        AND catb.name LIKE :busca_categoria))';

            $params[':busca_nome'] = $busca;
            $params[':busca_cor'] = $busca;
            $params[':busca_material'] = $busca;
            $params[':busca_descricao'] = $busca;
            $params[':busca_categoria'] = $busca;
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

    /**
     * Escapa os curingas do LIKE: o que a pessoa digita é texto, não padrão.
     */
    private function like(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }

    private function texto(mixed $valor): string
    {
        if (!is_string($valor)) {
            return '';
        }

        return mb_substr(trim($valor), 0, 60, 'UTF-8');
    }

    /**
     * Converte o preço digitado ("150", "150,90", "150.90") em centavos.
     * Vazio ou zero quer dizer "sem limite", que é `null`.
     */
    private function dinheiro(mixed $valor): ?int
    {
        if (!is_string($valor) && !is_int($valor) && !is_float($valor)) {
            return null;
        }

        $limpo = preg_replace('/[^0-9.,]/', '', (string) $valor) ?? '';

        if ($limpo === '') {
            return null;
        }

        if (!str_contains($limpo, ',')) {
            $limpo = str_replace('.', ',', $limpo);
        } else {
            $limpo = str_replace('.', '', $limpo);
        }

        [$inteiro, $decimal] = array_pad(explode(',', $limpo, 2), 2, '');
        $centavos = ((int) $inteiro * 100) + (int) substr(str_pad($decimal, 2, '0'), 0, 2);

        if ($centavos <= 0) {
            return null;
        }

        return min($centavos, self::PRECO_MAXIMO);
    }
}
