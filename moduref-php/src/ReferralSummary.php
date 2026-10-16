<?php

declare(strict_types=1);

namespace Moduref;

final class ReferralSummary
{
    public readonly int $totalReferrals;
    public readonly ?string $referrerUuid;

    public function __construct(
        public readonly int $referralPoints,
        public readonly ?string $referralAttribution,
    ) {
        $this->totalReferrals = $this->referralPoints;
        $this->referrerUuid = $this->referralAttribution;
    }

    /**
     * Mengonversi ringkasan menjadi array.
     *
     * @return array{referral_points: int, referral_attribution: ?string}
     */
    public function toArray(): array
    {
        return [
            'referral_points' => $this->referralPoints,
            'referral_attribution' => $this->referralAttribution,
        ];
    }
}
