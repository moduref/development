import test from "node:test";
import assert from "node:assert/strict";
import {
  createMysqlReferralAdapter,
  createPostgresReferralAdapter,
  createSqliteReferralAdapter,
} from "../src/adapters/index.js";

test("createMysqlReferralAdapter validation", () => {
  assert.throws(() => createMysqlReferralAdapter(null), /must be a mysql2\/promise pool/);
  assert.throws(() => createMysqlReferralAdapter({}), /must be a mysql2\/promise pool/);

  const mockPool = {
    async execute() { return [[]]; },
    async getConnection() { return {}; },
  };
  const adapter = createMysqlReferralAdapter(mockPool);
  assert.equal(adapter.dialect, "mysql");
  assert.equal(adapter.forUpdateClause, " FOR UPDATE");
  assert.equal(adapter.quoteIdentifier("col"), "`col`");
});

test("createPostgresReferralAdapter validation & placeholder conversion", async () => {
  assert.throws(() => createPostgresReferralAdapter(null), /must be a node-postgres Pool/);

  let capturedQuery = null;
  let capturedValues = null;
  const mockPool = {
    async query(sql, values) {
      capturedQuery = sql;
      capturedValues = values;
      return { rows: [{ id: 1 }], rowCount: 1 };
    },
    async connect() { return {}; },
  };

  const adapter = createPostgresReferralAdapter(mockPool);
  assert.equal(adapter.dialect, "postgres");
  assert.equal(adapter.quoteIdentifier("col"), '"col"');

  // Query placeholder conversion test: ? -> $1, $2
  const rows = await adapter.query(null, "SELECT * FROM users WHERE id = ? AND status = ?", ["uuid-1", "active"]);
  assert.equal(capturedQuery, "SELECT * FROM users WHERE id = $1 AND status = $2");
  assert.deepEqual(capturedValues, ["uuid-1", "active"]);
  assert.deepEqual(rows, [{ id: 1 }]);

  // Execute placeholder conversion test
  const count = await adapter.execute(null, "UPDATE users SET status = ? WHERE id = ?", ["disabled", "uuid-1"]);
  assert.equal(capturedQuery, "UPDATE users SET status = $1 WHERE id = $2");
  assert.equal(count, 1);
});

test("createSqliteReferralAdapter validation", () => {
  assert.throws(() => createSqliteReferralAdapter(null), /better-sqlite3/);
  assert.throws(() => createSqliteReferralAdapter({}), /better-sqlite3/);

  const mockDb = {
    prepare() {},
    exec() {},
  };
  const adapter = createSqliteReferralAdapter(mockDb);
  assert.equal(adapter.dialect, "sqlite");
  assert.equal(adapter.forUpdateClause, "");
  assert.equal(adapter.quoteIdentifier("col"), '"col"');
});
