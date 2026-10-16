<?php

declare(strict_types=1);

namespace Moduref\Adapters;

use Moduref\Validation;

class PdoAdapter implements ReferralAdapterInterface
{
    private readonly string $dialect;
    private readonly string $forUpdateClause;

    public function __construct(
        private readonly \PDO $pdo,
        ?string $dialect = null,
    ) {
        $driver = $dialect ?? (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $normalized = strtolower($driver);

        if ($normalized === 'mysql') {
            $this->dialect = 'mysql';
            $this->forUpdateClause = ' FOR UPDATE';
        } elseif ($normalized === 'pgsql' || $normalized === 'postgres' || $normalized === 'postgresql') {
            $this->dialect = 'postgres';
            $this->forUpdateClause = ' FOR UPDATE';
        } elseif ($normalized === 'sqlite') {
            $this->dialect = 'sqlite';
            $this->forUpdateClause = '';
        } else {
            $this->dialect = 'standard';
            $this->forUpdateClause = '';
        }

        // Pastikan PDO melempar PDOException saat terjadi kegagalan query
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public static function fromPdo(\PDO $pdo): self
    {
        return new self($pdo);
    }

    public static function mysql(\PDO $pdo): self
    {
        return new self($pdo, 'mysql');
    }

    public static function postgres(\PDO $pdo): self
    {
        return new self($pdo, 'postgres');
    }

    public static function sqlite(\PDO $pdo): self
    {
        return new self($pdo, 'sqlite');
    }

    public function getDialect(): string
    {
        return $this->dialect;
    }

    public function getForUpdateClause(): string
    {
        return $this->forUpdateClause;
    }

    public function quoteIdentifier(string $value, string $label = 'identifier'): string
    {
        return Validation::quoteIdentifier($value, $this->dialect, $label);
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback($this);
        }

        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
