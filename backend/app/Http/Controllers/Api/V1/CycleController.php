<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Services\BodyRhythm\BodyRhythmSyncService;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use App\Services\Conversion\LoyaltySignalEvaluator;
use App\Models\CycleSymptom;
use App\Services\Gamification\CalendarEventCatalog;
use App\Services\Gamification\GamificationPublisher;
use App\Services\Gamification\IdempotencyKey;
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
        $cycleDate = $cycle->start_date->toDateString();

        if (! $existed) {
            $this->gamification->publish(
                $user,
                CalendarEventCatalog::FIRST_CYCLE,
                ['cycle_id' => $cycle->id],
                IdempotencyKey::lifetime(CalendarEventCatalog::FIRST_CYCLE, $user->id),
            );
        }
        $this->gamification->publish(
            $user,
            CalendarEventCatalog::CYCLE_LOGGED,
            ['cycle_id' => $cycle->id, 'start_date' => $cycleDate],
            IdempotencyKey::make(CalendarEventCatalog::CYCLE_LOGGED, $user->id, $cycle->id, $cycleDate),
        );

        // P5.2：cycle 結束日 + 開始日都填了 → 完整週期記錄
        if ($cycle->end_date) {
            $this->gamification->publish(
                $user,
                CalendarEventCatalog::FULL_CYCLE_TRACKED,
                ['cycle_id' => $cycle->id, 'length_days' => $cycle->lengthInDays()],
                IdempotencyKey::make(CalendarEventCatalog::FULL_CYCLE_TRACKED, $user->id, $cycle->id, $cycleDate),
            );
        }

        // P5.2：連續 7 天記錄（cycle 或 symptom 任一）→ milestone, lifetime once
        if ($this->hasSevenDayStreak($user->id)) {
            $this->gamification->publish(
                $user,
                CalendarEventCatalog::TRACK_7_DAYS,
                [],
                IdempotencyKey::lifetime(CalendarEventCatalog::TRACK_7_DAYS, $user->id),
            );
        }

        // 若連續 3 個月每月都記到 → cycle_streak_3_months
        if ($this->hasMonthlyStreak($user->id, 3)) {
            $this->gamification->publish(
                $user,
                CalendarEventCatalog::CYCLE_STREAK_3_MONTHS,
                [],
                IdempotencyKey::make(
                    CalendarEventCatalog::CYCLE_STREAK_3_MONTHS,
                    $user->id, 0,
                    now()->format('Y-m'),
                ),
            );
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

    /**
     * P5.2：判斷該 user 在最近 7 天內是否每天都有 cycle 或 symptom 記錄。
     * 取 cycles + symptoms 的不同 logged_on 日期 union，看 distinct 天數 >= 7。
     */
    private function hasSevenDayStreak(int $userId): bool
    {
        $sevenDaysAgo = now()->subDays(7)->toDateString();

        $cycleDates = \App\Models\Cycle::where('user_id', $userId)
            ->where('start_date', '>=', $sevenDaysAgo)
            ->pluck('start_date')
            ->map(fn ($d) => $d instanceof \Carbon\Carbon ? $d->toDateString() : (string) $d);

        $symptomDates = CycleSymptom::where('user_id', $userId)
            ->where('logged_on', '>=', $sevenDaysAgo)
            ->pluck('logged_on')
            ->map(fn ($d) => $d instanceof \Carbon\Carbon ? $d->toDateString() : (string) $d);

        return $cycleDates->merge($symptomDates)->unique()->count() >= 7;
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
