# Demo Aplikasi Referral Moduref PHP (`demo-php`)

Aplikasi demo interaktif berbasis **PHP Native** dan **PDO** untuk menguji sistem referral Moduref PHP (`moduref/moduref`).

Demo ini mendukung multi-database hanya dengan mengubah konfigurasi `.env`:
- **SQLite** *(Default)*: Siap langsung dijalankan tanpa perlu instalasi server database atau Docker.
- **PostgreSQL**: Menggunakan driver PDO `pgsql` dengan penanganan atomic transaction dan row-locking.
- **MySQL / MariaDB**: Dapat diaktifkan melalui konfigurasi `.env` dengan driver PDO `mysql`.


---

## ⚡ Cara Menjalankan (Mulai Cepat dalam 5 Detik)

Cukup buka terminal dan jalankan server bawaan PHP:

```bash
cd demo-php
php -S localhost:8000
```

Buka peramban (*browser*) Anda di: **`http://localhost:8000`**

---

## 🧪 Skenario Pengujian Alur Referral

Untuk menguji alur referral secara nyata:

1. **Buat Akun Pertama (Pengundang)**:
   - Buka `http://localhost:8000/register`.
   - Daftarkan akun baru (contoh username: `budi`).
   - Anda akan diarahkan ke **Dashboard**.
   - Di dashboard, salin **Link Referral Pribadi** Anda (contoh: `http://localhost:8000/referral/123e4567...`).

2. **Gunakan Link Referral (Sebagai Calon Pengguna Baru)**:
   - Buka jendela **Incognito / Private Browsing** (atau browser berbeda).
   - Tempel (*paste*) dan buka link referral yang tadi disalin.
   - Halaman akan otomatis menyimpan cookie `referral_uuid` dan mengarahkan ke form `/register`.
   - Anda akan melihat notifikasi: *"🎁 Tautan Referral Aktif! Anda mendaftar melalui undangan dari @budi"*.

3. **Selesaikan Pendaftaran Pengguna Kedua**:
   - Daftarkan akun kedua (contoh username: `siti`).
   - Akun baru otomatis terhubung ke pengundang (`@budi`).

4. **Verifikasi Poin & Rekapitulasi**:
   - Kembali ke akun `@budi` dan segarkan (*refresh*) dashboard.
   - Perhatikan:
     - **Total Poin Referral** bertambah menjadi `1`.
     - **Teman Berhasil Diundang** menampilkan `@siti` dengan waktu pendaftarannya.

---

## 🗄️ Beralih ke Database MySQL / MariaDB

Jika Anda ingin menguji menggunakan MySQL:

1. Jalankan container MySQL menggunakan Docker Compose:
   ```bash
   docker compose up -d
   ```
   *(Atau gunakan server MySQL lokal yang sedang berjalan di port 3306)*.

2. Buka berkas `.env` di folder `demo-php/` dan ubah:
   ```ini
   DB_DRIVER=mysql
   MYSQL_HOST=127.0.0.1
   MYSQL_PORT=3306
   MYSQL_DATABASE=moduref_demo
   MYSQL_USER=root
   MYSQL_PASSWORD=
   ```

3. Jalankan kembali `php -S localhost:8000`. Tabel `users` akan dibuat secara otomatis saat pertama kali diakses.

---

## 🐘 Beralih ke Database PostgreSQL

Cukup ubah konfigurasi `.env` di folder `demo-php/`:

```ini
DB_DRIVER=pgsql
PGSQL_HOST=127.0.0.1
PGSQL_PORT=5432
PGSQL_DATABASE=moduref_demo
PGSQL_USER=postgres
PGSQL_PASSWORD=rahasia
```

Jalankan kembali `php -S localhost:8000`. Tabel `users` akan otomatis dibuat saat aplikasi dijalankan.

---

## 📂 Struktur Berkas

```
demo-php/
├── .env                # Konfigurasi aktif (driver database, port, dll)
├── .env.example        # Template konfigurasi
├── bootstrap.php       # Inisialisasi PDO, autoloader Moduref, helper HTML
├── index.php           # Router dan antarmuka UI (Login, Register, Dashboard)
├── compose.yaml        # Docker Compose untuk database MySQL 8.4 opsional
├── database.sqlite     # Basis data SQLite lokal siap pakai (Default)
├── sql/
│   ├── schema-sqlite.sql   # Skrip skema tabel SQLite siap import
│   ├── schema-mysql.sql    # Skrip skema tabel MySQL / MariaDB siap import
│   └── schema-postgres.sql # Skrip skema tabel PostgreSQL siap import
├── composer.json       # Metadata proyek demo
└── README.md           # Panduan ini
```
