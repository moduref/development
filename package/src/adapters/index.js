/**
 * @typedef {Object} ReferralAdapter
 * @property {"mysql" | "postgres" | "sqlite" | "standard"} dialect - Dialek database SQL.
 * @property {string} forUpdateClause - Klausa SQL row locking (misal: " FOR UPDATE" atau kosong).
 * @property {(value: string, label?: string) => string} quoteIdentifier - Fungsi quoting kolom/tabel.
 * @property {(executor: any, sql: string, values?: any[]) => Promise<any[]>} query - Menjalankan query SELECT dan mengembalikan array baris.
 * @property {(executor: any, sql: string, values?: any[]) => Promise<number>} execute - Menjalankan query UPDATE/INSERT dan mengembalikan affected rows.
 * @property {<T>(work: (trx: any) => Promise<T>, executor?: any) => Promise<T>} transaction - Menjalankan pekerjaan dalam blok transaksi atomik.
 */

export { createMysqlReferralAdapter } from "./mysql.js";
export { createPostgresReferralAdapter } from "./postgres.js";
export { createSqliteReferralAdapter } from "./sqlite.js";
