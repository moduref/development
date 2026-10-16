# Adapter Database & Custom Adapter Moduref PHP

Dokumen ini memandu konfigurasi adapter database bawaan (`PdoAdapter`), penyesuaian dialek SQL, mekanisme transaksi, serta panduan membangun adapter kustom pihak ketiga.

---

## 1. Antarmuka Kontrak: `ReferralAdapterInterface`

Seluruh komunikasi database di Moduref diabstraksikan melalui interface:
`Moduref\Adapters\ReferralAdapterInterface`.

```php
namespace Moduref\Adapters;

interface ReferralAdapterInterface
{
    public function getDialect(): string;
    public function getForUpdateClause(): string;
    public function quoteIdentifier(string $identifier): string;
    public function query(?object $client, string $sql, array $values = []): array;
    public function execute(?object $client, string $sql, array $values = []): int;
    public function transaction(callable $work, ?object $client = null): mixed;
}
```

---

## 2. Adapter Universal Bawaan: `PdoAdapter`

Moduref menyediakan `Moduref\Adapters\PdoAdapter` yang kompatibel dengan PDO standar PHP dan otomatis mengenali driver aktif:

```php
use Moduref\Adapters\PdoAdapter;

$adapter = new PdoAdapter($pdo);
```

### Auto-Detection Dialek & Konfigurasi

| Driver PDO | Dialek | Karakter Kutip (*Identifier Quoting*) | Klausa Kunci (*Row Lock*) |
| :--- | :--- | :--- | :--- |
| `mysql` | MySQL / MariaDB | Backticks (`` `kolom` ``) | ` FOR UPDATE` |
| `pgsql` | PostgreSQL | Double Quotes (`"kolom"`) | ` FOR UPDATE` |
| `sqlite` | SQLite | Double Quotes (`"kolom"`) | `""` (Menggunakan *File Locking*) |

---

## 3. Konfigurasi untuk Berbagai Database

### A. MySQL / MariaDB
Pastikan tabel pengguna menggunakan engine **InnoDB** agar mendukung transaksi dan row locking:

```php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=my_app;charset=utf8mb4', 'root', 'password', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$store = Moduref::createPdoStore($pdo, 'users');
$store->initialize();
```

### B. PostgreSQL
Mendukung penamaan tabel berskema (misal `public.users`):

```php
$pdo = new PDO('pgsql:host=localhost;port=5432;dbname=my_app', 'postgres', 'password', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$store = Moduref::createPdoStore($pdo, 'public.users');
$store->initialize();
```

### C. SQLite
Sangat cocok untuk pengujian lokal, microservice ringan, atau aplikasi embedded:

```php
$pdo = new PDO('sqlite:' . __DIR__ . '/storage/database.sqlite', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$store = Moduref::createPdoStore($pdo, 'users');
$store->initialize();
```

---

## 4. Mekanisme Transaksi & Nesting

Saat Anda mendaftarkan pengguna baru, biasanya operasi insert user dan penautan referral berada dalam **satu transaksi database**:

```php
$pdo->beginTransaction();
try {
    // 1. Insert pengguna baru
    $stmt = $pdo->prepare("INSERT INTO users (uuid, username, email) VALUES (?, ?, ?)");
    $stmt->execute([$newUserUuid, $username, $email]);

    // 2. Hubungkan referral dengan meneruskan $pdo sebagai executor aktif
    $result = Moduref::attachFromCookie(
        cookies: $_COOKIE,
        userUuid: $newUserUuid,
        store: $store,
        executor: $pdo // Mencegah nested transaction conflict
    );

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

> **Tips Penting**: Dengan meneruskan `$pdo` ke argumen `executor`, Moduref akan mengeksekusi kueri di dalam transaksi yang sedang berjalan tanpa memanggil `beginTransaction()` kedua kali, menjaga atomisitas data secara sempurna.

---

## 5. Membuat Custom Adapter (Contoh: Doctrine DBAL)

Jika proyek Anda menggunakan ORM/DBAL seperti Doctrine, Anda dapat membuat adapter kustom yang mengimplementasikan `ReferralAdapterInterface`:

```php
namespace App\Adapters;

use Doctrine\DBAL\Connection;
use Moduref\Adapters\ReferralAdapterInterface;

class DoctrineDbalAdapter implements ReferralAdapterInterface
{
    public function __construct(private Connection $connection) {}

    public function getDialect(): string
    {
        return 'standard';
    }

    public function getForUpdateClause(): string
    {
        return ' FOR UPDATE';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return $this->connection->quoteIdentifier($identifier);
    }

    public function query(?object $client, string $sql, array $values = []): array
    {
        $conn = $client instanceof Connection ? $client : $this->connection;
        return $conn->fetchAllAssociative($sql, $values);
    }

    public function execute(?object $client, string $sql, array $values = []): int
    {
        $conn = $client instanceof Connection ? $client : $this->connection;
        return $conn->executeStatement($sql, $values);
    }

    public function transaction(callable $work, ?object $client = null): mixed
    {
        $conn = $client instanceof Connection ? $client : $this->connection;
        return $conn->transactional(fn() => $work($conn));
    }
}
```

Kemudian gunakan langsung pada `ReferralStore`:
```php
$customAdapter = new DoctrineDbalAdapter($doctrineConnection);
$store = new \Moduref\ReferralStore($customAdapter, 'users');
$store->initialize();
```
