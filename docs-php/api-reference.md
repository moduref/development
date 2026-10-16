# Referensi API Lengkap Moduref PHP

Daftar lengkap kelas, metode, data transfer object (DTO), dan fungsi pembantu yang tersedia di namespace `Moduref\*`.

---

## 1. Facade Statis: `Moduref\Moduref`

Facade utama yang menyediakan antarmuka terpusat dan ergonomis untuk seluruh fungsionalitas Moduref:

```php
use Moduref\Moduref;
```

### `Moduref::createUrl(string $baseUrl, string $userUuid, string $path = '/referral'): string`
Membangun tautan referral yang dinormalisasi dan divalidasi.
- **Parameter**:
  - `$baseUrl` *(string)*: URL dasar aplikasi (contoh: `'https://app.com'`).
  - `$userUuid` *(string)*: UUID pengguna (harus berformat UUID v4 valid).
  - `$path` *(string, default: `'/referral'`)*: Rute prefix referral.
- **Return**: `string` (contoh: `'https://app.com/referral/123e4567-e89b-12d3-a456-426614174000'`).
- **Exception**: Melempar `InvalidUuidException` jika format UUID tidak valid.

---

### `Moduref::createPdoStore(PDO $pdo, string $tableName = 'users', array $columns = []): ReferralStore`
Membuat instance `ReferralStore` baru menggunakan koneksi PDO aktif.
- **Parameter**:
  - `$pdo` *(PDO)*: Instance koneksi PDO (MySQL, PostgreSQL, atau SQLite).
  - `$tableName` *(string, default: `'users'`)*: Nama tabel pengguna.
  - `$columns` *(array, opsional)*: Pemetaan kolom kustom (lihat bagian ReferralStore).
- **Return**: `Moduref\ReferralStore`.

---

### `Moduref::attachFromCookie(array $cookies, string $userUuid, ReferralStore $store, ?object $executor = null, string $cookieName = Moduref::COOKIE_NAME): AttachResult`
Mengekstrak UUID referral dari array cookie (misal `$_COOKIE` atau request header cookies) dan menautkannya ke store.
- **Parameter**:
  - `$cookies` *(array)*: Array pasangan nama-nilai cookie (contoh: `$_COOKIE` atau `$request->cookies->all()`).
  - `$userUuid` *(string)*: UUID pengguna baru yang baru didaftarkan.
  - `$store` *(ReferralStore)*: Instance store referral aktif.
  - `$executor` *(?object, opsional)*: Koneksi PDO transaksi yang sedang aktif (jika registrasi dibungkus transaksi luar).
  - `$cookieName` *(string, default: `'referral_uuid'`)*: Nama cookie yang diperiksa.
- **Return**: `Moduref\AttachResult`.

---

### `Moduref::getCookieUuid(array $cookies, string $cookieName = Moduref::COOKIE_NAME): ?string`
Membaca dan memvalidasi UUID dari cookie. Mengembalikan `null` jika tidak ditemukan atau jika format tidak valid.
- **Return**: `?string`.

---

### `Moduref::clearCookieHeader(string $path = '/', ?string $domain = null, bool $secure = false, string $sameSite = 'Lax'): string`
Menghasilkan string header HTTP `Set-Cookie` yang kedaluwarsa untuk membersihkan cookie referral dari browser pengguna.
- **Return**: `string` (contoh: `'Set-Cookie: referral_uuid=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; HttpOnly; SameSite=Lax'`).

---

### `Moduref::isValidUuid(string $uuid): bool`
Memeriksa apakah string merupakan format UUID v4 yang valid sesuai RFC 4122.
- **Return**: `bool`.

---

### `Moduref::generateUuid(): string`
Menghasilkan string UUID v4 acak kriptografis menggunakan `random_bytes()`.
- **Return**: `string`.

---

## 2. Kelas Inti: `Moduref\ReferralStore`

Mengelola eksekusi kueri database, penguncian baris, dan verifikasi integritas data.

### Konstruktor
```php
public function __construct(
    ReferralAdapterInterface $adapter,
    string $tableName = 'users',
    array $columns = []
)
```

**Konfigurasi Kolom Kustom (`$columns`)**:
```php
$store = new ReferralStore($adapter, 'pengguna', [
    'uuid'                => 'id_pengguna',
    'username'            => 'nama_user',
    'referralPoints'      => 'total_poin_referral',
    'referralAttribution' => 'diundang_oleh_uuid',
]);
```

### Metode Utama

#### `initialize(): void`
Memvalidasi nama tabel dan kolom untuk mencegah potensi SQL Injection identifier. Wajib dipanggil saat bootstrap aplikasi.

#### `attach(array $options): AttachResult`
Menautkan referral ke pengguna di dalam transaksi database atomik dengan row lock.
- **Opsi yang diterima**:
  - `userUuid` *(string, wajib)*: UUID user yang menerima referral.
  - `referrerUuid` *(string, wajib)*: UUID user pengundang.
  - `executor` *(?object, opsional)*: Instance transaksi aktif.
- **Return**: `AttachResult`
  - Jika sukses: `$result->attached === true`, `$result->referrerUuid === '...'`.
  - Jika gagal (self-referral, pengundang tidak ditemukan, atau user sudah memiliki pengundang): `$result->attached === false`, `$result->reason === '...'`.

#### `getReferralSummary(string $userUuid, ?object $executor = null): ReferralSummary`
Mengambil rekapitulasi statistik referral untuk pengguna tertentu.
- **Return**: `ReferralSummary` berisi `totalReferrals`, `activeReferrals`, `referrerUuid`, dan `lastReferredAt`.

---

## 3. Data Transfer Objects (DTO)

### `Moduref\AttachResult`
Objek immutable bertipe kuat yang mengembalikan hasil operasi penautan:

```php
final class AttachResult
{
    public readonly bool $attached;
    public readonly ?string $reason;            // 'no_referral', 'self_referral', 'referrer_not_found', 'already_referred'
    public readonly ?string $referrerUuid;
    public readonly ?string $referrerUsername;

    public function toArray(): array;
}
```

### `Moduref\ReferralSummary`
Objek immutable statistik referral:

```php
final class ReferralSummary
{
    public readonly string $userUuid;
    public readonly int $totalReferrals;
    public readonly int $activeReferrals;
    public readonly ?string $referrerUuid;
    public readonly ?string $lastReferredAt;

    public function toArray(): array;
}
```

---

## 4. Helper Fungsional Global (`functions.php`)

Jika Anda lebih menyukai gaya fungsional dibanding Facade statis, fungsi-fungsi berikut diekspor langsung di namespace `Moduref\*`:

```php
use function Moduref\createReferralUrl;
use function Moduref\createPdoReferralStore;
use function Moduref\attachReferralFromCookie;
use function Moduref\getReferralCookieUuid;
use function Moduref\isUuidValid;
use function Moduref\generateUuid;
```

---

## 5. Hierarki Exception

Seluruh exception yang dilempar oleh Moduref mewarisi `Moduref\Exceptions\ModurefException`:

```
\Exception
  └── ModurefException
        ├── InvalidUuidException       (Format UUID tidak sesuai RFC 4122)
        ├── InvalidIdentifierException (Nama tabel/kolom mengandung karakter tidak aman)
        └── InvalidPathException       (Path URL tidak valid)
```
Contoh penanganan exception:
```php
try {
    $link = Moduref::createUrl('https://example.com', $userInput);
} catch (\Moduref\Exceptions\InvalidUuidException $e) {
    // Tangani input UUID yang tidak valid
}
```
