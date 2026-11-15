<?php

declare(strict_types=1);

namespace App\Support;

use Config\Env;
use RuntimeException;

/**
 * Cifra de dados sensíveis em repouso.
 *
 * AES-256-GCM via OpenSSL, com a versão da chave carimbada no payload:
 * "v2:" + base64(nonce + tag + ciphertext). A versão permite rotacionar a
 * APP_KEY sem perder o que já foi gravado — a chave antiga continua em
 * APP_KEY_PREVIOUS só para decifrar.
 *
 * Campos que precisam de unicidade ou busca (CPF) usam um índice cego
 * (HMAC-SHA256) derivado de APP_INDEX_KEY, que é independente da rotação para
 * não invalidar as buscas.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;
    private const KEY_BYTES = 32;

    /**
     * @var array<int, string>|null chaves por versão
     */
    private static ?array $keys = null;

    private static ?int $currentVersion = null;

    private static ?string $indexKey = null;

    public static function encrypt(string $plainText): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';

        $cipherText = openssl_encrypt(
            $plainText,
            self::CIPHER,
            self::keyFor(self::currentVersion()),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_BYTES
        );

        if ($cipherText === false) {
            throw new RuntimeException('Falha ao cifrar o dado.');
        }

        return 'v' . self::currentVersion() . ':' . base64_encode($nonce . $tag . $cipherText);
    }

    public static function decrypt(string $payload): string
    {
        if (preg_match('/^v(\d+):(.+)$/s', $payload, $partes) !== 1) {
            throw new RuntimeException('Dado cifrado sem versão de chave.');
        }

        $version = (int) $partes[1];
        $raw = base64_decode($partes[2], true);

        if ($raw === false || strlen($raw) <= self::NONCE_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Dado cifrado em formato inválido.');
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $tag = substr($raw, self::NONCE_BYTES, self::TAG_BYTES);
        $cipherText = substr($raw, self::NONCE_BYTES + self::TAG_BYTES);

        $plainText = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            self::keyFor($version),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plainText === false) {
            throw new RuntimeException(sprintf('Não foi possível decifrar o dado (chave v%d).', $version));
        }

        return $plainText;
    }

    /**
     * Versão usada nas novas gravações.
     */
    public static function currentVersion(): int
    {
        if (self::$currentVersion === null) {
            self::$currentVersion = max(1, Env::int('APP_KEY_VERSION', 1));
        }

        return self::$currentVersion;
    }

    /**
     * Índice cego: valor determinístico para WHERE e UNIQUE sobre campos
     * cifrados. O contexto separa domínios (cpf, telefone, ...).
     */
    public static function blindIndex(string $value, string $context): string
    {
        return hash_hmac('sha256', $context . '|' . $value, self::indexKey());
    }

    /**
     * Índice de CPF: normaliza os dígitos antes do HMAC, para que
     * "529.982.247-25" e "52998224725" caiam no mesmo índice.
     */
    public static function cpfIndex(string $cpf): string
    {
        return self::blindIndex(self::normalizeCpf($cpf), 'cpf');
    }

    public static function normalizeCpf(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?? '';
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * Converte uma chave "base64:..." em 32 bytes, ou null se inválida.
     */
    public static function decodeKey(string $configured): ?string
    {
        if (!str_starts_with($configured, 'base64:')) {
            return null;
        }

        $raw = base64_decode(substr($configured, strlen('base64:')), true);

        return $raw !== false && strlen($raw) === self::KEY_BYTES ? $raw : null;
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(self::KEY_BYTES));
    }

    /**
     * @return array<int, string>
     */
    private static function keys(): array
    {
        if (self::$keys !== null) {
            return self::$keys;
        }

        $current = Env::get('APP_KEY');
        $raw = $current !== null && $current !== '' ? self::decodeKey($current) : null;

        if ($raw === null) {
            throw new RuntimeException('APP_KEY ausente ou inválida: esperado base64: com 32 bytes.');
        }

        $keys = [self::currentVersion() => $raw];

        $anteriores = Env::get('APP_KEY_PREVIOUS', '');

        if ($anteriores !== null && $anteriores !== '') {
            foreach (explode(',', $anteriores) as $entrada) {
                $partes = explode(':', trim($entrada), 2);

                if (count($partes) !== 2) {
                    continue;
                }

                $version = (int) $partes[0];
                $chave = self::decodeKey($partes[1]);

                if ($version > 0 && $chave !== null) {
                    $keys[$version] = $chave;
                }
            }
        }

        return self::$keys = $keys;
    }

    private static function keyFor(int $version): string
    {
        $keys = self::keys();

        if (!isset($keys[$version])) {
            throw new RuntimeException(sprintf('Não há chave configurada para a versão v%d.', $version));
        }

        return $keys[$version];
    }

    private static function indexKey(): string
    {
        if (self::$indexKey !== null) {
            return self::$indexKey;
        }

        $configurada = Env::get('APP_INDEX_KEY');

        if ($configurada !== null && $configurada !== '') {
            $raw = self::decodeKey($configurada);

            if ($raw === null) {
                throw new RuntimeException('APP_INDEX_KEY inválida: esperado base64: com 32 bytes.');
            }

            return self::$indexKey = $raw;
        }

        return self::$indexKey = hash_hmac('sha256', 'obsyrium:blind-index', self::keyFor(self::currentVersion()), true);
    }
}
