<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Sentry\SentryHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * P5.3 ADR-009：py-service → calendar gamification webhook receiver.
 *
 * Signature + replay 由 VerifyGamificationWebhookSignature middleware 處理；
 * controller 收到時 event_id 已 fresh、簽名已驗，可信任 body。
 *
 * 支援的 event_type（dispatch by name）：
 *   - gamification.level_up            → mirror users.total_xp / level
 *   - gamification.achievement_awarded → cache pending payload 給 frontend toast
 *   - gamification.outfit_unlocked     → 合併 codes 進 outfit_state.owned
 *
 * Payload 兼容兩個 shape：
 *   shape A（task spec 簡化版）：{user_uuid, total_xp, level, outfit_state, achievement_unlocked?:[]}
 *   shape B（meal / py-service 實際）：{event_type, event_id, pandora_user_uuid, payload:{...}}
 *
 * 找不到 user → 404；unknown event_type → 200 ignored（forward-compat）。
 */
class GamificationWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $eventType = (string) $request->json('event_type');
        $uuid = (string) ($request->json('pandora_user_uuid') ?? $request->json('user_uuid') ?? '');
        $payload = (array) ($request->json('payload') ?? $request->all());

        if ($uuid === '') {
            SentryHelper::captureMessage(
                'gamification webhook missing user_uuid in payload',
                'warning',
                'webhook.gamification',
                ['stage' => 'payload_schema', 'event_type' => $eventType]
            );

            return response()->json(['error' => 'missing user_uuid'], 422);
        }

        $user = User::where('identity_uuid', $uuid)->first();
        if ($user === null) {
            Log::info('[GamificationWebhook] unknown user', ['uuid' => $uuid, 'event_type' => $eventType]);
            // 預期會有：calendar 用戶尚未首次登入時 publisher 已 fire；breadcrumb 即可
            SentryHelper::addBreadcrumb('webhook.gamification', 'unknown user', [
                'event_type' => $eventType,
                'user_uuid' => $uuid,
            ]);

            return response()->json(['error' => 'user not found', 'uuid' => $uuid], 404);
        }

        switch ($eventType) {
            case 'gamification.level_up':
                $changed = $this->applyLevelUp($user, $payload);

                return response()->json([
                    'status' => 'ok',
                    'event_type' => $eventType,
                    'mirrored' => $changed,
                ], 200);

            case 'gamification.achievement_awarded':
                $cached = $this->cacheAchievement($user, $payload);

                return response()->json([
                    'status' => 'ok',
                    'event_type' => $eventType,
                    'cached' => $cached,
                ], 200);

            case 'gamification.outfit_unlocked':
                $added = $this->applyOutfitUnlock($user, $payload);

                return response()->json([
                    'status' => 'ok',
                    'event_type' => $eventType,
                    'mirrored' => $added,
                ], 200);

            default:
                Log::info('[GamificationWebhook] unhandled event_type', [
                    'event_type' => $eventType,
                    'event_id' => $request->json('event_id'),
                ]);
                SentryHelper::captureMessage(
                    "gamification webhook unhandled event_type: {$eventType}",
                    'warning',
                    'webhook.gamification',
                    ['stage' => 'dispatch', 'event_type' => $eventType]
                );

                return response()->json([
                    'status' => 'ignored',
                    'event_type' => $eventType,
                ], 200);
        }
    }

    /**
     * Lossy mirror：webhook 是 authoritative 但只升不降（避免亂序到達把 level 拉低）
     */
    private function applyLevelUp(User $user, array $payload): bool
    {
        $newLevel = (int) ($payload['new_level'] ?? $payload['level'] ?? 0);
        $totalXp = (int) ($payload['total_xp'] ?? 0);

        $changed = false;
        if ($newLevel > 0 && $newLevel > (int) $user->level) {
            $user->level = $newLevel;
            $changed = true;
        }
        if ($totalXp > (int) $user->total_xp) {
            $user->total_xp = $totalXp;
            $changed = true;
        }
        if (isset($payload['outfit_state']) && is_array($payload['outfit_state'])) {
            $user->outfit_state = $payload['outfit_state'];
            $changed = true;
        }

        if ($changed) {
            $user->save();

            // pending payload for frontend toast (level up 也想 toast)
            Cache::put($this->pendingCacheKey($user->identity_uuid), [
                'kind' => 'level_up',
                'level' => $user->level,
                'total_xp' => $user->total_xp,
                'outfit_state' => $user->outfit_state,
                'pushed_at' => now()->toIso8601String(),
            ], 600);
        }

        return $changed;
    }

    /**
     * Achievement → cache 60s 給 GET /me/gamification/pending pull
     */
    private function cacheAchievement(User $user, array $payload): bool
    {
        Cache::put($this->pendingCacheKey($user->identity_uuid), [
            'kind' => 'achievement_unlocked',
            'code' => (string) ($payload['code'] ?? ''),
            'name' => (string) ($payload['name'] ?? ($payload['code'] ?? '')),
            'tier' => (string) ($payload['tier'] ?? 'bronze'),
            'pushed_at' => now()->toIso8601String(),
        ], 600);

        return true;
    }

    /**
     * outfit_state.owned 合併 codes (idempotent；已擁有 silent skip)
     */
    private function applyOutfitUnlock(User $user, array $payload): int
    {
        $codes = $payload['codes'] ?? [];
        if (! is_array($codes) || $codes === []) {
            return 0;
        }

        $state = (array) ($user->outfit_state ?? ['owned' => [], 'equipped' => null]);
        $owned = (array) ($state['owned'] ?? []);
        $added = 0;
        foreach ($codes as $code) {
            if (! is_string($code) || $code === '') {
                continue;
            }
            if (! in_array($code, $owned, true)) {
                $owned[] = $code;
                $added++;
            }
        }

        if ($added > 0) {
            $state['owned'] = $owned;
            $user->outfit_state = $state;
            $user->save();

            Cache::put($this->pendingCacheKey($user->identity_uuid), [
                'kind' => 'outfit_unlocked',
                'codes' => array_values((array) $codes),
                'pushed_at' => now()->toIso8601String(),
            ], 600);
        }

        return $added;
    }

    private function pendingCacheKey(string $uuid): string
    {
        return "gamification:pending:{$uuid}";
    }
}
