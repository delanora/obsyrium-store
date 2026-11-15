<?php

declare(strict_types=1);

namespace App\Support;

use Config\Env;
use RuntimeException;

/**
 * Cifra de dados sensíveis em repouso.
 *
 * Usa AES-256-GCM via OpenSSL: o valor é gravado como base64 de nonce + tag +
 * ciphertext. Além de ilegível, o dado é autenticado — qualquer alteração no
 * banco faz a decifragem falhar em vez de devolver lixo.
 *
 * A chave vem de APP_KEY (.env, fora do controle de versão). Para campos que
 * precisam de unicidade ou busca (CPF), grava-se junto um índice cego:
 * HMAC-SHA256 determinístico, que compara valores sem revelá-los.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;
    private const KEY_BYTES = 32;

    private static ?string $key = null;

    public static function encrypt(string $plainText): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';

        $cipherText = openssl_encrypt(
            $plainText,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_BYTES
        );

        if ($cipherText === false) {
            throw new RuntimeException('Falha ao cifrar o dado.');
        }

        return base64_encode($nonce . $tag . $cipherText);
    }

    public static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);

        if ($raw === false || strlen($raw) <= self::NONCE_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Dado cifrado em formato inválido.');
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $tag = substr($raw, self::NONCE_BYTES, self::TAG_BYTES);
        $cipherText = substr($raw, self::NONCE_BYTES + self::TAG_BYTES);

        $plainText = openssl_decrypt($cipherText, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $nonce, $tag);

        if ($plainText === false) {
            throw new RuntimeException('Não foi possível decifrar o dado.');
        }

        return $plainText;
    }

    /**
     * Índice cego: valor determinístico para WHERE e UNIQUE sobre campos
     * cifrados. O contexto separa domínios (cpf, telefone, ...).
     */
    public static function blindIndex(string $value, string $context): string
    {
        return hash_hmac('sha256', $context . '|' . $value, self::indexKey());
    }

    public static function normalizeCpf(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?? '';
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(self::KEY_BYTES));
    }

    private static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $configured = Env::get('APP_KEY');

        if ($configured === null || $configured === '') {
            throw new RuntimeException('APP_KEY não configurada: os dados sensíveis não podem ser cifrados.');
        }

        $raw = str_starts_with($configured, 'base64:')
            ? base64_decode(substr($configured, strlen('base64:')), true)
            : false;

        if ($raw === false || strlen($raw) !== self::KEY_BYTES) {
            throw new RuntimeException('APP_KEY inválida: esperado base64: com 32 bytes.');
        }

        return self::$key = $raw;
    }

    private static function indexKey(): string
    {
        return hash_hmac('sha256', 'obsyrium:blind-index', self::key(), true);
    }
}
