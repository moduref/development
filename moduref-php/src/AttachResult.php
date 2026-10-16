<?php

declare(strict_types=1);

namespace Moduref;

final class AttachResult
{
    public function __construct(
        public readonly bool $attached,
        public readonly ?string $reason = null,
        public readonly ?string $referrerUuid = null,
        public readonly ?string $referrerUsername = null,
    ) {
    }

    /**
     * Apakah penautan referral dan penambahan poin berhasil.
     */
    public function isAttached(): bool
    {
        return $this->attached;
    }

    /**
     * Mengonversi hasil menjadi representasi array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'attached' => $this->attached,
        ];

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }
        if ($this->referrerUuid !== null) {
            $data['referrer_uuid'] = $this->referrerUuid;
        }
        if ($this->referrerUsername !== null) {
            $data['referrer_username'] = $this->referrerUsername;
        }

        return $data;
    }
}
