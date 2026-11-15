<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CartRepository;
use App\Support\Price;
use App\Support\Session;
use PDO;
use Throwable;

/**
 * Regras do carrinho: no máximo 5 unidades do mesmo item e nunca mais do que
 * o estoque disponível (quantidade menos o que já está reservado).
 *
 * As regras valem para o visitante (carrinho na sessão) e para o cliente
 * autenticado (carrinho no banco); o que muda é só o CartStorage por trás.
 *
 * A conferência do estoque acontece dentro da transação, com a linha travada
 * por FOR UPDATE: dois cliques simultâneos não podem passar os dois pela mesma
 * verificação e estourar o estoque.
 */
final class CartService
{
    public const MAX_PER_ITEM = 5;

    private const FALLBACK_IMAGE = '/assets/images/placeholder.svg';

    public function __construct(
        private readonly PDO $pdo,
        private readonly CartRepository $carts,
        private readonly CartStorage $storage
    ) {
    }

    /**
     * Carrinho de quem está navegando: sessão para o visitante, banco para o
     * cliente autenticado.
     */
    public static function forCurrentUser(PDO $pdo): self
    {
        $carts = new CartRepository($pdo);
        $userId = Session::userId();

        return new self(
            $pdo,
            $carts,
            $userId === null ? new SessionCartStorage() : new DatabaseCartStorage($carts, $userId)
        );
    }

    public function add(int $variantId, int $quantity): ?string
    {
        if ($variantId <= 0) {
            return 'Escolha um tamanho.';
        }

        if ($quantity < 1) {
            return 'Informe uma quantidade válida.';
        }

        if ($quantity > self::MAX_PER_ITEM) {
            return $this->limite();
        }

        $this->pdo->beginTransaction();

        try {
            $variacao = $this->disponivel($variantId);

            if ($variacao === null) {
                $this->pdo->rollBack();

                return 'Este item não está disponível.';
            }

            $atual = $this->storage->quantity($variantId);
            $total = $atual + $quantity;

            if ($total > self::MAX_PER_ITEM) {
                $this->pdo->rollBack();

                return $atual === 0
                    ? $this->limite()
                    : sprintf('Você já tem %d no carrinho e o limite é %d por item.', $atual, self::MAX_PER_ITEM);
            }

            $erro = $this->conferirEstoque($variacao, $total);

            if ($erro !== null) {
                $this->pdo->rollBack();

                return $erro;
            }

            $this->storage->setQuantity($variantId, $total);
            $this->pdo->commit();

            return null;
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }
    }

    public function update(int $variantId, int $quantity): ?string
    {
        if ($variantId <= 0) {
            return 'Escolha um tamanho.';
        }

        if ($quantity < 1) {
            return 'Informe uma quantidade válida.';
        }

        if ($quantity > self::MAX_PER_ITEM) {
            return $this->limite();
        }

        $this->pdo->beginTransaction();

        try {
            $variacao = $this->disponivel($variantId);

            if ($variacao === null) {
                $this->pdo->rollBack();

                return 'Este item não está disponível.';
            }

            if ($this->storage->quantity($variantId) === 0) {
                $this->pdo->rollBack();

                return 'Este item não está no carrinho.';
            }

            $erro = $this->conferirEstoque($variacao, $quantity);

            if ($erro !== null) {
                $this->pdo->rollBack();

                return $erro;
            }

            $this->storage->setQuantity($variantId, $quantity);
            $this->pdo->commit();

            return null;
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }
    }

    public function remove(int $variantId): ?string
    {
        if ($variantId <= 0) {
            return 'Escolha um tamanho.';
        }

        $this->storage->remove($variantId);

        return null;
    }

    /**
     * Soma das quantidades, para o contador do cabeçalho.
     */
    public function total(): int
    {
        return array_sum($this->storage->quantities());
    }

    /**
     * Linhas prontas para a página do carrinho: dados da variação e do produto
     * mais a quantidade guardada no carrinho.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $quantidades = $this->storage->quantities();

        if ($quantidades === []) {
            return [];
        }

        $porVariacao = [];

        foreach ($this->carts->itemsForVariants(array_keys($quantidades)) as $linha) {
            $porVariacao[(int) $linha['variant_id']] = $linha;
        }

        $itens = [];

        foreach ($quantidades as $variantId => $quantidade) {
            if (!isset($porVariacao[$variantId])) {
                continue;
            }

            $linha = $porVariacao[$variantId];
            $linha['quantity'] = $quantidade;
            $itens[] = $linha;
        }

        return $itens;
    }

    /**
     * Leva o carrinho do visitante para o carrinho do cliente que acabou de
     * entrar ou de se cadastrar, respeitando limite e estoque de cada item.
     *
     * @return int quantidade de itens aproveitados
     */
    public function mergeGuestCart(): int
    {
        if (!$this->storage instanceof DatabaseCartStorage) {
            return 0;
        }

        $visitante = new SessionCartStorage();
        $quantidades = $visitante->quantities();

        if ($quantidades === []) {
            return 0;
        }

        $mesclados = 0;

        $this->pdo->beginTransaction();

        try {
            foreach ($quantidades as $variantId => $quantidade) {
                $variacao = $this->disponivel($variantId);

                if ($variacao === null) {
                    continue;
                }

                $disponivel = max(0, (int) $variacao['quantity'] - (int) $variacao['reserved']);
                $total = min(
                    $this->storage->quantity($variantId) + $quantidade,
                    self::MAX_PER_ITEM,
                    $disponivel
                );

                if ($total < 1) {
                    continue;
                }

                $this->storage->setQuantity($variantId, $total);
                $mesclados++;
            }

            $this->pdo->commit();
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }

        $visitante->clear();

        return $mesclados;
    }

    /**
     * Itens prontos para a interface: preço em reais, imagem padrão quando o
     * produto não tem foto e a quantidade que está no carrinho.
     *
     * @return list<array<string, mixed>>
     */
    public function itemsForDisplay(): array
    {
        $itens = [];

        foreach ($this->items() as $linha) {
            $imagem = (string) ($linha['image_path'] ?? '');

            $itens[] = [
                'variant_id' => (int) $linha['variant_id'],
                'name' => (string) $linha['name'],
                'slug' => (string) $linha['slug'],
                'size' => (string) $linha['size'],
                'quantity' => (int) $linha['quantity'],
                'available' => (int) $linha['available'],
                'active' => (int) $linha['is_active'] === 1,
                'price' => Price::format((int) $linha['price_cents']),
                'image' => $imagem !== '' ? $imagem : self::FALLBACK_IMAGE,
                'alt' => (string) ($linha['image_alt'] ?? ''),
            ];
        }

        return $itens;
    }
    /**
     * @return array<string, mixed>|null
     */
    private function disponivel(int $variantId): ?array
    {
        $variacao = $this->carts->lockedVariant($variantId);

        if ($variacao === null || (int) $variacao['is_active'] !== 1) {
            return null;
        }

        return $variacao;
    }

    /**
     * @param array<string, mixed> $variacao
     */
    private function conferirEstoque(array $variacao, int $quantidade): ?string
    {
        $disponivel = max(0, (int) $variacao['quantity'] - (int) $variacao['reserved']);

        if ($quantidade <= $disponivel) {
            return null;
        }

        $tamanho = (string) $variacao['size'];

        if ($disponivel === 0) {
            return sprintf('O tamanho %s está esgotado.', $tamanho);
        }

        return $disponivel === 1
            ? sprintf('Resta 1 unidade do tamanho %s.', $tamanho)
            : sprintf('Restam %d unidades do tamanho %s.', $disponivel, $tamanho);
    }

    private function limite(): string
    {
        return sprintf('O limite é de %d unidades do mesmo item.', self::MAX_PER_ITEM);
    }
}
