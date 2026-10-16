<?php

declare(strict_types=1);

namespace Moduref\Tests;

class BundleTest
{
    private function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \AssertionError("Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ". {$message}");
        }
    }

    private function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new \AssertionError("Expected true, got false. {$message}");
        }
    }

    public function testMinifiedBundleInSeparateProcess(): void
    {
        $code = <<<'PHP'
        require_once __DIR__ . '/../dist/moduref.min.php';

        $url = \Moduref\createReferralUrl('https://example.com', '123e4567-e89b-12d3-a456-426614174000');
        if ($url !== 'https://example.com/referral/123e4567-e89b-12d3-a456-426614174000') {
            exit(1);
        }

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (uuid TEXT PRIMARY KEY, username TEXT, referral_poin INT DEFAULT 0, referral_attribution TEXT);');

        $store = \Moduref\Moduref::createPdoStore($pdo);
        $store->initialize();

        $inviter = '11111111-1111-1111-1111-111111111111';
        $user = '22222222-2222-2222-2222-222222222222';
        $pdo->exec("INSERT INTO users (uuid, username) VALUES ('{$inviter}', 'boss'), ('{$user}', 'worker')");

        $result = $store->attach($user, $inviter);
        if (!$result->isAttached() || $result->referrerUsername !== 'boss') {
            exit(2);
        }

        $summary = $store->getReferralSummary($inviter);
        if ($summary->referralPoints !== 1) {
            exit(3);
        }

        echo "BUNDLE_OK";
        PHP;

        $tempFile = __DIR__ . '/temp_bundle_test.php';
        file_put_contents($tempFile, "<?php\n" . $code);

        $output = shell_exec("php " . escapeshellarg($tempFile));
        @unlink($tempFile);

        $this->assertSame('BUNDLE_OK', trim((string) $output), 'Minified bundle execution must succeed in isolated process');
    }
}
