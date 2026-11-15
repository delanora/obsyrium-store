<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Session;

/**
 * Carrinho do visitante, guardado na sessão do navegador.
 *
 * O visitante monta o carrinho sem conta; ao entrar ou se cadastrar, o
 * CartService leva os itens para o carrinho do banco (mergeGuestCart).
 */
final class SessionCartStorage implements CartStorage
{
    private const KEY = 'guest_cart';

    /**
     * @return array<int, int>
     */
    public function quantities(): array
    {
        $guardado = Session::get(self::KEY, []);
        $quantidades = [];

        if (!is_array($guardado)) {
            return $quantidades;
        }

        foreach ($guardado as $variantId => $quantidade) {
            $id = (int) $variantId;
            $qtd = (int) $quantidade;

            if ($id > 0 && $qtd > 0) {
                $quantidades[$id] = min($qtd, CartService::MAX_PER_ITEM);
            }
        }

        return $quantidades;
    }

    public function quantity(int $variantId): int
    {
        return $this->quantities()[$variantId] ?? 0;
    }

    public function setQuantity(int $variantId, int $quantity): void
    {
        $quantidades = $this->quantities();

        if ($quantity < 1) {
            unset($quantidades[$variantId]);
        } else {
            $quantidades[$variantId] = $quantity;
        }

        $this->salvar($quantidades);
    }

    public function remove(int $variantId): void
    {
        $quantidades = $this->quantities();
        unset($quantidades[$variantId]);

        $this->salvar($quantidades);
    }

    /**
     * Esvazia o carrinho do visitante (usado depois da fusão no banco).
     */
    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    /**
     * @param array<int, int> $quantidades
     */
    private function salvar(array $quantidades): void
    {
        if ($quantidades === []) {
            Session::forget(self::KEY);

            return;
        }

        Session::put(self::KEY, $quantidades);
    }
}
