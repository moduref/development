# Moduref for PHP

> **Engine Referral Ringan Berbasis UUID Pengguna dengan Row-Locking Transaksional di PHP.**

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Zero Dependencies](https://img.shields.io/badge/dependencies-zero-success.svg)](composer.json)

Moduref adalah library PHP modern yang dirancang untuk mengelola link referral pengguna langsung pada tabel database pengguna yang sudah ada, **tanpa memerlukan tabel relasi perantara tambahan** (seperti tabel `referrals` atau `affiliates`).

Semua mutasi database dijalankan di dalam transaksi atomik dengan row-locking (`SELECT ... FOR UPDATE`) untuk mencegah *race condition* dan kecurangan referral ganda.

---

## Fitur Utama

- **Zero Production Dependencies**: Hanya membutuhkan PHP standard core (`ext-pdo` opsional untuk database).
- **Database Agnostic**: Mendukung **MySQL**, **PostgreSQL**, dan **SQLite** via PDO.
- **Framework Agnostic**: Siap digunakan pada **Native PHP (PDO)**, **Laravel**, **Symfony**, **CodeIgniter**, maupun **Slim**.
- **Anti Race-Condition**: Menggunakan row-locking atomik (`FOR UPDATE`) pada saat transaksi penautan attribution dan penambahan poin.
- **Keamanan Ketat**:
  - Validasi ketat format UUID (v1-v5).
  - Sanitasi nama kolom dan tabel SQL (anti SQL Injection).
  - Proteksi Open Redirect dan Path Traversal pada URL referral.
  - Penolakan referral diri sendiri (*self-referral prevention*).

---

## Instalasi

Install melalui [Composer](https://getcomposer.org/):

```bash
composer require moduref/moduref
```

### Alternatif Tanpa Composer (Standalone Single-File Bundle)
Bagi Anda yang tidak menggunakan Composer, Moduref menyediakan file bundle mandiri siap pakai di folder `dist/`:
```php
// Cukup require satu file ini, semua class & helper langsung siap digunakan
require_once __DIR__ . '/dist/moduref.min.php';
```
Untuk meng-generate ulang bundle:
```bash
php build.php
```

---

## Persyaratan Skema Database

Tabel pengguna Anda hanya membutuhkan **4 kolom inti** (nama kolom dapat dipetakan ulang jika tabel Anda menggunakan nama berbeda):

```sql
-- Contoh struktur tabel pengguna di MySQL / MariaDB
CREATE TABLE users (
    uuid CHAR(36) PRIMARY KEY,
    username VARCHAR(120) NOT NULL UNIQUE,
    referral_poin INT UNSIGNED NOT NULL DEFAULT 0,
    referral_attribution VARCHAR(120) NULL
);
```

---

## Panduan Penggunaan Cepat

### 1. Membuat Link Referral untuk Dibagikan Pengguna

```php
use function Moduref\createReferralUrl;
// Atau: use Moduref\Moduref;

$referralLink = createReferralUrl(
    baseUrl: 'https://example.com',
    userUuid: $currentUser->uuid, // Contoh: "123e4567-e89b-12d3-a456-426614174000"
    path: '/referral'
);

echo $referralLink;
// Output: "https://example.com/referral/123e4567-e89b-12d3-a456-426614174000"
```

---

### 2. Menangkap Klik Referral & Menyimpan Cookie (Native PHP)

Ketika pengunjung mengklik tautan referral (misal rute `/referral/{uuid}`):

```php
use Moduref\Moduref;

// 1. Ambil UUID pengundang dari parameter URL
$referrerUuid = $_GET['uuid'] ?? '';

// 2. Simpan cookie referral (default berlaku 30 hari, HttpOnly, SameSite=Lax)
Moduref::setCookie($referrerUuid);

// 3. Arahkan pengguna ke halaman registrasi
header('Location: /register');
exit;
```

---

### 3. Menautkan Referral saat Registrasi Pengguna Baru

Saat proses pendaftaran akun baru berhasil:

```php
use Moduref\Moduref;
use function Moduref\createPdoReferralStore;

// 1. Inisialisasi store database menggunakan PDO yang sudah ada
$pdo = new PDO('mysql:host=localhost;dbname=mydb', 'root', 'secret');
$store = createPdoReferralStore($pdo, tableName: 'users');

// 2. Ambil UUID referral dari cookie browser
$referrerUuid = Moduref::getUuid();

if ($referrerUuid !== null) {
    // 3. Tautkan pengguna baru ke pengundang
    $result = $store->attach(
        userUuid: $newUserUuid,
        referrerUuid: $referrerUuid
    );

    if ($result->isAttached()) {
        // Referral berhasil! Poin pengundang otomatis bertambah +1
        // dan kolom referral_attribution user baru terisi username pengundang.
        echo "Berhasil ditautkan ke: " . $result->referrerUsername;
        
        // Bersihkan cookie referral
        Moduref::clearCookie();
    } else {
        // Alasan penolakan: "self_referral", "referrer_not_found", "already_referred"
        error_log("Referral gagal: " . $result->reason);
    }
}
```

---

## Integrasi di Laravel

### Controller untuk Menangkap Klik Referral:
```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Moduref\Moduref;

class ReferralController extends Controller
{
    public function handle(string $uuid)
    {
        Moduref::setCookie($uuid);
        return redirect()->route('register');
    }
}
```

### Penautan saat Registrasi Akun:
```php
namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Moduref\Moduref;

class RegisteredUserController extends Controller
{
    public function store(Request $request)
    {
        // 1. Buat pengguna baru
        $user = User::create([...]);

        // 2. Tautkan referral menggunakan koneksi PDO bawaan Laravel
        $store = Moduref::createPdoStore(DB::connection()->getPdo(), 'users');
        $referrerUuid = Moduref::getUuid();

        if ($referrerUuid) {
            $store->attach($user->uuid, $referrerUuid);
            Moduref::clearCookie();
        }

        return redirect()->route('dashboard');
    }
}
```

---

## Pemetaan Kolom Kustom (Custom Column Mapping)

Jika tabel database Anda menggunakan nama kolom yang berbeda:

```php
use Moduref\Moduref;

$store = Moduref::createPdoStore(
    pdo: $pdo,
    tableName: 'members',
    columns: [
        'uuid' => 'id_uuid',
        'username' => 'uname',
        'referral_points' => 'poin_affiliate',
        'referral_attribution' => 'direferensikan_oleh',
    ]
);
```

---

## Mengambil Ringkasan Referral Pengguna

```php
$summary = $store->getReferralSummary($userUuid);

if ($summary) {
    echo "Total Poin: " . $summary->referralPoints;
    echo "Pengundang: " . ($summary->referralAttribution ?? 'Organik (Tanpa Referral)');
}
```

---

## Menjalankan Unit Test

Untuk menjalankan test suite Moduref PHP:

```bash
php tests/run.php
```

---

## Lisensi & Pembuat

Dibuat oleh **[Muhamad Syamsudin](https://udenbaguse.my.id/)** untuk ekosistem **[Modujur](https://modujur.web.id/)**.  
Dilisensikan di bawah [MIT License](LICENSE).
