<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\Push\PushDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Push subscription registry — 同時支援 web-push (VAPID) 與 native (iOS APNs / Android FCM)。
 *
 * Web subscribe payload：{ endpoint, keys: { p256dh, auth }, platform: 'web' }
 * Native subscribe payload：{ device_token, platform: 'ios'|'android' }
 *
 * 真正寄推播由 PushDispatcher 路由到對應 channel；缺 credential 時 channel 為 noop。
 */
class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $platform = $request->input('platform', 'web');

        if (in_array($platform, ['ios', 'android'], true)) {
            $data = $request->validate([
                'platform' => ['required', Rule::in(['ios', 'android'])],
                'device_token' => ['required', 'string', 'max:500'],
            ]);

            $sub = PushSubscription::query()->updateOrCreate(
                ['device_token' => $data['device_token']],
                [
                    'user_id' => $request->user()->id,
                    'platform' => $data['platform'],
                    'last_used_at' => now(),
                ],
            );
        } else {
            $data = $request->validate([
                'endpoint' => ['required', 'string', 'max:500', 'url'],
                'keys.p256dh' => ['required', 'string', 'max:255'],
                'keys.auth' => ['required', 'string', 'max:128'],
                'platform' => ['nullable', 'in:web'],
            ]);

            $sub = PushSubscription::query()->updateOrCreate(
                ['endpoint' => $data['endpoint']],
                [
                    'user_id' => $request->user()->id,
                    'p256dh' => $data['keys']['p256dh'],
                    'auth' => $data['keys']['auth'],
                    'platform' => 'web',
                    'last_used_at' => now(),
                ],
            );
        }

        $u = $request->user();
        if (! $u->push_opted_in) {
            $u->push_opted_in = true;
            $u->save();
        }

        return response()->json(['data' => ['id' => $sub->id, 'platform' => $sub->platform]], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['nullable', 'string'],
            'device_token' => ['nullable', 'string'],
        ]);

        $q = PushSubscription::query()->where('user_id', $request->user()->id);
        if (! empty($data['endpoint'])) {
            $q->where('endpoint', $data['endpoint']);
        } elseif (! empty($data['device_token'])) {
            $q->where('device_token', $data['device_token']);
        } else {
            return response()->json(['errors' => ['target' => ['endpoint or device_token required']]], 422);
        }
        $q->delete();

        $remaining = PushSubscription::query()->where('user_id', $request->user()->id)->count();
        if ($remaining === 0) {
            $u = $request->user();
            $u->push_opted_in = false;
            $u->save();
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * GET /me/push/subscriptions — 列出當前用戶的所有 sub（給 Profile UI 顯示「已註冊 N 個裝置」）。
     */
    public function index(Request $request): JsonResponse
    {
        $subs = PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_used_at')
            ->get(['id', 'platform', 'last_used_at', 'created_at'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'platform' => $s->platform,
                'last_used_at' => $s->last_used_at?->toIso8601String(),
                'created_at' => $s->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $subs]);
    }

    /**
     * POST /me/push/test — 對自己所有 sub 送一條測試訊息確認接通。
     */
    public function test(Request $request, PushDispatcher $dispatcher): JsonResponse
    {
        $subs = PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->get();

        $results = [];
        foreach ($subs as $sub) {
            $r = $dispatcher->dispatch(
                $sub,
                title: '朵朵 Dodo',
                body: '這是一條測試 ⛅️',
                data: ['url' => '/#/dodo', 'kind' => 'test'],
            );
            $results[] = [
                'platform' => $r['channel'],
                'ok' => $r['ok'],
                'reason' => $r['reason'],
            ];
        }

        return response()->json([
            'data' => [
                'count' => count($results),
                'results' => $results,
            ],
        ]);
    }
}
