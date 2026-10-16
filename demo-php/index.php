<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Moduref\Moduref;

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// -------------------------------------------------------------
// Rute 1: Penangkap Link Referral: /referral/{uuid}
// -------------------------------------------------------------
if (preg_match('#^/referral/([a-fA-F0-9\-]+)$#', $requestUri, $matches)) {
    $referrerUuid = $matches[1];
    if (Moduref::isValidUuid($referrerUuid)) {
        setcookie(Moduref::COOKIE_NAME, $referrerUuid, [
            'expires'  => time() + (30 * 86400),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setFlash('info', 'Kode referral terdeteksi! Silakan lengkapi formulir pendaftaran di bawah ini.');
    }
    header('Location: /register');
    exit;
}

// -------------------------------------------------------------
// Rute 2: Logout
// -------------------------------------------------------------
if ($requestUri === '/logout') {
    unset($_SESSION['user_uuid']);
    session_destroy();
    header('Location: /login');
    exit;
}

// -------------------------------------------------------------
// Rute 3: Halaman Depan
// -------------------------------------------------------------
if ($requestUri === '/' || $requestUri === '') {
    if (isset($_SESSION['user_uuid'])) {
        header('Location: /dashboard');
    } else {
        header('Location: /login');
    }
    exit;
}

// -------------------------------------------------------------
// Rute 4: Login Pengguna
// -------------------------------------------------------------
if ($requestUri === '/login') {
    if (isset($_SESSION['user_uuid'])) {
        header('Location: /dashboard');
        exit;
    }

    if ($method === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            setFlash('error', 'Username dan password wajib diisi.');
            header('Location: /login');
            exit;
        }

        $stmt = $pdo->prepare("SELECT uuid, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_uuid'] = $user['uuid'];
            setFlash('success', 'Selamat datang kembali, @' . $user['username'] . '!');
            header('Location: /dashboard');
            exit;
        }

        setFlash('error', 'Username atau password tidak sesuai.');
        header('Location: /login');
        exit;
    }

    // Ambil beberapa akun yang ada untuk memudahkan pengujian instan
    $sampleUsers = $pdo->query("SELECT username FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

    renderLayout('Masuk ke Akun', '
        <h2>Masuk ke Akun</h2>
        <p class="subtitle">Aplikasi demo referral engine Moduref PHP.</p>

        <form method="POST" action="/login">
            <div class="form-group">
                <label for="username">Username:</label>
                <input id="username" name="username" type="text" required placeholder="Contoh: budi123" autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input id="password" name="password" type="password" required placeholder="Masukkan password Anda">
            </div>
            <button type="submit" class="btn btn-primary">Masuk</button>
        </form>

        <p class="auth-switch">Belum punya akun? <a href="/register">Daftar sekarang</a></p>

        ' . (!empty($sampleUsers) ? '
        <div class="sample-box">
            <div class="sample-title">💡 Akun Pengujian Tersedia:</div>
            <div class="sample-tags">
                ' . implode('', array_map(fn($u) => '<span class="tag">@' . e($u['username']) . '</span>', $sampleUsers)) . '
            </div>
        </div>' : '') . '
    ');
    exit;
}

// -------------------------------------------------------------
// Rute 5: Registrasi Pengguna Baru
// -------------------------------------------------------------
if ($requestUri === '/register') {
    if (isset($_SESSION['user_uuid'])) {
        header('Location: /dashboard');
        exit;
    }

    // Periksa apakah ada cookie referral aktif
    $activeReferralUuid = Moduref::getCookieUuid($_COOKIE);
    $referrerUser = null;
    if ($activeReferralUuid) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE uuid = ?");
        $stmt->execute([$activeReferralUuid]);
        $referrerUser = $stmt->fetch();
    }

    if ($method === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            setFlash('error', 'Semua kolom wajib diisi.');
            header('Location: /register');
            exit;
        }

        // Cek duplikasi username / email
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ((int)$stmt->fetchColumn() > 0) {
            setFlash('error', 'Username atau Email sudah terdaftar. Silakan pilih yang lain.');
            header('Location: /register');
            exit;
        }

        $newUserUuid = Moduref::generateUuid();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Eksekusi transaksi aman
        $pdo->beginTransaction();
        try {
            // 1. Insert pengguna baru
            $insertStmt = $pdo->prepare("
                INSERT INTO users (uuid, username, email, password_hash, referral_poin, referral_attribution)
                VALUES (?, ?, ?, ?, 0, NULL)
            ");
            $insertStmt->execute([$newUserUuid, $username, $email, $passwordHash]);

            // 2. Tautkan referral secara otomatis dari cookie
            $attachResult = Moduref::attachFromCookie($_COOKIE, $newUserUuid, $referralStore, $pdo);

            $pdo->commit();

            // 3. Bersihkan cookie referral setelah sukses digunakan
            setcookie(Moduref::COOKIE_NAME, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            $_SESSION['user_uuid'] = $newUserUuid;

            if ($attachResult->attached) {
                setFlash('success', "Akun berhasil dibuat! Anda berhasil terhubung dengan pengundang @{$attachResult->referrerUsername}.");
            } else {
                setFlash('success', "Akun berhasil dibuat sebagai pengguna organik!");
            }

            header('Location: /dashboard');
            exit;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            setFlash('error', 'Gagal mendaftar: ' . $e->getMessage());
            header('Location: /register');
            exit;
        }
    }

    $referralNotice = '';
    if ($activeReferralUuid) {
        $inviterLabel = $referrerUser ? "@" . e($referrerUser['username']) : "Pengguna ID: " . substr(e($activeReferralUuid), 0, 8) . "...";
        $referralNotice = '
            <div class="alert alert-referral">
                <div class="alert-icon">🎁</div>
                <div>
                    <strong>Tautan Referral Aktif!</strong>
                    <div>Anda mendaftar melalui undangan dari <strong>' . $inviterLabel . '</strong>. Poin referral akan otomatis diberikan kepada pengundang saat Anda mendaftar.</div>
                </div>
            </div>
        ';
    }

    renderLayout('Daftar Akun Baru', '
        <h2>Pendaftaran Akun Baru</h2>
        <p class="subtitle">Buat akun untuk mendapatkan link referral Anda sendiri.</p>

        ' . $referralNotice . '

        <form method="POST" action="/register">
            <div class="form-group">
                <label for="username">Username:</label>
                <input id="username" name="username" type="text" required placeholder="Contoh: sitiaisyah" autofocus>
            </div>
            <div class="form-group">
                <label for="email">Alamat Email:</label>
                <input id="email" name="email" type="email" required placeholder="nama@email.com">
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input id="password" name="password" type="password" required placeholder="Minimal 6 karakter">
            </div>
            <button type="submit" class="btn btn-primary">Daftar Sekarang</button>
        </form>

        <p class="auth-switch">Sudah punya akun? <a href="/login">Masuk di sini</a></p>
    ');
    exit;
}

// -------------------------------------------------------------
// Rute 6: Dashboard Pengguna
// -------------------------------------------------------------
if ($requestUri === '/dashboard') {
    if (!isset($_SESSION['user_uuid'])) {
        setFlash('error', 'Silakan masuk terlebih dahulu.');
        header('Location: /login');
        exit;
    }

    $userUuid = $_SESSION['user_uuid'];
    $stmt = $pdo->prepare("SELECT uuid, username, email, referral_poin, referral_attribution, created_at FROM users WHERE uuid = ?");
    $stmt->execute([$userUuid]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_uuid']);
        header('Location: /login');
        exit;
    }

    // Buat URL Referral pribadi
    $myReferralUrl = Moduref::createUrl($appUrl, $user['uuid']);

    // Ambil rekap referral store
    $summary = $referralStore->getReferralSummary($user['uuid']);

    // Ambil detail pengundang jika ada
    $inviterName = null;
    if ($user['referral_attribution']) {
        $invStmt = $pdo->prepare("SELECT username FROM users WHERE uuid = ?");
        $invStmt->execute([$user['referral_attribution']]);
        $inviterName = $invStmt->fetchColumn() ?: null;
    }

    // Ambil daftar teman yang berhasil diundang
    $referredStmt = $pdo->prepare("
        SELECT username, email, created_at 
        FROM users 
        WHERE referral_attribution = ? 
        ORDER BY created_at DESC
    ");
    $referredStmt->execute([$user['uuid']]);
    $referredUsers = $referredStmt->fetchAll();

    renderLayout('Dashboard Referral', '
        <div class="dashboard-header">
            <div>
                <h2>Halo, @' . e($user['username']) . '! 👋</h2>
                <div class="user-meta">UUID: <code>' . e($user['uuid']) . '</code> · Database: <strong>' . strtoupper(e($dbDriver)) . '</strong></div>
            </div>
            <a href="/logout" class="btn btn-secondary btn-sm">Keluar</a>
        </div>

        <!-- Kartu Link Referral -->
        <div class="card link-card">
            <div class="card-label">🔗 Link Referral Pribadi Anda:</div>
            <div class="link-input-group">
                <input id="refUrl" type="text" readonly value="' . e($myReferralUrl) . '">
                <button type="button" class="btn btn-copy" onclick="copyReferralLink()">Salin Link</button>
                <a href="' . e($myReferralUrl) . '" target="_blank" class="btn btn-outline" title="Buka di tab baru untuk menguji">Tes Klik</a>
            </div>
            <div class="link-hint">Bagikan link di atas kepada teman Anda. Saat mereka mendaftar, poin referral Anda akan bertambah secara otomatis!</div>
        </div>

        <!-- Grid Statistik -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">' . (int)$user['referral_poin'] . '</div>
                <div class="stat-title">Total Poin Referral</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">' . count($referredUsers) . '</div>
                <div class="stat-title">Teman Berhasil Diundang</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="font-size: 1.15rem; margin-top: 0.35rem;">' . ($inviterName ? '@' . e($inviterName) : 'Organik') . '</div>
                <div class="stat-title">Status Asal Akun Anda</div>
            </div>
        </div>

        <!-- Tabel Teman yang Diundang -->
        <div class="card">
            <h3 style="margin-top: 0; margin-bottom: 0.75rem;">Daftar Teman yang Anda Undang</h3>
            ' . (!empty($referredUsers) ? '
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Waktu Mendaftar</th>
                    </tr>
                </thead>
                <tbody>
                    ' . implode('', array_map(fn($r) => '
                    <tr>
                        <td><strong>@' . e($r['username']) . '</strong></td>
                        <td>' . e($r['email']) . '</td>
                        <td><span class="muted">' . e($r['created_at']) . '</span></td>
                    </tr>
                    ', $referredUsers)) . '
                </tbody>
            </table>
            ' : '
            <div class="empty-state">
                <p>Belum ada teman yang mendaftar melalui link referral Anda.</p>
                <p class="muted">Salin dan bagikan link di atas untuk mulai mengundang pengguna baru!</p>
            </div>
            ') . '
        </div>
    ', '
    <script>
    function copyReferralLink() {
        const input = document.getElementById("refUrl");
        input.select();
        navigator.clipboard.writeText(input.value).then(() => {
            alert("Tautan referral berhasil disalin ke clipboard!");
        });
    }
    </script>
    ');
    exit;
}

// Rute Tidak Ditemukan
http_response_code(404);
renderLayout('404 Tidak Ditemukan', '
    <h2>404 · Halaman Tidak Ditemukan</h2>
    <p>Halaman yang Anda tuju tidak tersedia.</p>
    <a href="/login" class="btn btn-primary">Kembali ke Halaman Utama</a>
');
exit;

// -------------------------------------------------------------
// Helper Render Layout HTML
// -------------------------------------------------------------
function renderLayout(string $title, string $content, string $extraScripts = ''): void
{
    $flash = getFlash();
    $flashHtml = '';
    if ($flash) {
        $alertClass = $flash['type'] === 'error' ? 'alert-danger' : ($flash['type'] === 'info' ? 'alert-info' : 'alert-success');
        $flashHtml = '<div class="alert ' . $alertClass . '">' . e($flash['message']) . '</div>';
    }

    echo '<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . e($title) . ' · Moduref PHP Demo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0b0f19;
            --bg-card: #131b2e;
            --border-color: rgba(255, 255, 255, 0.1);
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --text-main: #f9fafb;
            --text-muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --radius: 12px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Inter", system-ui, -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .container {
            width: 100%;
            max-width: 680px;
        }
        .app-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -0.02em;
        }
        .app-brand .badge-tech {
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            background: rgba(99, 102, 241, 0.18);
            border: 1px solid rgba(99, 102, 241, 0.4);
            border-radius: 9999px;
            color: #a5b4fc;
            font-weight: 600;
        }
        .main-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 2.25rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
        }
        h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }
        .subtitle { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #cbd5e1; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            padding: 0.75rem 0.95rem;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
        }
        .btn-primary {
            width: 100%;
            background: var(--primary);
            color: #fff;
        }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: rgba(255, 255, 255, 0.08); color: var(--text-main); border: 1px solid var(--border-color); }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.15); }
        .btn-sm { padding: 0.45rem 0.85rem; font-size: 0.85rem; }
        .btn-copy { background: #3b82f6; color: white; white-space: nowrap; }
        .btn-copy:hover { background: #2563eb; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: #94a3b8; }
        .btn-outline:hover { color: #fff; border-color: #fff; }
        .auth-switch { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted); }
        .auth-switch a { color: #818cf8; text-decoration: none; font-weight: 600; }
        .auth-switch a:hover { text-decoration: underline; }
        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; }
        .alert-danger { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
        .alert-info { background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.4); color: #93c5fd; }
        .alert-referral { background: rgba(168, 85, 247, 0.15); border: 1px solid rgba(168, 85, 247, 0.4); color: #e9d5ff; }
        .alert-icon { font-size: 1.3rem; }
        .card {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .link-card { background: rgba(99, 102, 241, 0.08); border-color: rgba(99, 102, 241, 0.25); }
        .card-label { font-size: 0.85rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.5rem; }
        .link-input-group { display: flex; gap: 0.5rem; }
        .link-input-group input { font-family: "JetBrains Mono", monospace; font-size: 0.85rem; }
        .link-hint { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .stat-card {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }
        .stat-value { font-size: 1.85rem; font-weight: 800; color: #818cf8; }
        .stat-title { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-top: 0.25rem; letter-spacing: 0.04em; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
        .user-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem; }
        code { font-family: "JetBrains Mono", monospace; font-size: 0.85em; background: rgba(255, 255, 255, 0.08); padding: 0.15rem 0.35rem; border-radius: 4px; color: #f1f5f9; }
        .table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .table th, .table td { padding: 0.65rem 0.5rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .table th { color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; font-weight: 600; }
        .muted { color: var(--text-muted); font-size: 0.8rem; }
        .empty-state { text-align: center; padding: 1.5rem 0; color: var(--text-muted); font-size: 0.9rem; }
        .sample-box { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); }
        .sample-title { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .sample-tags { display: flex; flex-wrap: wrap; gap: 0.4rem; }
        .tag { font-size: 0.8rem; padding: 0.2rem 0.5rem; background: rgba(255, 255, 255, 0.06); border-radius: 4px; color: #cbd5e1; }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
            .link-input-group { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="app-brand">
            <span>⚡ Moduref PHP Demo</span>
            <span class="badge-tech">Native PDO</span>
        </div>
        <main class="main-card">
            ' . $flashHtml . '
            ' . $content . '
        </main>
    </div>
    ' . $extraScripts . '
</body>
</html>';
}
