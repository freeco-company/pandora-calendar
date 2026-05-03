<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web Push subscription registry。前端 Service Worker 拿到 PushSubscription 後 POST 上來。
 *
 * 真正寄推播的 worker 由 PushDispatcher 負責（schedule 每天早上 8:00 跑），
 * 觸發條件：用戶 phase 進入 luteal_late / menstrual / ovulation 等關鍵時段，且 push_opted_in=true。
 *
 * Capacitor iOS / Android push 待 Apple Dev / FCM 設定後加 platform=ios|android。
 */
class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', 'url'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:128'],
            'platform' => ['nullable', 'in:web,ios,android'],
        ]);

        $sub = PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'platform' => $data['platform'] ?? 'web',
                'last_used_at' => now(),
            ],
        );

        // 順手把 user opt-in flag 開
        $u = $request->user();
        if (! $u->push_opted_in) {
            $u->push_opted_in = true;
            $u->save();
        }

        return response()->json(['data' => ['id' => $sub->id, 'platform' => $sub->platform]], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint', $data['endpoint'])
            ->delete();

        // 若用戶已無任何 endpoint 就把 opt-in 關掉
        $remaining = PushSubscription::query()->where('user_id', $request->user()->id)->count();
        if ($remaining === 0) {
            $u = $request->user();
            $u->push_opted_in = false;
            $u->save();
        }

        return response()->json(['status' => 'ok']);
    }
}
