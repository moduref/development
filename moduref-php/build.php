<?php

declare(strict_types=1);

/**
 * Moduref PHP - Standalone Bundler & Minifier
 *
 * Script ini menggabungkan semua modul di src/ menjadi satu file mandiri (zero-dependency single include)
 * dan menghasilkan dua output:
 * 1. dist/moduref.php     (Unminified bundle lengkap dengan dokumentasi PHPDoc)
 * 2. dist/moduref.min.php (Minified bundle berukuran sangat kecil tanpa komentar/whitespace)
 */

$rootDir = __DIR__;
$srcDir = $rootDir . '/src';
$distDir = $rootDir . '/dist';

if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}

echo "Building Moduref PHP bundle...\n";

// Urutan file berdasarkan dependensi (Exceptions -> Adapters -> Core -> Functions)
$groups = [
    'Moduref\Exceptions' => [
        $srcDir . '/Exceptions/ModurefException.php',
        $srcDir . '/Exceptions/InvalidUuidException.php',
        $srcDir . '/Exceptions/InvalidPathException.php',
        $srcDir . '/Exceptions/InvalidIdentifierException.php',
    ],
    'Moduref\Adapters' => [
        $srcDir . '/Adapters/ReferralAdapterInterface.php',
        $srcDir . '/Adapters/PdoAdapter.php',
    ],
    'Moduref' => [
        $srcDir . '/Constants.php',
        $srcDir . '/Validation.php',
        $srcDir . '/AttachResult.php',
        $srcDir . '/ReferralSummary.php',
        $srcDir . '/ReferralStore.php',
        $srcDir . '/Http.php',
        $srcDir . '/Moduref.php',
        $srcDir . '/functions.php',
    ],
];

function processNamespaceFiles(array $files, string $namespace): string
{
    $uses = [];
    $bodies = [];

    $escapedNs = preg_quote($namespace, '/');

    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new \RuntimeException("Could not read file: {$file}");
        }

        // Buang <?php dan declare(strict_types=1);
        $content = preg_replace('/^<\?php\s*/', '', $content);
        $content = preg_replace('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*/', '', $content);

        // Buang deklarasi namespace
        $content = preg_replace("/namespace\s+{$escapedNs}\s*;\s*/", '', $content);

        // Ekstrak dan buang semua 'use ...;' statements yang berdiri sendiri di baris baru
        if (preg_match_all('/^\s*use\s+([A-Za-z0-9_\\\s,]+);\s*$/m', $content, $matches)) {
            foreach ($matches[1] as $useTarget) {
                $cleanTarget = trim($useTarget);
                $uses[$cleanTarget] = "use {$cleanTarget};";
            }
            $content = preg_replace('/^\s*use\s+[A-Za-z0-9_\\\s,]+;\s*$/m', '', $content);
        }

        $baseName = basename($file);
        $bodies[] = "    // --- File: {$baseName} ---\n    " . str_replace("\n", "\n    ", trim($content));
    }

    $out = "namespace {$namespace} {\n";

    if (!empty($uses)) {
        ksort($uses);
        foreach ($uses as $stmt) {
            $out .= "    {$stmt}\n";
        }
        $out .= "\n";
    }

    $out .= implode("\n\n", $bodies) . "\n}\n\n";

    return $out;
}

// 1. Bangun bundle unminified
$bundle = "<?php\n\ndeclare(strict_types=1);\n\n";
$bundle .= "/**\n * Moduref for PHP (Standalone Bundle)\n * Repository: https://github.com/moduref/development\n * License: MIT\n */\n\n";

foreach ($groups as $ns => $files) {
    $bundle .= processNamespaceFiles($files, $ns);
}

$unminifiedPath = $distDir . '/moduref.php';
file_put_contents($unminifiedPath, $bundle);
$unminifiedSize = filesize($unminifiedPath);
echo "✔ Generated {$unminifiedPath} (" . round($unminifiedSize / 1024, 2) . " KB)\n";

// 2. Bangun bundle minified
$minifiedPath = $distDir . '/moduref.min.php';
$minifiedContent = php_strip_whitespace($unminifiedPath);

// Tambahkan header komentar lisensi ringkas
$header = "<?php\n/** Moduref for PHP (Minified) | MIT License | https://github.com/moduref/development */\n";
$minifiedContent = preg_replace('/^<\?php\s*/', $header, $minifiedContent);

file_put_contents($minifiedPath, $minifiedContent);
$minifiedSize = filesize($minifiedPath);
echo "✔ Generated {$minifiedPath} (" . round($minifiedSize / 1024, 2) . " KB)\n";

echo "\nBuild complete!\n";
