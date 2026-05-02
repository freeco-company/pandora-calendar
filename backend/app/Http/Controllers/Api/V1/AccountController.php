<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\DodoCheckin;
use App\Models\HealthSample;
use App\Models\OutboxEvent;
use App\Models\Pregnancy;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * App Store / GDPR 合規：用戶能在 App 內刪除自己的資料。
 *
 * 月曆只擁有「身體節律業務資料」(cycles / symptoms / dodo_checkins /
 * pregnancies / health_samples / subscriptions / outbox_events / user mirror)。
 * 帳號身份本身在 Pandora Core，這裡只刪 calendar 端的 mirror + 業務資料。
 *
 * 真正跨集團砍帳號需另外 contact support（PC 還沒做 self-service delete
 * endpoint；待 PC 實作後本 controller 可加 forward 一行）。
 */
class AccountController extends Controller
{
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $uuid = $user->identity_uuid;
        $userId = $user->id;

        $deleted = DB::transaction(function () use ($user, $userId) {
            $counts = [
                'cycles' => Cycle::query()->where('user_id', $userId)->delete(),
                'symptoms' => CycleSymptom::query()->where('user_id', $userId)->delete(),
                'dodo_checkins' => DodoCheckin::query()->where('user_id', $userId)->delete(),
                'pregnancies' => class_exists(Pregnancy::class) ? Pregnancy::query()->where('user_id', $userId)->delete() : 0,
                'health_samples' => class_exists(HealthSample::class) ? HealthSample::query()->where('user_id', $userId)->delete() : 0,
                'subscriptions' => Subscription::query()->where('user_id', $userId)->delete(),
                'outbox_events' => OutboxEvent::query()->where('aggregate_type', 'user')->where('aggregate_id', $userId)->delete(),
            ];

            // sanctum tokens
            $user->tokens()->delete();

            // user mirror row 本身：保留 identity_uuid 但清掉所有 personal 欄位
            // （不真的 DELETE：webhook reconcile 還會 recreate，留空 row 避免拉不出來）
            $user->forceFill([
                'name' => null,
                'email' => null,
                'password' => null,
                'display_name' => null,
                'avatar_url' => null,
                'subscription_tier' => null,
                'mother_customer_id' => null,
                'mother_total_orders' => 0,
                'mother_first_order_at' => null,
                'mother_last_order_at' => null,
                'total_xp' => 0,
                'level' => 1,
                'outfit_state' => null,
                'pet_species' => null,
                'pet_nickname' => null,
            ])->save();

            return $counts;
        });

        return response()->json([
            'status' => 'ok',
            'message' => 'Calendar data purged. To delete your full FP account across all apps, contact support.',
            'identity_uuid' => $uuid,
            'deleted' => $deleted,
        ]);
    }
}
