<?php

declare(strict_types=1);

namespace Moduref\Adapters;

interface ReferralAdapterInterface
{
    /**
     * Mengembalikan dialek SQL adapter ('mysql', 'postgres', 'sqlite', 'standard').
     */
    public function getDialect(): string;

    /**
     * Mengembalikan klausa row-locking (contoh: " FOR UPDATE" atau kosong "").
     */
    public function getForUpdateClause(): string;

    /**
     * Melakukan escaping/quoting nama kolom atau tabel.
     */
    public function quoteIdentifier(string $value, string $label = 'identifier'): string;

    /**
     * Menjalankan query SELECT dan mengembalikan array baris sebagai associative array.
     *
     * @param string $sql Query SQL dengan placeholder '?'.
     * @param array<int, mixed> $params Parameter query.
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array;

    /**
     * Menjalankan query UPDATE/INSERT/DELETE dan mengembalikan jumlah baris yang terpengaruh (affected rows).
     *
     * @param string $sql Query SQL dengan placeholder '?'.
     * @param array<int, mixed> $params Parameter query.
     * @return int Jumlah baris yang terpengaruh.
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * Menjalankan callback di dalam transaksi database atomik.
     *
     * @template T
     * @param callable(ReferralAdapterInterface): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed;
}
