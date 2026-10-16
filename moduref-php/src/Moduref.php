<?php

declare(strict_types=1);

namespace Moduref;

use Moduref\Adapters\PdoAdapter;
use Moduref\Adapters\ReferralAdapterInterface;

/**
 * Fasade utama Moduref untuk kemudahan akses fungsi-fungsi inti referral.
 */
final class Moduref
{
    /**
     * Membangun URL tautan referral lengkap.
     */
    public static function createUrl(string $baseUrl, string $userUuid, string $path = '/referral'): string
    {
        return Http::createReferralUrl($baseUrl, $userUuid, $path);
    }

    /**
     * Membaca UUID referral dari cookies.
     */
    public static function getUuid(string $cookieName = Constants::DEFAULT_COOKIE_NAME, ?array $cookies = null): ?string
    {
        return Http::getReferralUuid($cookieName, $cookies);
    }

    /**
     * Menyimpan cookie referral.
     */
    public static function setCookie(
        string $referrerUuid,
        string $cookieName = Constants::DEFAULT_COOKIE_NAME,
        int $maxAge = Constants::DEFAULT_COOKIE_MAX_AGE,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): bool {
        return Http::setReferralCookie($referrerUuid, $cookieName, $maxAge, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * Menghapus cookie referral.
     */
    public static function clearCookie(
        string $cookieName = Constants::DEFAULT_COOKIE_NAME,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): bool {
        return Http::clearReferralCookie($cookieName, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * Membuat instance ReferralStore generik dengan adapter kustom.
     */
    public static function createStore(
        ReferralAdapterInterface $adapter,
        string $tableName = 'users',
        array $columns = [],
    ): ReferralStore {
        return new ReferralStore($adapter, $tableName, $columns);
    }

    /**
     * Membuat instance ReferralStore menggunakan koneksi PDO native PHP.
     */
    public static function createPdoStore(
        \PDO $pdo,
        string $tableName = 'users',
        array $columns = [],
    ): ReferralStore {
        return new ReferralStore(PdoAdapter::fromPdo($pdo), $tableName, $columns);
    }

    /**
     * Membuat instance ReferralStore khusus MySQL menggunakan PDO.
     */
    public static function createMysqlStore(
        \PDO $pdo,
        string $tableName = 'users',
        array $columns = [],
    ): ReferralStore {
        return new ReferralStore(PdoAdapter::mysql($pdo), $tableName, $columns);
    }

    /**
     * Membuat instance ReferralStore khusus PostgreSQL menggunakan PDO.
     */
    public static function createPostgresStore(
        \PDO $pdo,
        string $tableName = 'users',
        array $columns = [],
    ): ReferralStore {
        return new ReferralStore(PdoAdapter::postgres($pdo), $tableName, $columns);
    }

    /**
     * Membuat instance ReferralStore khusus SQLite menggunakan PDO.
     */
    public static function createSqliteStore(
        \PDO $pdo,
        string $tableName = 'users',
        array $columns = [],
    ): ReferralStore {
        return new ReferralStore(PdoAdapter::sqlite($pdo), $tableName, $columns);
    }

    private function __construct()
    {
    }
}
