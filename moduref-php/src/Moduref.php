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
    public const COOKIE_NAME = Constants::DEFAULT_COOKIE_NAME;
    public const COOKIE_MAX_AGE = Constants::DEFAULT_COOKIE_MAX_AGE;

    /**
     * Membangun URL tautan referral lengkap.
     */
    public static function createUrl(string $baseUrl, string $userUuid, string $path = '/referral'): string
    {
        return Http::createReferralUrl($baseUrl, $userUuid, $path);
    }

    /**
     * Membaca UUID referral dari cookies.
     * Menerima ($cookieName, $cookies) atau ($cookies, $cookieName).
     *
     * @param string|array<string, string> $cookieNameOrCookies
     * @param array<string, string>|string|null $cookiesOrName
     */
    public static function getUuid(string|array $cookieNameOrCookies = Constants::DEFAULT_COOKIE_NAME, array|string|null $cookiesOrName = null): ?string
    {
        if (is_array($cookieNameOrCookies)) {
            $cookies = $cookieNameOrCookies;
            $cookieName = is_string($cookiesOrName) ? $cookiesOrName : Constants::DEFAULT_COOKIE_NAME;
            return Http::getReferralUuid($cookieName, $cookies);
        }

        $cookieName = $cookieNameOrCookies;
        $cookies = is_array($cookiesOrName) ? $cookiesOrName : null;
        return Http::getReferralUuid($cookieName, $cookies);
    }

    /**
     * Alias ergonomis untuk getUuid().
     *
     * @param string|array<string, string> $cookieNameOrCookies
     * @param array<string, string>|string|null $cookiesOrName
     */
    public static function getCookieUuid(string|array $cookieNameOrCookies = Constants::DEFAULT_COOKIE_NAME, array|string|null $cookiesOrName = null): ?string
    {
        return self::getUuid($cookieNameOrCookies, $cookiesOrName);
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
     * Memeriksa apakah string merupakan format UUID yang valid (RFC 4122).
     */
    public static function isValidUuid(?string $uuid): bool
    {
        return Validation::normalizeUuid($uuid) !== null;
    }

    /**
     * Menghasilkan string UUID v4 acak kriptografis via random_bytes.
     */
    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Membaca cookie referral dari request lalu otomatis menautkannya ke store database.
     */
    public static function attachFromRequest(
        string $userUuid,
        ReferralStore $store,
        string $cookieName = Constants::DEFAULT_COOKIE_NAME,
        ?array $cookies = null
    ): AttachResult {
        return Http::attachReferralFromRequest($userUuid, $store, $cookieName, $cookies);
    }

    /**
     * Helper alternatif: Menautkan referral dari array cookie kustom.
     *
     * @param array<string, string> $cookies
     * @param string $userUuid
     * @param ReferralStore $store
     * @param object|null $executor Koneksi transaksi aktif opsional.
     * @param string $cookieName
     */
    public static function attachFromCookie(
        array $cookies,
        string $userUuid,
        ReferralStore $store,
        ?object $executor = null,
        string $cookieName = Constants::DEFAULT_COOKIE_NAME
    ): AttachResult {
        return Http::attachReferralFromRequest($userUuid, $store, $cookieName, $cookies);
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
