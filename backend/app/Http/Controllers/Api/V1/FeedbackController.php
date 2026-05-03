<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Rate limit 5/min/user。throttle middleware 也行，這裡顯式寫方便細緻控制。
        $key = 'feedback:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'error' => 'rate_limited',
                'message' => '太快了，等等再說一次好嗎？',
                'retry_after_seconds' => RateLimiter::availableIn($key),
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', Feedback::CATEGORIES)],
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'device_info' => ['nullable', 'array'],
        ]);

        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'message' => $data['message'],
            'app_version' => $data['app_version'] ?? null,
            'device_info' => $data['device_info'] ?? null,
        ]);

        $this->notifyDiscord($feedback);

        return response()->json([
            'data' => [
                'id' => $feedback->id,
                'created_at' => $feedback->created_at?->toAtomString(),
            ],
            'message' => '收到了，謝謝妳告訴朵朵。',
        ], 201);
    }

    private function notifyDiscord(Feedback $feedback): void
    {
        $url = config('services.feedback_discord_webhook') ?: env('FEEDBACK_NOTIFY_DISCORD_WEBHOOK');
        if (! $url) {
            return;
        }

        try {
            Http::timeout(3)->post($url, [
                'content' => sprintf(
                    "📝 New feedback (%s) from user %d\n> %s\n_app: %s_",
                    $feedback->category,
                    $feedback->user_id,
                    str(strip_tags($feedback->message))->limit(500),
                    $feedback->app_version ?? '—',
                ),
            ]);
        } catch (\Throwable $e) {
            // 不擋寫 DB，只 log
            Log::warning('feedback discord notify failed', ['err' => $e->getMessage()]);
        }
    }
}
