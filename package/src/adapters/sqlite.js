import { quoteIdentifier } from "../validation.js";

/**
 * Membuat adapter transaksi referral untuk driver `better-sqlite3`.
 * Menggunakan internal mutex lock (`withDatabaseLock`) dan transaksi `BEGIN IMMEDIATE`.
 *
 * @param {any} database - Instance Database yang telah dibuka dari `better-sqlite3` (misal: `new Database("app.db")`).
 * @returns {import("./index.js").ReferralAdapter} Instance adapter SQLite.
 * @throws {TypeError} Jika database bukan instance better-sqlite3 yang valid.
 * @example
 * import Database from "better-sqlite3";
 * const db = new Database("app.db");
 * const adapter = createSqliteReferralAdapter(db);
 */
export function createSqliteReferralAdapter(database) {
  if (!database || typeof database.prepare !== "function" || typeof database.exec !== "function") {
    throw new TypeError("database must be a better-sqlite3 Database instance");
  }

  const transactionMarker = Symbol("referral sqlite transaction");
  const transactionExecutor = { database, [transactionMarker]: true };
  let operationQueue = Promise.resolve();

  async function withDatabaseLock(work) {
    const previous = operationQueue;
    let release;
    operationQueue = new Promise((resolve) => { release = resolve; });
    await previous;
    try {
      return await work();
    } finally {
      release();
    }
  }

  function queryNow(sql, values) {
    return database.prepare(sql).all(...values);
  }

  function executeNow(sql, values) {
    return Number(database.prepare(sql).run(...values).changes ?? 0);
  }

  return {
    dialect: "sqlite",
    forUpdateClause: "",
    quoteIdentifier: (value, label) => quoteIdentifier(value, "sqlite", label),
    query(executor, sql, values = []) {
      if (executor?.[transactionMarker]) return queryNow(sql, values);
      return withDatabaseLock(() => queryNow(sql, values));
    },
    execute(executor, sql, values = []) {
      if (executor?.[transactionMarker]) return executeNow(sql, values);
      return withDatabaseLock(() => executeNow(sql, values));
    },
    async transaction(work, executor) {
      if (executor) return work(executor);
      return withDatabaseLock(async () => {
        let started = false;
        try {
          database.exec("BEGIN IMMEDIATE");
          started = true;
          const result = await work(transactionExecutor);
          database.exec("COMMIT");
          started = false;
          return result;
        } catch (error) {
          if (started) database.exec("ROLLBACK");
          throw error;
        }
      });
    },
  };
}
