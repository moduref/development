# Integrasi Framework Web dengan Moduref PHP

Panduan integrasi langkah-demi-langkah Moduref pada framework PHP terpopuler: **Laravel**, **Symfony**, **CodeIgniter 4**, dan **Native PHP**.

---

## 1. Integrasi Laravel (10.x / 11.x)

Moduref dapat langsung digunakan di Laravel tanpa memerlukan package pihak ketiga.

### A. Rute Penangkap Referral (`routes/web.php`)
```php
use Moduref\Moduref;
use Illuminate\Support\Facades\Route;

// Menangkap tautan referral: /referral/{uuid} -> simpan cookie -> redirect ke register
Route::get('/referral/{uuid}', function (string $uuid) {
    if (!Moduref::isValidUuid($uuid)) {
        return redirect()->route('register');
    }

    return redirect()->route('register')->cookie(
        name: Moduref::COOKIE_NAME,
        value: $uuid,
        minutes: 60 * 24 * 30, // 30 hari
        path: '/',
        domain: null,
        secure: config('app.env') === 'production',
        httpOnly: true,
        raw: false,
        sameSite: 'lax'
    );
});
```

### B. Registrasi Controller (`RegisteredUserController.php`)
```php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Moduref\Moduref;

class RegisteredUserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed'],
        ]);

        return DB::transaction(function () use ($request) {
            $newUuid = (string) Str::uuid();

            // 1. Simpan pengguna baru
            $user = User::create([
                'uuid'     => $newUuid,
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => bcrypt($request->password),
            ]);

            // 2. Hubungkan ke Moduref dengan koneksi PDO aktif
            $pdo = DB::connection()->getPdo();
            $store = Moduref::createPdoStore($pdo, 'users');

            // 3. Tautkan referral secara otomatis dari cookie
            $result = Moduref::attachFromCookie(
                cookies: $request->cookies->all(),
                userUuid: $newUuid,
                store: $store,
                executor: $pdo
            );

            // 4. Hapus cookie referral setelah digunakan
            return redirect('/dashboard')
                ->withoutCookie(Moduref::COOKIE_NAME)
                ->with('status', $result->attached ? 'Terdaftar via referral!' : 'Akun berhasil dibuat!');
        });
    }
}
```

---

## 2. Integrasi Symfony (6.x / 7.x)

### A. Controller Penanganan Referral
```php
namespace App\Controller;

use Moduref\Moduref;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReferralController extends AbstractController
{
    #[Route('/referral/{uuid}', name: 'app_referral')]
    public function index(string $uuid): Response
    {
        if (!Moduref::isValidUuid($uuid)) {
            return $this->redirectToRoute('app_register');
        }

        $response = $this->redirectToRoute('app_register');
        $response->headers->setCookie(
            Cookie::create(Moduref::COOKIE_NAME)
                ->withValue($uuid)
                ->withExpires(new \DateTime('+30 days'))
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );

        return $response;
    }
}
```

### B. Controller Registrasi dengan Doctrine DBAL
```php
namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Moduref\Moduref;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationController extends AbstractController
{
    public function register(Request $request, EntityManagerInterface $em): Response
    {
        // ... Proses form registrasi & pembuatan entitas User ...
        $userUuid = $user->getUuid();

        // Ambil PDO bawaan dari koneksi Doctrine
        $pdo = $em->getConnection()->getNativeConnection();
        $store = Moduref::createPdoStore($pdo, 'users');

        // Tautkan referral
        $result = Moduref::attachFromCookie($request->cookies->all(), $userUuid, $store, $pdo);

        $response = $this->redirectToRoute('app_dashboard');
        $response->headers->clearCookie(Moduref::COOKIE_NAME);

        return $response;
    }
}
```

---

## 3. Integrasi CodeIgniter 4

```php
namespace App\Controllers;

use CodeIgniter\Controller;
use Moduref\Moduref;

class Referral extends Controller
{
    public function index(string $uuid)
    {
        if (Moduref::isValidUuid($uuid)) {
            set_cookie(Moduref::COOKIE_NAME, $uuid, 30 * 86400, '', '/', '', false, true, 'Lax');
        }
        return redirect()->to('/register');
    }
}
```

---

## 4. Integrasi PHP Native / Procedural

Bekerja langsung tanpa framework dengan fungsi global bawaan PHP:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Moduref\Moduref;

$pdo = new PDO('mysql:host=localhost;dbname=demo', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$store = Moduref::createPdoStore($pdo, 'users');
$store->initialize();

// 1. Endpoint penangkap rute (?ref=UUID)
if (isset($_GET['ref'])) {
    $refUuid = $_GET['ref'];
    if (Moduref::isValidUuid($refUuid)) {
        setcookie(Moduref::COOKIE_NAME, $refUuid, [
            'expires'  => time() + (30 * 86400),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    header('Location: /register.php');
    exit;
}

// 2. Endpoint registrasi pengguna baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUserUuid = Moduref::generateUuid();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO users (uuid, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$newUserUuid, $_POST['email'], password_hash($_POST['password'], PASSWORD_DEFAULT)]);

        // Tautkan referral secara otomatis
        $result = Moduref::attachFromCookie($_COOKIE, $newUserUuid, $store, $pdo);

        $pdo->commit();

        // Bersihkan cookie
        setcookie(Moduref::COOKIE_NAME, '', time() - 3600, '/');

        echo "Pendaftaran selesai! Status referral: " . ($result->attached ? "Terhubung ke " . $result->referrerUuid : "Pengguna Organik");
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo "Gagal: " . htmlspecialchars($e->getMessage());
    }
}
```
