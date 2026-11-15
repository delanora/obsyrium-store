<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Onde o carrinho fica guardado. O visitante usa a sessão do navegador e o
 * cliente autenticado usa o banco: as regras (limite por item e estoque)
 * ficam em CartService e valem para os dois.
 */
interface CartStorage
{
    /**
     * Quantidade por variação, na ordem em que os itens entraram.
     *
     * @return array<int, int> variant_id => quantidade
     */
    public function quantities(): array;

    public function quantity(int $variantId): int;

    public function setQuantity(int $variantId, int $quantity): void;

    public function remove(int $variantId): void;
}
