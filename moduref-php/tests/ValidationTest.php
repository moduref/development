<?php

declare(strict_types=1);

namespace Moduref\Tests;

use Moduref\Exceptions\InvalidIdentifierException;
use Moduref\Exceptions\InvalidPathException;
use Moduref\Exceptions\InvalidUuidException;
use Moduref\Validation;

class ValidationTest
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

    public function testNormalizeUuid(): void
    {
        $this->assertSame(
            '123e4567-e89b-12d3-a456-426614174000',
            Validation::normalizeUuid('123E4567-E89B-12D3-A456-426614174000')
        );
        $this->assertSame(
            '123e4567-e89b-12d3-a456-426614174000',
            Validation::normalizeUuid(' 123e4567-e89b-12d3-a456-426614174000 ')
        );
        $this->assertSame(null, Validation::normalizeUuid('invalid-uuid'));
        $this->assertSame(null, Validation::normalizeUuid(''));
        $this->assertSame(null, Validation::normalizeUuid(null));
    }

    public function testAssertUuid(): void
    {
        $valid = '123e4567-e89b-12d3-a456-426614174000';
        $this->assertSame($valid, Validation::assertUuid($valid, 'userUuid'));

        $thrown = false;
        try {
            Validation::assertUuid('not-a-uuid', 'userUuid');
        } catch (InvalidUuidException $e) {
            $thrown = true;
            $this->assertTrue(str_contains($e->getMessage(), 'userUuid must be a valid UUID string'));
        }
        $this->assertTrue($thrown, 'Should throw InvalidUuidException on invalid UUID');
    }

    public function testValidateLocalPath(): void
    {
        $this->assertSame('/register', Validation::validateLocalPath('/register', 'registerPath'));
        $this->assertSame('/auth/login', Validation::validateLocalPath('/auth/login', 'loginPath'));

        $this->assertSame(
            '/register?step=2',
            Validation::validateLocalPath('/register?step=2', 'registerPath', true)
        );

        $invalidCases = [
            'register', // no leading slash
            '//evil.com', // double slash
            '/path\\traversal', // backslash
            'https://evil.com/register', // external url
        ];

        foreach ($invalidCases as $case) {
            $thrown = false;
            try {
                Validation::validateLocalPath($case, 'test');
            } catch (InvalidPathException) {
                $thrown = true;
            }
            $this->assertTrue($thrown, "Should reject invalid path: {$case}");
        }

        // Query not allowed by default
        $thrown = false;
        try {
            Validation::validateLocalPath('/register?foo=bar', 'path', false);
        } catch (InvalidPathException) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should reject query string when allowQuery is false');
    }

    public function testValidateCookieName(): void
    {
        $this->assertSame('referral_uuid', Validation::validateCookieName('referral_uuid'));
        $this->assertSame('my-cookie.123', Validation::validateCookieName('my-cookie.123'));

        $thrown = false;
        try {
            Validation::validateCookieName('bad cookie;name');
        } catch (\InvalidArgumentException) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should reject invalid cookie name with semicolon or spaces');
    }

    public function testQuoteIdentifier(): void
    {
        $this->assertSame('`users`', Validation::quoteIdentifier('users', 'mysql'));
        $this->assertSame('"users"', Validation::quoteIdentifier('users', 'postgres'));
        $this->assertSame('"users"', Validation::quoteIdentifier('users', 'sqlite'));
        $this->assertSame('"users"', Validation::quoteIdentifier('users', 'standard'));

        $thrown = false;
        try {
            Validation::quoteIdentifier('bad; DROP TABLE users--', 'mysql');
        } catch (InvalidIdentifierException) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should reject SQL injection in identifier');
    }

    public function testQuoteTableName(): void
    {
        $this->assertSame('`users`', Validation::quoteTableName('users', 'mysql'));
        $this->assertSame('"users"', Validation::quoteTableName('users', 'postgres'));
        $this->assertSame('"public"."users"', Validation::quoteTableName('public.users', 'postgres'));
        $this->assertSame('`auth`.`users`', Validation::quoteTableName('auth.users', 'mysql'));

        $thrown = false;
        try {
            Validation::quoteTableName('users; DROP TABLE', 'postgres');
        } catch (InvalidIdentifierException) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should reject SQL injection in table name');
    }
}
