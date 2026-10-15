import { DEFAULT_COLUMNS } from "./constants.js";
import { assertUuid, quoteTableName } from "./validation.js";
import {
  createMysqlReferralAdapter,
  createPostgresReferralAdapter,
  createSqliteReferralAdapter,
} from "./adapters/index.js";

/**
 * Create the reusable referral service. The existing user table must have
 * UUID, username, points, and attribution columns; unrelated columns remain
 * untouched.
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
    /** Validate that the configured table and all four columns exist. */
    async initialize(executor) {
      await adapter.query(executor, `SELECT ${selectedFields} FROM ${table} WHERE 1 = 0`);
    },

    /**
     * Attach the new user to one referrer and increment the referrer's
     * lifetime counter exactly once. It creates a transaction unless an
     * active registration transaction is supplied as `executor`.
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

export function createMysqlReferralStore({ pool, tableName, columns } = {}) {
  return createReferralStore({ adapter: createMysqlReferralAdapter(pool), tableName, columns });
}

export function createPostgresReferralStore({ pool, tableName, columns } = {}) {
  return createReferralStore({ adapter: createPostgresReferralAdapter(pool), tableName, columns });
}

export function createSqliteReferralStore({ database, tableName, columns } = {}) {
  return createReferralStore({ adapter: createSqliteReferralAdapter(database), tableName, columns });
}
