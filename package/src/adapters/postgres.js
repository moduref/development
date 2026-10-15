import { quoteIdentifier } from "../validation.js";

/**
 * Membuat adapter transaksi referral untuk driver PostgreSQL (`pg` / `node-postgres`).
 *
 * @param {any} pool - Instance `Pool` dari package `pg` (misal: `new Pool({ ... })`).
 * @returns {import("./index.js").ReferralAdapter} Instance adapter PostgreSQL.
 * @throws {TypeError} Jika pool tidak memiliki method `.query` atau `.connect`.
 * @example
 * import { Pool } from "pg";
 * const pool = new Pool({ connectionString: process.env.DATABASE_URL });
 * const adapter = createPostgresReferralAdapter(pool);
 */
export function createPostgresReferralAdapter(pool) {
  if (!pool || typeof pool.query !== "function" || typeof pool.connect !== "function") {
    throw new TypeError("pool must be a node-postgres Pool");
  }
  function toPostgresPlaceholders(sql) {
    let index = 0;
    return sql.replace(/\?/g, () => `$${++index}`);
  }

  return {
    dialect: "postgres",
    forUpdateClause: " FOR UPDATE",
    quoteIdentifier: (value, label) => quoteIdentifier(value, "postgres", label),
    async query(executor, sql, values = []) {
      const target = executor ?? pool;
      const result = await target.query(toPostgresPlaceholders(sql), values);
      return result.rows;
    },
    async execute(executor, sql, values = []) {
      const target = executor ?? pool;
      const result = await target.query(toPostgresPlaceholders(sql), values);
      return Number(result.rowCount ?? 0);
    },
    async transaction(work, executor) {
      if (executor) return work(executor);
      const client = await pool.connect();
      try {
        await client.query("BEGIN");
        try {
          const result = await work(client);
          await client.query("COMMIT");
          return result;
        } catch (error) {
          await client.query("ROLLBACK");
          throw error;
        }
      } finally {
        client.release();
      }
    },
  };
}
