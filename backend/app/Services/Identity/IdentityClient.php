<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Support\Sentry\SentryHelper;
use Lcobucci\JWT\Token\Plain;

/**
 * Calendar 對 Pandora Core Identity 的 facade（ADR-007）。
 *
 * 提供：
 *   - resolveFromJwt(bearer): 驗 JWT + 取本地 mirror（沒有則建 stub）
 *   - findOrCreateMirror(uuid): 純本地 upsert，給 reconcile worker 用
 *
 * 嚴守 ADR-007 §2.3：API 不暴露任何 PII helper（沒有 fetchUserPII()）。
 * 需要 PII 時必須走 Pandora Core 的 GET /api/v1/internal/users/{uuid}（尚未實作於 PC，
 * Phase 5+ 才會接）。本實作只搞定身份驗證 + minimal mirror。
 *
 * 取代舊的 lookupByUuid / lookupByEmail / exchangeAccessToken / listConsentScopes
 * 介面 — 那些 PC 端從未實作；改為 mirror meal 的 resolveFromJwt pattern。
 */
class IdentityClient
{
    public function __construct(private PlatformJwtVerifier $verifier) {}

    /**
     * 驗 JWT + 取 mirror。失敗回 null。
     *
     * @return ?array{token: Plain, user: User}
     */
    public function resolveFromJwt(string $bearerToken): ?array
    {
        try {
            $verified = $this->verifier->verify($bearerToken);
        } catch (\Throwable $e) {
            // verifier 預期回 null 而非 throw，但若意外 throw → 報
            SentryHelper::captureException($e, 'oauth', [
                'stage' => 'jwt_verify',
            ]);
            return null;
        }

        if ($verified === null) {
            // verifier 自己內部 log；不報 — 預期的 token 過期 / 簽名錯算 user error
            SentryHelper::addBreadcrumb('oauth.jwt', 'jwt verify returned null', []);
            return null;
        }

        $uuid = $verified->claims()->get('sub');
        if (! is_string($uuid) || $uuid === '') {
            SentryHelper::captureMessage('JWT missing sub claim', 'warning', 'oauth', []);
            return null;
        }

        $user = $this->findOrCreateMirror($uuid);

        return ['token' => $verified, 'user' => $user];
    }

    /**
     * 確保本地有一筆 users (identity_uuid)，沒有則建 stub。
     * display_name / avatar_url / subscription_tier 由 reconcile worker 之後填入。
     */
    public function findOrCreateMirror(string $uuid): User
    {
        return User::query()->firstOrCreate(
            ['identity_uuid' => $uuid],
            [
                // legacy users.name 仍 NOT NULL（dev SQLite + prod 既有資料）；
                // 這裡用 uuid prefix 當 placeholder。reconcile worker 後會被
                // PC display_name 覆蓋；P2+ 把 name 改成 nullable 後可拿掉。
                'name' => 'user-'.substr($uuid, 0, 8),
                'identity_synced_at' => null,
            ],
        );
    }

    /**
     * Webhook 收到 user.upserted / user.suspended / user.merged 時觸發 mirror upsert。
     *
     * 嚴守 ADR-007 §2.3：只取 minimal identity 欄位，PII（email/phone/...）即使
     * payload 帶了也忽略。
     *
     * @param  array<string,mixed>  $payload
     */
    public function syncFromPlatform(string $uuid, array $payload): User
    {
        $user = $this->findOrCreateMirror($uuid);

        $user->fill(array_filter([
            'display_name' => isset($payload['display_name']) ? (string) $payload['display_name'] : null,
            'avatar_url' => isset($payload['avatar_url']) ? (string) $payload['avatar_url'] : null,
            'subscription_tier' => isset($payload['subscription_tier']) ? (string) $payload['subscription_tier'] : null,
            'identity_synced_at' => isset($payload['updated_at']) ? $payload['updated_at'] : now(),
            'last_synced_at' => now(),
        ], fn ($v) => $v !== null && $v !== ''));

        $user->save();

        return $user->refresh();
    }
}
