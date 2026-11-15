<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CartRepository;
use PDO;
use Throwable;

/**
 * Regras do carrinho: no máximo 5 unidades do mesmo item e nunca mais do que
 * o estoque disponível (quantidade menos o que já está reservado).
 *
 * A conferência do estoque acontece dentro da transação, com a linha travada
 * por FOR UPDATE: dois cliques simultâneos não podem passar os dois pela mesma
 * verificação e estourar o estoque.
 */
final class CartService
{
    public const MAX_PER_ITEM = 5;

    public function __construct(
        private readonly PDO $pdo,
        private readonly CartRepository $carts
    ) {
    }

    public function add(int $userId, int $variantId, int $quantity): ?string
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

            $cartId = $this->carts->openForUser($userId);
            $atual = $this->carts->itemQuantity($cartId, $variantId);
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

            $this->carts->setItem($cartId, $variantId, $total);
            $this->pdo->commit();

            return null;
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }
    }

    public function update(int $userId, int $variantId, int $quantity): ?string
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

            $cartId = $this->carts->openForUser($userId);

            if ($this->carts->itemQuantity($cartId, $variantId) === 0) {
                $this->pdo->rollBack();

                return 'Este item não está no carrinho.';
            }

            $erro = $this->conferirEstoque($variacao, $quantity);

            if ($erro !== null) {
                $this->pdo->rollBack();

                return $erro;
            }

            $this->carts->setItem($cartId, $variantId, $quantity);
            $this->pdo->commit();

            return null;
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }
    }

    public function remove(int $userId, int $variantId): ?string
    {
        if ($variantId <= 0) {
            return 'Escolha um tamanho.';
        }

        $cartId = $this->carts->cartIdForUser($userId);

        if ($cartId !== null) {
            $this->carts->removeItem($cartId, $variantId);
        }

        return null;
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
