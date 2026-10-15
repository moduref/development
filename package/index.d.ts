import type { Router } from "express";

/**
 * Konfigurasi kolom pengguna untuk pelacakan referral.
 */
export interface ReferralStoreColumns {
  /** Nama kolom UUID pengguna (default: "uuid") */
  uuid?: string;
  /** Nama kolom username pengundang (default: "username") */
  username?: string;
  /** Nama kolom akumulasi poin referral (default: "referral_poin") */
  referralPoints?: string;
  /** Nama kolom attribution username pengundang (default: "referral_attribution") */
  referralAttribution?: string;
}

/**
 * Interface adapter database untuk ModuRef.
 */
export interface ReferralAdapter {
  dialect: "mysql" | "postgres" | "sqlite" | "standard";
  forUpdateClause: string;
  quoteIdentifier(identifier: string, label?: string): string;
  query(executor: any, sql: string, values?: any[]): Promise<any[]>;
  execute(executor: any, sql: string, values?: any[]): Promise<number>;
  transaction<T>(work: (trx: any) => Promise<T>, executor?: any): Promise<T>;
}

/**
 * Opsi konfigurasi pembuatan generic referral store.
 */
export interface ReferralStoreOptions {
  /** Database adapter instance (MySQL, Postgres, SQLite, atau kustom) */
  adapter: ReferralAdapter;
  /** Nama tabel pengguna di database (default: "users") */
  tableName?: string;
  /** Pemetaan nama kolom jika berbeda dari bawaan */
  columns?: ReferralStoreColumns;
}

/**
 * Opsi untuk menautkan pengguna baru ke pengundangnya.
 */
export interface AttachOptions {
  /** UUID pengguna baru yang mendaftar */
  userUuid: string;
  /** UUID pengundang yang membagikan tautan */
  referrerUuid: string;
  /** Koneksi transaksi database aktif jika ingin menyatu dalam satu transaksi */
  executor?: any;
}

/**
 * Hasil proses penautan referral.
 */
export interface AttachResult {
  /** `true` jika berhasil, `false` jika ditolak */
  attached: boolean;
  /** Alasan kegagalan penautan jika `attached: false` */
  reason?: "self_referral" | "referrer_not_found" | "user_not_found" | "already_referred" | "no_referral";
  /** UUID pengundang jika berhasil */
  referrerUuid?: string;
  /** Username pengundang yang disimpan ke baris pendaftar */
  referrerUsername?: string;
}

/**
 * Ringkasan data referral pengguna.
 */
export interface ReferralSummary {
  /** Total poin kumulatif referral pengguna */
  referralPoints: number;
  /** Username pengundang saat pendaftaran, atau `null` jika tidak ada */
  referralAttribution: string | null;
}

/**
 * Instance layanan referral store.
 */
export interface ReferralStore {
  /** Memvalidasi bahwa tabel dan keempat kolom referral tersedia di database */
  initialize(executor?: any): Promise<void>;
  /** Menautkan referral dan menambah poin (+1) pengundang secara atomik */
  attach(options: AttachOptions): Promise<AttachResult>;
  /** Mengambil ringkasan poin dan attribution untuk suatu UUID pengguna */
  getReferralSummary(userUuid: string, executor?: any): Promise<ReferralSummary | null>;
}

/**
 * Opsi untuk membuat tautan URL referral.
 */
export interface CreateReferralUrlOptions {
  /** Origin URL aplikasi (contoh: "https://example.com" atau "http://localhost:3000") */
  baseUrl: string;
  /** UUID pengguna aktif yang membagikan tautan */
  userUuid: string;
  /** Prefix path rute referral (default: "/referral") */
  path?: string;
}

/**
 * Opsi pembuatan Express Router referral.
 */
export interface CreateReferralRouterOptions {
  /** URL tujuan redirect setelah cookie berhasil disimpan (default: "/register") */
  registerPath?: string;
  /** Nama cookie untuk pelacakan referral (default: "referral_uuid") */
  cookieName?: string;
  /** Masa berlaku cookie dalam milidetik (default: 30 hari) */
  cookieMaxAge?: number;
  /** Scope path cookie (default: "/") */
  cookiePath?: string;
  /** Kebijakan SameSite cookie (default: "lax") */
  cookieSameSite?: "lax" | "strict" | "none";
  /** Flag Secure HTTPS-only (otomatis true di production) */
  cookieSecure?: boolean;
}

/**
 * Opsi pembacaan UUID referral dari request.
 */
export interface GetReferralUuidOptions {
  /** Nama cookie yang dicari (default: "referral_uuid") */
  cookieName?: string;
}

/**
 * Opsi penghapusan cookie referral.
 */
export interface ClearReferralCookieOptions {
  /** Nama cookie yang dihapus (default: "referral_uuid") */
  cookieName?: string;
  /** Scope path cookie (default: "/") */
  cookiePath?: string;
  /** Kebijakan SameSite (default: "lax") */
  cookieSameSite?: "lax" | "strict" | "none";
  /** Flag Secure */
  cookieSecure?: boolean;
}

/**
 * Opsi penautan referral langsung dari Express request.
 */
export interface AttachReferralFromRequestOptions {
  /** Request object dari Express */
  req: any;
  /** UUID pengguna baru yang mendaftar */
  userUuid: string;
  /** Instance ReferralStore yang digunakan */
  store: ReferralStore;
  /** Koneksi transaksi database aktif jika ada */
  executor?: any;
  /** Nama cookie referral (default: "referral_uuid") */
  cookieName?: string;
}

/**
 * Membangun URL referral yang siap dibagikan oleh user.
 *
 * @example
 * const url = createReferralUrl({
 *   baseUrl: "https://example.com",
 *   userUuid: user.uuid
 * });
 */
export function createReferralUrl(options: CreateReferralUrlOptions): string;

/**
 * Membuat Express Router untuk menangani rute referral (default: `/:referrerUuid`).
 * Menyimpan cookie referral selama 30 hari dan me-redirect pengguna ke halaman pendaftaran.
 *
 * @example
 * app.use("/referral", createReferralRouter({ registerPath: "/register" }));
 */
export function createReferralRouter(options?: CreateReferralRouterOptions): Router;

/**
 * Membaca dan memvalidasi UUID referral langsung dari header `cookie` request Express.
 *
 * @example
 * const referrerUuid = getReferralUuid(req);
 */
export function getReferralUuid(req: any, options?: GetReferralUuidOptions): string | null;

/**
 * Menghapus cookie referral setelah pendaftaran berhasil disimpan ke database.
 *
 * @example
 * clearReferralCookie(res);
 */
export function clearReferralCookie(res: any, options?: ClearReferralCookieOptions): any;

/**
 * Membaca cookie referral dari request pendaftaran dan langsung menautkannya ke akun pengguna baru.
 *
 * @example
 * await attachReferralFromRequest({
 *   req,
 *   userUuid: newUser.uuid,
 *   store: referrals,
 *   executor: dbTransaction
 * });
 */
export function attachReferralFromRequest(options: AttachReferralFromRequestOptions): Promise<AttachResult>;

/**
 * Membuat instance generic ReferralStore.
 */
export function createReferralStore(options?: ReferralStoreOptions): ReferralStore;

/**
 * Membuat instance ReferralStore untuk database MySQL/MariaDB (`mysql2/promise`).
 */
export function createMysqlReferralStore(options: { pool: any; tableName?: string; columns?: ReferralStoreColumns }): ReferralStore;

/**
 * Membuat instance ReferralStore untuk database PostgreSQL (`pg`).
 */
export function createPostgresReferralStore(options: { pool: any; tableName?: string; columns?: ReferralStoreColumns }): ReferralStore;

/**
 * Membuat instance ReferralStore untuk database SQLite (`better-sqlite3`).
 */
export function createSqliteReferralStore(options: { database: any; tableName?: string; columns?: ReferralStoreColumns }): ReferralStore;

/**
 * Membuat adapter database untuk `mysql2/promise`.
 */
export function createMysqlReferralAdapter(pool: any): ReferralAdapter;

/**
 * Membuat adapter database untuk `pg`.
 */
export function createPostgresReferralAdapter(pool: any): ReferralAdapter;

/**
 * Membuat adapter database untuk `better-sqlite3`.
 */
export function createSqliteReferralAdapter(database: any): ReferralAdapter;
