# Demo Aplikasi Referral Moduref Node.js (`demo`)

Aplikasi demo interaktif berbasis **Node.js** dan **Express.js** untuk menguji sistem referral Moduref (`moduref`).

Demo ini mendukung pemilihan mesin database hanya dengan mengubah konfigurasi `.env`:
- **SQLite** *(Default)*: Menggunakan modul `node:sqlite` bawaan Node.js (Node 22+ / 24+) dengan penyimpanan berkas `database.sqlite`. Langsung berjalan tanpa perlu konfigurasi atau instalasi server database eksternal.
- **PostgreSQL**: Menggunakan driver `pg` (`node-postgres`) dengan penanganan atomik transaksi dan placeholder dinamis.
- **MySQL / MariaDB**: Menggunakan driver `mysql2/promise` (dapat dijalankan via Docker Compose atau server MySQL lokal).


---

## ⚡ Cara Menjalankan (Mulai Cepat)

Pastikan dependensi sudah terpasang dari root workspace:
```bash
npm install
```

Jalankan aplikasi demo:
```bash
npm start
```
*(Atau dari folder `demo/`: `cd demo && npm start`)*

Buka peramban (*browser*) Anda di: **`http://localhost:3000`**

---

## 🧪 Skenario Pengujian Alur Referral

Untuk menguji alur referral secara nyata:

1. **Buat Akun Pertama (Pengundang)**:
   - Buka `http://localhost:3000/register`.
   - Isi form pendaftaran (contoh username: `budi`, email: `budi@example.com`, password: `password123`).
   - Anda akan diarahkan ke **Dashboard**.
   - Salin tautan pada bagian **Link referral kamu** (contoh: `http://localhost:3000/referral/123e4567...`).

2. **Gunakan Link Referral (Sebagai Calon Pengguna Baru)**:
   - Buka jendela **Incognito / Private Browsing** (atau browser berbeda).
   - Tempel dan akses tautan referral yang tadi disalin.
   - Server Express akan menangkap tautan, menyimpan cookie `referral_uuid` selama 30 hari, dan otomatis mengarahkan ke form `/register`.

3. **Selesaikan Pendaftaran Pengguna Kedua**:
   - Daftarkan akun kedua (contoh username: `siti`, email: `siti@example.com`, password: `password123`).
   - Akun baru otomatis terhubung ke pengundang (`budi`).

4. **Verifikasi Poin & Attribution**:
   - Kembali ke akun `budi` dan refresh dashboard.
   - **Jumlah pendaftar dari link kamu** akan bertambah menjadi `1`.
   - Di dashboard `siti`, **Referral yang digunakan saat mendaftar** akan menampilkan `budi`.

---

## 🗄️ Beralih ke Database MySQL / MariaDB

Jika Anda ingin menguji menggunakan MySQL:

1. Jalankan container MySQL menggunakan Docker Compose:
   ```bash
   npm run db:up
   # atau dari folder demo: docker compose up -d
   ```

2. Buka berkas `.env` di folder `demo/` dan ubah:
   ```ini
   DB_DRIVER=mysql
   MYSQL_HOST=127.0.0.1
   MYSQL_PORT=3306
   MYSQL_USER=moduref
   MYSQL_PASSWORD=moduref-local
   MYSQL_DATABASE=moduref_demo
   ```

3. Jalankan kembali aplikasi (`npm start`). Tabel `users` akan dibuat secara otomatis jika belum ada.

---

## 🐘 Beralih ke Database PostgreSQL

Cukup ubah konfigurasi `.env` di folder `demo/`:

```ini
DB_DRIVER=postgres
PG_HOST=127.0.0.1
PG_PORT=5432
PG_USER=postgres
PG_PASSWORD=rahasia
PG_DATABASE=moduref_demo
```

Jalankan kembali aplikasi (`npm start`). Tabel `users` akan otomatis dibuat jika belum ada.

---

## 📂 Struktur Berkas

```
demo/
├── .env                # Konfigurasi aktif (driver database, port, session secret)
├── .env.example        # Template konfigurasi environment
├── .env.docker.example # Template konfigurasi khusus Docker MySQL
├── app.js              # Aplikasi Express.js terpadu (SQLite, MySQL, PostgreSQL)
├── compose.yaml        # Docker Compose untuk database MySQL 8.4 opsional
├── database.sqlite     # Basis data SQLite lokal siap pakai (Default)
├── sql/
│   ├── schema-sqlite.sql   # Skrip skema tabel SQLite siap import
│   ├── schema-mysql.sql    # Skrip skema tabel MySQL / MariaDB siap import
│   └── schema-postgres.sql # Skrip skema tabel PostgreSQL siap import
├── package.json        # Dependensi dan skrip demo
└── README.md           # Panduan ini
```
