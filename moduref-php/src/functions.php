<?php

declare(strict_types=1);

namespace Moduref;

use Moduref\Adapters\PdoAdapter;
use Moduref\Adapters\ReferralAdapterInterface;

if (!function_exists('Moduref\createReferralUrl')) {
    function createReferralUrl(string $baseUrl, string $userUuid, string $path = '/referral'): string
    {
        return Http::createReferralUrl($baseUrl, $userUuid, $path);
    }
}

if (!function_exists('Moduref\getReferralUuid')) {
    function getReferralUuid(string $cookieName = Constants::DEFAULT_COOKIE_NAME, ?array $cookies = null): ?string
    {
        return Http::getReferralUuid($cookieName, $cookies);
    }
}

if (!function_exists('Moduref\setReferralCookie')) {
    function setReferralCookie(
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
}

if (!function_exists('Moduref\clearReferralCookie')) {
    function clearReferralCookie(
        string $cookieName = Constants::DEFAULT_COOKIE_NAME,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): bool {
        return Http::clearReferralCookie($cookieName, $path, $domain, $secure, $httpOnly, $sameSite);
    }
}

if (!function_exists('Moduref\attachReferralFromRequest')) {
    function attachReferralFromRequest(
        string $userUuid,
        ReferralStore $store,
        string $cookieName = Constants::DEFAULT_COOKIE_NAME,
        ?array $cookies = null
    ): AttachResult {
        return Http::attachReferralFromRequest($userUuid, $store, $cookieName, $cookies);
    }
}

if (!function_exists('Moduref\createReferralStore')) {
    function createReferralStore(
        ReferralAdapterInterface $adapter,
        string $tableName = 'users',
        array $columns = [],
    ): ReferralStore {
        return new ReferralStore($adapter, $tableName, $columns);
    }
}

if (!function_exists('Moduref\createPdoReferralAdapter')) {
    function createPdoReferralAdapter(\PDO $pdo, ?string $dialect = null): PdoAdapter
    {
        return new PdoAdapter($pdo, $dialect);
    }
}

if (!function_exists('Moduref\createPdoReferralStore')) {
    function createPdoReferralStore(
        \PDO $pdo,
        string $tableName = 'users',
        array $columns = [],
    ): ReferralStore {
        return Moduref::createPdoStore($pdo, $tableName, $columns);
    }
}

if (!function_exists('Moduref\createMysqlReferralStore')) {
    function createMysqlReferralStore(
        \PDO $pdo,
        string $tableName = 'users',
        array $columns = [],
    ): ReferralStore {
        return Moduref::createMysqlStore($pdo, $tableName, $columns);
    }
}

if (!function_exists('Moduref\createPostgresReferralStore')) {
    function createPostgresReferralStore(
        \PDO $pdo,
        string $tableName = 'users',
        array $columns = [],
    ): ReferralStore {
        return Moduref::createPostgresStore($pdo, $tableName, $columns);
    }
}

if (!function_exists('Moduref\createSqliteReferralStore')) {
    function createSqliteReferralStore(
        \PDO $pdo,
        string $tableName = 'users',
        array $columns = [],
    ): ReferralStore {
        return Moduref::createSqliteStore($pdo, $tableName, $columns);
    }
}
