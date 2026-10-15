/**
 * @module moduref
 * Package referral Express berbasis UUID dengan adapter untuk MySQL/MariaDB, PostgreSQL, dan SQLite.
 */

export {
  attachReferralFromRequest,
  clearReferralCookie,
  createReferralRouter,
  createReferralUrl,
  getReferralUuid,
} from "./http.js";

export {
  createMysqlReferralStore,
  createPostgresReferralStore,
  createReferralStore,
  createSqliteReferralStore,
} from "./store.js";

export {
  createMysqlReferralAdapter,
  createPostgresReferralAdapter,
  createSqliteReferralAdapter,
} from "./adapters/index.js";
