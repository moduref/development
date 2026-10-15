import { DEFAULT_COLUMNS } from "./constants.js";
import { assertUuid, quoteTableName } from "./validation.js";
import {
  createMysqlReferralAdapter,
  createPostgresReferralAdapter,
  createSqliteReferralAdapter,
} from "./adapters/index.js";

/**
 * @typedef {Object} ReferralStoreColumns
 * @property {string} [uuid="uuid"] - Nama kolom UUID user.
 * @property {string} [username="username"] - Nama kolom username.
 * @property {string} [referralPoints="referral_poin"] - Nama kolom total akumulasi poin referral.
 * @property {string} [referralAttribution="referral_attribution"] - Nama kolom username pengundang (attribution).
 */

/**
 * @typedef {Object} ReferralStoreOptions
 * @property {import("./adapters/index.js").ReferralAdapter} adapter - Instance database adapter.
 * @property {string} [tableName="users"] - Nama tabel pengguna (bisa format schema-qualified misal "public.users").
 * @property {ReferralStoreColumns} [columns] - Konfigurasi pemetaan nama kolom kustom.
 */

/**
 * @typedef {Object} AttachOptions
 * @property {string} userUuid - UUID user baru yang mendaftar.
 * @property {string} referrerUuid - UUID user pengundang yang membagikan link.
 * @property {any} [executor] - Koneksi transaksi aktif database jika ingin menyatu dengan transaksi registrasi aplikasi.
 */

/**
 * @typedef {Object} AttachResult
 * @property {boolean} attached - `true` jika penautan referral dan penambahan poin berhasil, `false` jika gagal.
 * @property {"self_referral" | "referrer_not_found" | "user_not_found" | "already_referred" | "no_referral"} [reason] - Alasan penolakan jika gagal.
 * @property {string} [referrerUuid] - UUID pengundang jika berhasil.
 * @property {string} [referrerUsername] - Username pengundang yang disimpan ke kolom attribution jika berhasil.
 */

/**
 * @typedef {Object} ReferralSummary
 * @property {number} referralPoints - Jumlah total poin kumulatif referral pengguna.
 * @property {string | null} referralAttribution - Username pengundang saat pendaftaran, atau `null` jika tidak ada.
 */

/**
 * @typedef {Object} ReferralStore
 * @property {(executor?: any) => Promise<void>} initialize - Memvalidasi keberadaan tabel dan keempat kolom referral.
 * @property {(options: AttachOptions) => Promise<AttachResult>} attach - Menautkan referral dan menaikkan poin (+1) secara atomik.
 * @property {(userUuid: string, executor?: any) => Promise<ReferralSummary | null>} getReferralSummary - Mengambil ringkasan poin dan pengundang user.
 */

/**
 * Membuat instance referral store generik menggunakan database adapter yang diberikan.
 *
 * @param {ReferralStoreOptions} [options] - Opsi konfigurasi store.
 * @returns {ReferralStore} Instance referral store.
 * @throws {TypeError} Jika adapter tidak memiliki method interface yang disyaratkan.
 * @example
 * const store = createReferralStore({
 *   adapter: createMysqlReferralAdapter(pool),
 *   tableName: "users",
 *   columns: { referralPoints: "points" }
 * });
 * await store.initialize();
 */
export function createReferralStore({
  adapter,
  tableName = "users",
  columns = {},
} = {}) {
  if (!adapter || typeof adapter.query !== "function" || typeof adapter.execute !== "function"
      || typeof adapter.transaction !== "function" || typeof adapter.quoteIdentifier !== "function") {
    throw new TypeError("adapter must provide query, execute, transaction, and quoteIdentifier methods");
  }

  const configuredColumns = { ...DEFAULT_COLUMNS, ...columns };
  const table = quoteTableName(tableName, adapter.dialect === "mysql" ? "mysql" : "standard");
  const uuidColumn = adapter.quoteIdentifier(configuredColumns.uuid, "columns.uuid");
  const usernameColumn = adapter.quoteIdentifier(configuredColumns.username, "columns.username");
  const pointsColumn = adapter.quoteIdentifier(configuredColumns.referralPoints, "columns.referralPoints");
  const attributionColumn = adapter.quoteIdentifier(configuredColumns.referralAttribution, "columns.referralAttribution");

  const selectedFields = `${uuidColumn} AS uuid, ${usernameColumn} AS username, `
    + `${pointsColumn} AS referral_points, ${attributionColumn} AS referral_attribution`;

  return {
    /**
     * Memvalidasi bahwa tabel dan keempat kolom yang dikonfigurasi ada di database.
     * Disarankan dipanggil saat server aplikasi pertama kali mulai (*startup*).
     *
     * @param {any} [executor] - Koneksi database opsional.
     * @returns {Promise<void>}
     */
    async initialize(executor) {
      await adapter.query(executor, `SELECT ${selectedFields} FROM ${table} WHERE 1 = 0`);
    },

    /**
     * Menautkan user baru ke pengundangnya dan menaikkan total poin pengundang (+1) secara atomik.
     * Menolak referral diri sendiri, pengundang yang tidak ada, atau user yang sudah memiliki attribution.
     *
     * @param {AttachOptions} [options]
     * @returns {Promise<AttachResult>}
     */
    async attach({ userUuid, referrerUuid, executor } = {}) {
      const referredUuid = assertUuid(userUuid, "userUuid");
      const inviterUuid = assertUuid(referrerUuid, "referrerUuid");
      if (referredUuid === inviterUuid) return { attached: false, reason: "self_referral" };

      return adapter.transaction(async (transaction) => {
        const lockClause = adapter.forUpdateClause ?? "";
        const inviterRows = await adapter.query(
          transaction,
          `SELECT ${uuidColumn} AS uuid, ${usernameColumn} AS username
           FROM ${table} WHERE ${uuidColumn} = ?${lockClause}`,
          [inviterUuid],
        );
        const inviter = inviterRows[0];
        if (!inviter) return { attached: false, reason: "referrer_not_found" };

        const inviteeRows = await adapter.query(
          transaction,
          `SELECT ${uuidColumn} AS uuid, ${attributionColumn} AS referral_attribution
           FROM ${table} WHERE ${uuidColumn} = ?${lockClause}`,
          [referredUuid],
        );
        const invitee = inviteeRows[0];
        if (!invitee) return { attached: false, reason: "user_not_found" };
        if (invitee.referral_attribution !== null && invitee.referral_attribution !== "") {
          return { attached: false, reason: "already_referred" };
        }

        const linked = await adapter.execute(
          transaction,
          `UPDATE ${table} SET ${attributionColumn} = ?
           WHERE ${uuidColumn} = ? AND (${attributionColumn} IS NULL OR ${attributionColumn} = '')`,
          [inviter.username, referredUuid],
        );
        if (linked !== 1) return { attached: false, reason: "already_referred" };

        const incremented = await adapter.execute(
          transaction,
          `UPDATE ${table} SET ${pointsColumn} = COALESCE(${pointsColumn}, 0) + 1
           WHERE ${uuidColumn} = ?`,
          [inviterUuid],
        );
        if (incremented !== 1) throw new Error("Could not increment the referrer's referral_poin");

        return { attached: true, referrerUuid: inviterUuid, referrerUsername: inviter.username };
      }, executor);
    },

    /**
     * Mengambil ringkasan poin referral dan username pengundang untuk suatu UUID pengguna.
     *
     * @param {string} userUuid - UUID user yang diperiksa.
     * @param {any} [executor] - Koneksi query opsional.
     * @returns {Promise<ReferralSummary | null>} Data ringkasan referral atau `null` jika user tidak ditemukan.
     */
    async getReferralSummary(userUuid, executor) {
      const uuid = assertUuid(userUuid, "userUuid");
      const [row] = await adapter.query(
        executor,
        `SELECT ${pointsColumn} AS referral_points, ${attributionColumn} AS referral_attribution
         FROM ${table} WHERE ${uuidColumn} = ? LIMIT 1`,
        [uuid],
      );
      if (!row) return null;
      return {
        referralPoints: Number(row.referral_points ?? 0),
        referralAttribution: row.referral_attribution ?? null,
      };
    },
  };
}

/**
 * Helper langsung untuk membuat ReferralStore dengan adapter MySQL (`mysql2/promise`).
 *
 * @param {Object} options
 * @param {any} options.pool - Pool koneksi dari `mysql2/promise`.
 * @param {string} [options.tableName="users"] - Nama tabel pengguna.
 * @param {ReferralStoreColumns} [options.columns] - Pemetaan nama kolom kustom.
 * @returns {ReferralStore}
 * @example
 * const referrals = createMysqlReferralStore({ pool, tableName: "users" });
 * await referrals.initialize();
 */
export function createMysqlReferralStore({ pool, tableName, columns } = {}) {
  return createReferralStore({ adapter: createMysqlReferralAdapter(pool), tableName, columns });
}

/**
 * Helper langsung untuk membuat ReferralStore dengan adapter PostgreSQL (`pg`).
 *
 * @param {Object} options
 * @param {any} options.pool - Pool koneksi dari `pg`.
 * @param {string} [options.tableName="users"] - Nama tabel pengguna.
 * @param {ReferralStoreColumns} [options.columns] - Pemetaan nama kolom kustom.
 * @returns {ReferralStore}
 * @example
 * const referrals = createPostgresReferralStore({ pool, tableName: "public.users" });
 * await referrals.initialize();
 */
export function createPostgresReferralStore({ pool, tableName, columns } = {}) {
  return createReferralStore({ adapter: createPostgresReferralAdapter(pool), tableName, columns });
}

/**
 * Helper langsung untuk membuat ReferralStore dengan adapter SQLite (`better-sqlite3`).
 *
 * @param {Object} options
 * @param {any} options.database - Instance database yang telah dibuka dari `better-sqlite3`.
 * @param {string} [options.tableName="users"] - Nama tabel pengguna.
 * @param {ReferralStoreColumns} [options.columns] - Pemetaan nama kolom kustom.
 * @returns {ReferralStore}
 * @example
 * const referrals = createSqliteReferralStore({ database, tableName: "users" });
 * await referrals.initialize();
 */
export function createSqliteReferralStore({ database, tableName, columns } = {}) {
  return createReferralStore({ adapter: createSqliteReferralAdapter(database), tableName, columns });
}
