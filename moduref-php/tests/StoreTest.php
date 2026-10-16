<?php

declare(strict_types=1);

namespace Moduref\Tests;

use Moduref\Adapters\PdoAdapter;
use Moduref\Moduref;
use Moduref\ReferralStore;

class StoreTest
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

    private function createTestDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec('
            CREATE TABLE users (
                uuid TEXT PRIMARY KEY,
                username TEXT UNIQUE NOT NULL,
                referral_poin INTEGER NOT NULL DEFAULT 0,
                referral_attribution TEXT NULL
            );
        ');

        return $pdo;
    }

    public function testStoreInitialize(): void
    {
        $pdo = $this->createTestDatabase();
        $store = Moduref::createPdoStore($pdo, 'users');

        // Should succeed without throwing
        $store->initialize();
        $this->assertTrue(true);

        // Non-existent table should throw
        $badStore = Moduref::createPdoStore($pdo, 'non_existent_table');
        $thrown = false;
        try {
            $badStore->initialize();
        } catch (\PDOException) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should throw PDOException on missing table');
    }

    public function testSelfReferralPrevention(): void
    {
        $pdo = $this->createTestDatabase();
        $store = Moduref::createPdoStore($pdo);

        $sameUuid = '123e4567-e89b-12d3-a456-426614174000';
        $result = $store->attach($sameUuid, $sameUuid);

        $this->assertSame(false, $result->isAttached());
        $this->assertSame('self_referral', $result->reason);
    }

    public function testReferrerNotFound(): void
    {
        $pdo = $this->createTestDatabase();
        $store = Moduref::createPdoStore($pdo);

        $userUuid = '11111111-1111-1111-1111-111111111111';
        $referrerUuid = '22222222-2222-2222-2222-222222222222';

        $pdo->exec("INSERT INTO users (uuid, username) VALUES ('{$userUuid}', 'john_doe')");

        $result = $store->attach($userUuid, $referrerUuid);
        $this->assertSame(false, $result->isAttached());
        $this->assertSame('referrer_not_found', $result->reason);
    }

    public function testUserNotFound(): void
    {
        $pdo = $this->createTestDatabase();
        $store = Moduref::createPdoStore($pdo);

        $userUuid = '11111111-1111-1111-1111-111111111111';
        $referrerUuid = '22222222-2222-2222-2222-222222222222';

        $pdo->exec("INSERT INTO users (uuid, username) VALUES ('{$referrerUuid}', 'inviter_jane')");

        $result = $store->attach($userUuid, $referrerUuid);
        $this->assertSame(false, $result->isAttached());
        $this->assertSame('user_not_found', $result->reason);
    }

    public function testSuccessfulAttachmentAndPointsIncrement(): void
    {
        $pdo = $this->createTestDatabase();
        $store = Moduref::createPdoStore($pdo);

        $inviterUuid = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $newUserUuid = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $pdo->exec("INSERT INTO users (uuid, username, referral_poin) VALUES ('{$inviterUuid}', 'inviter_bob', 0)");
        $pdo->exec("INSERT INTO users (uuid, username, referral_poin) VALUES ('{$newUserUuid}', 'newbie_alice', 0)");

        $result = $store->attach($newUserUuid, $inviterUuid);

        $this->assertSame(true, $result->isAttached());
        $this->assertSame(null, $result->reason);
        $this->assertSame($inviterUuid, $result->referrerUuid);
        $this->assertSame('inviter_bob', $result->referrerUsername);

        // Check inviter points
        $inviterSummary = $store->getReferralSummary($inviterUuid);
        $this->assertSame(1, $inviterSummary?->referralPoints);
        $this->assertSame(null, $inviterSummary?->referralAttribution);

        // Check newbie attribution
        $newbieSummary = $store->getReferralSummary($newUserUuid);
        $this->assertSame(0, $newbieSummary?->referralPoints);
        $this->assertSame('inviter_bob', $newbieSummary?->referralAttribution);

        // Second attach on already referred user should fail
        $secondResult = $store->attach($newUserUuid, $inviterUuid);
        $this->assertSame(false, $secondResult->isAttached());
        $this->assertSame('already_referred', $secondResult->reason);

        // Inviter points should still be 1 (not double incremented)
        $inviterSummaryAfter = $store->getReferralSummary($inviterUuid);
        $this->assertSame(1, $inviterSummaryAfter?->referralPoints);
    }

    public function testCustomColumnsMapping(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('
            CREATE TABLE members (
                id_uuid TEXT PRIMARY KEY,
                uname TEXT UNIQUE NOT NULL,
                points INTEGER NOT NULL DEFAULT 0,
                invited_by TEXT NULL
            );
        ');

        $store = new ReferralStore(
            new PdoAdapter($pdo),
            'members',
            [
                'uuid' => 'id_uuid',
                'username' => 'uname',
                'referral_points' => 'points',
                'referral_attribution' => 'invited_by',
            ]
        );

        $store->initialize();

        $inviterUuid = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $newUserUuid = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $pdo->exec("INSERT INTO members (id_uuid, uname, points) VALUES ('{$inviterUuid}', 'boss', 5)");
        $pdo->exec("INSERT INTO members (id_uuid, uname, points) VALUES ('{$newUserUuid}', 'worker', 0)");

        $result = $store->attach($newUserUuid, $inviterUuid);
        $this->assertSame(true, $result->isAttached());

        $summary = $store->getReferralSummary($inviterUuid);
        $this->assertSame(6, $summary?->referralPoints);

        $newUserSummary = $store->getReferralSummary($newUserUuid);
        $this->assertSame('boss', $newUserSummary?->referralAttribution);
    }
}
