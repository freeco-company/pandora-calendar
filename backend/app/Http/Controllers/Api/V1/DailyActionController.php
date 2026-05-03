<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DailyActionRecommendation;
use App\Models\UserActionProtocol;
use App\Services\Action\ActionFeedbackProcessor;
use App\Services\Action\ActionRecommender;
use App\Services\Subscription\FeatureGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daily Action Engine — endpoints。
 *
 * Action Engine 本體（today / complete / feedback / history）對 free 開放。
 * Premium-only：protocol 完整 view（free 看 top 1，premium 看完整 phase × action 表）。
 */
class DailyActionController extends Controller
{
    public function __construct(
        private readonly ActionRecommender $recommender,
        private readonly ActionFeedbackProcessor $feedbackProcessor,
        private readonly FeatureGate $gate,
    ) {}

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $rec = $this->recommender->recommendForToday($user->id);

        if (! $rec) {
            return response()->json([
                'data' => null,
                'message' => '今天沒有特別建議的行動，做妳本來會做的事就好。',
            ]);
        }

        return response()->json(['data' => $this->present($rec)]);
    }

    public function complete(Request $request, int $recId): JsonResponse
    {
        $user = $request->user();
        $rec = DailyActionRecommendation::where('user_id', $user->id)->findOrFail($recId);

        if (! $rec->is_completed) {
            $rec->update([
                'is_completed' => true,
                'completed_at' => CarbonImmutable::now(),
            ]);
        }

        return response()->json(['data' => $this->present($rec->fresh())]);
    }

    public function feedback(Request $request, int $recId): JsonResponse
    {
        $data = $request->validate([
            'feedback' => ['required', 'string', 'in:helpful,neutral,unhelpful'],
            'body_note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        // 確保 ownership（findOrFail 不夠，要 enforce user_id）
        $rec = DailyActionRecommendation::where('user_id', $user->id)->findOrFail($recId);

        $entry = $this->feedbackProcessor->record(
            $rec->id,
            $data['feedback'],
            $data['body_note'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'feedback' => $entry->feedback,
                'submitted_at' => $entry->submitted_at?->toIso8601String(),
                'recommendation' => $this->present($rec->fresh()),
            ],
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $days = max(1, min(180, (int) $request->query('days', 30)));
        $user = $request->user();
        $since = CarbonImmutable::today()->subDays($days)->toDateString();

        $rows = DailyActionRecommendation::where('user_id', $user->id)
            ->where('recommended_on', '>=', $since)
            ->orderByDesc('recommended_on')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => $this->present($r))->all(),
        ]);
    }

    public function protocol(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPremium = $this->gate->isPremium($user);

        $q = UserActionProtocol::where('user_id', $user->id)
            ->orderBy('phase')
            ->orderByDesc('effectiveness_score');

        if (! $isPremium) {
            // Free: 只看每 phase top 1
            $rows = $q->get();
            $picked = $rows->groupBy('phase')->map(fn ($g) => $g->first())->values();

            return response()->json([
                'data' => [
                    'tier' => 'free',
                    'protocols' => $picked->map(fn ($p) => $this->presentProtocol($p))->all(),
                    'upgrade_hint' => '升級 Premium 看完整 phase × action 對照表。',
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'tier' => 'premium',
                'protocols' => $q->get()->map(fn ($p) => $this->presentProtocol($p))->all(),
            ],
        ]);
    }

    private function present(DailyActionRecommendation $rec): array
    {
        $card = (array) config('daily-actions.'.$rec->action_key, []);

        return [
            'id' => $rec->id,
            'recommended_on' => $rec->recommended_on?->toDateString(),
            'action_key' => $rec->action_key,
            'phase' => $rec->phase,
            'cycle_day' => $rec->cycle_day,
            'is_completed' => (bool) $rec->is_completed,
            'completed_at' => $rec->completed_at?->toIso8601String(),
            'card' => [
                'title' => (string) ($card['title'] ?? $rec->action_key),
                'body' => (string) ($card['body'] ?? ''),
                'type' => (string) ($card['type'] ?? 'relax'),
                'expected_benefit' => (string) ($card['expected_benefit'] ?? ''),
                'time_minutes' => (int) ($card['time_minutes'] ?? $card['duration_min'] ?? 0),
                'difficulty' => (string) ($card['difficulty'] ?? 'easy'),
            ],
        ];
    }

    private function presentProtocol(UserActionProtocol $p): array
    {
        $card = (array) config('daily-actions.'.$p->action_key, []);

        return [
            'phase' => $p->phase,
            'action_key' => $p->action_key,
            'title' => (string) ($card['title'] ?? $p->action_key),
            'sample_size' => $p->sample_size,
            'effectiveness_score' => $p->effectiveness_score,
        ];
    }
}
