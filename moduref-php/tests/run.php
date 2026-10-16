<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$testFiles = [
    __DIR__ . '/ValidationTest.php',
    __DIR__ . '/HttpTest.php',
    __DIR__ . '/PdoAdapterTest.php',
    __DIR__ . '/StoreTest.php',
    __DIR__ . '/BundleTest.php',
];

$passed = 0;
$failed = 0;
$startTime = microtime(true);

echo "\n> moduref-php test suite (PHP " . PHP_VERSION . ")\n\n";

foreach ($testFiles as $file) {
    require_once $file;
    $className = 'Moduref\\Tests\\' . basename($file, '.php');

    if (!class_exists($className)) {
        continue;
    }

    $instance = new $className();
    $methods = get_class_methods($instance);

    foreach ($methods as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }

        $testStart = microtime(true);
        try {
            $instance->$method();
            $durationMs = round((microtime(true) - $testStart) * 1000, 2);
            echo "✔ {$className}::{$method} ({$durationMs}ms)\n";
            $passed++;
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $testStart) * 1000, 2);
            echo "✖ {$className}::{$method} ({$durationMs}ms)\n";
            echo "  Error: " . $e->getMessage() . "\n";
            echo "  At: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
            $failed++;
        }
    }
}

$totalDuration = round((microtime(true) - $startTime) * 1000, 2);

echo "\nℹ tests " . ($passed + $failed) . "\n";
echo "ℹ pass {$passed}\n";
echo "ℹ fail {$failed}\n";
echo "ℹ duration_ms {$totalDuration}\n\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
