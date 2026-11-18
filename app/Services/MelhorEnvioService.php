<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Crypto;
use App\Support\Price;
use PDO;
use Throwable;

/**
 * Cotação de frete no Melhor Envio.
 *
 * O Melhor Envio agrega Correios, Jadlog, Loggi e outras transportadoras numa
 * conta só. A API v2 é OAuth 2.0: a loja cria um aplicativo no painel, informa
 * client_id e client_secret no painel do frete, clica em "Conectar" e autoriza
 * a conta; o retorno traz access_token (30 dias) e refresh_token (45 dias).
 *
 * O token fica cifrado em `settings` e é renovado sozinho perto de expirar. A
 * cotação vai em `POST /api/v2/me/shipment/calculate`, com peso em quilos e
 * medidas em centímetros, e o resultado fica em `shipping_quotes` por seis
 * horas — carrinho e checkout não chamam a API a cada visita.
 *
 * Sem conexão, a loja continua vendendo com a tabela por estado: o
 * ShippingService cai nela sempre que a cotação não responde.
 */
final class MelhorEnvioService
{
    public const PRODUCTION = 'https://melhorenvio.com.br';

    public const SANDBOX = 'https://sandbox.melhorenvio.com.br';

    /** Permissão pedida na autorização: só o cálculo de frete. */
    public const SCOPE = 'shipping-calculate';

    private const ACCESS_KEY = 'shipping_melhorenvio_access_token';

    private const REFRESH_KEY = 'shipping_melhorenvio_refresh_token';

    private const EXPIRES_KEY = 'shipping_melhorenvio_expires_at';

    private const CACHE_SECONDS = 21600;

    /** Limite de peso por volume aceito pelas transportadoras (kg). */
    private const MIN_WEIGHT_KG = 0.3;

    private const MAX_WEIGHT_KG = 30.0;

    private string $error = '';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function lastError(): string
    {
        return $this->error;
    }

    /**
     * Endereço do painel de autorização do Melhor Envio.
     *
     * @param array<string, mixed> $config configurações do ShippingService
     */
    public function authorizeUrl(array $config, string $redirectUri, string $state): ?string
    {
        $this->error = '';

        if ((string) ($config['melhorenvio_client_id'] ?? '') === ''
            || (string) ($config['melhorenvio_client_secret'] ?? '') === ''
        ) {
            $this->error = 'Preencha o Client ID e o Client Secret do aplicativo antes de conectar.';

            return null;
        }

        return $this->base($config) . '/oauth/authorize?' . http_build_query([
            'client_id' => (string) $config['melhorenvio_client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
        ]);
    }

    /**
     * Troca o código devolvido pelo Melhor Envio pelos tokens e guarda tudo.
     *
     * @param array<string, mixed> $config
     */
    public function exchangeCode(array $config, string $code, string $redirectUri): bool
    {
        $this->error = '';

        $resposta = $this->postJson($this->base($config) . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => (int) $config['melhorenvio_client_id'],
            'client_secret' => (string) $config['melhorenvio_client_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ], null);

        if ($resposta === null) {
            // O código devolvido na autorização vale uma vez só e expira rápido:
            // o caso comum aqui é ele ter sido usado ou ter demorado, não a
            // credencial. Credencial recusada pede outro caminho de conserto.
            $this->error = str_contains($this->error, 'invalid_client')
                ? 'O Melhor Envio recusou as credenciais do aplicativo (Client ID/Secret). Use "Testar credenciais" e confira se os dois são do mesmo aplicativo.'
                : $this->error . ' O código de autorização vale uma vez só: clique em Conectar de novo.';

            return false;
        }

        return $this->storeTokens($resposta);
    }

    /**
     * Confere se o Client ID e o Client Secret são aceitos, sem precisar de uma
     * autorização nova: com credencial válida o `/oauth/token` recusa o
     * refresh_token de teste (erro de grant); com credencial errada devolve
     * `invalid_client`.
     *
     * @param array<string, mixed> $config
     *
     * @return string|null mensagem de erro, ou null quando as credenciais valem
     */
    public function verifyCredentials(array $config): ?string
    {
        $this->error = '';

        if ((string) ($config['melhorenvio_client_id'] ?? '') === ''
            || (string) ($config['melhorenvio_client_secret'] ?? '') === ''
        ) {
            return 'Preencha o Client ID e o Client Secret antes de testar.';
        }

        $resposta = $this->request($this->base($config) . '/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => (int) $config['melhorenvio_client_id'],
            'client_secret' => (string) $config['melhorenvio_client_secret'],
            'refresh_token' => 'verificacao-de-credenciais',
        ], null, $this->userAgent($config));

        if ($resposta === null) {
            return $this->error;
        }

        $dados = is_array($resposta['data']) ? $resposta['data'] : [];
        $erro = strtolower((string) ($dados['error'] ?? ''));
        $descricao = trim((string) ($dados['error_description'] ?? $dados['message'] ?? ''));

        if (str_contains($erro, 'invalid_client')) {
            return 'O Melhor Envio recusou as credenciais: o Client ID e o Client Secret não são do mesmo aplicativo, ou são de outro ambiente (sandbox x produção).'
                . ($descricao !== '' ? ' ' . $descricao : '');
        }

        if ($resposta['status'] >= 500) {
            return sprintf('O Melhor Envio respondeu %d ao teste. Tente de novo em instantes.', $resposta['status']);
        }

        return null;
    }

    public function disconnect(): void
    {
        foreach ([self::ACCESS_KEY, self::REFRESH_KEY] as $nome) {
            $this->saveSetting($nome, '');
        }

        $this->saveSetting(self::EXPIRES_KEY, '0');
    }

    /**
     * Preço e prazo por transportadora para o CEP de destino.
     *
     * @param array<string, mixed>            $config
     * @param list<array<string, mixed>>      $products linhas no formato do Melhor Envio
     *
     * @return list<array{code: string, name: string, company: string, price_cents: int, days: int}>
     */
    public function quote(array $config, string $toCep, array $products, int $subtotalCents): array
    {
        $this->error = '';

        $origem = preg_replace('/\D+/', '', (string) ($config['origin_postal_code'] ?? '')) ?? '';
        $destino = preg_replace('/\D+/', '', $toCep) ?? '';

        if (strlen($origem) !== 8) {
            $this->error = 'Informe o CEP de origem da loja (oito dígitos) no painel do frete.';

            return [];
        }

        if (strlen($destino) !== 8) {
            $this->error = 'CEP de destino inválido.';

            return [];
        }

        if ($products === []) {
            $this->error = 'Nenhum produto para cotar.';

            return [];
        }

        $chave = 'melhor-envio|' . $origem . '|' . $destino . '|' . $subtotalCents
            . '|' . md5((string) json_encode($products));

        $emCache = $this->cacheGet($chave);

        if (is_array($emCache)) {
            return $emCache;
        }

        $token = $this->token($config);

        if ($token === null) {
            return [];
        }

        $resposta = $this->postJson(
            $this->base($config) . '/api/v2/me/shipment/calculate',
            [
                'from' => ['postal_code' => $origem],
                'to' => ['postal_code' => $destino],
                'products' => $products,
                'options' => ['receipt' => false, 'own_hand' => false],
            ],
            $token,
            $this->userAgent($config)
        );

        if ($resposta === null) {
            return [];
        }

        $cotacoes = [];

        foreach ($resposta as $linha) {
            if (!is_array($linha) || isset($linha['message'])) {
                continue;
            }

            if (trim((string) ($linha['error'] ?? '')) !== '') {
                continue;
            }

            $centavos = Price::parse((string) ($linha['custom_price'] ?? $linha['price'] ?? ''));

            if ($centavos === null || $centavos <= 0) {
                continue;
            }

            $id = (string) ($linha['id'] ?? '');
            $nome = trim((string) ($linha['name'] ?? ''));
            $empresa = trim((string) ($linha['company']['name'] ?? ''));
            $dias = (int) ($linha['custom_delivery_time'] ?? $linha['delivery_time'] ?? 0);

            $cotacoes[] = [
                'code' => 'me-' . ($id !== '' ? $id : substr(md5($nome . $empresa), 0, 8)),
                'name' => $nome !== '' ? $nome : 'Entrega',
                'company' => $empresa,
                'price_cents' => $centavos,
                'days' => max(0, $dias),
            ];
        }

        if ($cotacoes === []) {
            $this->error = 'Nenhuma transportadora atende este trecho agora.';

            return [];
        }

        $this->cachePut($chave, $cotacoes, self::CACHE_SECONDS);

        return $cotacoes;
    }

    public function hasToken(): bool
    {
        $consulta = $this->pdo->prepare('SELECT value FROM settings WHERE name = :nome LIMIT 1');
        $consulta->execute([':nome' => self::ACCESS_KEY]);

        return trim($this->decifrar((string) ($consulta->fetchColumn() ?: ''))) !== '';
    }

    public function expiresAt(): int
    {
        $consulta = $this->pdo->prepare('SELECT value FROM settings WHERE name = :nome LIMIT 1');
        $consulta->execute([':nome' => self::EXPIRES_KEY]);

        return (int) ($consulta->fetchColumn() ?: 0);
    }

    /**
     * Access token válido: usa o guardado enquanto durar e renova com o
     * refresh_token quando estiver perto de expirar.
     *
     * @param array<string, mixed> $config
     */
    public function token(array $config): ?string
    {
        $consulta = $this->pdo->prepare('SELECT name, value FROM settings WHERE name IN (:access, :refresh, :expires)');
        $consulta->execute([
            ':access' => self::ACCESS_KEY,
            ':refresh' => self::REFRESH_KEY,
            ':expires' => self::EXPIRES_KEY,
        ]);

        $valores = [];

        foreach ($consulta as $linha) {
            $valores[(string) $linha['name']] = (string) $linha['value'];
        }

        $acesso = $this->decifrar($valores[self::ACCESS_KEY] ?? '');
        $expira = (int) ($valores[self::EXPIRES_KEY] ?? '0');

        if ($acesso !== '' && $expira > time() + 120) {
            return $acesso;
        }

        $renovacao = $this->decifrar($valores[self::REFRESH_KEY] ?? '');

        if ($renovacao === '') {
            $this->error = 'Conecte a conta do Melhor Envio no painel do frete para calcular o frete.';

            return null;
        }

        $resposta = $this->postJson($this->base($config) . '/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => (int) ($config['melhorenvio_client_id'] ?? 0),
            'client_secret' => (string) ($config['melhorenvio_client_secret'] ?? ''),
            'refresh_token' => $renovacao,
        ], null);

        if ($resposta === null || !$this->storeTokens($resposta)) {
            $this->error = 'A conexão com o Melhor Envio expirou. Conecte a conta de novo no painel do frete.';

            return null;
        }

        return (string) ($resposta['access_token'] ?? '');
    }

    private function base(array $config): string
    {
        return ($config['melhorenvio_sandbox'] ?? false) ? self::SANDBOX : self::PRODUCTION;
    }

    /**
     * O Melhor Envio exige o nome do aplicativo e um e-mail de contato no
     * User-Agent; sem esse cabeçalho a requisição é recusada.
     *
     * @param array<string, mixed> $config
     */
    private function userAgent(array $config): string
    {
        $email = trim((string) ($config['melhorenvio_email'] ?? ''));

        return sprintf('Obsyrium Store (%s)', $email !== '' ? $email : 'contato@obsyrium.com.br');
    }

    /**
     * @param array<int|string, mixed> $tokens
     */
    private function storeTokens(array $tokens): bool
    {
        $acesso = trim((string) ($tokens['access_token'] ?? ''));

        if ($acesso === '') {
            $this->error = 'O Melhor Envio não devolveu o token de acesso.';

            return false;
        }

        $segundos = max(600, (int) ($tokens['expires_in'] ?? 2592000));

        $this->saveSetting(self::ACCESS_KEY, Crypto::encrypt($acesso));
        $this->saveSetting(self::EXPIRES_KEY, (string) (time() + $segundos - 60));

        $renovacao = trim((string) ($tokens['refresh_token'] ?? ''));

        if ($renovacao !== '') {
            $this->saveSetting(self::REFRESH_KEY, Crypto::encrypt($renovacao));
        }

        return true;
    }

    /**
     * Produz uma linha de produto no formato aceito pelo cálculo do Melhor
     * Envio: medidas em cm, peso em kg e valor unitário do seguro. As medidas
     * podem vir no próprio item (`width`, `height`, `length`), como faz a
     * consulta da página do produto; sem elas vale o pacote pequeno padrão.
     *
     * @param array<string, mixed> $item
     *
     * @return array{id: string, width: int, height: int, length: int, weight: float, insurance_value: float, quantity: int}
     */
    public static function productLine(array $item, int $defaultWeightGrams): array
    {
        $gramas = (int) ($item['weight_grams'] ?? 0);

        if ($gramas <= 0) {
            $gramas = $defaultWeightGrams;
        }

        $quilos = min(self::MAX_WEIGHT_KG, max(self::MIN_WEIGHT_KG, $gramas / 1000));

        return [
            'id' => (string) ($item['id'] ?? $item['slug'] ?? 'peca'),
            'width' => max(1, (int) ($item['width'] ?? 20)),
            'height' => max(1, (int) ($item['height'] ?? 10)),
            'length' => max(1, (int) ($item['length'] ?? 15)),
            'weight' => round($quilos, 3),
            'insurance_value' => round(max(0, (int) ($item['price_cents'] ?? 0)) / 100, 2),
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
        ];
    }

    /**
     * @param array<int|string, mixed> $payload
     */
    private function postJson(
        string $url,
        array $payload,
        ?string $token,
        ?string $userAgent = null
    ): ?array {
        $resposta = $this->request($url, $payload, $token, $userAgent);

        if ($resposta === null) {
            return null;
        }

        if ($resposta['status'] >= 400) {
            $this->error = sprintf(
                'Melhor Envio (%d): %s',
                $resposta['status'],
                $this->errorMessage($resposta['data'])
            );

            return null;
        }

        if (!is_array($resposta['data'])) {
            $this->error = 'Resposta inesperada do Melhor Envio.';

            return null;
        }

        return $resposta['data'];
    }

    /**
     * POST cru: devolve o status e o corpo decodificado sem julgar o que é
     * erro. A verificação de credenciais precisa ler o `invalid_client` do 401
     * para separar credencial recusada de grant inválido.
     *
     * @param array<int|string, mixed> $payload
     *
     * @return array{status: int, data: mixed}|null
     */
    private function request(
        string $url,
        array $payload,
        ?string $token,
        ?string $userAgent = null
    ): ?array {
        $cabecalhos = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ' . ($userAgent ?? 'Obsyrium Store (contato@obsyrium.com.br)'),
        ];

        if ($token !== null) {
            $cabecalhos[] = 'Authorization: Bearer ' . $token;
        }

        $contexto = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $cabecalhos),
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}',
                'timeout' => 20,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $corpo = @file_get_contents($url, false, $contexto);

        if ($corpo === false) {
            $this->error = 'Não foi possível falar com o Melhor Envio agora.';

            return null;
        }

        $status = 0;

        foreach ($http_response_header ?? [] as $linha) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $linha, $partes) === 1) {
                $status = (int) $partes[1];
            }
        }

        return ['status' => $status, 'data' => json_decode($corpo, true)];
    }

    /**
     * A API responde erros em formatos diferentes: `message`, `error` +
     * `error_description` ou `errors` por campo.
     */
    private function errorMessage(mixed $dados): string
    {
        if (!is_array($dados)) {
            return 'consulta recusada.';
        }

        $mensagem = trim((string) ($dados['error_description'] ?? $dados['message'] ?? $dados['error'] ?? ''));

        if ($mensagem !== '') {
            return $mensagem;
        }

        if (isset($dados['errors']) && is_array($dados['errors'])) {
            $partes = [];

            foreach ($dados['errors'] as $campo => $lista) {
                $texto = is_array($lista) ? implode(' ', array_map('strval', $lista)) : (string) $lista;

                if ($texto !== '') {
                    $partes[] = $texto;
                }
            }

            if ($partes !== []) {
                return implode(' ', $partes);
            }
        }

        return 'consulta recusada.';
    }

    private function cacheGet(string $chave): mixed
    {
        $consulta = $this->pdo->prepare(
            'SELECT payload FROM shipping_quotes WHERE cache_key = :chave AND expires_at > NOW() LIMIT 1'
        );
        $consulta->execute([':chave' => hash('sha256', $chave)]);

        $payload = $consulta->fetchColumn();

        if ($payload === false) {
            return null;
        }

        $dados = json_decode((string) $payload, true);

        return is_array($dados) ? $dados : null;
    }

    /**
     * @param array<int|string, mixed> $dados
     */
    private function cachePut(string $chave, array $dados, int $segundos): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO shipping_quotes (cache_key, payload, expires_at)
             VALUES (:chave, :payload, DATE_ADD(NOW(), INTERVAL :segundos SECOND))
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), expires_at = VALUES(expires_at)'
        );

        $consulta->bindValue(':chave', hash('sha256', $chave));
        $consulta->bindValue(':payload', json_encode($dados, JSON_UNESCAPED_UNICODE) ?: '{}');
        $consulta->bindValue(':segundos', $segundos, PDO::PARAM_INT);
        $consulta->execute();
    }

    private function saveSetting(string $nome, string $valor): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO settings (name, value) VALUES (:name, :value)
             ON DUPLICATE KEY UPDATE value = :value_2'
        );

        $consulta->execute([':name' => $nome, ':value' => $valor, ':value_2' => $valor]);
    }

    private function decifrar(string $valor): string
    {
        if ($valor === '') {
            return '';
        }

        try {
            return Crypto::decrypt($valor);
        } catch (Throwable) {
            return $valor;
        }
    }
}
