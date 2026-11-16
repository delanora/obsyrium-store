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
            'SELECT id, name, slug, parent_id FROM categories ORDER BY position, name'
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
     * @return list<array<string, mixed>>
     */
    public function roots(): array
    {
        $consulta = $this->pdo->query(
            'SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY position, name'
        );

        return array_map(
            static fn (array $linha): array => ['id' => (int) $linha['id'], 'name' => (string) $linha['name']],
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
            'SELECT id, name, parent_id FROM categories ORDER BY position, name'
        )->fetchAll();

        $filhos = [];

        foreach ($categorias as $categoria) {
            if ($categoria['parent_id'] !== null) {
                $filhos[(int) $categoria['parent_id']][] = [
                    'id' => (int) $categoria['id'],
                    'name' => (string) $categoria['name'],
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
     * @return array{id: int, name: string, parent_id: int|null}|null
     */
    private function find(int $id): ?array
    {
        $consulta = $this->pdo->prepare('SELECT id, name, parent_id FROM categories WHERE id = :id LIMIT 1');
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();

        $linha = $consulta->fetch();

        if ($linha === false) {
            return null;
        }

        return [
            'id' => (int) $linha['id'],
            'name' => (string) $linha['name'],
            'parent_id' => $linha['parent_id'] !== null ? (int) $linha['parent_id'] : null,
        ];
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
