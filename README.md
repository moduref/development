# ModuRef Referral

Package npm kecil untuk mengelola referral berbasis UUID user yang sudah dimiliki aplikasi. Workspace ini juga berisi demo register/login Express dengan MySQL.

## Aturan referral

- Link berbentuk `/referral/:uuid`, dengan UUID user yang membagikan link.
- Membuka link menyimpan atau mengganti cookie referral selama 30 hari.
- Saat pendaftaran berhasil, username pengundang disimpan di kolom `referral_attribution` pada baris user baru.
- `referral_poin` pengundang bertambah satu untuk setiap pendaftaran sukses dan tidak berkurang saat invitee dihapus.
- Satu user dapat mengundang banyak user; satu user baru hanya dapat memiliki satu pengundang.
- UUID pengundang harus menunjuk ke user yang ada. Referral diri sendiri ditolak.
- Semua nilai referral ada di tabel user yang sama. Nama tabel dan pemetaan kolom dapat dikonfigurasi; kolom aplikasi lain tetap dipertahankan.

## Menjalankan demo dengan XAMPP

Butuh Node.js 20.6+ dan npm. Project tetap boleh berada di folder Desktop; XAMPP dipakai untuk MySQL, sedangkan Express menyajikan form HTML dan menangani register/login di `http://localhost:3000`. Tidak perlu memindahkan project ke `htdocs`.

1. Di XAMPP Control Panel, start **MySQL**. Port bawaan yang dicantumkan XAMPP adalah `3306`; pastikan port itu sama dengan `MYSQL_PORT` di `.env`. [Dokumentasi XAMPP](https://www.apachefriends.org/faq_windows.html)
2. Untuk membuka phpMyAdmin, start **Apache** juga. Buka tab **Import**, pilih `demo/sql/create-database.sql`, lalu klik **Import/Go**. File ini membuat database `moduref_demo` dan tabel `users`, termasuk empat kolom referral. Jika database itu sudah ada, catatan `#1007` menyatakan database dilewati dan aman diabaikan; tabel diarahkan langsung ke database tersebut. Apache hanya diperlukan untuk phpMyAdmin; demo web tetap dijalankan oleh Express.
3. Salin `demo/.env.example` menjadi `demo/.env`. Sesuaikan `MYSQL_USER` dan `MYSQL_PASSWORD` jika instalasi XAMPP-mu memakai kredensial berbeda.
4. Jalankan `npm install` dari direktori project.
5. Jalankan `npm start`, lalu buka `http://localhost:3000`.

Database hasil import sudah berisi struktur yang dibutuhkan. Aplikasi memeriksa kolom tersebut saat mulai. Jika kamu sudah mengimpor schema versi sebelumnya, jalankan `demo/sql/upgrade-v1-to-v2.sql` sekali sebelum menjalankan aplikasi. Setelah server berjalan, buat akun lewat halaman register; database kosong tidak berisi akun demo. Session login demo disimpan di memory, jadi ini untuk pengujian lokal dan session akan hilang saat server dimulai ulang.

### Alternatif: MySQL lewat Docker

Jika ingin memakai database Docker, salin `demo/.env.docker.example` ke `demo/.env`, lalu jalankan `npm run db:up` dan `npm start`. Docker menerbitkan port MySQL di `3307` agar tidak bentrok dengan MySQL XAMPP di `3306`. Selesai mencoba, jalankan `npm run db:down`.

## Package

Source package ada di [`package`](package/README.md). Adapter bawaan mendukung MySQL/MariaDB, PostgreSQL, dan SQLite.
