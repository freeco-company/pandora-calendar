<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DodoCheckin;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use App\Services\Dodo\DodoCheckinResponder;
use App\Services\Gamification\CalendarEventCatalog;
use App\Services\Gamification\GamificationPublisher;
use App\Services\Gamification\IdempotencyKey;
use App\Services\Subscription\FeatureGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DodoController extends Controller
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythmCalc,
        private readonly DodoCheckinResponder $responder,
        private readonly FeatureGate $gate,
        private readonly GamificationPublisher $gamification,
    ) {}

    public function checkin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mood' => ['required', 'string', 'in:good,okay,bad,tired,cramping'],
            'checked_on' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $checkedOn = isset($data['checked_on'])
            ? CarbonImmutable::parse($data['checked_on'])
            : CarbonImmutable::today();

        // Free tier gating: 1 check-in per day
        $gate = $this->gate->canCheckinDodo($user, $checkedOn);
        if (! $gate->allowed) {
            return response()->json([
                'error' => $gate->reason,
                'message' => $gate->message,
                'upgrade_to' => 'calendar.premium.monthly',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        $prediction = $this->predictor->predict($user->id, $checkedOn);
        $rhythm = $this->rhythmCalc->compute($prediction, $checkedOn);

        // 朵朵回應：先試 LLM（pluggable env-driven），無 key / 違規 / 過 cap 自動 fallback library。
        // 里程碑日（7/14/30/60/90）由 DodoLLMResponder 內部處理（優先用 library 里程碑句）。
        $streakDays = $this->computeStreak($user->id, $checkedOn);
        $reply = $this->responder->respondWithLLM($user, $data['mood'], $rhythm, $streakDays);
        $response = $reply['text'];

        $checkin = DodoCheckin::updateOrCreate(
            ['user_id' => $user->id, 'checked_on' => $checkedOn->toDateString()],
            [
                'mood' => $data['mood'],
                'phase_at_checkin' => $rhythm->phase,
                'cycle_day_at_checkin' => $rhythm->cycleDay,
                'dodo_response' => $response,
            ],
        );

        $this->gamification->publish(
            $user,
            CalendarEventCatalog::DODO_CHECKIN,
            ['mood' => $data['mood'], 'phase' => $rhythm->phase],
            IdempotencyKey::make(
                CalendarEventCatalog::DODO_CHECKIN,
                $user->id,
                $checkin->id,
                $checkedOn->toDateString(),
            ),
        );

        return response()->json([
            'data' => [
                'id' => $checkin->id,
                'mood' => $checkin->mood,
                'phase' => $checkin->phase_at_checkin,
                'cycle_day' => $checkin->cycle_day_at_checkin,
                'dodo_response' => $checkin->dodo_response,
                'dodo_source' => $reply['source'], // 'llm' | 'library'（debug / 監控用）
            ],
        ], 201);
    }

    /**
     * 連續打卡天數（從 checked_on 往回算，必須每天連著）
     */
    private function computeStreak(int $userId, CarbonImmutable $checkedOn): int
    {
        $dates = \App\Models\DodoCheckin::where('user_id', $userId)
            ->where('checked_on', '<=', $checkedOn->toDateString())
            ->orderByDesc('checked_on')
            ->limit(120)
            ->pluck('checked_on')
            ->map(fn ($d) => $d instanceof \Carbon\Carbon ? $d->toDateString() : (string) $d)
            ->all();

        // 今日 checkin 還沒寫入 DB，從 1 起算（含今天），往前找連續日
        $streak = 1;
        $cursor = $checkedOn->subDay();
        foreach ($dates as $d) {
            if ($d === $cursor->toDateString()) {
                $streak++;
                $cursor = $cursor->subDay();
            } elseif ($d === $checkedOn->toDateString()) {
                continue; // 今日若已存在（updateOrCreate path），略過避免重複算
            } else {
                break;
            }
        }

        return $streak;
    }

    public function recent(Request $request): JsonResponse
    {
        $checkins = DodoCheckin::where('user_id', $request->user()->id)
            ->orderByDesc('checked_on')
            ->limit(30)
            ->get()
            ->map(fn (DodoCheckin $c) => [
                'checked_on' => $c->checked_on->toDateString(),
                'mood' => $c->mood,
                'phase' => $c->phase_at_checkin,
                'cycle_day' => $c->cycle_day_at_checkin,
                'dodo_response' => $c->dodo_response,
            ]);

        return response()->json(['data' => $checkins]);
    }
}
