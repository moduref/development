import test from "node:test";
import assert from "node:assert/strict";
import { createReferralStore } from "../src/store.js";

const USER_A_UUID = "11111111-1111-1111-1111-111111111111";
const USER_B_UUID = "22222222-2222-2222-2222-222222222222";

function createMockAdapter(initialUsers = []) {
  const users = new Map(initialUsers.map((u) => [u.uuid, { ...u }]));

  return {
    dialect: "standard",
    forUpdateClause: " FOR UPDATE",
    quoteIdentifier: (val) => `"${val}"`,
    async transaction(work, executor) {
      return work(executor ?? this);
    },
    async query(executor, sql, values = []) {
      // Check for initialize query: WHERE 1 = 0
      if (sql.includes("1 = 0")) return [];

      // Check for user lookup: WHERE "uuid" = ?
      if (sql.includes('WHERE "uuid" = ?')) {
        const targetUuid = values[0];
        const user = users.get(targetUuid);
        if (!user) return [];
        return [{
          uuid: user.uuid,
          username: user.username,
          referral_points: user.referral_points ?? 0,
          referral_attribution: user.referral_attribution ?? null,
        }];
      }
      return [];
    },
    async execute(executor, sql, values = []) {
      // Attribution update: UPDATE "users" SET "referral_attribution" = ? WHERE "uuid" = ? ...
      if (sql.includes('SET "referral_attribution" = ?')) {
        const [attribution, targetUuid] = values;
        const user = users.get(targetUuid);
        if (!user) return 0;
        if (user.referral_attribution !== null && user.referral_attribution !== "") return 0;
        user.referral_attribution = attribution;
        return 1;
      }

      // Points increment: UPDATE "users" SET "referral_poin" = COALESCE(...) + 1 WHERE "uuid" = ?
      if (sql.includes('SET "referral_poin" =')) {
        const [targetUuid] = values;
        const user = users.get(targetUuid);
        if (!user) return 0;
        user.referral_points = (user.referral_points ?? 0) + 1;
        return 1;
      }

      return 0;
    },
    _users: users,
  };
}

test("createReferralStore - initialize", async () => {
  const adapter = createMockAdapter();
  const store = createReferralStore({ adapter });
  await assert.doesNotReject(() => store.initialize());
});

test("createReferralStore - self referral prevention", async () => {
  const adapter = createMockAdapter([{ uuid: USER_A_UUID, username: "alice", referral_points: 0 }]);
  const store = createReferralStore({ adapter });

  const result = await store.attach({ userUuid: USER_A_UUID, referrerUuid: USER_A_UUID });
  assert.deepEqual(result, { attached: false, reason: "self_referral" });
});

test("createReferralStore - referrer not found", async () => {
  const adapter = createMockAdapter([{ uuid: USER_B_UUID, username: "bob", referral_points: 0 }]);
  const store = createReferralStore({ adapter });

  const result = await store.attach({ userUuid: USER_B_UUID, referrerUuid: USER_A_UUID });
  assert.deepEqual(result, { attached: false, reason: "referrer_not_found" });
});

test("createReferralStore - user not found", async () => {
  const adapter = createMockAdapter([{ uuid: USER_A_UUID, username: "alice", referral_points: 0 }]);
  const store = createReferralStore({ adapter });

  const result = await store.attach({ userUuid: USER_B_UUID, referrerUuid: USER_A_UUID });
  assert.deepEqual(result, { attached: false, reason: "user_not_found" });
});

test("createReferralStore - already referred user", async () => {
  const adapter = createMockAdapter([
    { uuid: USER_A_UUID, username: "alice", referral_points: 0 },
    { uuid: USER_B_UUID, username: "bob", referral_attribution: "charlie" },
  ]);
  const store = createReferralStore({ adapter });

  const result = await store.attach({ userUuid: USER_B_UUID, referrerUuid: USER_A_UUID });
  assert.deepEqual(result, { attached: false, reason: "already_referred" });
});

test("createReferralStore - successful attachment and points increment", async () => {
  const adapter = createMockAdapter([
    { uuid: USER_A_UUID, username: "alice", referral_points: 2, referral_attribution: null },
    { uuid: USER_B_UUID, username: "bob", referral_points: 0, referral_attribution: null },
  ]);
  const store = createReferralStore({ adapter });

  const result = await store.attach({ userUuid: USER_B_UUID, referrerUuid: USER_A_UUID });
  assert.deepEqual(result, {
    attached: true,
    referrerUuid: USER_A_UUID,
    referrerUsername: "alice",
  });

  // Verify DB state
  const alice = adapter._users.get(USER_A_UUID);
  const bob = adapter._users.get(USER_B_UUID);
  assert.equal(alice.referral_points, 3, "Alice's referral points should increment by 1");
  assert.equal(bob.referral_attribution, "alice", "Bob's attribution should be Alice's username");

  // Verify getReferralSummary
  const aliceSummary = await store.getReferralSummary(USER_A_UUID);
  assert.deepEqual(aliceSummary, { referralPoints: 3, referralAttribution: null });

  const bobSummary = await store.getReferralSummary(USER_B_UUID);
  assert.deepEqual(bobSummary, { referralPoints: 0, referralAttribution: "alice" });
});
