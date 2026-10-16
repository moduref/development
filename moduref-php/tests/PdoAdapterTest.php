<?php

declare(strict_types=1);

namespace Moduref\Tests;

use Moduref\Adapters\PdoAdapter;

class PdoAdapterTest
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

    public function testPdoAdapterBasics(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $adapter = new PdoAdapter($pdo);

        $this->assertSame('sqlite', $adapter->getDialect());
        $this->assertSame('', $adapter->getForUpdateClause());
        $this->assertSame('"users"', $adapter->quoteIdentifier('users'));

        $adapter->execute('CREATE TABLE test_items (id INT, name TEXT)');
        $affected = $adapter->execute('INSERT INTO test_items (id, name) VALUES (?, ?)', [1, 'item1']);
        $this->assertSame(1, $affected);

        $rows = $adapter->query('SELECT * FROM test_items WHERE id = ?', [1]);
        $this->assertSame(1, count($rows));
        $this->assertSame('item1', $rows[0]['name']);
    }

    public function testPdoAdapterTransactions(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $adapter = new PdoAdapter($pdo);
        $adapter->execute('CREATE TABLE balances (id INT, amount INT)');
        $adapter->execute('INSERT INTO balances (id, amount) VALUES (1, 100)');

        // Successful transaction
        $adapter->transaction(function (PdoAdapter $trx) {
            $trx->execute('UPDATE balances SET amount = amount + 50 WHERE id = 1');
        });

        $rows = $adapter->query('SELECT amount FROM balances WHERE id = 1');
        $this->assertSame(150, (int) $rows[0]['amount']);

        // Failed transaction (should rollback)
        $thrown = false;
        try {
            $adapter->transaction(function (PdoAdapter $trx) {
                $trx->execute('UPDATE balances SET amount = amount + 50 WHERE id = 1');
                throw new \RuntimeException('Intentional failure');
            });
        } catch (\RuntimeException) {
            $thrown = true;
        }
        $this->assertTrue($thrown);

        // Amount should still be 150
        $rowsAfter = $adapter->query('SELECT amount FROM balances WHERE id = 1');
        $this->assertSame(150, (int) $rowsAfter[0]['amount']);
    }

    public function testDialectFactories(): void
    {
        $pdo = new \PDO('sqlite::memory:');

        $mysql = PdoAdapter::mysql($pdo);
        $this->assertSame('mysql', $mysql->getDialect());
        $this->assertSame(' FOR UPDATE', $mysql->getForUpdateClause());
        $this->assertSame('`users`', $mysql->quoteIdentifier('users'));

        $postgres = PdoAdapter::postgres($pdo);
        $this->assertSame('postgres', $postgres->getDialect());
        $this->assertSame(' FOR UPDATE', $postgres->getForUpdateClause());
        $this->assertSame('"users"', $postgres->quoteIdentifier('users'));
    }
}
