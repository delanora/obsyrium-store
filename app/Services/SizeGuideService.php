<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

/**
 * Guia de medidas da camiseta: largura e comprimento de cada tamanho, em
 * centímetros, editados no painel e mostrados na página do produto.
 *
 * As medidas são opcionais. Enquanto nenhuma estiver preenchida, a loja não
 * mostra o guia — melhor não ter guia do que ter uma tabela vazia.
 */
final class SizeGuideService
{
    public const MIN_CM = 20;

    public const MAX_CM = 150;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Todos os tamanhos, na ordem do seletor.
     *
     * @return list<array{id: int, label: string, chest: int|null, length: int|null}>
     */
    public function rows(): array
    {
        $consulta = $this->pdo->query(
            'SELECT id, label, chest_cm, length_cm FROM size_guides ORDER BY position, id'
        );

        return array_map(
            static fn (array $linha): array => [
                'id' => (int) $linha['id'],
                'label' => (string) $linha['label'],
                'chest' => $linha['chest_cm'] !== null ? (int) $linha['chest_cm'] : null,
                'length' => $linha['length_cm'] !== null ? (int) $linha['length_cm'] : null,
            ],
            $consulta->fetchAll()
        );
    }

    /**
     * Só o que a loja tem para mostrar: tamanhos com pelo menos uma medida.
     *
     * @return list<array{id: int, label: string, chest: int|null, length: int|null}>
     */
    public function visible(): array
    {
        return array_values(array_filter(
            $this->rows(),
            static fn (array $linha): bool => $linha['chest'] !== null || $linha['length'] !== null
        ));
    }

    /**
     * @param array<string, mixed> $dados
     *
     * @return array{
     *     errors: list<string>,
     *     values: array<int, array{chest: string, length: string}>
     * }
     */
    public function save(array $dados): array
    {
        $medidas = is_array($dados['chest'] ?? null) ? $dados['chest'] : [];
        $comprimentos = is_array($dados['length'] ?? null) ? $dados['length'] : [];

        $linhas = [];
        $valores = [];
        $erros = [];

        foreach ($this->rows() as $linha) {
            $id = $linha['id'];
            $largura = trim((string) ($medidas[$id] ?? ''));
            $comprimento = trim((string) ($comprimentos[$id] ?? ''));
            $valores[$id] = ['chest' => $largura, 'length' => $comprimento];

            $campos = ['Largura' => $largura, 'Comprimento' => $comprimento];

            foreach ($campos as $campo => $valor) {
                if ($valor === '') {
                    continue;
                }

                if (preg_match('/^\d{1,3}$/', $valor) !== 1) {
                    $erros[] = sprintf('%s de %s: use só números inteiros, em centímetros.', $campo, $linha['label']);

                    continue;
                }

                if ((int) $valor < self::MIN_CM || (int) $valor > self::MAX_CM) {
                    $erros[] = sprintf(
                        '%s de %s: use um valor entre %d e %d cm.',
                        $campo,
                        $linha['label'],
                        self::MIN_CM,
                        self::MAX_CM
                    );
                }
            }

            $linhas[$id] = [
                'chest' => $largura === '' ? null : (int) $largura,
                'length' => $comprimento === '' ? null : (int) $comprimento,
            ];
        }

        if ($erros !== []) {
            return ['errors' => array_values(array_unique($erros)), 'values' => $valores];
        }

        $atualizar = $this->pdo->prepare(
            'UPDATE size_guides SET chest_cm = :chest, length_cm = :length WHERE id = :id'
        );

        $this->pdo->beginTransaction();

        try {
            foreach ($linhas as $id => $medida) {
                $atualizar->bindValue(':chest', $medida['chest'], $medida['chest'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $atualizar->bindValue(':length', $medida['length'], $medida['length'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $atualizar->bindValue(':id', $id, PDO::PARAM_INT);
                $atualizar->execute();
            }

            $this->pdo->commit();
        } catch (Throwable $erro) {
            $this->pdo->rollBack();

            throw $erro;
        }

        return ['errors' => [], 'values' => $valores];
    }
}
