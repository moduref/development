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
 * Build a shareable referral URL from the existing user UUID.
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
 * Return an Express router to mount at `/referral`. Every valid link replaces
 * the prior referral cookie, with a 30-day default lifetime.
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

/** Read and validate the referral cookie from an Express request. */
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

/** Clear the referral cookie after the registration transaction commits. */
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
 * Read the pending referral cookie and link it to a user created by the app.
 * Call this within the transaction that inserts the user.
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
