export const DEFAULT_COOKIE_NAME = "referral_uuid";
export const DEFAULT_COOKIE_MAX_AGE = 30 * 24 * 60 * 60 * 1000;
export const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
export const IDENTIFIER_PATTERN = /^[A-Za-z_][A-Za-z0-9_$]*$/;

export const DEFAULT_COLUMNS = Object.freeze({
  uuid: "uuid",
  username: "username",
  referralPoints: "referral_poin",
  referralAttribution: "referral_attribution",
});
