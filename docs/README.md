# Dokumentasi Moduref

Selamat datang di dokumentasi resmi **Moduref** (`Modujur Referral`). Moduref adalah package Node.js ringan untuk mengelola sistem referral berbasis UUID user pada aplikasi Express.js dan database SQL (MySQL, PostgreSQL, SQLite).

---

## 📚 Daftar Dokumen

1. [**Arsitektur & Konsep Dasar (`architecture.md`)**](./architecture.md)
   - Filosofi desain penyimpanan single-table.
   - Siklus hidup referral (User Flow & Data Flow).
   - Penjelasan struktur modul kode (`src/`).

2. [**Referensi API Lengkap (`api-reference.md`)**](./api-reference.md)
   - Dokumentasi seluruh fungsi publik (`createReferralUrl`, `createReferralRouter`, `attachReferralFromRequest`, dsb).
   - Parameter, opsi konfigurasi, dan contoh nilai kembalian.

3. [**Adapter Database & Custom Adapter (`database-adapters.md`)**](./database-adapters.md)
   - Konfigurasi adapter bawaan (MySQL, PostgreSQL, SQLite).
   - Mekanisme transaksi dan row-level locking (`FOR UPDATE`).
   - Panduan membuat adapter kustom (Prisma, Drizzle, Kysely, dll).

4. [**Panduan Berkontribusi & Development (`contributing.md`)**](./contributing.md)
   - Panduan setup lokal dan menjalankan aplikasi demo.
   - Menjalankan unit test otomatis (`node:test`).
   - Menjalankan proses bundling & minifikasi (`esbuild`).
   - Pedoman gaya kode dan pembuatan Pull Request.
