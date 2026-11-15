<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CartRepository;

/**
 * Carrinho do cliente autenticado, persistido em carts/cart_items.
 */
final class DatabaseCartStorage implements CartStorage
{
    public function __construct(
        private readonly CartRepository $carts,
        private readonly int $userId
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function quantities(): array
    {
        return $this->carts->quantitiesForUser($this->userId);
    }

    public function quantity(int $variantId): int
    {
        $cartId = $this->carts->cartIdForUser($this->userId);

        return $cartId === null ? 0 : $this->carts->itemQuantity($cartId, $variantId);
    }

    public function setQuantity(int $variantId, int $quantity): void
    {
        $cartId = $this->carts->openForUser($this->userId);

        $this->carts->setItem($cartId, $variantId, $quantity);
    }

    public function remove(int $variantId): void
    {
        $cartId = $this->carts->cartIdForUser($this->userId);

        if ($cartId !== null) {
            $this->carts->removeItem($cartId, $variantId);
        }
    }
}
