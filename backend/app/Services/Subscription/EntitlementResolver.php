<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;

class EntitlementResolver
{
    public function resolve(User $user): Entitlements
    {
        $sub = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'grace'])
            ->orderByDesc('ends_at')
            ->first();

        if (! $sub || ! $sub->isActive()) {
            return Entitlements::free();
        }

        return new Entitlements(
            premium: true,
            activeSubscription: $sub,
            premiumUntil: $sub->ends_at ? CarbonImmutable::parse($sub->ends_at) : null,
        );
    }
}
