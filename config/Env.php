<?php

declare(strict_types=1);

namespace Config;

use RuntimeException;

/**
 * Lê o arquivo .env uma única vez e expõe os valores por chave.
 *
 * Formato suportado: KEY=VALOR por linha, comentários iniciados por #,
 * valores opcionalmente entre aspas e comentário no fim da linha.
 */
final class Env
{
    /**
     * @var array<string, string>|null
     */
    private static ?array $values = null;

    public static function load(string $file): void
    {
        if (self::$values !== null) {
            return;
        }

        self::$values = [];

        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new RuntimeException('Não foi possível ler o arquivo de ambiente.');
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');

            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));

            if ($key === '') {
                continue;
            }

            self::$values[$key] = self::parseValue(trim(substr($line, $separator + 1)));
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::ensureLoaded();

        $value = self::$values[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return $default;
        }

        return (int) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (strlen($value) >= 2 && ($first === '"' || $first === "'") && $first === $last) {
            return substr($value, 1, -1);
        }

        $comment = strpos($value, ' #');

        if ($comment !== false) {
            return rtrim(substr($value, 0, $comment));
        }

        return $value;
    }

    private static function ensureLoaded(): void
    {
        if (self::$values === null) {
            throw new RuntimeException('Config\Env não inicializado.');
        }
    }
}
