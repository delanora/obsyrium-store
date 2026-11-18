<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Price;
use PDO;

/**
 * Cotação de frete nos Correios pela API oficial.
 *
 * A API de preço e prazo é restrita: exige contrato e as credenciais do CWS
 * (usuário, senha, contrato e DR). Sem elas a chamada volta 403, então a loja
 * continua vendendo com a tabela por estado e o checkout cai nela sempre que a
 * cotação não está configurada ou falha.
 *
 * O token (Basic com usuário e senha do CWS) vale por algumas horas e fica no
 * cache da tabela `shipping_quotes`. Preço e prazo vão em lote, um item por
 * serviço (PAC e SEDEX), e o resultado é cacheado por seis horas: cada visita
 * ao carrinho e ao checkout não vira uma chamada nova aos Correios.
 */
final class CorreiosService
{
    public const PAC = '03298';

    public const SEDEX = '04014';

    /** Serviços cotados, na ordem em que aparecem no checkout. */
    public const SERVICES = [self::PAC => 'PAC', self::SEDEX => 'SEDEX'];

    private const TOKEN_URL = 'https://api.correios.com.br/token/v1/autentica/contrato';

    private const PRICE_URL = 'https://api.correios.com.br/preco/v1/nacional';

    private const DEADLINE_URL = 'https://api.correios.com.br/prazo/v1/nacional';

    private const CACHE_SECONDS = 21600;

    /** Pacote mínimo aceito pelos Correios, em gramas. */
    private const MIN_WEIGHT = 300;

    private const MAX_WEIGHT = 30000;

    /** Medidas da embalagem, em centímetros (caixa padrão da loja). */
    private const PACKAGE = ['length' => '20', 'width' => '15', 'height' => '10'];

    private string $error = '';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function lastError(): string
    {
        return $this->error;
    }

    /**
     * Preço e prazo de PAC e SEDEX para o CEP de destino.
     *
     * @param array<string, mixed> $config configurações do ShippingService
     *
     * @return list<array{code: string, name: string, price_cents: int, days: int}>
     */
    public function quote(array $config, string $destinationCep, int $weightGrams): array
    {
        $this->error = '';

        $origem = preg_replace('/\D+/', '', (string) ($config['origin_postal_code'] ?? '')) ?? '';
        $destino = preg_replace('/\D+/', '', $destinationCep) ?? '';

        if (strlen($origem) !== 8) {
            $this->error = 'Informe o CEP de origem da loja (oito dígitos) no painel do frete.';

            return [];
        }

        if (strlen($destino) !== 8) {
            $this->error = 'CEP de destino inválido.';

            return [];
        }

        $peso = max(self::MIN_WEIGHT, min(self::MAX_WEIGHT, $weightGrams));
        $chave = 'cotacao|' . $origem . '|' . $destino . '|' . $peso;

        $emCache = $this->cacheGet($chave);

        if (is_array($emCache)) {
            return $emCache;
        }

        $token = $this->token($config);

        if ($token === null) {
            return [];
        }

        $precos = $this->prices($config, $token, $origem, $destino, $peso);

        if ($precos === []) {
            return [];
        }

        $prazos = $this->deadlines($token, $origem, $destino);
        $cotacoes = [];

        foreach ($precos as $codigo => $centavos) {
            $cotacoes[] = [
                'code' => (string) $codigo,
                'name' => self::SERVICES[$codigo],
                'price_cents' => (int) $centavos,
                'days' => (int) ($prazos[$codigo] ?? 0),
            ];
        }

        $this->cachePut($chave, $cotacoes, self::CACHE_SECONDS);

        return $cotacoes;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, int> preço em centavos por código de serviço
     */
    private function prices(
        array $config,
        string $token,
        string $origem,
        string $destino,
        int $peso
    ): array {
        $parametros = [];

        foreach (array_keys(self::SERVICES) as $codigo) {
            $parametros[] = [
                'coProduto' => $codigo,
                'nuContrato' => (string) $config['correios_contract'],
                'nuDR' => (int) $config['correios_dr'],
                'cepOrigem' => $origem,
                'cepDestino' => $destino,
                'psObjeto' => (string) $peso,
                'tpObjeto' => '2',
                'comprimento' => self::PACKAGE['length'],
                'largura' => self::PACKAGE['width'],
                'altura' => self::PACKAGE['height'],
            ];
        }

        $resposta = $this->postJson(self::PRICE_URL, [
            'idLote' => $this->batchId(),
            'parametrosProduto' => $parametros,
        ], 'Bearer ' . $token);

        if ($resposta === null) {
            return [];
        }

        $valores = [];

        foreach ($resposta as $linha) {
            if (!is_array($linha)) {
                continue;
            }

            $codigo = (string) ($linha['coProduto'] ?? '');

            if (!isset(self::SERVICES[$codigo])) {
                continue;
            }

            $erro = trim((string) ($linha['txErro'] ?? ''));

            if ($erro !== '') {
                $this->error = sprintf('%s: %s', self::SERVICES[$codigo], $erro);

                continue;
            }

            $centavos = Price::parse((string) ($linha['pcFinal'] ?? ''));

            if ($centavos === null || $centavos <= 0) {
                $this->error = sprintf('%s: preço não retornado pelos Correios.', self::SERVICES[$codigo]);

                continue;
            }

            $valores[$codigo] = $centavos;
        }

        return $valores;
    }

    /**
     * @return array<string, int> prazo em dias úteis por código de serviço
     */
    private function deadlines(string $token, string $origem, string $destino): array
    {
        $parametros = [];

        foreach (array_keys(self::SERVICES) as $codigo) {
            $parametros[] = [
                'coProduto' => $codigo,
                'cepOrigem' => $origem,
                'cepDestino' => $destino,
            ];
        }

        $resposta = $this->postJson(self::DEADLINE_URL, [
            'idLote' => $this->batchId(),
            'parametrosPrazo' => $parametros,
        ], 'Bearer ' . $token);

        if ($resposta === null) {
            return [];
        }

        $prazos = [];

        foreach ($resposta as $linha) {
            if (!is_array($linha)) {
                continue;
            }

            $codigo = (string) ($linha['coProduto'] ?? '');
            $dias = (int) ($linha['prazoEntrega'] ?? 0);

            if (isset(self::SERVICES[$codigo]) && $dias > 0) {
                $prazos[$codigo] = $dias;
            }
        }

        return $prazos;
    }

    /**
     * Token de contrato, reaproveitado do cache enquanto estiver válido.
     *
     * @param array<string, mixed> $config
     */
    private function token(array $config): ?string
    {
        $usuario = (string) ($config['correios_user'] ?? '');
        $senha = (string) ($config['correios_password'] ?? '');
        $contrato = (string) ($config['correios_contract'] ?? '');

        if ($usuario === '' || $senha === '' || $contrato === '') {
            $this->error = 'Preencha usuário, senha e contrato dos Correios no painel do frete.';

            return null;
        }

        $chave = 'token|' . hash('sha256', $usuario . '|' . $contrato);
        $emCache = $this->cacheGet($chave);

        if (is_array($emCache) && isset($emCache['token'])) {
            return (string) $emCache['token'];
        }

        $resposta = $this->postJson(
            self::TOKEN_URL,
            [
                'numero' => $contrato,
                'contrato' => $contrato,
                'dr' => (int) ($config['correios_dr'] ?? 0),
            ],
            'Basic ' . base64_encode($usuario . ':' . $senha)
        );

        if ($resposta === null || !isset($resposta['token'])) {
            if ($this->error === '') {
                $this->error = 'Os Correios não devolveram o token de acesso.';
            }

            return null;
        }

        $token = (string) $resposta['token'];
        $expira = strtotime((string) ($resposta['expiraEm'] ?? ''));
        $segundos = $expira !== false
            ? max(60, min(self::CACHE_SECONDS, $expira - time() - 60))
            : 1800;

        $this->cachePut($chave, ['token' => $token], $segundos);

        return $token;
    }

    /**
     * POST em JSON com o cabeçalho de autorização informado.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<int|string, mixed>|null
     */
    private function postJson(string $url, array $payload, string $authorization): ?array
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $authorization,
                ]),
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}',
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $corpo = @file_get_contents($url, false, $contexto);

        if ($corpo === false) {
            $this->error = 'Não foi possível falar com os Correios agora.';

            return null;
        }

        $status = 0;

        foreach ($http_response_header ?? [] as $linha) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $linha, $partes) === 1) {
                $status = (int) $partes[1];
            }
        }

        $dados = json_decode($corpo, true);

        // O gateway dos Correios responde 401 sem corpo quando o usuário ou a
        // senha não conferem — sem esta checagem antes do JSON, o painel
        // mostraria "resposta inesperada" no lugar do motivo real.
        if ($status >= 400) {
            $mensagens = is_array($dados) && isset($dados['msgs']) && is_array($dados['msgs'])
                ? implode(' ', array_map(static fn ($item): string => (string) $item, $dados['msgs']))
                : '';

            $this->error = sprintf(
                'Correios (%d): %s',
                $status,
                $mensagens !== '' ? $mensagens : $this->statusMessage($status)
            );

            return null;
        }

        if (!is_array($dados)) {
            $this->error = 'Resposta inesperada dos Correios.';

            return null;
        }

        return $dados;
    }

    private function statusMessage(int $status): string
    {
        return match ($status) {
            401, 403 => 'usuário, senha ou contrato recusados.',
            429 => 'consultas em excesso em pouco tempo. Tente de novo em alguns minutos.',
            default => 'consulta recusada.',
        };
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

    private function batchId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
