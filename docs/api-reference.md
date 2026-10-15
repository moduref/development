# Referensi API ModuRef

Daftar lengkap fungsi dan helper publik yang diekspor oleh `moduref`.

```javascript
import {
  createReferralUrl,
  createReferralRouter,
  getReferralUuid,
  clearReferralCookie,
  attachReferralFromRequest,
  createReferralStore,
  createMysqlReferralStore,
  createPostgresReferralStore,
  createSqliteReferralStore,
  createMysqlReferralAdapter,
  createPostgresReferralAdapter,
  createSqliteReferralAdapter,
} from "moduref";
```

---

## 1. Modul HTTP & Express

### `createReferralUrl(options)`
Membangun URL referral yang siap dibagikan oleh user.

- **Parameter (`options`)**:
  - `baseUrl` *(string, wajib)*: Origin URL aplikasi (contoh: `"https://example.com"`).
  - `userUuid` *(string, wajib)*: UUID pengguna yang sedang login.
  - `path` *(string, opsional, default: `"/referral"`)*: Prefix rute referral.
- **Return**: `string` (contoh: `"https://example.com/referral/123e4567-e89b-12d3-a456-426614174000"`).

---

### `createReferralRouter(options)`
Membuat Express Router untuk menangkap rute `GET /:referrerUuid`. Saat tautan diakses:
1. Memvalidasi format UUID pengundang.
2. Menyimpan UUID ke dalam cookie HTTP-Only selama 30 hari (dapat disesuaikan).
3. Melakukan redirect `302` ke halaman pendaftaran.

- **Parameter (`options`)**:
  - `registerPath` *(string, default: `"/register"`)*: URL tujuan redirect setelah cookie disimpan.
  - `cookieName` *(string, default: `"referral_uuid"`)*: Nama cookie.
  - `cookieMaxAge` *(number, default: `30 * 24 * 60 * 60 * 1000`)*: Durasi kedaluwarsa cookie (ms).
  - `cookiePath` *(string, default: `"/"`)*: Scope path cookie.
  - `cookieSameSite` *(string, default: `"lax"`)*: Kebijakan SameSite (`"lax"`, `"strict"`, atau `"none"`).
  - `cookieSecure` *(boolean, default: `process.env.NODE_ENV === "production"`)*: Flag HTTPS-only.
- **Return**: `express.Router`

---

### `getReferralUuid(req, options)`
Membaca dan memvalidasi UUID referral langsung dari header `cookie` request tanpa memerlukan middleware pihak ketiga seperti `cookie-parser`.

- **Parameter**:
  - `req` *(IncomingMessage / Request Express)*.
  - `options.cookieName` *(string, opsional)*.
- **Return**: `string | null` (UUID lowercase yang valid atau `null` jika tidak ada/tidak valid).

---

### `clearReferralCookie(res, options)`
Menghapus cookie referral dari browser pengguna setelah registrasi berhasil.

- **Parameter**:
  - `res` *(ServerResponse / Response Express)*.
  - `options`: Opsi cookie yang sama dengan yang digunakan saat menyimpan (path, secure, samesite).

---

### `attachReferralFromRequest(options)`
Fungsi kemudahan (*convenience helper*) untuk membaca cookie referral dari request dan menautkannya ke pengguna baru di database store.

- **Parameter (`options`)**:
  - `req` *(Request, wajib)*.
  - `userUuid` *(string, wajib)*: UUID user baru yang baru saja diinsert.
  - `store` *(ReferralStore, wajib)*: Instance store referral.
  - `executor` *(any, opsional)*: Koneksi transaksi aktif database jika registrasi berada dalam transaksi aplikasi.
  - `cookieName` *(string, opsional)*.
- **Return**: `Promise<{ attached: boolean, reason?: string, referrerUuid?: string, referrerUsername?: string }>`
  - Jika tidak ada cookie: `{ attached: false, reason: "no_referral" }`.
  - Jika sukses: `{ attached: true, referrerUuid: "...", referrerUsername: "..." }`.

---

## 2. Modul Store & Transaksi

### `createReferralStore(options)`
Membuat instance referral store independen.

- **Parameter (`options`)**:
  - `adapter` *(Adapter, wajib)*: Instance database adapter.
  - `tableName` *(string, default: `"users"`)*: Nama tabel pengguna (bisa `schema.table`).
  - `columns` *(object, opsional)*: Pemetaan nama kolom jika berbeda dari bawaan:
    ```javascript
    columns: {
      uuid: "id",                           // default: "uuid"
      username: "user_name",                 // default: "username"
      referralPoints: "total_points",        // default: "referral_poin"
      referralAttribution: "referred_by",    // default: "referral_attribution"
    }
    ```

### Metode Store:

#### `store.initialize(executor?)`
Menjalankan verifikasi awal (`SELECT ... WHERE 1 = 0`) untuk memastikan tabel dan ke-4 kolom referral tersedia sebelum server mulai menerima request.

#### `store.attach({ userUuid, referrerUuid, executor? })`
Menautkan referral antar dua user dan menaikkan poin referral pengundang (+1) secara atomik.
- **Hasil Kembalian**:
  - `{ attached: true, referrerUuid, referrerUsername }`: Berhasil.
  - `{ attached: false, reason: "self_referral" }`: Pengguna mencoba mereferensikan dirinya sendiri.
  - `{ attached: false, reason: "referrer_not_found" }`: UUID pengundang tidak terdaftar di database.
  - `{ attached: false, reason: "user_not_found" }`: User baru tidak ditemukan di database.
  - `{ attached: false, reason: "already_referred" }`: User baru sudah memiliki pengundang sebelumnya.

#### `store.getReferralSummary(userUuid, executor?)`
Mengambil ringkasan poin dan attribution untuk satu pengguna.
- **Return**: `Promise<{ referralPoints: number, referralAttribution: string | null } | null>`

---

## 3. Factory Helpers

Untuk kemudahan, ModuRef menyediakan factory helper langsung untuk masing-masing database:

- `createMysqlReferralStore({ pool, tableName?, columns? })`
- `createPostgresReferralStore({ pool, tableName?, columns? })`
- `createSqliteReferralStore({ database, tableName?, columns? })`
- `createMysqlReferralAdapter(pool)`
- `createPostgresReferralAdapter(pool)`
- `createSqliteReferralAdapter(database)`
