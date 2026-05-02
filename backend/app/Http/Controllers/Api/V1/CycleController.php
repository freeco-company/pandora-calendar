<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Services\BodyRhythm\BodyRhythmSyncService;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use App\Services\Conversion\LoyaltySignalEvaluator;
use App\Services\Gamification\CalendarEventCatalog;
use App\Services\Gamification\GamificationPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CycleController extends Controller
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythm,
        private readonly GamificationPublisher $gamification,
        private readonly BodyRhythmSyncService $bodyRhythmSync,
        private readonly LoyaltySignalEvaluator $loyalty,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $cycles = Cycle::where('user_id', $userId)
            ->orderByDesc('start_date')
            ->limit(24)
            ->get()
            ->map(fn (Cycle $c) => [
                'id' => $c->id,
                'start_date' => $c->start_date->toDateString(),
                'end_date' => $c->end_date?->toDateString(),
                'peak_flow' => $c->peak_flow,
                'length_days' => $c->lengthInDays(),
                'notes' => $c->notes,
            ]);

        $prediction = $this->predictor->predict($userId);
        $rhythm = $this->rhythm->compute($prediction);

        return response()->json([
            'data' => $cycles,
            'prediction' => $prediction->toArray(),
            'body_rhythm' => $rhythm->toArray(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'peak_flow' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $existed = Cycle::where('user_id', $user->id)->exists();

        $cycle = Cycle::updateOrCreate(
            ['user_id' => $user->id, 'start_date' => $data['start_date']],
            $data,
        );

        // Gamification publisher：發到集團 ADR-009 catalog
        if (! $existed) {
            $this->gamification->publish($user, CalendarEventCatalog::FIRST_CYCLE, ['cycle_id' => $cycle->id]);
        }
        $this->gamification->publish($user, CalendarEventCatalog::CYCLE_LOGGED, [
            'cycle_id' => $cycle->id,
            'start_date' => $cycle->start_date->toDateString(),
        ]);

        // 若連續 3 個月每月都記到 → cycle_streak_3_months
        if ($this->hasMonthlyStreak($user->id, 3)) {
            $this->gamification->publish($user, CalendarEventCatalog::CYCLE_STREAK_3_MONTHS, []);
        }

        // bodyRhythm sync 給其他 App 讀
        $prediction = $this->predictor->predict($user->id);
        $rhythm = $this->rhythm->compute($prediction);
        $this->bodyRhythmSync->publish($user, $rhythm);

        // ADR-003 lifecycle 訊號（不在 App 內顯示）
        $this->loyalty->evaluate($user);

        return response()->json([
            'data' => [
                'id' => $cycle->id,
                'start_date' => $cycle->start_date->toDateString(),
                'end_date' => $cycle->end_date?->toDateString(),
                'peak_flow' => $cycle->peak_flow,
            ],
        ], 201);
    }

    public function destroy(Request $request, Cycle $cycle): JsonResponse
    {
        abort_if($cycle->user_id !== $request->user()->id, 403);
        $cycle->delete();

        return response()->json(['deleted' => true]);
    }

    private function hasMonthlyStreak(int $userId, int $months): bool
    {
        $cycles = Cycle::where('user_id', $userId)
            ->orderByDesc('start_date')
            ->limit($months)
            ->get();

        if ($cycles->count() < $months) {
            return false;
        }

        // very simple: 起始日距離今天 < months × 35 days
        return $cycles->first()->start_date->diffInDays(now()) < ($months * 35);
    }
}
