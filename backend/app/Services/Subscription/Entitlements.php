<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use Carbon\CarbonImmutable;

final class Entitlements
{
    public const TIER_FREE = 'free';
    public const TIER_TRIAL = 'trial';
    public const TIER_PREMIUM = 'premium';

    public function __construct(
        public readonly bool $premium,
        public readonly ?Subscription $activeSubscription = null,
        public readonly ?CarbonImmutable $premiumUntil = null,
        public readonly bool $inTrial = false,
        public readonly ?int $trialDaysRemaining = null,
        public readonly ?CarbonImmutable $trialEndsAt = null,
        public readonly bool $trialUsed = false,
        public readonly ?string $trialSource = null,
    ) {}

    public static function free(bool $trialUsed = false): self
    {
        return new self(false, null, null, false, null, null, $trialUsed, null);
    }

    public function isPremium(): bool
    {
        return $this->premium;
    }

    public function tier(): string
    {
        if (! $this->premium) {
            return self::TIER_FREE;
        }

        return $this->activeSubscription !== null ? self::TIER_PREMIUM : self::TIER_TRIAL;
    }

    public function toArray(): array
    {
        return [
            'premium' => $this->premium,
            'tier' => $this->tier(),
            'subscription_active' => $this->activeSubscription !== null,
            'premium_until' => $this->premiumUntil?->toAtomString(),
            'product_id' => $this->activeSubscription?->product_id,
            'platform' => $this->activeSubscription?->platform,
            'auto_renew' => $this->activeSubscription?->auto_renew ?? false,
            'trial' => [
                'is_trial' => $this->inTrial,
                'days_remaining' => $this->trialDaysRemaining,
                'ends_at' => $this->trialEndsAt?->toAtomString(),
                'trial_used' => $this->trialUsed,
                'source' => $this->trialSource,
            ],
        ];
    }
}
