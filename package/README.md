# moduref

Package referral Express berbasis UUID dengan adapter untuk MySQL/MariaDB, PostgreSQL, dan SQLite. Package memakai user table milik aplikasi; kolom lain yang sudah ada tidak dibaca atau diubah.

Membutuhkan Node.js 20.6+ dan Express 4.18 atau 5.

## Kolom yang dibutuhkan

User table harus memiliki empat kolom berikut (nama dapat dipetakan lewat konfigurasi):

| Makna | Nama bawaan | Isi |
| --- | --- | --- |
| UUID user | `uuid` | UUID user yang sudah dibuat aplikasi; harus unik |
| Username | `username` | Username pengundang |
| Poin referral | `referral_poin` | Total referral sukses; bertambah satu per pendaftaran |
| Referral attribution | `referral_attribution` | Snapshot username pengundang saat pendaftaran |

Kolom `referral_poin` sebaiknya bernilai angka dan memiliki default `0`. `referral_attribution` harus menerima `NULL` untuk akun tanpa referral. User table boleh memiliki kolom tambahan; package hanya memperbarui kolom UUID, username, poin, dan attribution yang dikonfigurasi.

Poin adalah total kumulatif: package menambahnya satu kali saat akun baru berhasil ditautkan. Poin tidak dikurangi bila akun invitee kemudian dihapus. Username pengundang disimpan sebagai snapshot, sehingga perubahan username di masa depan tidak mengubah attribution lama.

## Adapter database

Package tidak memasang atau mengimpor driver database. Aplikasi memasang driver yang dipakai, lalu memberikan pool/connection-nya:

```sh
npm install moduref express mysql2
```

PostgreSQL memakai `pg`; SQLite memakai `better-sqlite3`:

```sh
npm install pg
npm install better-sqlite3
```

### MySQL atau MariaDB

```js
import express from "express";
import mysql from "mysql2/promise";
import {
  attachReferralFromRequest,
  clearReferralCookie,
  createMysqlReferralStore,
  createReferralRouter,
  createReferralUrl,
} from "moduref";

const app = express();
const pool = mysql.createPool({ host, user, password, database });
const referrals = createMysqlReferralStore({
  pool,
  tableName: "accounts",
  columns: {
    uuid: "uuid",
    username: "username",
    referralPoints: "referral_poin",
    referralAttribution: "referral_attribution",
  },
});

await referrals.initialize();
app.use("/referral", createReferralRouter({ registerPath: "/register" }));
```

### PostgreSQL

```js
import { Pool } from "pg";
import { createPostgresReferralStore } from "moduref";

const pool = new Pool({ connectionString: process.env.DATABASE_URL });
const referrals = createPostgresReferralStore({ pool, tableName: "public.accounts" });
await referrals.initialize();
```

### SQLite dengan better-sqlite3

```js
import Database from "better-sqlite3";
import { createSqliteReferralStore } from "moduref";

const database = new Database("app.sqlite");
const referrals = createSqliteReferralStore({ database, tableName: "accounts" });
await referrals.initialize();
```

Semua store menerima opsi `columns` yang sama untuk memetakan nama kolom aplikasi. `tableName` boleh berupa `schema.table` pada database yang mendukung schema.

Contoh tipe kolom inti:

```sql
-- PostgreSQL
CREATE TABLE accounts (
  uuid UUID PRIMARY KEY,
  username VARCHAR(120) NOT NULL UNIQUE,
  referral_poin INTEGER NOT NULL DEFAULT 0,
  referral_attribution VARCHAR(120)
);

-- SQLite
CREATE TABLE accounts (
  uuid TEXT PRIMARY KEY,
  username TEXT NOT NULL UNIQUE,
  referral_poin INTEGER NOT NULL DEFAULT 0,
  referral_attribution TEXT
);
```

## Buat link referral

UUID dari user yang sedang login menjadi referral identifier:

```js
const link = createReferralUrl({
  baseUrl: "https://example.com",
  userUuid: loggedInUser.uuid,
});
```

Secara bawaan link berbentuk `https://example.com/referral/<uuid>`. Saat link valid dibuka, router menyimpan UUID ke cookie HTTP-only selama 30 hari lalu mengarahkan browser ke `/register`. Link berbeda yang dibuka kemudian mengganti cookie tersebut.

## Tautkan setelah user baru dibuat

Pembuatan user dan attribution harus berada di transaksi yang sama. Contoh MySQL:

```js
const connection = await pool.getConnection();
try {
  await connection.beginTransaction();
  await connection.execute(
    `INSERT INTO accounts (uuid, username, email, password_hash)
     VALUES (?, ?, ?, ?)`,
    [newUserUuid, username, email, passwordHash],
  );

  await attachReferralFromRequest({
    req,
    userUuid: newUserUuid,
    store: referrals,
    executor: connection,
  });

  await connection.commit();
  clearReferralCookie(res);
} catch (error) {
  await connection.rollback();
  throw error;
} finally {
  connection.release();
}
```

Tanpa `executor`, store membuka dan mengelola transaksinya sendiri. Bila transaksi registrasi sudah dimulai oleh aplikasi, berikan connection transaksi sebagai `executor` supaya insert user, attribution, dan kenaikan poin tetap atomik.

Adapter SQLite memakai transaksi internal package untuk perubahan referral. Karena API transaksi `better-sqlite3` bersifat sinkron, panggil helper referral tanpa `executor` untuk SQLite; setelah insert akun berhasil, store akan menautkan referral dan menaikkan poin dalam satu transaksinya sendiri.

`attachReferralFromRequest()` mengembalikan `no_referral` bila cookie tidak ada. `store.attach()` menolak referral diri sendiri, UUID pengundang yang tidak ada, user baru yang tidak ada, dan user yang sudah memiliki attribution. Pengulangan request tidak menambah poin dua kali.

Ambil nilai referral user dengan:

```js
const summary = await referrals.getReferralSummary(userUuid);
// { referralPoints: 3, referralAttribution: "username_pengundang" }
```

## Skema demo

Demo XAMPP menggunakan schema MySQL di `demo/sql/create-database.sql`. File itu membuat empat kolom referral ditambah kolom email/password yang diperlukan untuk login. Untuk database lama dari schema demo sebelumnya, gunakan `demo/sql/upgrade-v1-to-v2.sql` sekali sebelum menjalankan aplikasi.
