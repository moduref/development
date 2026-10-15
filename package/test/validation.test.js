import test from "node:test";
import assert from "node:assert/strict";
import {
  assertUuid,
  normalizeUuid,
  quoteIdentifier,
  quoteTableName,
  resolveCookieSettings,
  validateCookieName,
  validateLocalPath,
} from "../src/validation.js";

test("normalizeUuid", () => {
  const validUuid = "123e4567-e89b-12d3-a456-426614174000";
  assert.equal(normalizeUuid(validUuid), validUuid);
  assert.equal(normalizeUuid(validUuid.toUpperCase()), validUuid);

  assert.equal(normalizeUuid("not-a-uuid"), null);
  assert.equal(normalizeUuid(""), null);
  assert.equal(normalizeUuid(null), null);
  assert.equal(normalizeUuid(undefined), null);
  assert.equal(normalizeUuid(12345), null);
});

test("assertUuid", () => {
  const validUuid = "123e4567-e89b-12d3-a456-426614174000";
  assert.equal(assertUuid(validUuid, "testUuid"), validUuid);

  assert.throws(
    () => assertUuid("invalid-uuid", "userUuid"),
    { name: "TypeError", message: "userUuid must be a UUID string" },
  );
});

test("validateLocalPath", () => {
  assert.equal(validateLocalPath("/register", "path"), "/register");
  assert.equal(validateLocalPath("/auth/callback", "path"), "/auth/callback");

  // With allowQuery
  assert.equal(
    validateLocalPath("/register?step=2", "registerPath", { allowQuery: true }),
    "/register?step=2",
  );

  // Without allowQuery, queries should throw
  assert.throws(() => validateLocalPath("/register?step=2", "path"));

  // Disallowed paths (open redirect or traversal risks)
  assert.throws(() => validateLocalPath("https://evil.com", "path"));
  assert.throws(() => validateLocalPath("//evil.com", "path"));
  assert.throws(() => validateLocalPath("/\\evil.com", "path"));
  assert.throws(() => validateLocalPath("register", "path"));
  assert.throws(() => validateLocalPath(null, "path"));
});

test("validateCookieName", () => {
  assert.equal(validateCookieName("referral_uuid"), "referral_uuid");
  assert.equal(validateCookieName("custom-cookie.1"), "custom-cookie.1");

  assert.throws(() => validateCookieName("cookie with spaces"));
  assert.throws(() => validateCookieName("cookie;bad"));
  assert.throws(() => validateCookieName(""));
});

test("resolveCookieSettings", () => {
  const defaults = resolveCookieSettings();
  assert.equal(defaults.cookieName, "referral_uuid");
  assert.equal(defaults.cookiePath, "/");
  assert.equal(defaults.cookieSameSite, "lax");

  const custom = resolveCookieSettings({
    cookieName: "my_ref",
    cookiePath: "/app",
    cookieSameSite: "strict",
    cookieSecure: true,
  });
  assert.equal(custom.cookieName, "my_ref");
  assert.equal(custom.cookiePath, "/app");
  assert.equal(custom.cookieSameSite, "strict");
  assert.equal(custom.cookieSecure, true);

  // sameSite: "none" requires cookieSecure: true
  assert.throws(
    () => resolveCookieSettings({ cookieSameSite: "none", cookieSecure: false }),
    /cookieSameSite "none" requires cookieSecure: true/,
  );
  assert.doesNotThrow(
    () => resolveCookieSettings({ cookieSameSite: "none", cookieSecure: true }),
  );

  // Invalid sameSite
  assert.throws(() => resolveCookieSettings({ cookieSameSite: "invalid" }));
  // Invalid cookiePath
  assert.throws(() => resolveCookieSettings({ cookiePath: "no-slash" }));
});

test("quoteIdentifier", () => {
  assert.equal(quoteIdentifier("users", "mysql"), "`users`");
  assert.equal(quoteIdentifier("users", "standard"), '"users"');
  assert.equal(quoteIdentifier("referral_poin", "mysql"), "`referral_poin`");

  // SQL injection prevention: invalid characters must throw
  assert.throws(() => quoteIdentifier("users; DROP TABLE users;--", "mysql"));
  assert.throws(() => quoteIdentifier("users`name", "mysql"));
  assert.throws(() => quoteIdentifier("users name", "standard"));
});

test("quoteTableName", () => {
  assert.equal(quoteTableName("accounts", "mysql"), "`accounts`");
  assert.equal(quoteTableName("public.accounts", "standard"), '"public"."accounts"');

  assert.throws(() => quoteTableName("accounts; DROP TABLE accounts;", "mysql"));
});
