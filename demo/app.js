import { randomBytes, randomUUID, scrypt as scryptCallback, timingSafeEqual } from "node:crypto";
import { promisify } from "node:util";
import path from "node:path";
import express from "express";
import session from "express-session";
import {
  attachReferralFromRequest,
  clearReferralCookie,
  createMysqlReferralStore,
  createPostgresReferralStore,
  createSqliteReferralStore,
  createReferralRouter,
  createReferralUrl,
} from "moduref";

const scrypt = promisify(scryptCallback);
const app = express();
const port = Number(process.env.PORT ?? 3000);
const appUrl = process.env.APP_URL ?? `http://localhost:${port}`;
const dbDriver = (process.env.DB_DRIVER ?? "sqlite").toLowerCase();

let pool = null;
let sqliteDb = null;
let referrals = null;

// Inisialisasi Database (SQLite, MySQL, atau PostgreSQL)
if (dbDriver === "mysql") {
  const mysql = (await import("mysql2/promise")).default;
  pool = mysql.createPool({
    host: process.env.MYSQL_HOST ?? "127.0.0.1",
    port: Number(process.env.MYSQL_PORT ?? 3306),
    user: process.env.MYSQL_USER ?? "moduref",
    password: process.env.MYSQL_PASSWORD ?? "moduref-local",
    database: process.env.MYSQL_DATABASE ?? "moduref_demo",
    waitForConnections: true,
    connectionLimit: 10,
    charset: "utf8mb4",
  });

  referrals = createMysqlReferralStore({
    pool,
    tableName: "users",
    columns: {
      uuid: "uuid",
      username: "username",
      referralPoints: "referral_poin",
      referralAttribution: "referral_attribution",
    },
  });
} else if (dbDriver === "postgres" || dbDriver === "pgsql" || dbDriver === "postgresql") {
  const { Pool } = await import("pg");
  pool = new Pool({
    host: process.env.PG_HOST ?? process.env.PGSQL_HOST ?? "127.0.0.1",
    port: Number(process.env.PG_PORT ?? process.env.PGSQL_PORT ?? 5432),
    user: process.env.PG_USER ?? process.env.PGSQL_USER ?? "postgres",
    password: process.env.PG_PASSWORD ?? process.env.PGSQL_PASSWORD ?? "",
    database: process.env.PG_DATABASE ?? process.env.PGSQL_DATABASE ?? "moduref_demo",
  });

  // Otomatis buat tabel users jika belum ada
  await pool.query(`
    CREATE TABLE IF NOT EXISTS users (
      uuid CHAR(36) NOT NULL PRIMARY KEY,
      username VARCHAR(120) NOT NULL UNIQUE,
      referral_poin INTEGER NOT NULL DEFAULT 0,
      referral_attribution VARCHAR(120) NULL,
      email VARCHAR(254) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
  `);

  referrals = createPostgresReferralStore({
    pool,
    tableName: "users",
    columns: {
      uuid: "uuid",
      username: "username",
      referralPoints: "referral_poin",
      referralAttribution: "referral_attribution",
    },
  });
} else {
  // Default SQLite menggunakan modul bawaan node:sqlite (Node 22+)
  const { DatabaseSync } = await import("node:sqlite");
  const sqliteFile = path.resolve(process.env.DB_SQLITE_PATH ?? "database.sqlite");
  sqliteDb = new DatabaseSync(sqliteFile);

  // Otomatis buat tabel users jika belum ada
  sqliteDb.exec(`
    CREATE TABLE IF NOT EXISTS users (
      uuid TEXT PRIMARY KEY,
      username TEXT NOT NULL UNIQUE,
      referral_poin INTEGER NOT NULL DEFAULT 0,
      referral_attribution TEXT NULL,
      email TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
  `);

  referrals = createSqliteReferralStore({
    database: sqliteDb,
    tableName: "users",
    columns: {
      uuid: "uuid",
      username: "username",
      referralPoints: "referral_poin",
      referralAttribution: "referral_attribution",
    },
  });
}

const sessionSecret = process.env.SESSION_SECRET || "local-only-demo-secret-change-me";

app.disable("x-powered-by");
app.use(express.urlencoded({ extended: false, limit: "10kb" }));
app.use(session({
  name: "moduref_session",
  secret: sessionSecret,
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    maxAge: 7 * 24 * 60 * 60 * 1000,
  },
}));

app.use("/referral", createReferralRouter({ registerPath: "/register" }));

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (character) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[character]);
}

function page(title, content) {
  return `<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${escapeHtml(title)} · ModuRef Demo</title>
  <style>
    :root { color-scheme: light; font: 16px/1.5 system-ui, sans-serif; color: #202938; background: #f3f5f8; }
    body { margin: 0; padding: 3rem 1rem; }
    main { box-sizing: border-box; max-width: 620px; margin: auto; padding: 2rem; background: white; border: 1px solid #dce2eb; border-radius: 14px; }
    h1 { margin-top: 0; } label { display: block; margin: 1rem 0 .3rem; font-weight: 600; }
    input { box-sizing: border-box; width: 100%; padding: .7rem; border: 1px solid #aab5c5; border-radius: 7px; font: inherit; }
    button, .button { display: inline-block; margin-top: 1rem; padding: .7rem 1rem; color: white; background: #245fca; border: 0; border-radius: 7px; font: inherit; text-decoration: none; cursor: pointer; }
    .muted { color: #5a6575; overflow-wrap: anywhere; } .error { padding: .7rem; background: #fff0ee; border-radius: 7px; color: #9e2b1d; }
    .badge { display: inline-block; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; background: #e0e7ff; color: #3730a3; border-radius: 4px; text-transform: uppercase; }
    li { margin: .4rem 0; } code { overflow-wrap: anywhere; }
  </style>
</head>
<body><main>${content}</main></body>
</html>`;
}

function formField(name, label, type = "text", autocomplete = "") {
  return `<label for="${name}">${label}</label><input id="${name}" name="${name}" type="${type}" ${autocomplete ? `autocomplete="${autocomplete}"` : ""} required>`;
}

function showPage(res, title, content, status = 200) {
  return res.status(status).type("html").send(page(title, content));
}

function requireLogin(req, res, next) {
  if (!req.session.userUuid) return res.redirect("/login");
  return next();
}

async function hashPassword(password) {
  const salt = randomBytes(16);
  const derivedKey = await scrypt(password, salt, 64);
  return `${salt.toString("base64url")}:${derivedKey.toString("base64url")}`;
}

async function verifyPassword(password, savedHash) {
  const [saltText, keyText] = String(savedHash).split(":");
  if (!saltText || !keyText) return false;
  const salt = Buffer.from(saltText, "base64url");
  const expected = Buffer.from(keyText, "base64url");
  if (expected.length !== 64) return false;
  const actual = await scrypt(password, salt, expected.length);
  return timingSafeEqual(expected, actual);
}

// Helper Query Database Fleksibel (SQLite / MySQL / PostgreSQL)
async function findUserByUsername(username) {
  if (dbDriver === "sqlite") {
    return sqliteDb.prepare("SELECT uuid, password_hash FROM users WHERE username = ?").get(username) || null;
  }
  if (dbDriver === "postgres" || dbDriver === "pgsql" || dbDriver === "postgresql") {
    const { rows } = await pool.query(
      "SELECT uuid, password_hash FROM users WHERE username = $1 LIMIT 1",
      [username],
    );
    return rows[0] || null;
  }
  const [rows] = await pool.execute(
    "SELECT uuid, password_hash FROM users WHERE username = ? LIMIT 1",
    [username],
  );
  return rows[0] || null;
}

async function findUserByUuid(userUuid) {
  if (dbDriver === "sqlite") {
    return sqliteDb.prepare("SELECT uuid, username, email FROM users WHERE uuid = ?").get(userUuid) || null;
  }
  if (dbDriver === "postgres" || dbDriver === "pgsql" || dbDriver === "postgresql") {
    const { rows } = await pool.query(
      "SELECT uuid, username, email FROM users WHERE uuid = $1 LIMIT 1",
      [userUuid],
    );
    return rows[0] || null;
  }
  const [[user]] = await pool.execute(
    "SELECT uuid, username, email FROM users WHERE uuid = ? LIMIT 1",
    [userUuid],
  );
  return user || null;
}

async function registerUser({ req, userUuid, username, email, passwordHash }) {
  if (dbDriver === "sqlite") {
    try {
      sqliteDb.exec("BEGIN IMMEDIATE");
      sqliteDb.prepare(
        "INSERT INTO users (uuid, username, email, password_hash) VALUES (?, ?, ?, ?)"
      ).run(userUuid, username, email, passwordHash);

      await attachReferralFromRequest({ req, userUuid, store: referrals, executor: sqliteDb });
      sqliteDb.exec("COMMIT");
    } catch (error) {
      try { sqliteDb.exec("ROLLBACK"); } catch {}
      if (String(error?.message).includes("UNIQUE constraint failed")) {
        const err = new Error("Username atau email sudah terdaftar");
        err.code = "ER_DUP_ENTRY";
        throw err;
      }
      throw error;
    }
    return;
  }

  if (dbDriver === "postgres" || dbDriver === "pgsql" || dbDriver === "postgresql") {
    const client = await pool.connect();
    try {
      await client.query("BEGIN");
      await client.query(
        "INSERT INTO users (uuid, username, email, password_hash) VALUES ($1, $2, $3, $4)",
        [userUuid, username, email, passwordHash],
      );
      await attachReferralFromRequest({ req, userUuid, store: referrals, executor: client });
      await client.query("COMMIT");
    } catch (error) {
      await client.query("ROLLBACK");
      if (error?.code === "23505") {
        const err = new Error("Username atau email sudah terdaftar");
        err.code = "ER_DUP_ENTRY";
        throw err;
      }
      throw error;
    } finally {
      client.release();
    }
    return;
  }

  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();
    await connection.execute(
      "INSERT INTO users (uuid, username, email, password_hash) VALUES (?, ?, ?, ?)",
      [userUuid, username, email, passwordHash],
    );
    await attachReferralFromRequest({ req, userUuid, store: referrals, executor: connection });
    await connection.commit();
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
}

app.get("/", (req, res) => {
  const content = `<h1>ModuRef Referral Demo <span class="badge">${escapeHtml(dbDriver)}</span></h1>
    <p>Daftar melalui link referral untuk melihat attribution dan poin referral tersimpan di database.</p>
    <p><a class="button" href="/register">Daftar</a> <a class="button" href="/login">Login</a></p>`;
  return showPage(res, "Beranda", content);
});

app.get("/register", (req, res) => {
  const content = `<h1>Buat akun <span class="badge">${escapeHtml(dbDriver)}</span></h1>
    <form method="post" action="/register">
      ${formField("username", "Username", "text", "username")}
      ${formField("email", "Email", "email", "email")}
      ${formField("password", "Password (minimal 8 karakter)", "password", "new-password")}
      <button type="submit">Daftar</button>
    </form>
    <p class="muted">Sudah punya akun? <a href="/login">Login</a>.</p>`;
  return showPage(res, "Daftar", content);
});

app.post("/register", async (req, res, next) => {
  const username = String(req.body.username ?? "").trim();
  const email = String(req.body.email ?? "").trim().toLowerCase();
  const password = String(req.body.password ?? "");
  if (!/^[A-Za-z0-9_.-]{3,30}$/.test(username) || !/^\S+@\S+\.\S+$/.test(email) || email.length > 254 || password.length < 8) {
    return showPage(res, "Daftar", `<h1>Data belum valid</h1><p class="error">Username harus 3–30 karakter (huruf, angka, titik, garis bawah, atau strip); periksa juga email dan password (minimal 8 karakter).</p><p><a href="/register">Kembali</a></p>`, 400);
  }

  const userUuid = randomUUID();
  const passwordHash = await hashPassword(password);

  try {
    await registerUser({ req, userUuid, username, email, passwordHash });
  } catch (error) {
    if (error?.code === "ER_DUP_ENTRY") {
      return showPage(res, "Daftar", `<h1>Username atau email sudah terdaftar</h1><p><a href="/register">Coba lagi</a> atau <a href="/login">login</a>.</p>`, 409);
    }
    return next(error);
  }

  clearReferralCookie(res);
  req.session.regenerate((error) => {
    if (error) return next(error);
    req.session.userUuid = userUuid;
    return req.session.save((saveError) => {
      if (saveError) return next(saveError);
      return res.redirect("/dashboard");
    });
  });
});

app.get("/login", (req, res) => {
  const content = `<h1>Login <span class="badge">${escapeHtml(dbDriver)}</span></h1>
    <form method="post" action="/login">
      ${formField("username", "Username", "text", "username")}
      ${formField("password", "Password", "password", "current-password")}
      <button type="submit">Login</button>
    </form>
    <p class="muted">Belum punya akun? <a href="/register">Daftar</a>.</p>`;
  return showPage(res, "Login", content);
});

app.post("/login", async (req, res, next) => {
  try {
    const username = String(req.body.username ?? "").trim();
    const password = String(req.body.password ?? "");
    const user = await findUserByUsername(username);

    if (!user || !(await verifyPassword(password, user.password_hash))) {
      return showPage(res, "Login", `<h1>Login gagal</h1><p class="error">Username atau password tidak cocok.</p><p><a href="/login">Coba lagi</a></p>`, 401);
    }
    req.session.regenerate((error) => {
      if (error) return next(error);
      req.session.userUuid = user.uuid;
      return req.session.save((saveError) => {
        if (saveError) return next(saveError);
        return res.redirect("/dashboard");
      });
    });
  } catch (error) {
    return next(error);
  }
});

app.get("/dashboard", requireLogin, async (req, res, next) => {
  try {
    const user = await findUserByUuid(req.session.userUuid);
    if (!user) {
      req.session.destroy(() => res.redirect("/login"));
      return;
    }
    const referralSummary = await referrals.getReferralSummary(user.uuid);
    const referralUrl = createReferralUrl({ baseUrl: appUrl, userUuid: user.uuid });
    const content = `<h1>Halo, ${escapeHtml(user.username)} <span class="badge">${escapeHtml(dbDriver)}</span></h1>
      <p>Email: ${escapeHtml(user.email)}</p>
      <h2>Link referral kamu</h2><p><code>${escapeHtml(referralUrl)}</code></p>
      <p>Bagikan link ini. Jika orang membuka link referral lain, referral yang tersimpan di browser akan berganti ke link terbaru.</p>
      <h2>Referral yang digunakan saat mendaftar</h2><p>${referralSummary?.referralAttribution ? escapeHtml(referralSummary.referralAttribution) : "Tidak ada"}</p>
      <h2>Jumlah pendaftar dari link kamu</h2><p>${referralSummary?.referralPoints ?? 0}</p>
      <form method="post" action="/logout"><button type="submit">Logout</button></form>`;
    return showPage(res, "Dashboard", content);
  } catch (error) {
    return next(error);
  }
});

app.post("/logout", requireLogin, (req, res, next) => {
  req.session.destroy((error) => {
    if (error) return next(error);
    res.clearCookie("moduref_session", { httpOnly: true, sameSite: "lax", path: "/" });
    return res.redirect("/");
  });
});

app.use((error, req, res, next) => {
  console.error(error);
  if (res.headersSent) return next(error);
  return showPage(res, "Terjadi kesalahan", `<h1>Terjadi kesalahan</h1><p>Coba lagi. Detail error dicatat di terminal.</p><p><a href="/">Beranda</a></p>`, 500);
});

async function start() {
  await referrals.initialize();

  app.listen(port, "127.0.0.1", () => {
    console.log(`ModuRef demo running at ${appUrl} [Database: ${dbDriver.toUpperCase()}]`);
    if (!process.env.SESSION_SECRET) {
      console.warn("Set SESSION_SECRET in demo/.env before using beyond local testing.");
    }
  });
}

start().catch(async (error) => {
  console.error(`Could not start the demo (${dbDriver}). Check database connection and environment variables.`, error);
  if (pool) await pool.end();
  if (sqliteDb) sqliteDb.close();
  process.exitCode = 1;
});
