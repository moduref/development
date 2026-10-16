<?php

declare(strict_types=1);

namespace Moduref;

final class Constants
{
    /**
     * Nama default untuk cookie pelacak referral.
     */
    public const DEFAULT_COOKIE_NAME = 'referral_uuid';

    /**
     * Masa berlaku default cookie referral (30 hari dalam detik untuk PHP setcookie).
     */
    public const DEFAULT_COOKIE_MAX_AGE = 30 * 24 * 60 * 60; // 2592000 detik

    /**
     * Regex pola UUID standar (v1-v5).
     */
    public const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Regex validasi identifier SQL aman (mencegah SQL Injection pada nama tabel/kolom).
     */
    public const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_$]*$/';

    /**
     * Pemetaan nama kolom default pada tabel pengguna.
     */
    public const DEFAULT_COLUMNS = [
        'uuid' => 'uuid',
        'username' => 'username',
        'referral_points' => 'referral_poin',
        'referral_attribution' => 'referral_attribution',
    ];

    private function __construct()
    {
    }
}
