<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Phase 0-P1 mock：把本機 User 當 Pandora Core 用。
 *
 * 限制：
 * - 仍然會在本機存 email + password_hash（P0 demo 妥協），但所有 controllers 透過 IdentityClient
 *   取資料時只拿 IdentityProfile (uuid + display_name)，不直接讀 email
 * - P1 結束時切到 HttpIdentityClient，本機 email/password 欄位移除
 */
final class MockIdentityClient implements IdentityClient
{
    public function lookupByUuid(string $uuid): ?IdentityProfile
    {
        $user = User::where('identity_uuid', $uuid)->first();
        if (! $user) {
            return null;
        }

        return $this->toProfile($user);
    }

    public function lookupByEmail(string $email): ?IdentityProfile
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            return null;
        }

        if (! $user->identity_uuid) {
            $user->identity_uuid = (string) Str::uuid();
            $user->identity_synced_at = now();
            $user->save();
        }

        return $this->toProfile($user);
    }

    public function exchangeAccessToken(string $accessToken): ?IdentityProfile
    {
        // Mock: token = "mock|<uuid>"; not real OAuth.
        if (! str_starts_with($accessToken, 'mock|')) {
            return null;
        }
        $uuid = substr($accessToken, 5);

        return $this->lookupByUuid($uuid);
    }

    public function listConsentScopes(string $uuid): array
    {
        return ['profile.read', 'cycle.write', 'body_rhythm.publish'];
    }

    private function toProfile(User $user): IdentityProfile
    {
        $linked = (bool) ($user->mother_customer_id ?? false);
        $hasPurchase = (bool) ($user->mother_total_orders ?? 0);

        return new IdentityProfile(
            uuid: $user->identity_uuid,
            displayName: $user->name,
            avatarUrl: null,
            scopes: ['profile.read', 'cycle.write', 'body_rhythm.publish'],
            linkedToMother: $linked,
            hasMotherPurchase: $hasPurchase,
        );
    }
}
