import express from "express";
import { DEFAULT_COOKIE_MAX_AGE, DEFAULT_COOKIE_NAME } from "./constants.js";
import {
  assertUuid,
  normalizeUuid,
  resolveCookieSettings,
  validateCookieName,
  validateLocalPath,
} from "./validation.js";

/**
 * @typedef {Object} CreateReferralUrlOptions
 * @property {string} baseUrl - Origin URL aplikasi (contoh: "https://example.com" atau "http://localhost:3000").
 * @property {string} userUuid - UUID pengguna aktif yang membagikan tautan.
 * @property {string} [path="/referral"] - Prefix path referral (contoh: "/referral").
 */

/**
 * @typedef {Object} CreateReferralRouterOptions
 * @property {string} [registerPath="/register"] - URL tujuan redirect setelah cookie referral berhasil disimpan.
 * @property {string} [cookieName="referral_uuid"] - Nama cookie untuk menyimpan UUID referral.
 * @property {number} [cookieMaxAge=2592000000] - Masa berlaku cookie dalam milidetik (default 30 hari).
 * @property {string} [cookiePath="/"] - Path scope cookie.
 * @property {"lax" | "strict" | "none"} [cookieSameSite="lax"] - Atribut SameSite cookie.
 * @property {boolean} [cookieSecure] - Apakah cookie hanya dikirim via HTTPS (otomatis true di production).
 */

/**
 * @typedef {Object} GetReferralUuidOptions
 * @property {string} [cookieName="referral_uuid"] - Nama cookie referral yang dicari.
 */

/**
 * @typedef {Object} ClearReferralCookieOptions
 * @property {string} [cookieName="referral_uuid"] - Nama cookie yang dihapus.
 * @property {string} [cookiePath="/"] - Scope path cookie.
 * @property {"lax" | "strict" | "none"} [cookieSameSite="lax"] - Atribut SameSite cookie.
 * @property {boolean} [cookieSecure] - Flag secure cookie.
 */

/**
 * @typedef {Object} AttachReferralFromRequestOptions
 * @property {any} req - Request object dari Express (memuat header cookies).
 * @property {string} userUuid - UUID user baru yang baru saja dibuat.
 * @property {import("./store.js").ReferralStore} store - Instance ReferralStore.
 * @property {any} [executor] - Koneksi transaksi aktif database.
 * @property {string} [cookieName="referral_uuid"] - Nama cookie referral.
 */

/**
 * Membangun URL tautan referral yang dapat dibagikan oleh pengguna berdasarkan UUID miliknya.
 *
 * @param {CreateReferralUrlOptions} options - Opsi pembuatan URL referral.
 * @returns {string} URL referral lengkap (contoh: "https://example.com/referral/<uuid>").
 * @throws {TypeError} Jika userUuid bukan UUID valid, baseUrl tidak valid, atau mengandung kredensial.
 * @example
 * const referralLink = createReferralUrl({
 *   baseUrl: "https://example.com",
 *   userUuid: loggedInUser.uuid,
 * });
 * // Output: "https://example.com/referral/123e4567-e89b-12d3-a456-426614174000"
 */
export function createReferralUrl({ baseUrl, userUuid, path = "/referral" }) {
  const uuid = assertUuid(userUuid, "userUuid");
  if (typeof baseUrl !== "string") throw new TypeError("baseUrl must be a URL string");
  validateLocalPath(path, "path");

  const base = new URL(baseUrl);
  if (!["http:", "https:"].includes(base.protocol) || base.username || base.password) {
    throw new TypeError("baseUrl must use http or https and must not contain credentials");
  }

  const prefix = path.replace(/\/+$/, "");
  return new URL(`${prefix}/${encodeURIComponent(uuid)}`, base).toString();
}

/**
 * Mengembalikan Express Router untuk rute `/:referrerUuid`.
 * Setiap kunjungan ke tautan referral valid akan menyimpan/memperbarui cookie referral (default 30 hari)
 * dan mengarahkan pengguna (`302 redirect`) ke halaman pendaftaran.
 *
 * @param {CreateReferralRouterOptions} [options] - Opsi konfigurasi router dan cookie.
 * @returns {express.Router} Express Router yang siap dipasang di aplikasi via `app.use("/referral", router)`.
 * @example
 * app.use("/referral", createReferralRouter({
 *   registerPath: "/register",
 *   cookieMaxAge: 30 * 24 * 60 * 60 * 1000,
 * }));
 */
export function createReferralRouter({
  registerPath = "/register",
  cookieName = DEFAULT_COOKIE_NAME,
  cookieMaxAge = DEFAULT_COOKIE_MAX_AGE,
  cookiePath = "/",
  cookieSameSite = "lax",
  cookieSecure = process.env.NODE_ENV === "production",
} = {}) {
  validateLocalPath(registerPath, "registerPath", { allowQuery: true });
  if (!Number.isSafeInteger(cookieMaxAge) || cookieMaxAge <= 0) {
    throw new TypeError("cookieMaxAge must be a positive integer in milliseconds");
  }

  const cookie = resolveCookieSettings({ cookieName, cookiePath, cookieSameSite, cookieSecure });
  const router = express.Router();

  router.get("/:referrerUuid", (req, res) => {
    const referrerUuid = normalizeUuid(req.params.referrerUuid);
    if (!referrerUuid) return res.status(400).type("text").send("UUID referral tidak valid.");

    res.cookie(cookie.cookieName, referrerUuid, {
      maxAge: cookieMaxAge,
      httpOnly: true,
      sameSite: cookie.cookieSameSite,
      secure: cookie.cookieSecure,
      path: cookie.cookiePath,
    });
    return res.redirect(302, registerPath);
  });

  return router;
}

/**
 * Membaca dan memvalidasi UUID referral langsung dari header `cookie` request Express.
 * Tidak memerlukan middleware pihak ketiga seperti `cookie-parser`.
 *
 * @param {any} req - Request object dari Express.
 * @param {GetReferralUuidOptions} [options]
 * @returns {string | null} UUID pengundang lowercase jika ditemukan, atau `null` jika tidak ada/tidak valid.
 * @example
 * const referrerUuid = getReferralUuid(req);
 * if (referrerUuid) {
 *   console.log("Pengguna mendaftar melalui referral:", referrerUuid);
 * }
 */
export function getReferralUuid(req, { cookieName = DEFAULT_COOKIE_NAME } = {}) {
  validateCookieName(cookieName);
  const cookieHeader = req?.headers?.cookie;
  if (typeof cookieHeader !== "string") return null;

  for (const part of cookieHeader.split(";")) {
    const separator = part.indexOf("=");
    if (separator < 0 || part.slice(0, separator).trim() !== cookieName) continue;

    let value = part.slice(separator + 1).trim();
    try {
      value = decodeURIComponent(value);
    } catch {
      return null;
    }
    return normalizeUuid(value);
  }
  return null;
}

/**
 * Menghapus cookie referral dari browser klien setelah proses registrasi berhasil di-commit.
 *
 * @param {any} res - Response object dari Express.
 * @param {ClearReferralCookieOptions} [options]
 * @returns {any}
 * @example
 * await connection.commit();
 * clearReferralCookie(res);
 */
export function clearReferralCookie(res, options = {}) {
  const cookie = resolveCookieSettings(options);
  return res.clearCookie(cookie.cookieName, {
    httpOnly: true,
    sameSite: cookie.cookieSameSite,
    secure: cookie.cookieSecure,
    path: cookie.cookiePath,
  });
}

/**
 * Membaca cookie referral dari request pendaftaran dan langsung menautkannya ke akun pengguna baru.
 * Disarankan dipanggil di dalam blok transaksi pembuatan akun pengguna.
 *
 * @param {AttachReferralFromRequestOptions} options
 * @returns {Promise<import("./store.js").AttachResult>} Hasil penautan referral.
 * @example
 * await attachReferralFromRequest({
 *   req,
 *   userUuid: newUserUuid,
 *   store: referrals,
 *   executor: dbConnection,
 * });
 */
export async function attachReferralFromRequest({
  req,
  userUuid,
  store,
  executor,
  cookieName = DEFAULT_COOKIE_NAME,
} = {}) {
  if (!store || typeof store.attach !== "function") {
    throw new TypeError("store must provide an async attach(options) method");
  }
  const referrerUuid = getReferralUuid(req, { cookieName });
  if (!referrerUuid) return { attached: false, reason: "no_referral" };
  return store.attach({ userUuid, referrerUuid, executor });
}
