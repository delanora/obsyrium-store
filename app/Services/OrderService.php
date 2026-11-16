<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AddressRepository;
use App\Support\Crypto;
use App\Support\Price;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Fechamento de pedido e leitura do histórico de compras.
 *
 * O pedido é montado como um retrato: preço, nome, tamanho e SKU saem da
 * variação no momento da compra, e o endereço escolhido é copiado cifrado para
 * a própria linha do pedido. Nem a mudança de preço na loja nem a edição do
 * endereço no perfil reescrevem o que já foi vendido.
 *
 * Tudo acontece numa transação só: carrinho travado (FOR UPDATE), estoque
 * conferido e debitado, pedido e itens gravados e o carrinho esvaziado. Se
 * qualquer conferência falhar, nada fica pela metade.
 */
final class OrderService
{
    /**
     * Situações do pedido, na ordem em que acontecem. O pagamento ainda não
     * existe, então todo pedido nasce em `aguardando_pagamento`.
     */
    public const STATUS_LABELS = [
        'aguardando_pagamento' => 'Aguardando pagamento',
        'pago' => 'Pago',
        'enviado' => 'Enviado',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ];

    /**
     * Prefixo do número que o cliente lê. O número sai do id: OBS-000042.
     */
    private const NUMBER_PREFIX = 'OBS-';

    private const HISTORY_LIMIT = 50;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Cria o pedido a partir do carrinho e do endereço escolhidos.
     *
     * @return array{id: int, number: string}|array{error: string}
     */
    public function place(int $userId, int $addressId): array
    {
        if ($userId <= 0) {
            return ['error' => 'Entre na sua conta para finalizar a compra.'];
        }

        $endereco = (new AddressRepository($this->pdo))->findForUser($addressId, $userId);

        if ($endereco === null) {
            return ['error' => 'Escolha um endereço de entrega da sua conta.'];
        }

        $this->pdo->beginTransaction();

        try {
            $itens = $this->lockedCartItems($userId);

            if ($itens === []) {
                $this->pdo->rollBack();

                return ['error' => 'Seu carrinho está vazio.'];
            }

            $erro = $this->conferirEstoque($itens);

            if ($erro !== null) {
                $this->pdo->rollBack();

                return ['error' => $erro];
            }

            $subtotal = 0;
            $pecas = 0;

            foreach ($itens as $item) {
                $subtotal += (int) $item['price_cents'] * (int) $item['quantity'];
                $pecas += (int) $item['quantity'];
            }

            [$orderId, $numero] = $this->insertOrder($userId, $endereco, $subtotal, $pecas);
            $this->insertItems($orderId, $itens);
            $this->debitStock($itens);
            $this->clearCart($userId);

            $this->pdo->commit();

            return ['id' => $orderId, 'number' => $numero];
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $erro;
        }
    }

    /**
     * Pedidos do cliente, do mais novo para o mais antigo, com os itens e o
     * endereço de entrega já decifrado e formatado para a aba Compras.
     *
     * @return list<array<string, mixed>>
     */
    public function ordersForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $consulta = $this->pdo->prepare(
            'SELECT id,
                    number,
                    status,
                    pieces,
                    subtotal_cents,
                    shipping_cents,
                    total_cents,
                    created_at,
                    ship_label,
                    ship_recipient_encrypted,
                    ship_postal_code,
                    ship_street_encrypted,
                    ship_number_encrypted,
                    ship_complement_encrypted,
                    ship_district_encrypted,
                    ship_city_encrypted,
                    ship_state
               FROM orders
              WHERE user_id = :user
              ORDER BY created_at DESC, id DESC
              LIMIT :limite'
        );

        $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
        $consulta->bindValue(':limite', self::HISTORY_LIMIT, PDO::PARAM_INT);
        $consulta->execute();

        $linhas = $consulta->fetchAll();

        if ($linhas === []) {
            return [];
        }

        $itensPorPedido = $this->itemsForOrders(array_map(
            static fn (array $linha): int => (int) $linha['id'],
            $linhas
        ));

        $pedidos = [];

        foreach ($linhas as $linha) {
            $id = (int) $linha['id'];
            $status = (string) $linha['status'];

            $pedidos[] = [
                'number' => (string) $linha['number'],
                'date' => date('d/m/Y', strtotime((string) $linha['created_at'])),
                'status' => $status,
                'status_label' => self::statusLabel($status),
                'pieces' => (int) $linha['pieces'],
                'subtotal' => Price::format((int) $linha['subtotal_cents']),
                'shipping' => (int) $linha['shipping_cents'] > 0
                    ? Price::format((int) $linha['shipping_cents'])
                    : 'A calcular',
                'total' => Price::format((int) $linha['total_cents']),
                'address' => [
                    'label' => (string) $linha['ship_label'],
                    'recipient' => Crypto::decrypt((string) $linha['ship_recipient_encrypted']),
                    'postal_code' => (string) $linha['ship_postal_code'],
                    'street' => Crypto::decrypt((string) $linha['ship_street_encrypted']),
                    'number' => Crypto::decrypt((string) $linha['ship_number_encrypted']),
                    'complement' => $linha['ship_complement_encrypted'] !== null
                        ? Crypto::decrypt((string) $linha['ship_complement_encrypted'])
                        : '',
                    'district' => Crypto::decrypt((string) $linha['ship_district_encrypted']),
                    'city' => Crypto::decrypt((string) $linha['ship_city_encrypted']),
                    'state' => (string) $linha['ship_state'],
                ],
                'items' => $itensPorPedido[$id] ?? [],
            ];
        }

        return $pedidos;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? 'Em análise';
    }

    /**
     * Itens do carrinho com as linhas travadas para o fechamento.
     *
     * Precisa rodar dentro da transação: o FOR UPDATE impede que duas abas
     * fechem o mesmo carrinho ao mesmo tempo e vendam o mesmo estoque duas
     * vezes. A ordem por variante mantém a ordem de travamento estável, o que
     * evita deadlock com o CartService.
     *
     * @return list<array<string, mixed>>
     */
    private function lockedCartItems(int $userId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT ci.variant_id,
                    ci.quantity,
                    v.size,
                    v.sku,
                    p.name,
                    p.slug,
                    p.price_cents,
                    p.is_active,
                    COALESCE(s.quantity, 0) AS stock_quantity,
                    COALESCE(s.reserved, 0) AS stock_reserved
               FROM cart_items ci
               INNER JOIN carts c ON c.id = ci.cart_id
               INNER JOIN product_variants v ON v.id = ci.variant_id
               INNER JOIN products p ON p.id = v.product_id
               LEFT JOIN stock s ON s.variant_id = v.id
              WHERE c.user_id = :user
              ORDER BY ci.variant_id
                FOR UPDATE'
        );

        $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
        $consulta->execute();

        return $consulta->fetchAll();
    }

    /**
     * @param list<array<string, mixed>> $itens
     */
    private function conferirEstoque(array $itens): ?string
    {
        foreach ($itens as $item) {
            $tamanho = (string) $item['size'];
            $nome = (string) $item['name'];

            if ((int) $item['is_active'] !== 1) {
                return sprintf('%s (tamanho %s) saiu da loja. Remova o item para continuar.', $nome, $tamanho);
            }

            $disponivel = max(0, (int) $item['stock_quantity'] - (int) $item['stock_reserved']);
            $quantidade = (int) $item['quantity'];

            if ($quantidade <= $disponivel) {
                continue;
            }

            if ($disponivel === 0) {
                return sprintf('O tamanho %s de %s esgotou. Remova o item para continuar.', $tamanho, $nome);
            }

            return $disponivel === 1
                ? sprintf('Resta 1 unidade do tamanho %s de %s. Ajuste a quantidade.', $tamanho, $nome)
                : sprintf('Restam %d unidades do tamanho %s de %s. Ajuste a quantidade.', $disponivel, $tamanho, $nome);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $endereco
     *
     * @return array{0: int, 1: string}
     */
    private function insertOrder(int $userId, array $endereco, int $subtotal, int $pecas): array
    {
        // O número legível nasce do id, que só existe depois do INSERT: a linha
        // entra com um marcador aleatório e é renomeada logo em seguida, ainda
        // dentro da transação. A coluna continua NOT NULL e UNIQUE.
        $consulta = $this->pdo->prepare(
            'INSERT INTO orders
                (number, user_id, status, pieces, subtotal_cents, shipping_cents, total_cents,
                 ship_label, ship_recipient_encrypted, ship_postal_code, ship_street_encrypted,
                 ship_number_encrypted, ship_complement_encrypted, ship_district_encrypted,
                 ship_city_encrypted, ship_state)
             VALUES
                (:marcador, :user, :status, :pieces, :subtotal, :frete, :total,
                 :label, :recipient, :postal_code, :street, :numero_endereco, :complement,
                 :district, :city, :state)'
        );

        $consulta->bindValue(':marcador', 'tmp-' . bin2hex(random_bytes(8)));
        $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
        $consulta->bindValue(':status', 'aguardando_pagamento');
        $consulta->bindValue(':pieces', $pecas, PDO::PARAM_INT);
        $consulta->bindValue(':subtotal', $subtotal, PDO::PARAM_INT);
        $consulta->bindValue(':frete', 0, PDO::PARAM_INT);
        $consulta->bindValue(':total', $subtotal, PDO::PARAM_INT);
        $consulta->bindValue(':label', $endereco['label']);
        $consulta->bindValue(':recipient', Crypto::encrypt((string) $endereco['recipient']));
        $consulta->bindValue(':postal_code', (string) $endereco['postal_code']);
        $consulta->bindValue(':street', Crypto::encrypt((string) $endereco['street']));
        $consulta->bindValue(':numero_endereco', Crypto::encrypt((string) $endereco['number']));

        if ((string) $endereco['complement'] === '') {
            $consulta->bindValue(':complement', null, PDO::PARAM_NULL);
        } else {
            $consulta->bindValue(':complement', Crypto::encrypt((string) $endereco['complement']));
        }

        $consulta->bindValue(':district', Crypto::encrypt((string) $endereco['district']));
        $consulta->bindValue(':city', Crypto::encrypt((string) $endereco['city']));
        $consulta->bindValue(':state', (string) $endereco['state']);
        $consulta->execute();

        $id = (int) $this->pdo->lastInsertId();
        $numero = self::NUMBER_PREFIX . str_pad((string) $id, 6, '0', STR_PAD_LEFT);

        $this->pdo->prepare('UPDATE orders SET number = :numero WHERE id = :id')
            ->execute([':numero' => $numero, ':id' => $id]);

        return [$id, $numero];
    }

    /**
     * @param list<array<string, mixed>> $itens
     */
    private function insertItems(int $orderId, array $itens): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO order_items
                (order_id, variant_id, product_name, product_slug, variant_size, sku,
                 unit_price_cents, quantity, line_total_cents)
             VALUES
                (:order, :variant, :name, :slug, :size, :sku, :preco, :quantidade, :linha)'
        );

        foreach ($itens as $item) {
            $preco = (int) $item['price_cents'];
            $quantidade = (int) $item['quantity'];

            $consulta->bindValue(':order', $orderId, PDO::PARAM_INT);
            $consulta->bindValue(':variant', (int) $item['variant_id'], PDO::PARAM_INT);
            $consulta->bindValue(':name', (string) $item['name']);
            $consulta->bindValue(':slug', (string) $item['slug']);
            $consulta->bindValue(':size', (string) $item['size']);
            $consulta->bindValue(':sku', (string) $item['sku']);
            $consulta->bindValue(':preco', $preco, PDO::PARAM_INT);
            $consulta->bindValue(':quantidade', $quantidade, PDO::PARAM_INT);
            $consulta->bindValue(':linha', $preco * $quantidade, PDO::PARAM_INT);
            $consulta->execute();
        }
    }

    /**
     * Baixa o estoque vendido. A condição no WHERE repete a conferência dentro
     * do próprio UPDATE: se a linha mudasse entre a leitura e a gravação, nada
     * é debitado e a transação cai.
     *
     * @param list<array<string, mixed>> $itens
     */
    private function debitStock(array $itens): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE stock
                SET quantity = quantity - :quantidade
              WHERE variant_id = :variant
                AND quantity - reserved >= :conferencia'
        );

        foreach ($itens as $item) {
            $quantidade = (int) $item['quantity'];

            $consulta->bindValue(':quantidade', $quantidade, PDO::PARAM_INT);
            $consulta->bindValue(':variant', (int) $item['variant_id'], PDO::PARAM_INT);
            $consulta->bindValue(':conferencia', $quantidade, PDO::PARAM_INT);
            $consulta->execute();

            if ($consulta->rowCount() !== 1) {
                throw new RuntimeException('O estoque mudou durante o fechamento do pedido.');
            }
        }
    }

    private function clearCart(int $userId): void
    {
        $consulta = $this->pdo->prepare(
            'DELETE ci
               FROM cart_items ci
               INNER JOIN carts c ON c.id = ci.cart_id
              WHERE c.user_id = :user'
        );

        $consulta->bindValue(':user', $userId, PDO::PARAM_INT);
        $consulta->execute();
    }

    /**
     * Itens de todos os pedidos da página em uma consulta só.
     *
     * @param list<int> $orderIds
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function itemsForOrders(array $orderIds): array
    {
        $marcadores = implode(', ', array_fill(0, count($orderIds), '?'));

        $consulta = $this->pdo->prepare(
            "SELECT order_id,
                    product_name,
                    product_slug,
                    variant_size,
                    quantity,
                    unit_price_cents,
                    line_total_cents
               FROM order_items
              WHERE order_id IN ({$marcadores})
              ORDER BY order_id, id"
        );

        $consulta->execute($orderIds);

        $porPedido = [];

        foreach ($consulta->fetchAll() as $linha) {
            $porPedido[(int) $linha['order_id']][] = [
                'name' => (string) $linha['product_name'],
                'slug' => (string) $linha['product_slug'],
                'size' => (string) $linha['variant_size'],
                'quantity' => (int) $linha['quantity'],
                'price' => Price::format((int) $linha['unit_price_cents']),
                'line_total' => Price::format((int) $linha['line_total_cents']),
            ];
        }

        return $porPedido;
    }
}
