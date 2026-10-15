# Adapter Database & Custom Adapter

ModuRef tidak mengikat dirinya ke satu driver database tertentu. Package ini menggunakan prinsip **Inversion of Control (Dependency Injection)** di mana aplikasi Anda memberikan koneksi database/pool yang sudah ada ke ModuRef.

---

## 1. Prasyarat Skema Database

Tabel pengguna Anda harus memiliki minimal 4 kolom berikut:

| Makna | Default | Rekomendasi Tipe Data | Keterangan |
| :--- | :--- | :--- | :--- |
| **User UUID** | `uuid` | `CHAR(36)` / `UUID` | Wajib unik (PRIMARY KEY / UNIQUE) |
| **Username** | `username` | `VARCHAR(120)` | Username unik pengundang |
| **Poin Referral** | `referral_poin` | `INT UNSIGNED` / `INTEGER` | Default `0`, akumulasi seumur hidup |
| **Attribution** | `referral_attribution` | `VARCHAR(120)` | Menerima `NULL` (untuk user tanpa referral) |

---

## 2. Adapter Bawaan

### MySQL / MariaDB (`mysql2/promise`)
Menggunakan row-locking `FOR UPDATE` dan transaksi native MySQL:

```javascript
import mysql from "mysql2/promise";
import { createMysqlReferralStore } from "moduref";

const pool = mysql.createPool({ host: "localhost", user: "root", database: "myapp" });

const store = createMysqlReferralStore({
  pool,
  tableName: "users",
});
```

### PostgreSQL (`pg`)
Mendukung skema (misal `public.users`) dan translasi otomatis placeholder parameter (`?` $\rightarrow$ `$1, $2`):

```javascript
import { Pool } from "pg";
import { createPostgresReferralStore } from "moduref";

const pool = new Pool({ connectionString: process.env.DATABASE_URL });

const store = createPostgresReferralStore({
  pool,
  tableName: "public.users",
});
```

### SQLite (`better-sqlite3`)
Menggunakan mutex antrean internal (`withDatabaseLock`) dan transaksi `BEGIN IMMEDIATE`:

```javascript
import Database from "better-sqlite3";
import { createSqliteReferralStore } from "moduref";

const database = new Database("app.db");

const store = createSqliteReferralStore({
  database,
  tableName: "users",
});
```

---

## 3. Cara Membuat Custom Adapter

Jika proyek Anda menggunakan ORM atau query builder lain (seperti **Prisma**, **Drizzle**, **Kysely**, atau **Knex**), Anda bisa membuat adapter sendiri dengan mengimplementasikan antarmuka sederhana berikut:

```typescript
interface ReferralAdapter {
  dialect: "mysql" | "postgres" | "sqlite" | "standard";
  forUpdateClause: string; // contoh: " FOR UPDATE" atau ""
  quoteIdentifier(identifier: string, label: string): string;
  query(executor: any, sql: string, values: any[]): Promise<any[]>;
  execute(executor: any, sql: string, values: any[]): Promise<number>; // mengembalikan affectedRows
  transaction<T>(work: (trx: any) => Promise<T>, executor?: any): Promise<T>;
}
```

### Contoh Implementasi Kustom Sederhana:

```javascript
import { createReferralStore } from "moduref";

const myCustomAdapter = {
  dialect: "standard",
  forUpdateClause: " FOR UPDATE",
  quoteIdentifier(col) {
    return `"${col}"`;
  },
  async query(executor, sql, values = []) {
    const client = executor ?? myDbPool;
    const res = await client.raw(sql, values);
    return res.rows;
  },
  async execute(executor, sql, values = []) {
    const client = executor ?? myDbPool;
    const res = await client.raw(sql, values);
    return res.rowCount ?? 0;
  },
  async transaction(work, executor) {
    if (executor) return work(executor);
    return myDbPool.transaction((trx) => work(trx));
  },
};

const store = createReferralStore({
  adapter: myCustomAdapter,
  tableName: "users",
});
```
Dengan antarmuka ini, ModuRef dapat bekerja di atas sistem database apa pun!
