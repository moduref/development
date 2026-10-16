# Panduan Berkontribusi & Development Moduref PHP

Terima kasih atas minat Anda untuk berkontribusi pada pengembangan **Moduref PHP**! Panduan ini menjelaskan alur kerja pengembangan, pengujian, bundling, dan pengiriman kontribusi.

---

## 1. Prasyarat Lingkungan

- **PHP 8.1 atau lebih tinggi** (CLI).
- Ekstensi PHP yang diperlukan:
  - `pdo`
  - `pdo_sqlite` (digunakan untuk eksekusi test suite otomatis yang cepat)
  - `pdo_mysql` (opsional untuk verifikasi MySQL)
  - `openssl` atau `random` (bawaan PHP 8.1+)
- **Composer** (opsional namun disarankan untuk verifikasi autoloading).

---

## 2. Struktur Repositori

```
moduref-php/
├── src/                # Kode sumber utama (PSR-4: Moduref\)
│   ├── Adapters/       # Adapter database
│   ├── Exceptions/     # Kelas-kelas Exception
│   ├── Constants.php
│   ├── Http.php
│   ├── Moduref.php
│   ├── ReferralStore.php
│   └── Validation.php
├── tests/              # Test suite unit & integrasi
│   └── run.php         # Test runner mandiri bebas dependensi
├── dist/               # Hasil bundler & minifier
│   ├── moduref.php     # Bundle single-file unminified
│   └── moduref.min.php # Bundle single-file minified
├── build.php           # Skrip bundler & minifier kustom
└── composer.json       # Metadata package Packagist
```

---

## 3. Menjalankan Test Otomatis

Moduref PHP memiliki test runner mandiri tanpa dependensi PHPUnit, sehingga sangat cepat dan tidak memerlukan instalasi vendor yang berat:

```bash
cd moduref-php
php tests/run.php
```

Test runner akan mengeksekusi pengujian:
- Validasi UUID v4 (format benar, karakter tidak sah, huruf kapital normalisasi).
- Pembuatan URL referral dan sanitasi path/trailing slashes.
- Ekstraksi dan sanitasi cookie request.
- Operasi transaksi SQLite in-memory:
  - Attachment referral normal.
  - Pencegahan self-referral.
  - Penanganan pengundang yang tidak ada di database.
  - Pencegahan re-attribution (pengguna yang sudah diundang tidak boleh ditautkan ulang).
  - Verifikasi rekap statistik (`getReferralSummary`).
- **Pengujian Bundle Terisolasi**: Menjalankan subprocess terpisah untuk memastikan file `dist/moduref.min.php` bekerja 100% tanpa error sintaks atau duplikasi namespace.

---

## 4. Menjalankan Bundler & Minifier

Kapan pun Anda mengubah kode di dalam folder `src/`, Anda harus memperbarui berkas distribusi di folder `dist/`:

```bash
php build.php
```

Skrip ini akan:
1. Membaca seluruh file di `src/`.
2. Menyusun urutan dependensi (Exceptions, DTOs, Adapters, Store, Facade, Functions).
3. Mengelompokkan namespace secara otomatis (`namespace Moduref { ... }`).
4. Menghasilkan:
   - `dist/moduref.php` (berisi komentar dan PHPDoc lengkap).
   - `dist/moduref.min.php` (terkompresi tanpa spasi dan komentar).

---

## 5. Standar Gaya Kode

- Selalu sertakan deklarasi strict types di baris pertama setiap file:
  ```php
  declare(strict_types=1);
  ```
- Patuhi standar **PSR-12** untuk penataan indentasi (4 spasi), kurung kurawal, dan penamaan.
- Berikan tipe data (*type hints*) pada seluruh argumen fungsi dan nilai kembalian (*return types*).
- Hindari menambahkan dependensi eksternal di `composer.json` bagian `"require"`. Moduref berkomitmen untuk tetap **Zero Runtime Dependencies**.

---

## 6. Mengirimkan Pull Request (PR)

1. Buat branch fitur baru dari `main`:
   ```bash
   git checkout -b feature/nama-fitur-baru
   ```
2. Tulis kode fitur beserta penambahan unit test di `tests/run.php`.
3. Jalankan `php build.php` dan pastikan seluruh test lulus (`php tests/run.php`).
4. Commit perubahan dengan pesan yang deskriptif:
   ```bash
   git commit -m "feat: add support for custom referral cookie prefix"
   ```
5. Buka Pull Request di repository GitHub [moduref/moduref-php](https://github.com/moduref/moduref-php).
