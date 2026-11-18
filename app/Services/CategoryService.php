<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Categorias do painel: árvore de dois níveis (categoria → subcategoria) e os
 * produtos que estão em cada uma.
 *
 * Um produto pode estar em várias categorias, sempre por uma "folha": se ele
 * escolhe a subcategoria, o vínculo é com a subcategoria; se não, com a
 * categoria. É por isso que a contagem de uma categoria não soma as
 * subcategorias — cada produto aparece onde foi colocado.
 *
 * O painel edita o nome (e com ele o slug), muda a categoria de lugar, sobe e
 * desce entre as irmãs e desativa sem apagar. Desativada, a categoria sai da
 * loja mas continua aqui com seus produtos e subcategorias.
 */
final class CategoryService
{
    public const MAX_NAME = 80;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Categorias raiz com suas subcategorias e produtos.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(): array
    {
        $categorias = $this->pdo->query(
            'SELECT id, name, slug, parent_id, position, is_active FROM categories ORDER BY position, name'
        )->fetchAll();

        $produtos = [];

        foreach ($this->vinculos() as $vinculo) {
            $produtos[(int) $vinculo['category_id']][] = [
                'id' => (int) $vinculo['id'],
                'name' => (string) $vinculo['name'],
                'is_active' => (int) $vinculo['is_active'] === 1,
            ];
        }

        $filhos = [];
        $raizes = [];

        foreach ($categorias as $categoria) {
            $item = [
                'id' => (int) $categoria['id'],
                'name' => (string) $categoria['name'],
                'slug' => (string) $categoria['slug'],
                'parent_id' => $categoria['parent_id'] !== null ? (int) $categoria['parent_id'] : null,
                'position' => (int) $categoria['position'],
                'is_active' => (int) $categoria['is_active'] === 1,
                'products' => $produtos[(int) $categoria['id']] ?? [],
            ];

            if ($categoria['parent_id'] !== null) {
                $filhos[(int) $categoria['parent_id']][] = $item;
            } else {
                $raizes[] = $item;
            }
        }

        foreach ($raizes as $indice => $raiz) {
            $raizes[$indice]['children'] = $filhos[$raiz['id']] ?? [];
            $raizes[$indice]['total'] = count($raiz['products']);
        }

        return $raizes;
    }

    /**
     * Categorias raiz, para o seletor de "subcategoria de".
     *
     * Traz as desativadas também: uma subcategoria pode continuar dentro de uma
     * categoria escondida, e o seletor precisa mostrar onde ela está.
     *
     * @return list<array<string, mixed>>
     */
    public function roots(): array
    {
        $consulta = $this->pdo->query(
            'SELECT id, name, is_active FROM categories WHERE parent_id IS NULL ORDER BY position, name'
        );

        return array_map(
            static fn (array $linha): array => [
                'id' => (int) $linha['id'],
                'name' => (string) $linha['name'],
                'is_active' => (int) $linha['is_active'] === 1,
            ],
            $consulta->fetchAll()
        );
    }

    /**
     * Árvore reduzida para o formulário do produto: raízes e subcategorias.
     *
     * @return list<array<string, mixed>>
     */
    public function formTree(): array
    {
        $categorias = $this->pdo->query(
            'SELECT id, name, parent_id, is_active FROM categories ORDER BY position, name'
        )->fetchAll();

        $filhos = [];

        foreach ($categorias as $categoria) {
            if ($categoria['parent_id'] !== null) {
                $filhos[(int) $categoria['parent_id']][] = [
                    'id' => (int) $categoria['id'],
                    'name' => (string) $categoria['name'],
                    'is_active' => (int) $categoria['is_active'] === 1,
                ];
            }
        }

        $arvore = [];

        foreach ($categorias as $categoria) {
            if ($categoria['parent_id'] !== null) {
                continue;
            }

            $arvore[] = [
                'id' => (int) $categoria['id'],
                'name' => (string) $categoria['name'],
                'is_active' => (int) $categoria['is_active'] === 1,
                'children' => $filhos[(int) $categoria['id']] ?? [],
            ];
        }

        return $arvore;
    }

    /**
     * Cria uma categoria ou subcategoria.
     *
     * @param array<string, mixed> $dados
     *
     * @return array{errors: list<string>, values: array<string, string>}
     */
    public function create(array $dados): array
    {
        $nome = trim((string) ($dados['name'] ?? ''));
        $ehSubcategoria = (string) ($dados['is_subcategory'] ?? '') === '1';

        // Sem a marca de subcategoria a categoria é principal: o seletor de
        // mãe é ignorado, mesmo que o formulário o envie.
        $paiBruto = $ehSubcategoria ? trim((string) ($dados['parent_id'] ?? '')) : '';
        $pai = $paiBruto === '' ? null : (int) $paiBruto;
        $valores = [
            'name' => $nome,
            'parent_id' => $paiBruto,
            'is_subcategory' => $ehSubcategoria ? '1' : '',
        ];

        $erros = [];

        if ($ehSubcategoria && $pai === null) {
            $erros[] = 'Escolha a categoria principal da subcategoria.';
        }

        if (mb_strlen($nome) < 2 || mb_strlen($nome) > self::MAX_NAME) {
            $erros[] = sprintf('O nome precisa ter de 2 a %d caracteres.', self::MAX_NAME);
        }

        $slug = $this->slugify($nome);

        if ($slug === '') {
            $erros[] = 'O nome precisa ter ao menos uma letra ou número.';
        } elseif ($this->slugEmUso($slug)) {
            $erros[] = sprintf('Já existe uma categoria em "%s".', $slug);
        }

        if ($pai !== null) {
            $mae = $this->find($pai);

            if ($mae === null) {
                $erros[] = 'Escolha uma categoria existente para ser a mãe.';
            } elseif ($mae['parent_id'] !== null) {
                $erros[] = 'Subcategoria não pode ter subcategoria: escolha uma categoria raiz.';
            }
        }

        if ($erros !== []) {
            return ['errors' => $erros, 'values' => $valores];
        }

        $posicao = $this->nextPosition($pai);

        $this->pdo->prepare(
            'INSERT INTO categories (name, slug, parent_id, position) VALUES (:name, :slug, :parent, :position)'
        )->execute([
            ':name' => $nome,
            ':slug' => $slug,
            ':parent' => $pai,
            ':position' => $posicao,
        ]);

        return ['errors' => [], 'values' => $valores];
    }

    /**
     * Edita nome, categoria principal e visibilidade de uma categoria.
     *
     * O slug acompanha o nome, então o endereço da categoria na loja muda junto.
     * Mudar de categoria principal só vale para quem não tem subcategorias
     * (senão a árvore passaria de dois níveis); a categoria entra no fim da nova
     * lista de irmãs e as duas listas são renumeradas.
     *
     * @param array<string, mixed> $dados
     *
     * @return array{errors: list<string>, values: array<string, string>}
     */
    public function update(int $id, array $dados): array
    {
        $categoria = $this->find($id);
        $nome = trim((string) ($dados['name'] ?? ''));
        $ativo = (string) ($dados['is_active'] ?? '') === '1';
        $paiBruto = trim((string) ($dados['parent_id'] ?? ''));
        $pai = $paiBruto === '' ? null : (int) $paiBruto;
        $valores = [
            'name' => $nome,
            'parent_id' => $paiBruto,
            'is_active' => $ativo ? '1' : '',
        ];

        if ($categoria === null) {
            return ['errors' => ['Categoria não encontrada.'], 'values' => $valores];
        }

        $erros = [];

        if (mb_strlen($nome) < 2 || mb_strlen($nome) > self::MAX_NAME) {
            $erros[] = sprintf('O nome precisa ter de 2 a %d caracteres.', self::MAX_NAME);
        }

        $slug = $this->slugify($nome);

        if ($slug === '') {
            $erros[] = 'O nome precisa ter ao menos uma letra ou número.';
        } elseif ($slug !== $categoria['slug'] && $this->slugEmUso($slug)) {
            $erros[] = sprintf('Já existe uma categoria em "%s".', $slug);
        }

        if ($pai !== null) {
            $mae = $this->find($pai);

            if ($pai === $id) {
                $erros[] = 'A categoria não pode ficar dentro dela mesma.';
            } elseif ($mae === null) {
                $erros[] = 'Escolha uma categoria existente para ser a mãe.';
            } elseif ($mae['parent_id'] !== null) {
                $erros[] = 'Subcategoria não pode ter subcategoria: escolha uma categoria raiz.';
            } elseif ($this->hasChildren($id)) {
                $erros[] = 'Esta categoria tem subcategorias e precisa continuar principal.';
            }
        }

        if ($erros !== []) {
            return ['errors' => $erros, 'values' => $valores];
        }

        $maeAntiga = $categoria['parent_id'];
        $mudouDeMae = $maeAntiga !== $pai;
        $posicao = $mudouDeMae ? $this->nextPosition($pai) : $categoria['position'];

        $this->pdo->prepare(
            'UPDATE categories
                SET name = :name, slug = :slug, parent_id = :parent, position = :position, is_active = :active
              WHERE id = :id'
        )->execute([
            ':name' => $nome,
            ':slug' => $slug,
            ':parent' => $pai,
            ':position' => $posicao,
            ':active' => $ativo ? 1 : 0,
            ':id' => $id,
        ]);

        if ($mudouDeMae) {
            $this->renumber($pai);
            $this->renumber($maeAntiga);
        }

        return ['errors' => [], 'values' => $valores];
    }

    /**
     * Sobe (-1) ou desce (+1) a categoria entre as irmãs da mesma mãe.
     *
     * @return string|null mensagem de erro, ou null quando moveu (ou já estava no fim)
     */
    public function move(int $id, int $direction): ?string
    {
        if ($direction !== -1 && $direction !== 1) {
            return 'Direção inválida para mover a categoria.';
        }

        $categoria = $this->find($id);

        if ($categoria === null) {
            return 'Categoria não encontrada.';
        }

        $irmas = $this->siblings($categoria['parent_id']);
        $atual = array_search($id, array_column($irmas, 'id'), true);

        if ($atual === false) {
            return 'Categoria não encontrada.';
        }

        $alvo = $atual + $direction;

        if ($alvo < 0 || $alvo >= count($irmas)) {
            return null;
        }

        [$irmas[$atual], $irmas[$alvo]] = [$irmas[$alvo], $irmas[$atual]];

        $this->pdo->beginTransaction();

        try {
            $atualizar = $this->pdo->prepare('UPDATE categories SET position = :position WHERE id = :id');

            foreach ($irmas as $posicao => $irma) {
                $atualizar->execute([':position' => $posicao, ':id' => $irma['id']]);
            }

            $this->pdo->commit();
        } catch (\Throwable $erro) {
            $this->pdo->rollBack();

            throw $erro;
        }

        return null;
    }

    /**
     * Apaga uma categoria vazia. Categoria com filhas ou com produto fica.
     *
     * @return string|null Mensagem de erro, ou null quando a categoria saiu.
     */
    public function delete(int $id): ?string
    {
        $categoria = $this->find($id);

        if ($categoria === null) {
            return 'Categoria não encontrada.';
        }

        $nome = $categoria['name'];

        $filhas = $this->pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = :id');
        $filhas->bindValue(':id', $id, PDO::PARAM_INT);
        $filhas->execute();
        $quantasFilhas = (int) $filhas->fetchColumn();

        if ($quantasFilhas > 0) {
            return sprintf(
                'A categoria "%s" tem %d subcategoria%s. Apague %s antes.',
                $nome,
                $quantasFilhas,
                $quantasFilhas === 1 ? '' : 's',
                $quantasFilhas === 1 ? 'ela' : 'elas'
            );
        }

        // `products.category_id` é a principal e `product_categories` guarda os
        // outros vínculos; qualquer um dos dois segura a categoria.
        // O mesmo id entra duas vezes, em marcadores diferentes: o MySQL não
        // aceita repetir um marcador nomeado na mesma consulta preparada.
        $produtos = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT p.id)
               FROM products p
               LEFT JOIN product_categories pc ON pc.product_id = p.id
              WHERE p.category_id = :principal OR pc.category_id = :vinculo'
        );
        $produtos->bindValue(':principal', $id, PDO::PARAM_INT);
        $produtos->bindValue(':vinculo', $id, PDO::PARAM_INT);
        $produtos->execute();
        $usados = (int) $produtos->fetchColumn();

        if ($usados > 0) {
            return sprintf(
                'A categoria "%s" não pode ser apagada: %d produto%s %s ela.',
                $nome,
                $usados,
                $usados === 1 ? '' : 's',
                $usados === 1 ? 'usa' : 'usam'
            );
        }

        $this->pdo->prepare('DELETE FROM categories WHERE id = :id')
            ->execute([':id' => $id]);

        $this->renumber($categoria['parent_id']);

        return null;
    }

    /**
     * @return array{id: int, name: string, slug: string, parent_id: int|null, position: int, is_active: bool}|null
     */
    private function find(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, name, slug, parent_id, position, is_active FROM categories WHERE id = :id LIMIT 1'
        );
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();

        $linha = $consulta->fetch();

        if ($linha === false) {
            return null;
        }

        return [
            'id' => (int) $linha['id'],
            'name' => (string) $linha['name'],
            'slug' => (string) $linha['slug'],
            'parent_id' => $linha['parent_id'] !== null ? (int) $linha['parent_id'] : null,
            'position' => (int) $linha['position'],
            'is_active' => (int) $linha['is_active'] === 1,
        ];
    }

    /**
     * Irmãs na ordem em que aparecem no painel.
     *
     * @return list<array{id: int, name: string, position: int}>
     */
    private function siblings(?int $pai): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, name, position FROM categories WHERE parent_id <=> :pai ORDER BY position, name'
        );
        $consulta->bindValue(':pai', $pai, $pai === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $consulta->execute();

        return array_map(
            static fn (array $linha): array => [
                'id' => (int) $linha['id'],
                'name' => (string) $linha['name'],
                'position' => (int) $linha['position'],
            ],
            $consulta->fetchAll()
        );
    }

    /**
     * Fecha os buracos de posição deixados por uma categoria que mudou de lugar.
     */
    private function renumber(?int $pai): void
    {
        $atualizar = $this->pdo->prepare('UPDATE categories SET position = :position WHERE id = :id');

        foreach ($this->siblings($pai) as $posicao => $irma) {
            $atualizar->execute([':position' => $posicao, ':id' => $irma['id']]);
        }
    }

    private function hasChildren(int $id): bool
    {
        $consulta = $this->pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = :id');
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();

        return (int) $consulta->fetchColumn() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function vinculos(): array
    {
        return $this->pdo->query(
            'SELECT pc.category_id, p.id, p.name, p.is_active
               FROM product_categories pc
               INNER JOIN products p ON p.id = pc.product_id
              ORDER BY p.name'
        )->fetchAll();
    }

    private function slugEmUso(string $slug): bool
    {
        $consulta = $this->pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = :slug');
        $consulta->bindValue(':slug', $slug);
        $consulta->execute();

        return (int) $consulta->fetchColumn() > 0;
    }

    private function nextPosition(?int $pai): int
    {
        $consulta = $this->pdo->prepare(
            'SELECT COALESCE(MAX(position) + 1, 0) FROM categories WHERE parent_id <=> :pai'
        );

        $consulta->bindValue(':pai', $pai, $pai === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $consulta->execute();

        return (int) $consulta->fetchColumn();
    }

    private function slugify(string $texto): string
    {
        $minusculo = mb_strtolower(trim($texto));
        $semAcento = strtr($minusculo, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        $slug = preg_replace('/[^a-z0-9]+/', '-', $semAcento) ?? '';

        return trim($slug, '-');
    }
}
