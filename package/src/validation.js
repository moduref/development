import {
  DEFAULT_COOKIE_NAME,
  IDENTIFIER_PATTERN,
  UUID_PATTERN,
} from "./constants.js";

/**
 * @typedef {Object} CookieSettings
 * @property {string} cookieName - Nama cookie yang divalidasi.
 * @property {string} cookiePath - Path cakupan cookie (misal: "/").
 * @property {"lax" | "strict" | "none"} cookieSameSite - Kebijakan SameSite cookie.
 * @property {boolean} cookieSecure - Apakah cookie hanya dikirim via koneksi HTTPS.
 */

/**
 * Memvalidasi dan menormalisasi string UUID menjadi huruf kecil (lowercase).
 * Mengembalikan `null` jika string tidak sesuai format UUID.
 *
 * @param {unknown} value - Nilai yang ingin diperiksa sebagai UUID.
 * @returns {string | null} UUID terformat huruf kecil atau null jika tidak valid.
 * @example
 * normalizeUuid("123E4567-E89B-12D3-A456-426614174000"); // "123e4567-e89b-12d3-a456-426614174000"
 * normalizeUuid("invalid-id"); // null
 */
export function normalizeUuid(value) {
  if (typeof value !== "string" || !UUID_PATTERN.test(value)) return null;
  return value.toLowerCase();
}

/**
 * Memastikan sebuah nilai adalah UUID valid. Melempar `TypeError` jika tidak valid.
 *
 * @param {unknown} value - Nilai yang divalidasi.
 * @param {string} label - Nama label field untuk pesan error.
 * @returns {string} UUID lowercase yang valid.
 * @throws {TypeError} Jika nilai bukan string UUID valid.
 */
export function assertUuid(value, label) {
  const uuid = normalizeUuid(value);
  if (!uuid) throw new TypeError(`${label} must be a UUID string`);
  return uuid;
}

/**
 * Memvalidasi path lokal internal aplikasi untuk mencegah kerentanan Open Redirect atau Path Traversal.
 *
 * @param {unknown} value - Path lokal yang divalidasi (harus diawali satu `/`).
 * @param {string} label - Nama label untuk pesan error.
 * @param {Object} [options]
 * @param {boolean} [options.allowQuery=false] - Izinkan query string (misal: /register?step=2).
 * @returns {string} Path lokal yang telah divalidasi.
 * @throws {TypeError} Jika path tidak diawali `/`, berisi double slash `//`, backslash `\`, atau domain luar.
 */
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

/**
 * Memvalidasi nama cookie sesuai karakter token RFC 6265.
 *
 * @param {unknown} value - Nama cookie yang diuji.
 * @returns {string} Nama cookie yang valid.
 * @throws {TypeError} Jika nama cookie mengandung karakter dilarang.
 */
export function validateCookieName(value) {
  if (typeof value !== "string" || !/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/.test(value)) {
    throw new TypeError("cookieName must be a valid cookie name");
  }
  return value;
}

/**
 * Menyelesaikan dan memvalidasi konfigurasi cookie referral.
 *
 * @param {Object} [options]
 * @param {string} [options.cookieName] - Nama cookie referral.
 * @param {string} [options.cookiePath] - Scope path cookie.
 * @param {"lax" | "strict" | "none" | string} [options.cookieSameSite] - Kebijakan SameSite.
 * @param {boolean} [options.cookieSecure] - Apakah cookie membutuhkan HTTPS.
 * @returns {CookieSettings} Objek konfigurasi cookie yang telah divalidasi.
 * @throws {TypeError} Jika konfigurasi melanggar aturan SameSite atau Secure.
 */
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
    cookieSameSite: /** @type {"lax" | "strict" | "none"} */ (String(cookieSameSite).toLowerCase()),
    cookieSecure: Boolean(cookieSecure),
  };
}

/**
 * Melakukan quoting/escaping identifier SQL (kolom/tabel) sesuai dialek database untuk mencegah SQL Injection.
 *
 * @param {unknown} value - Nama identifier (nama kolom atau tabel).
 * @param {"mysql" | "postgres" | "sqlite" | "standard"} quote - Dialek database pengutip.
 * @param {string} [label="identifier"] - Label untuk pesan error.
 * @returns {string} Identifier yang sudah di-quote (contoh: "`col`" di MySQL atau '"col"' di Postgres/SQLite).
 * @throws {TypeError} Jika nama identifier mengandung karakter tidak valid.
 */
export function quoteIdentifier(value, quote, label = "identifier") {
  if (typeof value !== "string" || !IDENTIFIER_PATTERN.test(value)) {
    throw new TypeError(`${label} must be a simple SQL identifier`);
  }
  if (quote === "mysql") return `\`${value}\``;
  return `"${value}"`;
}

/**
 * Melakukan quoting pada nama tabel yang mungkin memiliki schema-qualification (contoh: "public.users").
 *
 * @param {unknown} value - Nama tabel (bisa berupa "users" atau "schema.users").
 * @param {"mysql" | "postgres" | "sqlite" | "standard"} quote - Dialek database pengutip.
 * @returns {string} Nama tabel yang di-quote dengan benar.
 * @throws {TypeError} Jika struktur nama tabel tidak valid.
 */
export function quoteTableName(value, quote) {
  if (typeof value !== "string" || !value.split(".").every((part) => IDENTIFIER_PATTERN.test(part))) {
    throw new TypeError("tableName must be a table identifier, optionally schema-qualified");
  }
  return value.split(".").map((part) => quoteIdentifier(part, quote, "tableName")).join(".");
}
