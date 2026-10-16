<?php

declare(strict_types=1);

namespace Moduref;

use Moduref\Exceptions\InvalidPathException;

final class Http
{
    /**
     * Membangun URL tautan referral yang dapat dibagikan oleh pengguna berdasarkan UUID miliknya.
     *
     * @param string $baseUrl Origin URL aplikasi (contoh: "https://example.com" atau "http://localhost:8000").
     * @param string $userUuid UUID pengguna aktif yang membagikan tautan.
     * @param string $path Prefix path referral (default: "/referral").
     * @return string URL referral lengkap (contoh: "https://example.com/referral/<uuid>").
     *
     * @throws \InvalidArgumentException Jika baseUrl tidak valid atau mengandung kredensial.
     * @throws InvalidPathException Jika path tidak valid.
     */
    public static function createReferralUrl(string $baseUrl, string $userUuid, string $path = '/referral'): string
    {
        $uuid = Validation::assertUuid($userUuid, 'userUuid');
        Validation::validateLocalPath($path, 'path');

        $parts = parse_url($baseUrl);
        if ($parts === false || !isset($parts['scheme']) || !isset($parts['host'])) {
            throw new \InvalidArgumentException('baseUrl must be a valid URL string');
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \InvalidArgumentException('baseUrl must use http or https protocol');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('baseUrl must not contain credentials');
        }

        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        $baseHost = "{$scheme}://{$parts['host']}{$port}";
        $basePath = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        $cleanPath = rtrim($path, '/');
        $fullPath = "{$basePath}{$cleanPath}/" . rawurlencode($uuid);

        return "{$baseHost}{$fullPath}";
    }

    /**
     * Membaca dan memvalidasi UUID referral dari array cookies (default: $_COOKIE).
     *
     * @param string $cookieName Nama cookie yang dicari.
     * @param array<string, string>|null $cookies Array cookies kustom (jika null, menggunakan $_COOKIE).
     * @return string|null UUID pengundang lowercase jika ditemukan, atau null jika tidak ada/tidak valid.
     */
    public static function getReferralUuid(
        string $cookieName = Constants::DEFAULT_COOKIE_NAME,
        ?array $cookies = null
    ): ?string {
        Validation::validateCookieName($cookieName);

        $source = $cookies ?? $_COOKIE;
        if (!isset($source[$cookieName])) {
            return null;
        }

        $raw = (string) $source[$cookieName];
        $decoded = urldecode($raw);

        return Validation::normalizeUuid($decoded);
    }

    /**
     * Menyimpan cookie referral ke browser klien menggunakan setcookie() native PHP.
     *
     * @param string $referrerUuid UUID pengundang.
     * @param string $cookieName Nama cookie referral.
     * @param int $maxAge Masa berlaku dalam detik (default 30 hari).
     * @param string $path Scope path cookie.
     * @param string|null $domain Domain cookie.
     * @param bool $secure Flag HTTPS only.
     * @param bool $httpOnly Flag HttpOnly (mencegah akses JavaScript).
     * @param string $sameSite Kebijakan SameSite ('Lax', 'Strict', 'None').
     * @return bool True jika cookie berhasil dikirimkan ke header response.
     */
    public static function setReferralCookie(
        string $referrerUuid,
        string $cookieName = Constants::DEFAULT_COOKIE_NAME,
        int $maxAge = Constants::DEFAULT_COOKIE_MAX_AGE,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): bool {
        $uuid = Validation::assertUuid($referrerUuid, 'referrerUuid');
        Validation::validateCookieName($cookieName);

        $options = [
            'expires' => time() + $maxAge,
            'path' => $path,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => ucfirst(strtolower($sameSite)),
        ];

        if ($domain !== null && $domain !== '') {
            $options['domain'] = $domain;
        }

        return setcookie($cookieName, $uuid, $options);
    }

    /**
     * Menghapus cookie referral dari browser klien setelah registrasi selesai.
     */
    public static function clearReferralCookie(
        string $cookieName = Constants::DEFAULT_COOKIE_NAME,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): bool {
        Validation::validateCookieName($cookieName);

        $options = [
            'expires' => time() - 86400,
            'path' => $path,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => ucfirst(strtolower($sameSite)),
        ];

        if ($domain !== null && $domain !== '') {
            $options['domain'] = $domain;
        }

        return setcookie($cookieName, '', $options);
    }

    /**
     * Helper praktis: Membaca cookie referral dari request lalu otomatis menautkannya ke store database.
     *
     * @param string $userUuid UUID pengguna baru yang baru didaftarkan.
     * @param ReferralStore $store Instance ReferralStore database.
     * @param string $cookieName Nama cookie referral.
     * @param array<string, string>|null $cookies Array cookies kustom (opsional).
     */
    public static function attachReferralFromRequest(
        string $userUuid,
        ReferralStore $store,
        string $cookieName = Constants::DEFAULT_COOKIE_NAME,
        ?array $cookies = null
    ): AttachResult {
        $referrerUuid = self::getReferralUuid($cookieName, $cookies);
        if ($referrerUuid === null) {
            return new AttachResult(false, 'no_referral');
        }

        return $store->attach($userUuid, $referrerUuid);
    }

    private function __construct()
    {
    }
}
