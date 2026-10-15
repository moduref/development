import { quoteIdentifier } from "../validation.js";

/**
 * Membuat adapter transaksi referral untuk driver `mysql2/promise`.
 *
 * @param {any} pool - Instance pool koneksi dari `mysql2/promise` (misal: `mysql.createPool(...)`).
 * @returns {import("./index.js").ReferralAdapter} Instance adapter MySQL.
 * @throws {TypeError} Jika pool tidak memiliki method `.execute` atau `.getConnection`.
 * @example
 * import mysql from "mysql2/promise";
 * const pool = mysql.createPool({ host: "localhost", user: "root", database: "app" });
 * const adapter = createMysqlReferralAdapter(pool);
 */
export function createMysqlReferralAdapter(pool) {
  if (!pool || typeof pool.execute !== "function" || typeof pool.getConnection !== "function") {
    throw new TypeError("pool must be a mysql2/promise pool");
  }
  return {
    dialect: "mysql",
    forUpdateClause: " FOR UPDATE",
    quoteIdentifier: (value, label) => quoteIdentifier(value, "mysql", label),
    async query(executor, sql, values = []) {
      const target = executor ?? pool;
      const [rows] = await target.execute(sql, values);
      return rows;
    },
    async execute(executor, sql, values = []) {
      const target = executor ?? pool;
      const [result] = await target.execute(sql, values);
      return Number(result.affectedRows ?? 0);
    },
    async transaction(work, executor) {
      if (executor) return work(executor);
      const connection = await pool.getConnection();
      try {
        await connection.beginTransaction();
        try {
          const result = await work(connection);
          await connection.commit();
          return result;
        } catch (error) {
          await connection.rollback();
          throw error;
        }
      } finally {
        connection.release();
      }
    },
  };
}
