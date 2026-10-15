import {
  DEFAULT_COOKIE_NAME,
  IDENTIFIER_PATTERN,
  UUID_PATTERN,
} from "./constants.js";

export function normalizeUuid(value) {
  if (typeof value !== "string" || !UUID_PATTERN.test(value)) return null;
  return value.toLowerCase();
}

export function assertUuid(value, label) {
  const uuid = normalizeUuid(value);
  if (!uuid) throw new TypeError(`${label} must be a UUID string`);
  return uuid;
}

export function validateLocalPath(value, label, { allowQuery = false } = {}) {
  if (typeof value !== "string" || !value.startsWith("/") || value.startsWith("//") || value.includes("\\")) {
    throw new TypeError(`${label} must be a local path beginning with one /`);
  }
  const parsed = new URL(value, "http://referral.local");
  if (parsed.origin !== "http://referral.local" || parsed.hash || (!allowQuery && parsed.search)) {
    throw new TypeError(`${label} must be a local path${allowQuery ? "" : " without a query or hash"}`);
  }
  return value;
}

export function validateCookieName(value) {
  if (typeof value !== "string" || !/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/.test(value)) {
    throw new TypeError("cookieName must be a valid cookie name");
  }
  return value;
}

export function resolveCookieSettings({
  cookieName = DEFAULT_COOKIE_NAME,
  cookiePath = "/",
  cookieSameSite = "lax",
  cookieSecure = process.env.NODE_ENV === "production",
} = {}) {
  if (typeof cookiePath !== "string" || !cookiePath.startsWith("/")) {
    throw new TypeError("cookiePath must start with /");
  }
  if (!["lax", "strict", "none"].includes(String(cookieSameSite).toLowerCase())) {
    throw new TypeError('cookieSameSite must be "lax", "strict", or "none"');
  }
  if (String(cookieSameSite).toLowerCase() === "none" && !cookieSecure) {
    throw new TypeError('cookieSameSite "none" requires cookieSecure: true');
  }

  return {
    cookieName: validateCookieName(cookieName),
    cookiePath,
    cookieSameSite: String(cookieSameSite).toLowerCase(),
    cookieSecure: Boolean(cookieSecure),
  };
}

export function quoteIdentifier(value, quote, label = "identifier") {
  if (typeof value !== "string" || !IDENTIFIER_PATTERN.test(value)) {
    throw new TypeError(`${label} must be a simple SQL identifier`);
  }
  if (quote === "mysql") return `\`${value}\``;
  return `"${value}"`;
}

export function quoteTableName(value, quote) {
  if (typeof value !== "string" || !value.split(".").every((part) => IDENTIFIER_PATTERN.test(part))) {
    throw new TypeError("tableName must be a table identifier, optionally schema-qualified");
  }
  return value.split(".").map((part) => quoteIdentifier(part, quote, "tableName")).join(".");
}
