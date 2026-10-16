<?php

declare(strict_types=1);

namespace Moduref;

use Moduref\Adapters\ReferralAdapterInterface;

class ReferralStore
{
    private readonly string $table;
    private readonly string $uuidColumn;
    private readonly string $usernameColumn;
    private readonly string $pointsColumn;
    private readonly string $attributionColumn;
    private readonly string $selectedFields;

    /**
     * @param array{
     *   uuid?: string,
     *   username?: string,
     *   referral_points?: string,
     *   referral_attribution?: string
     * } $columns Pemetaan nama kolom kustom.
     */
    public function __construct(
        private readonly ReferralAdapterInterface $adapter,
        private readonly string $tableName = 'users',
        array $columns = [],
    ) {
        $configuredColumns = array_merge(Constants::DEFAULT_COLUMNS, $columns);
        $dialect = $this->adapter->getDialect();

        $this->table = Validation::quoteTableName($this->tableName, $dialect);
        $this->uuidColumn = $this->adapter->quoteIdentifier($configuredColumns['uuid'], 'columns.uuid');
        $this->usernameColumn = $this->adapter->quoteIdentifier($configuredColumns['username'], 'columns.username');
        $this->pointsColumn = $this->adapter->quoteIdentifier($configuredColumns['referral_points'], 'columns.referral_points');
        $this->attributionColumn = $this->adapter->quoteIdentifier($configuredColumns['referral_attribution'], 'columns.referral_attribution');

        $this->selectedFields = "{$this->uuidColumn} AS uuid, {$this->usernameColumn} AS username, "
            . "{$this->pointsColumn} AS referral_points, {$this->attributionColumn} AS referral_attribution";
    }

    /**
     * Memvalidasi bahwa tabel dan keempat kolom yang dikonfigurasi ada di database.
     * Disarankan dipanggil saat server aplikasi pertama kali mulai (startup/boot).
     */
    public function initialize(): void
    {
        $this->adapter->query("SELECT {$this->selectedFields} FROM {$this->table} WHERE 1 = 0");
    }

    /**
     * Menautkan user baru ke pengundangnya dan menaikkan total poin pengundang (+1) secara atomik.
     * Menolak referral diri sendiri, pengundang yang tidak ada, atau user yang sudah memiliki attribution.
     */
    public function attach(string $userUuid, string $referrerUuid): AttachResult
    {
        $referredUuid = Validation::assertUuid($userUuid, 'userUuid');
        $inviterUuid = Validation::assertUuid($referrerUuid, 'referrerUuid');

        if ($referredUuid === $inviterUuid) {
            return new AttachResult(false, 'self_referral');
        }

        return $this->adapter->transaction(function (ReferralAdapterInterface $adapter) use ($referredUuid, $inviterUuid): AttachResult {
            $lockClause = $adapter->getForUpdateClause();

            // 1. Ambil data pengundang (dengan row lock)
            $inviterRows = $adapter->query(
                "SELECT {$this->uuidColumn} AS uuid, {$this->usernameColumn} AS username "
                . "FROM {$this->table} WHERE {$this->uuidColumn} = ?{$lockClause}",
                [$inviterUuid]
            );

            if (empty($inviterRows)) {
                return new AttachResult(false, 'referrer_not_found');
            }
            $inviter = $inviterRows[0];

            // 2. Ambil data pengguna baru (dengan row lock)
            $inviteeRows = $adapter->query(
                "SELECT {$this->uuidColumn} AS uuid, {$this->attributionColumn} AS referral_attribution "
                . "FROM {$this->table} WHERE {$this->uuidColumn} = ?{$lockClause}",
                [$referredUuid]
            );

            if (empty($inviteeRows)) {
                return new AttachResult(false, 'user_not_found');
            }
            $invitee = $inviteeRows[0];

            if ($invitee['referral_attribution'] !== null && $invitee['referral_attribution'] !== '') {
                return new AttachResult(false, 'already_referred');
            }

            // 3. Update kolom attribution user baru dengan username pengundang
            $linked = $adapter->execute(
                "UPDATE {$this->table} SET {$this->attributionColumn} = ? "
                . "WHERE {$this->uuidColumn} = ? AND ({$this->attributionColumn} IS NULL OR {$this->attributionColumn} = '')",
                [(string) $inviter['username'], $referredUuid]
            );

            if ($linked !== 1) {
                return new AttachResult(false, 'already_referred');
            }

            // 4. Naikkan total poin pengundang (+1)
            $incremented = $adapter->execute(
                "UPDATE {$this->table} SET {$this->pointsColumn} = COALESCE({$this->pointsColumn}, 0) + 1 "
                . "WHERE {$this->uuidColumn} = ?",
                [$inviterUuid]
            );

            if ($incremented !== 1) {
                throw new \RuntimeException("Could not increment the referrer's referral_poin");
            }

            return new AttachResult(
                attached: true,
                reason: null,
                referrerUuid: $inviterUuid,
                referrerUsername: (string) $inviter['username']
            );
        });
    }

    /**
     * Mengambil ringkasan poin referral dan username pengundang untuk suatu UUID pengguna.
     */
    public function getReferralSummary(string $userUuid): ?ReferralSummary
    {
        $uuid = Validation::assertUuid($userUuid, 'userUuid');

        $rows = $this->adapter->query(
            "SELECT {$this->pointsColumn} AS referral_points, {$this->attributionColumn} AS referral_attribution "
            . "FROM {$this->table} WHERE {$this->uuidColumn} = ?",
            [$uuid]
        );

        if (empty($rows)) {
            return null;
        }

        $row = $rows[0];
        $points = isset($row['referral_points']) ? (int) $row['referral_points'] : 0;
        $attribution = isset($row['referral_attribution']) && $row['referral_attribution'] !== ''
            ? (string) $row['referral_attribution']
            : null;

        return new ReferralSummary($points, $attribution);
    }

    public function getAdapter(): ReferralAdapterInterface
    {
        return $this->adapter;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }
}
