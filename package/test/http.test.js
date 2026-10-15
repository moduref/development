import test from "node:test";
import assert from "node:assert/strict";
import {
  attachReferralFromRequest,
  clearReferralCookie,
  createReferralRouter,
  createReferralUrl,
  getReferralUuid,
} from "../src/http.js";

const VALID_UUID_1 = "123e4567-e89b-12d3-a456-426614174000";
const VALID_UUID_2 = "987fcdeb-51a2-43d7-9012-3456789abcde";

test("createReferralUrl", () => {
  const url = createReferralUrl({
    baseUrl: "https://example.com",
    userUuid: VALID_UUID_1,
  });
  assert.equal(url, `https://example.com/referral/${VALID_UUID_1}`);

  const customPathUrl = createReferralUrl({
    baseUrl: "https://example.com",
    userUuid: VALID_UUID_1,
    path: "/invite",
  });
  assert.equal(customPathUrl, `https://example.com/invite/${VALID_UUID_1}`);

  // Rejects invalid protocol or credentials
  assert.throws(() => createReferralUrl({ baseUrl: "ftp://example.com", userUuid: VALID_UUID_1 }));
  assert.throws(() => createReferralUrl({ baseUrl: "https://user:pass@example.com", userUuid: VALID_UUID_1 }));
  assert.throws(() => createReferralUrl({ baseUrl: "https://example.com", userUuid: "invalid-uuid" }));
});

test("getReferralUuid", () => {
  // Single cookie
  const req1 = { headers: { cookie: `referral_uuid=${VALID_UUID_1}` } };
  assert.equal(getReferralUuid(req1), VALID_UUID_1);

  // Multiple cookies
  const req2 = { headers: { cookie: `session=abc; referral_uuid=${VALID_UUID_1}; other=123` } };
  assert.equal(getReferralUuid(req2), VALID_UUID_1);

  // Custom cookie name
  const req3 = { headers: { cookie: `custom_ref=${VALID_UUID_2}` } };
  assert.equal(getReferralUuid(req3, { cookieName: "custom_ref" }), VALID_UUID_2);

  // Missing or empty header
  assert.equal(getReferralUuid({ headers: {} }), null);
  assert.equal(getReferralUuid(null), null);

  // Invalid uuid in cookie
  const req4 = { headers: { cookie: "referral_uuid=not-a-valid-uuid" } };
  assert.equal(getReferralUuid(req4), null);
});

test("clearReferralCookie", () => {
  let clearedName = null;
  let clearedOptions = null;
  const res = {
    clearCookie(name, options) {
      clearedName = name;
      clearedOptions = options;
    },
  };

  clearReferralCookie(res, { cookieName: "custom_cookie", cookiePath: "/app" });
  assert.equal(clearedName, "custom_cookie");
  assert.equal(clearedOptions.path, "/app");
  assert.equal(clearedOptions.httpOnly, true);
});

test("createReferralRouter route handler", () => {
  const router = createReferralRouter({ registerPath: "/daftar" });

  // Simulate GET /:referrerUuid
  const route = router.stack.find((layer) => layer.route?.path === "/:referrerUuid");
  assert.ok(route, "Route /:referrerUuid should be registered");

  const handler = route.route.stack[0].handle;

  // Case 1: valid UUID
  let setCookieCall = null;
  let redirectCall = null;
  const mockRes = {
    cookie(name, val, opts) {
      setCookieCall = { name, val, opts };
    },
    redirect(code, path) {
      redirectCall = { code, path };
    },
  };

  handler({ params: { referrerUuid: VALID_UUID_1 } }, mockRes);
  assert.equal(setCookieCall.name, "referral_uuid");
  assert.equal(setCookieCall.val, VALID_UUID_1);
  assert.equal(setCookieCall.opts.httpOnly, true);
  assert.equal(redirectCall.code, 302);
  assert.equal(redirectCall.path, "/daftar");

  // Case 2: invalid UUID -> 400
  let statusSent = null;
  let textSent = null;
  const mockResError = {
    status(code) {
      statusSent = code;
      return this;
    },
    type() {
      return this;
    },
    send(msg) {
      textSent = msg;
    },
  };
  handler({ params: { referrerUuid: "not-valid" } }, mockResError);
  assert.equal(statusSent, 400);
  assert.ok(textSent.includes("tidak valid"));
});

test("attachReferralFromRequest", async () => {
  const reqNoCookie = { headers: {} };
  const mockStore = {
    async attach() {
      return { attached: true };
    },
  };

  const res1 = await attachReferralFromRequest({
    req: reqNoCookie,
    userUuid: VALID_UUID_2,
    store: mockStore,
  });
  assert.deepEqual(res1, { attached: false, reason: "no_referral" });

  const reqWithCookie = { headers: { cookie: `referral_uuid=${VALID_UUID_1}` } };
  let attachParams = null;
  const capturingStore = {
    async attach(params) {
      attachParams = params;
      return { attached: true, referrerUuid: params.referrerUuid };
    },
  };

  const res2 = await attachReferralFromRequest({
    req: reqWithCookie,
    userUuid: VALID_UUID_2,
    store: capturingStore,
  });
  assert.equal(res2.attached, true);
  assert.equal(attachParams.userUuid, VALID_UUID_2);
  assert.equal(attachParams.referrerUuid, VALID_UUID_1);
});
