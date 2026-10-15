# Panduan Berkontribusi (Contributing Guide)

Terima kasih atas ketertarikan Anda untuk berkontribusi pada pengembangan **Moduref**! Dokumen ini memandu Anda dalam menyiapkan lingkungan pengembangan lokal, menjalankan pengujian, dan membuat kontribusi.

---

## 1. Persyaratan Sistem

- **Node.js**: Versi `>= 20.6.0`
- **npm**: Versi `>= 10.0.0`
- **Database (opsional untuk demo)**:
  - MySQL via XAMPP (port `3306`), ATAU
  - Docker & Docker Compose (port `3307`).

---

## 2. Setup Lingkungan Lokal

1. **Clone repositori**:
   ```bash
   git clone https://github.com/moduref/development
   cd moduref
   ```

2. **Install dependensi**:
   ```bash
   npm install
   ```
   *Perintah ini akan secara otomatis menghubungkan workspaces (`package` dan `demo`).*

---

## 3. Menjalankan Unit Test

ModuRef menggunakan **Node.js Native Test Runner (`node:test`)** tanpa dependensi pihak ketiga:

```bash
npm test
```

Semua pengujian baru harus diletakkan di dalam folder `package/test/` dengan akhiran `.test.js`.

---

## 4. Melakukan Bundling (`esbuild`)

ModuRef menggunakan `esbuild` untuk mengompilasi file-file di `package/src/` menjadi 1 bundle minified di `package/dist/index.js`:

```bash
npm run build
```

---

## 5. Menjalankan Aplikasi Demo

Aplikasi demo berada di folder `demo/` dan menggunakan Express + MySQL:

### A. Menggunakan XAMPP (Port 3306)
1. Nyalakan service **MySQL** di XAMPP Control Panel.
2. Buka phpMyAdmin, import file `demo/sql/create-database.sql`.
3. Salin file environment:
   ```bash
   cp demo/.env.example demo/.env
   ```
4. Jalankan aplikasi:
   ```bash
   npm start
   ```
5. Buka `http://localhost:3000` di browser.

### B. Menggunakan Docker (Port 3307)
```bash
cp demo/.env.docker.example demo/.env
npm run db:up
npm start
```
Selesai mencoba, matikan container dengan:
```bash
npm run db:down
```

---

## 6. Alur Pembuatan Pull Request (PR)

1. Buat branch baru dari `main`:
   ```bash
   git checkout -b feature/nama-fitur-baru
   ```
2. Buat perubahan kode Anda di dalam `package/src/`.
3. Tulis unit test terkait di `package/test/`.
4. Pastikan semua test lulus dan build berhasil:
   ```bash
   npm test
   npm run build
   ```
5. Commit perubahan Anda dengan pesan yang jelas:
   ```bash
   git commit -m "feat: tambahkan adapter kysely"
   ```
6. Push branch Anda dan buat **Pull Request** di GitHub!
