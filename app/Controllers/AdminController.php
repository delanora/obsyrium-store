<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Repositories\AdminRepository;
use App\Repositories\LoginAttemptRepository;
use App\Services\AdminAuthService;
use App\Services\DashboardService;
use App\Services\LoginThrottle;
use App\Services\OrderService;
use App\Services\ProductAdminService;
use App\Services\StockService;
use App\Support\Csrf;
use App\Support\Session;

/**
 * Painel da administração: login, dashboard, pedidos e estoque.
 *
 * Toda rota além do login passa por `admin()`, que confere a sessão de
 * administrador antes de qualquer leitura. Toda escrita exige CSRF e responde
 * com 303 para o navegador não repetir o envio.
 */
final class AdminController
{
    private const TITLE_LOGIN = 'Entrar — Administração Obsyrium';

    public function showLogin(): void
    {
        if ($this->auth()->currentAdmin() !== null) {
            $this->redirect('/admin');
        }

        $this->renderLogin();
    }

    public function login(): void
    {
        if (!$this->csrfOk()) {
            return;
        }

        $erro = $this->auth()->login($_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ($erro === null) {
            Session::flash('success', 'Painel aberto.');
            $this->redirect('/admin');
        }

        $this->renderLogin(['credentials' => $erro], (string) ($_POST['email'] ?? ''));
    }

    public function logout(): void
    {
        if (!$this->csrfOk()) {
            return;
        }

        $this->auth()->logout();
        Session::flash('success', 'Você saiu do painel.');
        $this->redirect('/admin/login');
    }

    public function dashboard(): void
    {
        $admin = $this->admin();

        $this->render('dashboard', 'Dashboard — Administração Obsyrium', $admin, 'dashboard', [
            'resumo' => (new DashboardService(Connection::get()))->summary(),
        ]);
    }

    public function orders(): void
    {
        $admin = $this->admin();
        $status = isset($_GET['status']) ? (string) $_GET['status'] : '';

        $this->render('orders', 'Pedidos — Administração Obsyrium', $admin, 'pedidos', [
            'pedidos' => (new OrderService(Connection::get()))->adminOrders($status),
            'filtro' => isset(OrderService::STATUS_LABELS[$status]) ? $status : '',
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function order(array $params): void
    {
        $admin = $this->admin();
        $numero = (string) ($params['number'] ?? '');
        $pedido = (new OrderService(Connection::get()))->adminOrder($numero);

        if ($pedido === null) {
            Session::flash('error', 'Pedido não encontrado.');
            $this->redirect('/admin/pedidos');
        }

        $this->render('order', sprintf('Pedido %s — Administração Obsyrium', $numero), $admin, 'pedidos', [
            'pedido' => $pedido,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function changeStatus(array $params): void
    {
        $this->admin();

        $numero = (string) ($params['number'] ?? '');

        if (!$this->csrfOk()) {
            return;
        }

        $novoStatus = (string) ($_POST['status'] ?? '');
        $erro = (new OrderService(Connection::get()))->changeStatus($numero, $novoStatus);

        if ($erro !== null) {
            Session::flash('error', $erro);
        } else {
            Session::flash('success', sprintf(
                'Pedido %s agora está como %s.',
                $numero,
                mb_strtolower(OrderService::statusLabel($novoStatus))
            ));
        }

        $this->redirect('/admin/pedidos/' . rawurlencode($numero));
    }

    public function stock(): void
    {
        $admin = $this->admin();

        $estoque = (new StockService(Connection::get()))->page(
            (string) ($_GET['aba'] ?? 'todos'),
            (string) ($_GET['ordem'] ?? 'produto'),
            (string) ($_GET['direcao'] ?? 'asc'),
            (int) ($_GET['pagina'] ?? 1)
        );

        $this->render('estoque', 'Estoque — Administração Obsyrium', $admin, 'estoque', [
            'estoque' => $estoque,
        ]);
    }

    public function saveStock(): void
    {
        $this->admin();

        if (!$this->csrfOk()) {
            return;
        }

        $variacaoId = (int) ($_POST['variant_id'] ?? 0);
        $esperado = (int) ($_POST['atual'] ?? 0);
        $saldo = trim((string) ($_POST['quantity'] ?? ''));

        if (preg_match('/^\d{1,5}$/', $saldo) !== 1) {
            Session::flash('error', sprintf(
                'Informe o saldo em número inteiro, de 0 a %d.',
                StockService::MAX_QUANTITY
            ));
            $this->redirect($this->stockUrl($_POST));
        }

        $erro = (new StockService(Connection::get()))->update($variacaoId, (int) $saldo, $esperado);

        Session::flash($erro === null ? 'success' : 'error', $erro ?? 'Saldo atualizado.');

        $this->redirect($this->stockUrl($_POST));
    }

    /**
     * Lixeira: tira o modelo de linha (sai da loja e das abas de produtos
     * ativos). O saldo fica como está.
     */
    public function discontinue(): void
    {
        $this->admin();

        if (!$this->csrfOk()) {
            return;
        }

        $resultado = (new StockService(Connection::get()))
            ->setLine((int) ($_POST['product_id'] ?? 0), false);

        Session::flash(
            isset($resultado['error']) ? 'error' : 'success',
            $resultado['error'] ?? sprintf('%s saiu de linha.', (string) $resultado['product'])
        );

        $this->redirect($this->stockUrl($_POST));
    }

    /**
     * Carrinho da aba fora de linha: devolve o modelo à loja. Se não houver
     * saldo, ele reaparece esgotado.
     */
    public function restore(): void
    {
        $this->admin();

        if (!$this->csrfOk()) {
            return;
        }

        $resultado = (new StockService(Connection::get()))
            ->setLine((int) ($_POST['product_id'] ?? 0), true);

        Session::flash(
            isset($resultado['error']) ? 'error' : 'success',
            $resultado['error'] ?? sprintf('%s voltou para a loja.', (string) $resultado['product'])
        );

        $this->redirect($this->stockUrl($_POST));
    }

    /**
     * Editor do produto. É a tela do item da loja, com todos os campos
     * editáveis e as fotos sendo enviadas por ela.
     *
     * @param array<string, string> $params
     */
    public function product(array $params): void
    {
        $admin = $this->admin();

        $this->renderProduct((int) ($params['id'] ?? 0), $admin);
    }

    /**
     * @param array<string, string> $params
     */
    public function saveProduct(array $params): void
    {
        $admin = $this->admin();

        if (!$this->csrfOk()) {
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $resultado = (new ProductAdminService(Connection::get()))->save($id, $_POST);

        if ($resultado['errors'] !== []) {
            $this->renderProduct($id, $admin, $resultado['errors'], $resultado['values']);

            return;
        }

        Session::flash('success', 'Produto atualizado.');

        $this->redirect($this->stockUrl($_POST));
    }

    /**
     * @param array<string, string> $params
     */
    public function uploadPhoto(array $params): void
    {
        $this->admin();

        if (!$this->csrfOk()) {
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $arquivo = isset($_FILES['imagem']) && is_array($_FILES['imagem']) ? $_FILES['imagem'] : [];
        $resultado = (new ProductAdminService(Connection::get()))->upload($id, $arquivo);

        Session::flash(
            isset($resultado['error']) ? 'error' : 'success',
            $resultado['error'] ?? 'Foto enviada. Ela já aparece na página do produto.'
        );

        $this->redirect($this->productUrl($id, $_POST));
    }

    /**
     * @param array<string, string> $params
     */
    public function removePhoto(array $params): void
    {
        $this->admin();

        if (!$this->csrfOk()) {
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $erro = (new ProductAdminService(Connection::get()))->removeImage($id, (int) ($_POST['image_id'] ?? 0));

        Session::flash($erro === null ? 'success' : 'error', $erro ?? 'Foto apagada.');

        $this->redirect($this->productUrl($id, $_POST));
    }

    /**
     * @return array<string, mixed>
     */
    private function admin(): array
    {
        $admin = $this->auth()->currentAdmin();

        if ($admin === null) {
            Session::flash('error', 'Entre com uma conta de administração.');
            $this->redirect('/admin/login');
        }

        return $admin;
    }

    private function auth(): AdminAuthService
    {
        $pdo = Connection::get();

        return new AdminAuthService(new AdminRepository($pdo), new LoginThrottle(new LoginAttemptRepository($pdo)));
    }

    /**
     * Reconstrói a URL do estoque a partir dos campos do formulário, só com os
     * valores que o serviço conhece: aba, coluna, direção e página.
     *
     * @param array<string, mixed> $dados
     */
    private function stockUrl(array $dados): string
    {
        return '/admin/estoque?' . http_build_query($this->stockFiltros($dados));
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function productUrl(int $id, array $dados): string
    {
        return '/admin/estoque/produto/' . $id . '?' . http_build_query($this->stockFiltros($dados));
    }

    /**
     * @param array<string, mixed> $dados
     *
     * @return array<string, int|string>
     */
    private function stockFiltros(array $dados): array
    {
        $aba = (string) ($dados['aba'] ?? 'todos');
        $ordem = (string) ($dados['ordem'] ?? 'produto');
        $direcao = (string) ($dados['direcao'] ?? 'asc');

        return [
            'aba' => isset(StockService::TABS[$aba]) ? $aba : 'todos',
            'ordem' => isset(StockService::SORTABLE[$ordem]) ? $ordem : 'produto',
            'direcao' => strtolower($direcao) === 'desc' ? 'desc' : 'asc',
            'pagina' => max(1, (int) ($dados['pagina'] ?? 1)),
        ];
    }

    /**
     * @param array<string, mixed> $admin
     * @param list<string> $erros
     * @param array<string, string> $valores
     */
    private function renderProduct(int $id, array $admin, array $erros = [], array $valores = []): void
    {
        $servico = new ProductAdminService(Connection::get());
        $produto = $id > 0 ? $servico->find($id) : null;

        if ($produto === null) {
            Session::flash('error', 'Produto não encontrado.');
            $this->redirect($this->stockUrl($_GET));
        }

        $this->render(
            'produto',
            sprintf('%s — Administração Obsyrium', (string) $produto['name']),
            $admin,
            'estoque',
            [
                'produto' => $produto,
                'categorias' => $servico->categories(),
                'erros' => $erros,
                'valores' => $valores,
                'precos' => $servico->priceTags($produto),
                'limiteFotos' => $servico->photoLimitLabel(),
                'maxFotos' => ProductAdminService::MAX_IMAGES,
                'voltar' => $this->stockFiltros($_GET),
            ]
        );
    }

    /**
     * @param array<string, string> $erros
     */
    private function renderLogin(array $erros = [], string $email = ''): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $title = self::TITLE_LOGIN;
        $errors = $erros;
        $old = ['email' => $email];
        $adminAtual = null;
        $adminAba = 'dashboard';
        $content = $basePath . '/resources/views/admin/login.php';

        require $basePath . '/resources/views/layouts/admin.php';
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $contexto
     */
    private function render(string $view, string $title, array $admin, string $aba, array $contexto): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');

        $basePath = dirname(__DIR__, 2);
        $adminAtual = $admin;
        $adminAba = $aba;
        $adminPedidos = $contexto['pedidos'] ?? [];
        $adminFiltro = $contexto['filtro'] ?? '';
        $adminPedido = $contexto['pedido'] ?? null;
        $adminResumo = $contexto['resumo'] ?? [];
        $adminEstoque = $contexto['estoque'] ?? [];
        $adminProduto = $contexto['produto'] ?? null;
        $adminCategorias = $contexto['categorias'] ?? [];
        $adminErros = $contexto['erros'] ?? [];
        $adminValores = $contexto['valores'] ?? [];
        $adminPrecos = $contexto['precos'] ?? [];
        $adminVoltar = $contexto['voltar'] ?? [];
        $adminLimiteFotos = $contexto['limiteFotos'] ?? '';
        $adminMaxFotos = $contexto['maxFotos'] ?? 0;
        $content = $basePath . '/resources/views/admin/' . $view . '.php';

        require $basePath . '/resources/views/layouts/admin.php';
    }

    private function csrfOk(): bool
    {
        if (Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
            return true;
        }

        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');

        require dirname(__DIR__, 2) . '/resources/views/errors/403.php';

        return false;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
