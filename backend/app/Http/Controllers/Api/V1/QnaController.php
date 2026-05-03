<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QnaQuestion;
use App\Services\Qna\QnaConductor;
use App\Services\Subscription\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4 — 含金量 Q&A（朵朵開放問答 + RAG 衛教）
 *
 * Endpoints
 *   POST   /api/v1/qna/ask        body { question }      → answer + sources
 *   GET    /api/v1/qna/history    ?days=30               → 用戶歷史（自己的）
 *   DELETE /api/v1/qna/{id}                               → 刪自己的單筆
 *
 * Free tier: 3 questions / day（從 config/qna.php free_daily_cap）
 * Premium: 無限
 */
class QnaController extends Controller
{
    public function __construct(
        private readonly QnaConductor $conductor,
        private readonly FeatureGate $gate,
    ) {}

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $user = $request->user();

        // gate: free tier daily cap
        if (! $this->conductor->hasQuotaToday($user)) {
            return response()->json([
                'error' => 'quota_exceeded',
                'message' => '今天的免費問答用完了，升級 Premium 可以無限制問朵朵。',
                'upgrade_to' => 'calendar.premium.monthly',
                'paywall_redirect' => '/me/premium',
            ], 402);
        }

        $result = $this->conductor->ask($user, $data['question']);

        return response()->json([
            'data' => [
                'id' => $result['record_id'],
                'answer' => $result['answer'],
                'sources' => $result['sources'],
                'safety_flag' => $result['safety_flag'],
                'remaining_today' => $this->conductor->dailyRemainingFor($user),
                'is_premium' => $this->gate->isPremium($user),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $days = $data['days'] ?? 30;
        $items = QnaQuestion::query()
            ->where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'question', 'answer', 'sources', 'safety_flag', 'created_at']);

        return response()->json([
            'data' => $items->map(fn ($q) => [
                'id' => $q->id,
                'question' => $q->question,
                'answer' => $q->answer,
                'sources' => $q->sources ?? [],
                'safety_flag' => $q->safety_flag,
                'created_at' => $q->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'remaining_today' => $this->conductor->dailyRemainingFor($request->user()),
                'is_premium' => $this->gate->isPremium($request->user()),
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $q = QnaQuestion::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $q) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $q->delete();
        return response()->json(['data' => ['deleted' => true]]);
    }
}
