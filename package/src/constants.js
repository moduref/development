/**
 * Nama default untuk cookie pelacak referral.
 * @type {string}
 */
export const DEFAULT_COOKIE_NAME = "referral_uuid";

/**
 * Masa berlaku default cookie referral (30 hari dalam milidetik).
 * @type {number}
 */
export const DEFAULT_COOKIE_MAX_AGE = 30 * 24 * 60 * 60 * 1000;

/**
 * Regex pola UUID standar (v1-v5).
 * @type {RegExp}
 */
export const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

/**
 * Regex validasi identifier SQL aman (mencegah SQL Injection pada nama tabel/kolom).
 * @type {RegExp}
 */
export const IDENTIFIER_PATTERN = /^[A-Za-z_][A-Za-z0-9_$]*$/;

/**
 * Pemetaan nama kolom default pada tabel pengguna.
 * @type {Readonly<{ uuid: string, username: string, referralPoints: string, referralAttribution: string }>}
 */
export const DEFAULT_COLUMNS = Object.freeze({
  uuid: "uuid",
  username: "username",
  referralPoints: "referral_poin",
  referralAttribution: "referral_attribution",
});
