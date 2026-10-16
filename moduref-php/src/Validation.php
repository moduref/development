<?php

declare(strict_types=1);

namespace Moduref;

use Moduref\Exceptions\InvalidIdentifierException;
use Moduref\Exceptions\InvalidPathException;
use Moduref\Exceptions\InvalidUuidException;

final class Validation
{
    /**
     * Memvalidasi dan menormalisasi string UUID menjadi huruf kecil (lowercase).
     * Mengembalikan null jika string tidak sesuai format UUID.
     */
    public static function normalizeUuid(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if (!preg_match(Constants::UUID_PATTERN, $trimmed)) {
            return null;
        }

        return strtolower($trimmed);
    }

    /**
     * Memastikan sebuah nilai adalah UUID valid. Melempar InvalidUuidException jika tidak valid.
     *
     * @throws InvalidUuidException
     */
    public static function assertUuid(?string $value, string $label = 'UUID'): string
    {
        $uuid = self::normalizeUuid($value);
        if ($uuid === null) {
            throw InvalidUuidException::forField($label);
        }

        return $uuid;
    }

    /**
     * Memvalidasi path lokal internal aplikasi untuk mencegah kerentanan Open Redirect atau Path Traversal.
     *
     * @throws InvalidPathException
     */
    public static function validateLocalPath(?string $value, string $label = 'path', bool $allowQuery = false): string
    {
        if ($value === null || !str_starts_with($value, '/') || str_starts_with($value, '//') || str_contains($value, '\\')) {
            throw new InvalidPathException("{$label} must be a local path beginning with one /");
        }

        $parsed = parse_url($value);
        if ($parsed === false) {
            throw new InvalidPathException("{$label} is not a valid URL path");
        }

        // Tidak boleh memiliki scheme (http/https) atau host luar
        if (isset($parsed['scheme']) || isset($parsed['host']) || isset($parsed['fragment'])) {
            throw new InvalidPathException("{$label} must be a local path without scheme, host, or fragment");
        }

        if (!$allowQuery && isset($parsed['query'])) {
            throw new InvalidPathException("{$label} must be a local path without a query string");
        }

        return $value;
    }

    /**
     * Memvalidasi nama cookie sesuai karakter token RFC 6265.
     *
     * @throws \InvalidArgumentException
     */
    public static function validateCookieName(?string $value): string
    {
        if ($value === null || !preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $value)) {
            throw new \InvalidArgumentException('cookieName must be a valid cookie name');
        }

        return $value;
    }

    /**
     * Melakukan quoting/escaping identifier SQL (kolom/tabel) sesuai dialek database untuk mencegah SQL Injection.
     *
     * @throws InvalidIdentifierException
     */
    public static function quoteIdentifier(string $value, string $dialect = 'standard', string $label = 'identifier'): string
    {
        if (!preg_match(Constants::IDENTIFIER_PATTERN, $value)) {
            throw new InvalidIdentifierException("{$label} must be a simple SQL identifier");
        }

        if (strtolower($dialect) === 'mysql') {
            return "`{$value}`";
        }

        return "\"{$value}\"";
    }

    /**
     * Melakukan quoting pada nama tabel yang mungkin memiliki schema-qualification (contoh: "public.users").
     *
     * @throws InvalidIdentifierException
     */
    public static function quoteTableName(string $value, string $dialect = 'standard'): string
    {
        $parts = explode('.', $value);
        foreach ($parts as $part) {
            if (!preg_match(Constants::IDENTIFIER_PATTERN, $part)) {
                throw new InvalidIdentifierException('tableName must be a table identifier, optionally schema-qualified');
            }
        }

        $quotedParts = array_map(
            static fn(string $part) => self::quoteIdentifier($part, $dialect, 'tableName'),
            $parts
        );

        return implode('.', $quotedParts);
    }

    private function __construct()
    {
    }
}
