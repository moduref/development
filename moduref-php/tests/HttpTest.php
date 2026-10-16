<?php

declare(strict_types=1);

namespace Moduref\Tests;

use Moduref\Exceptions\InvalidUuidException;
use Moduref\Http;

class HttpTest
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

    public function testCreateReferralUrl(): void
    {
        $url = Http::createReferralUrl(
            'https://example.com',
            '123e4567-e89b-12d3-a456-426614174000'
        );
        $this->assertSame('https://example.com/referral/123e4567-e89b-12d3-a456-426614174000', $url);

        // Custom path
        $urlCustom = Http::createReferralUrl(
            'http://localhost:8000/app',
            '123E4567-E89B-12D3-A456-426614174000',
            '/join'
        );
        $this->assertSame('http://localhost:8000/app/join/123e4567-e89b-12d3-a456-426614174000', $urlCustom);
    }

    public function testCreateReferralUrlRejectsInvalidInputs(): void
    {
        // Invalid protocol
        $thrown = false;
        try {
            Http::createReferralUrl('ftp://example.com', '123e4567-e89b-12d3-a456-426614174000');
        } catch (\InvalidArgumentException) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should reject non-http/https protocol');

        // Credentials in baseUrl
        $thrown = false;
        try {
            Http::createReferralUrl('https://user:pass@example.com', '123e4567-e89b-12d3-a456-426614174000');
        } catch (\InvalidArgumentException) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should reject credentials in baseUrl');

        // Invalid UUID
        $thrown = false;
        try {
            Http::createReferralUrl('https://example.com', 'not-valid');
        } catch (InvalidUuidException) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should reject invalid UUID');
    }

    public function testGetReferralUuid(): void
    {
        $cookies = [
            'referral_uuid' => '123E4567-E89B-12D3-A456-426614174000',
            'other_cookie' => 'test',
        ];

        $uuid = Http::getReferralUuid('referral_uuid', $cookies);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $uuid);

        // Missing cookie
        $this->assertSame(null, Http::getReferralUuid('missing_cookie', $cookies));

        // Malformed UUID in cookie
        $badCookies = ['referral_uuid' => 'attacker-malicious-value'];
        $this->assertSame(null, Http::getReferralUuid('referral_uuid', $badCookies));
    }
}
