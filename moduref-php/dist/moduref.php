<?php

declare(strict_types=1);

/**
 * Moduref for PHP (Standalone Bundle)
 * Repository: https://github.com/moduref/development
 * License: MIT
 */

namespace Moduref\Exceptions {
    // --- File: ModurefException.php ---
    /**
     * Interface penanda untuk semua exception yang dilempar oleh package Moduref.
     */
    interface ModurefException extends \Throwable
    {
    }

    // --- File: InvalidUuidException.php ---
    /**
     * Dilempar ketika nilai string bukan merupakan format UUID v1-v5 yang valid.
     */
    class InvalidUuidException extends \InvalidArgumentException implements ModurefException
    {
        public static function forField(string $label): self
        {
            return new self("{$label} must be a valid UUID string");
        }
    }

    // --- File: InvalidPathException.php ---
    /**
     * Dilempar ketika format path URL lokal tidak valid (contoh: tidak diawali '/', mengandung '//', atau traversal).
     */
    class InvalidPathException extends \InvalidArgumentException implements ModurefException
    {
    }

    // --- File: InvalidIdentifierException.php ---
    /**
     * Dilempar ketika nama identifier tabel atau kolom SQL tidak aman atau mengandung karakter berbahaya.
     */
    class InvalidIdentifierException extends \InvalidArgumentException implements ModurefException
    {
    }
}

namespace Moduref\Adapters {
    use Moduref\Validation;

    // --- File: ReferralAdapterInterface.php ---
    interface ReferralAdapterInterface
    {
        /**
         * Mengembalikan dialek SQL adapter ('mysql', 'postgres', 'sqlite', 'standard').
         */
        public function getDialect(): string;
    
        /**
         * Mengembalikan klausa row-locking (contoh: " FOR UPDATE" atau kosong "").
         */
        public function getForUpdateClause(): string;
    
        /**
         * Melakukan escaping/quoting nama kolom atau tabel.
         */
        public function quoteIdentifier(string $value, string $label = 'identifier'): string;
    
        /**
         * Menjalankan query SELECT dan mengembalikan array baris sebagai associative array.
         *
         * @param string $sql Query SQL dengan placeholder '?'.
         * @param array<int, mixed> $params Parameter query.
         * @return array<int, array<string, mixed>>
         */
        public function query(string $sql, array $params = []): array;
    
        /**
         * Menjalankan query UPDATE/INSERT/DELETE dan mengembalikan jumlah baris yang terpengaruh (affected rows).
         *
         * @param string $sql Query SQL dengan placeholder '?'.
         * @param array<int, mixed> $params Parameter query.
         * @return int Jumlah baris yang terpengaruh.
         */
        public function execute(string $sql, array $params = []): int;
    
        /**
         * Menjalankan callback di dalam transaksi database atomik.
         *
         * @template T
         * @param callable(ReferralAdapterInterface): T $callback
         * @return T
         */
        public function transaction(callable $callback): mixed;
    }

    // --- File: PdoAdapter.php ---
    class PdoAdapter implements ReferralAdapterInterface
    {
        private readonly string $dialect;
        private readonly string $forUpdateClause;
    
        public function __construct(
            private readonly \PDO $pdo,
            ?string $dialect = null,
        ) {
            $driver = $dialect ?? (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $normalized = strtolower($driver);
    
            if ($normalized === 'mysql') {
                $this->dialect = 'mysql';
                $this->forUpdateClause = ' FOR UPDATE';
            } elseif ($normalized === 'pgsql' || $normalized === 'postgres' || $normalized === 'postgresql') {
                $this->dialect = 'postgres';
                $this->forUpdateClause = ' FOR UPDATE';
            } elseif ($normalized === 'sqlite') {
                $this->dialect = 'sqlite';
                $this->forUpdateClause = '';
            } else {
                $this->dialect = 'standard';
                $this->forUpdateClause = '';
            }
    
            // Pastikan PDO melempar PDOException saat terjadi kegagalan query
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
    
        public static function fromPdo(\PDO $pdo): self
        {
            return new self($pdo);
        }
    
        public static function mysql(\PDO $pdo): self
        {
            return new self($pdo, 'mysql');
        }
    
        public static function postgres(\PDO $pdo): self
        {
            return new self($pdo, 'postgres');
        }
    
        public static function sqlite(\PDO $pdo): self
        {
            return new self($pdo, 'sqlite');
        }
    
        public function getDialect(): string
        {
            return $this->dialect;
        }
    
        public function getForUpdateClause(): string
        {
            return $this->forUpdateClause;
        }
    
        public function quoteIdentifier(string $value, string $label = 'identifier'): string
        {
            return Validation::quoteIdentifier($value, $this->dialect, $label);
        }
    
        public function query(string $sql, array $params = []): array
        {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
    
            /** @var array<int, array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $rows;
        }
    
        public function execute(string $sql, array $params = []): int
        {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
    
            return $stmt->rowCount();
        }
    
        public function transaction(callable $callback): mixed
        {
            if ($this->pdo->inTransaction()) {
                return $callback($this);
            }
    
            $this->pdo->beginTransaction();
    
            try {
                $result = $callback($this);
                $this->pdo->commit();
                return $result;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        }
    
        public function getPdo(): \PDO
        {
            return $this->pdo;
        }
    }
}

namespace Moduref {
    use Moduref\Adapters\PdoAdapter;
    use Moduref\Adapters\ReferralAdapterInterface;
    use Moduref\Exceptions\InvalidIdentifierException;
    use Moduref\Exceptions\InvalidPathException;
    use Moduref\Exceptions\InvalidUuidException;

    // --- File: Constants.php ---
    final class Constants
    {
        /**
         * Nama default untuk cookie pelacak referral.
         */
        public const DEFAULT_COOKIE_NAME = 'referral_uuid';
    
        /**
         * Masa berlaku default cookie referral (30 hari dalam detik untuk PHP setcookie).
         */
        public const DEFAULT_COOKIE_MAX_AGE = 30 * 24 * 60 * 60; // 2592000 detik
    
        /**
         * Regex pola UUID standar (v1-v5).
         */
        public const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    
        /**
         * Regex validasi identifier SQL aman (mencegah SQL Injection pada nama tabel/kolom).
         */
        public const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_$]*$/';
    
        /**
         * Pemetaan nama kolom default pada tabel pengguna.
         */
        public const DEFAULT_COLUMNS = [
            'uuid' => 'uuid',
            'username' => 'username',
            'referral_points' => 'referral_poin',
            'referral_attribution' => 'referral_attribution',
        ];
    
        private function __construct()
        {
        }
    }

    // --- File: Validation.php ---
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

    // --- File: AttachResult.php ---
    final class AttachResult
    {
        public function __construct(
            public readonly bool $attached,
            public readonly ?string $reason = null,
            public readonly ?string $referrerUuid = null,
            public readonly ?string $referrerUsername = null,
        ) {
        }
    
        /**
         * Apakah penautan referral dan penambahan poin berhasil.
         */
        public function isAttached(): bool
        {
            return $this->attached;
        }
    
        /**
         * Mengonversi hasil menjadi representasi array.
         *
         * @return array<string, mixed>
         */
        public function toArray(): array
        {
            $data = [
                'attached' => $this->attached,
            ];
    
            if ($this->reason !== null) {
                $data['reason'] = $this->reason;
            }
            if ($this->referrerUuid !== null) {
                $data['referrer_uuid'] = $this->referrerUuid;
            }
            if ($this->referrerUsername !== null) {
                $data['referrer_username'] = $this->referrerUsername;
            }
    
            return $data;
        }
    }

    // --- File: ReferralSummary.php ---
    final class ReferralSummary
    {
        public function __construct(
            public readonly int $referralPoints,
            public readonly ?string $referralAttribution,
        ) {
        }
    
        /**
         * Mengonversi ringkasan menjadi array.
         *
         * @return array{referral_points: int, referral_attribution: ?string}
         */
        public function toArray(): array
        {
            return [
                'referral_points' => $this->referralPoints,
                'referral_attribution' => $this->referralAttribution,
            ];
        }
    }

    // --- File: ReferralStore.php ---
    class ReferralStore
    {
        private readonly string $table;
        private readonly string $uuidColumn;
        private readonly string $usernameColumn;
        private readonly string $pointsColumn;
        private readonly string $attributionColumn;
        private readonly string $selectedFields;
    
        /**
         * @param array{
         *   uuid?: string,
         *   username?: string,
         *   referral_points?: string,
         *   referral_attribution?: string
         * } $columns Pemetaan nama kolom kustom.
         */
        public function __construct(
            private readonly ReferralAdapterInterface $adapter,
            private readonly string $tableName = 'users',
            array $columns = [],
        ) {
            $configuredColumns = array_merge(Constants::DEFAULT_COLUMNS, $columns);
            $dialect = $this->adapter->getDialect();
    
            $this->table = Validation::quoteTableName($this->tableName, $dialect);
            $this->uuidColumn = $this->adapter->quoteIdentifier($configuredColumns['uuid'], 'columns.uuid');
            $this->usernameColumn = $this->adapter->quoteIdentifier($configuredColumns['username'], 'columns.username');
            $this->pointsColumn = $this->adapter->quoteIdentifier($configuredColumns['referral_points'], 'columns.referral_points');
            $this->attributionColumn = $this->adapter->quoteIdentifier($configuredColumns['referral_attribution'], 'columns.referral_attribution');
    
            $this->selectedFields = "{$this->uuidColumn} AS uuid, {$this->usernameColumn} AS username, "
                . "{$this->pointsColumn} AS referral_points, {$this->attributionColumn} AS referral_attribution";
        }
    
        /**
         * Memvalidasi bahwa tabel dan keempat kolom yang dikonfigurasi ada di database.
         * Disarankan dipanggil saat server aplikasi pertama kali mulai (startup/boot).
         */
        public function initialize(): void
        {
            $this->adapter->query("SELECT {$this->selectedFields} FROM {$this->table} WHERE 1 = 0");
        }
    
        /**
         * Menautkan user baru ke pengundangnya dan menaikkan total poin pengundang (+1) secara atomik.
         * Menolak referral diri sendiri, pengundang yang tidak ada, atau user yang sudah memiliki attribution.
         */
        public function attach(string $userUuid, string $referrerUuid): AttachResult
        {
            $referredUuid = Validation::assertUuid($userUuid, 'userUuid');
            $inviterUuid = Validation::assertUuid($referrerUuid, 'referrerUuid');
    
            if ($referredUuid === $inviterUuid) {
                return new AttachResult(false, 'self_referral');
            }
    
            return $this->adapter->transaction(function (ReferralAdapterInterface $adapter) use ($referredUuid, $inviterUuid): AttachResult {
                $lockClause = $adapter->getForUpdateClause();
    
                // 1. Ambil data pengundang (dengan row lock)
                $inviterRows = $adapter->query(
                    "SELECT {$this->uuidColumn} AS uuid, {$this->usernameColumn} AS username "
                    . "FROM {$this->table} WHERE {$this->uuidColumn} = ?{$lockClause}",
                    [$inviterUuid]
                );
    
                if (empty($inviterRows)) {
                    return new AttachResult(false, 'referrer_not_found');
                }
                $inviter = $inviterRows[0];
    
                // 2. Ambil data pengguna baru (dengan row lock)
                $inviteeRows = $adapter->query(
                    "SELECT {$this->uuidColumn} AS uuid, {$this->attributionColumn} AS referral_attribution "
                    . "FROM {$this->table} WHERE {$this->uuidColumn} = ?{$lockClause}",
                    [$referredUuid]
                );
    
                if (empty($inviteeRows)) {
                    return new AttachResult(false, 'user_not_found');
                }
                $invitee = $inviteeRows[0];
    
                if ($invitee['referral_attribution'] !== null && $invitee['referral_attribution'] !== '') {
                    return new AttachResult(false, 'already_referred');
                }
    
                // 3. Update kolom attribution user baru dengan username pengundang
                $linked = $adapter->execute(
                    "UPDATE {$this->table} SET {$this->attributionColumn} = ? "
                    . "WHERE {$this->uuidColumn} = ? AND ({$this->attributionColumn} IS NULL OR {$this->attributionColumn} = '')",
                    [(string) $inviter['username'], $referredUuid]
                );
    
                if ($linked !== 1) {
                    return new AttachResult(false, 'already_referred');
                }
    
                // 4. Naikkan total poin pengundang (+1)
                $incremented = $adapter->execute(
                    "UPDATE {$this->table} SET {$this->pointsColumn} = COALESCE({$this->pointsColumn}, 0) + 1 "
                    . "WHERE {$this->uuidColumn} = ?",
                    [$inviterUuid]
                );
    
                if ($incremented !== 1) {
                    throw new \RuntimeException("Could not increment the referrer's referral_poin");
                }
    
                return new AttachResult(
                    attached: true,
                    reason: null,
                    referrerUuid: $inviterUuid,
                    referrerUsername: (string) $inviter['username']
                );
            });
        }
    
        /**
         * Mengambil ringkasan poin referral dan username pengundang untuk suatu UUID pengguna.
         */
        public function getReferralSummary(string $userUuid): ?ReferralSummary
        {
            $uuid = Validation::assertUuid($userUuid, 'userUuid');
    
            $rows = $this->adapter->query(
                "SELECT {$this->pointsColumn} AS referral_points, {$this->attributionColumn} AS referral_attribution "
                . "FROM {$this->table} WHERE {$this->uuidColumn} = ?",
                [$uuid]
            );
    
            if (empty($rows)) {
                return null;
            }
    
            $row = $rows[0];
            $points = isset($row['referral_points']) ? (int) $row['referral_points'] : 0;
            $attribution = isset($row['referral_attribution']) && $row['referral_attribution'] !== ''
                ? (string) $row['referral_attribution']
                : null;
    
            return new ReferralSummary($points, $attribution);
        }
    
        public function getAdapter(): ReferralAdapterInterface
        {
            return $this->adapter;
        }
    
        public function getTableName(): string
        {
            return $this->tableName;
        }
    }

    // --- File: Http.php ---
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

    // --- File: Moduref.php ---
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

    // --- File: functions.php ---
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
}

