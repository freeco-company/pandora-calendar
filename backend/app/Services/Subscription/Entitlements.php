<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use Carbon\CarbonImmutable;

final class Entitlements
{
    public function __construct(
        public readonly bool $premium,
        public readonly ?Subscription $activeSubscription = null,
        public readonly ?CarbonImmutable $premiumUntil = null,
    ) {}

    public static function free(): self
    {
        return new self(false);
    }

    public function isPremium(): bool
    {
        return $this->premium;
    }

    public function toArray(): array
    {
        return [
            'premium' => $this->premium,
            'premium_until' => $this->premiumUntil?->toAtomString(),
            'product_id' => $this->activeSubscription?->product_id,
            'platform' => $this->activeSubscription?->platform,
            'auto_renew' => $this->activeSubscription?->auto_renew ?? false,
        ];
    }
}
