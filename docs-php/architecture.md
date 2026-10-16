# Arsitektur & Konsep Dasar Moduref PHP

Dokumen ini menjelaskan filosofi desain, siklus hidup data, strategi transaksi bebas race-condition, serta struktur modul kode internal pada library **Moduref PHP**.

---

## 1. Filosofi Desain: Single-Table Storage

Kebanyakan sistem referral tradisional menggunakan tabel relasi perantara (misalnya tabel `referrals` atau `user_referrals`) yang memiliki kolom `id`, `referrer_id`, `referred_id`, `created_at`, dll. Pendekatan ini sering kali memperkenalkan:
- Overhead *JOIN* tambahan pada kueri profil/pengguna.
- Kompleksitas integritas referensial dan migrasi database berlebih.
- Potensi inkonsistensi data poin referral jika agregasi dilakukan secara terpisah.

**Moduref menggunakan pendekatan Single-Table**:
Cukup menambahkan **2 kolom** pada tabel pengguna yang sudah ada (contoh: tabel `users`):
1. `referral_attribution` (`VARCHAR(120) NULL`): Menyimpan UUID dari pengundang (*referrer*). Kolom ini bernilai `NULL` untuk pengguna organik.
2. `referral_poin` (`INT UNSIGNED NOT NULL DEFAULT 0`): Menyimpan akumulasi total undangan sukses pengguna secara instan (*counter*).

```
+---------------------------------------------------------------------------------+
|                                 TABEL USERS                                     |
+--------------------------------------+---------------+--------------------------+
| uuid (CHAR 36)                       | referral_poin | referral_attribution     |
+--------------------------------------+---------------+--------------------------+
| 11111111-1111-4111-8111-111111111111 | 2             | NULL (Organik)           |
| 22222222-2222-4222-8222-222222222222 | 0             | 11111111-... (Diundang 1)|
| 33333333-3333-4333-8333-333333333333 | 0             | 11111111-... (Diundang 1)|
+--------------------------------------+---------------+--------------------------+
```

---

## 2. Siklus Hidup Referral (Lifecycle & Flow)

Alur referral Moduref dirancang mulus dari penangkapan tautan hingga atribusi pengguna:

```
[Pengguna A]
     | (Bagi link: /referral/{uuid_A})
     v
[Calon Pengguna B Mengklik Link]
     |
     v
[HTTP Handler / Controller]
     |---> Validasi format UUID A (RFC 4122)
     |---> Simpan cookie HTTP-Only: referral_uuid={uuid_A} (Durasi 30 hari)
     |---> Redirect 302 ke halaman /register
     v
[Pengguna B Mengisi Form Registrasi & Submit]
     |
     v
[Registration Controller]
     |---> 1. Insert Pengguna B (uuid = uuid_B) ke tabel users
     |---> 2. Panggil Moduref::attachFromCookie($_COOKIE, uuid_B, $store, $pdo)
     |          |
     |          +---> Baca cookie "referral_uuid"
     |          +---> Transaksi Database Atomik (FOR UPDATE lock):
     |          |       a. Validasi: uuid_A != uuid_B (Cegah self-referral)
     |          |       b. Validasi: Akun A aktif & ada di database
     |          |       c. Validasi: Akun B belum pernah teratribusi
     |          |       d. Update Akun B: referral_attribution = uuid_A
     |          |       e. Update Akun A: referral_poin = referral_poin + 1
     |          v
     |---> 3. Hapus Cookie Referral (Set-Cookie expired)
     |---> 4. Redirect ke Dashboard
```

---

## 3. Perlindungan Race-Condition & Row-Level Locking

Salah satu tantangan kritis pada sistem referral adalah **kondisi balapan (*race condition*)** ketika:
- Dua atau lebih request registrasi dari link referral yang sama diproses secara paralel pada mili-detik yang sama.
- Pengguna mencoba melakukan registrasi ganda atau mengubah atribusi secara ilegal.

Moduref mengatasi hal ini secara native melalui **`Moduref\Adapters\PdoAdapter`**:

1. **MySQL / MariaDB**:
   Menggunakan klausa `FOR UPDATE` saat membaca record calon pengguna dan pengundang di dalam transaksi InnoDB (`START TRANSACTION`). Kueri membaca baris dan menguncinya secara eksklusif sehingga tidak dapat diubah oleh transaksi paralel lain hingga commit selesai.

2. **PostgreSQL**:
   Menggunakan klausa `FOR UPDATE` transaksional standar yang menjamin isolasi baris antar transaksi.

3. **SQLite**:
   SQLite tidak mendukung klausa `FOR UPDATE`. Sebagai gantinya, `PdoAdapter` memanfaatkan transaksi eksklusif database file locking bawaan SQLite (`BEGIN IMMEDIATE` / serialisasi transaksi) untuk menjamin atomisitas pembaruan.

---

## 4. Struktur Modul Kode (`src/`)

Arsitektur kode `moduref-php` dirancang modular mengikuti standar **PSR-4** dan **PSR-12**:

```
src/
├── Constants.php                   # Nama cookie default, usia cookie, pola regex UUID
├── Validation.php                  # Validasi RFC 4122, generator random_bytes UUID v4
├── Http.php                        # Konstruksi URL, parsing cookie, header Set-Cookie
├── AttachResult.php                # DTO immutable hasil penautan referral
├── ReferralSummary.php             # DTO immutable statistik rekapitulasi referral
├── ReferralStore.php               # Logika inti verifikasi, row-locking, & pembaruan database
├── Moduref.php                     # Facade statis terpadu (Moduref\Moduref)
├── functions.php                   # Functional helper global (Moduref\createReferralUrl, dll)
├── Adapters/
│   ├── ReferralAdapterInterface.php # Kontrak adapter database (query, execute, transaction)
│   └── PdoAdapter.php               # Adapter PDO universal (MySQL, PostgreSQL, SQLite)
└── Exceptions/
    ├── ModurefException.php         # Base exception untuk seluruh kesalahan Moduref
    ├── InvalidUuidException.php     # Dilempar jika format UUID tidak sesuai RFC 4122
    ├── InvalidIdentifierException.php# Dilempar jika nama tabel/kolom tidak aman (SQL injection prevention)
    └── InvalidPathException.php     # Dilempar jika path rute referral tidak valid
```
