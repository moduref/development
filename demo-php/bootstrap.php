<?php
declare(strict_types=1);

// Mulai session HTTP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Parser file .env sederhana tanpa dependensi
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// 2. Autoload Library Moduref PHP
if (file_exists(__DIR__ . '/../moduref-php/dist/moduref.min.php')) {
    require_once __DIR__ . '/../moduref-php/dist/moduref.min.php';
} elseif (file_exists(__DIR__ . '/../moduref-php/src/Moduref.php')) {
    spl_autoload_register(function (string $class) {
        if (str_starts_with($class, 'Moduref\\')) {
            $rel = substr($class, strlen('Moduref\\'));
            $path = __DIR__ . '/../moduref-php/src/' . str_replace('\\', '/', $rel) . '.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }
    });
    require_once __DIR__ . '/../moduref-php/src/functions.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    die("Error: Library Moduref PHP tidak ditemukan di ../moduref-php/dist atau ../moduref-php/src.");
}

// 3. Konfigurasi Database (SQLite atau MySQL)
$dbDriver = strtolower($_ENV['DB_DRIVER'] ?? 'sqlite');
$port = (int)($_ENV['PORT'] ?? 8000);
$appUrl = rtrim($_ENV['APP_URL'] ?? "http://localhost:$port", '/');

try {
    if ($dbDriver === 'mysql') {
        $host = $_ENV['MYSQL_HOST'] ?? '127.0.0.1';
        $dbPort = $_ENV['MYSQL_PORT'] ?? '3306';
        $dbname = $_ENV['MYSQL_DATABASE'] ?? 'moduref_demo';
        $user = $_ENV['MYSQL_USER'] ?? 'root';
        $pass = $_ENV['MYSQL_PASSWORD'] ?? '';

        $pdo = new PDO(
            "mysql:host={$host};port={$dbPort};dbname={$dbname};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // Pastikan tabel users tersedia di MySQL
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                username VARCHAR(120) NOT NULL,
                referral_poin INT UNSIGNED NOT NULL DEFAULT 0,
                referral_attribution VARCHAR(120) NULL,
                email VARCHAR(254) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (uuid),
                UNIQUE KEY users_username_unique (username),
                UNIQUE KEY users_email_unique (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } elseif ($dbDriver === 'pgsql' || $dbDriver === 'postgres' || $dbDriver === 'postgresql') {
        $host = $_ENV['PGSQL_HOST'] ?? $_ENV['PG_HOST'] ?? '127.0.0.1';
        $dbPort = $_ENV['PGSQL_PORT'] ?? $_ENV['PG_PORT'] ?? '5432';
        $dbname = $_ENV['PGSQL_DATABASE'] ?? $_ENV['PG_DATABASE'] ?? 'moduref_demo';
        $user = $_ENV['PGSQL_USER'] ?? $_ENV['PG_USER'] ?? 'postgres';
        $pass = $_ENV['PGSQL_PASSWORD'] ?? $_ENV['PG_PASSWORD'] ?? '';

        $pdo = new PDO(
            "pgsql:host={$host};port={$dbPort};dbname={$dbname}",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // Pastikan tabel users tersedia di PostgreSQL
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                uuid CHAR(36) NOT NULL PRIMARY KEY,
                username VARCHAR(120) NOT NULL UNIQUE,
                referral_poin INTEGER NOT NULL DEFAULT 0,
                referral_attribution VARCHAR(120) NULL,
                email VARCHAR(254) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");
    } else {
        // Default SQLite
        $sqlitePath = __DIR__ . '/' . ($_ENV['DB_SQLITE_PATH'] ?? 'database.sqlite');
        $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Pastikan tabel users tersedia di SQLite
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                uuid TEXT PRIMARY KEY,
                username TEXT NOT NULL UNIQUE,
                referral_poin INTEGER NOT NULL DEFAULT 0,
                referral_attribution TEXT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            );
        ");
    }
} catch (PDOException $e) {
    die("Koneksi Database Gagal ({$dbDriver}): " . htmlspecialchars($e->getMessage()));
}

// 4. Inisialisasi Moduref Referral Store
$referralStore = Moduref\Moduref::createPdoStore($pdo, 'users');
$referralStore->initialize();

// 5. Helper Sanitasi Tampilan HTML
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// 6. Helper Flash Message
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
