# Dokumentasi Moduref PHP (`moduref/moduref`)

Selamat datang di dokumentasi resmi **Moduref PHP**. Moduref adalah library PHP modern, ringan, dan zero-dependency untuk mengelola sistem referral berbasis UUID pengguna yang sudah ada pada database SQL (MySQL, MariaDB, PostgreSQL, SQLite) tanpa memerlukan tabel relasi perantara.

---

## 🚀 Fitur Unggulan

- **Zero Runtime Dependencies**: Murni PHP 8.1+ standar tanpa dependensi pihak ketiga.
- **Single-Table Design**: Memanfaatkan kolom `referral_poin` dan `referral_attribution` pada tabel pengguna yang sudah ada.
- **Bebas Race-Condition**: Perlindungan transaksi row-level locking (`FOR UPDATE` pada MySQL/Postgres atau transaksi eksklusif pada SQLite).
- **Universal Database Adapter**: Bekerja langsung dengan koneksi `PDO` standar apa pun.
- **Framework Agnostic**: Terintegrasi mulus dengan Laravel, Symfony, CodeIgniter 4, mau pun PHP Native / Procedural.
- **Format Ganda**: Tersedia melalui Composer (`moduref/moduref`) atau single-file bundle minified (`dist/moduref.min.php`).

---

## 📦 Instalasi

### Melalui Composer (Rekomendasi)
```bash
composer require moduref/moduref
```

### Standalone (Tanpa Composer)
Unduh file `dist/moduref.min.php` dari rilis [GitHub moduref-php](https://github.com/moduref/moduref-php) dan muat langsung di proyek Anda:
```php
require_once __DIR__ . '/path/to/moduref.min.php';
```

---

## 📚 Daftar Dokumen Panduan

1. [**Arsitektur & Konsep Dasar (`architecture.md`)**](./architecture.md)
   - Filosofi penyimpanan single-table.
   - Siklus hidup referral (User Flow, Cookie Handling, & Data Flow).
   - Mekanisme transaksi dan row-level locking.
   - Struktur modul kode internal (`src/`).

2. [**Referensi API Lengkap (`api-reference.md`)**](./api-reference.md)
   - Dokumentasi lengkap Facade `Moduref\Moduref`.
   - Dokumentasi kelas inti `ReferralStore`, `Http`, `Validation`, dan DTO (`AttachResult`, `ReferralSummary`).
   - Referensi fungsi pembantu global (*functional helpers*) dan penanganan Exception.

3. [**Adapter Database & Custom Adapter (`database-adapters.md`)**](./database-adapters.md)
   - Konfigurasi `PdoAdapter` untuk MySQL, PostgreSQL, dan SQLite.
   - Penyesuaian nama kolom dan tabel kustom.
   - Panduan membuat adapter kustom dengan `ReferralAdapterInterface` (Doctrine DBAL, Medoo, dll).

4. [**Integrasi Framework Web (`framework-integration.md`)**](./framework-integration.md)
   - Panduan integrasi di **Laravel** (Routes, Eloquent, Controller).
   - Panduan integrasi di **Symfony** (Service Container & Doctrine DBAL PDO).
   - Panduan integrasi di **CodeIgniter 4**.
   - Panduan integrasi di **PHP Native / PDO**.

5. [**Panduan Berkontribusi & Development (`contributing.md`)**](./contributing.md)
   - Setup lokal dan menjalankan unit tests otomatis.
   - Menjalankan bundler & minifier (`build.php`).
   - Panduan standar kode PSR-12 dan alur rilis Packagist.

---

## 💡 Contoh Singkat Penggunaan

```php
use Moduref\Moduref;

// 1. Siapkan koneksi PDO standar
$pdo = new PDO('mysql:host=127.0.0.1;dbname=app', 'root', 'secret', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// 2. Inisialisasi Referral Store
$store = Moduref::createPdoStore($pdo, 'users');
$store->initialize();

// 3. Buat link referral untuk pengguna yang sedang login
$referralLink = Moduref::createUrl('https://app.com', '123e4567-e89b-12d3-a456-426614174000');
// Hasil: https://app.com/referral/123e4567-e89b-12d3-a456-426614174000

// 4. Tautkan pengguna baru saat registrasi dari cookie
$result = Moduref::attachFromCookie($_COOKIE, $newUserUuid, $store, $pdo);

if ($result->attached) {
    echo "Referral berhasil ditautkan ke pengundang: " . $result->referrerUuid;
}
```
