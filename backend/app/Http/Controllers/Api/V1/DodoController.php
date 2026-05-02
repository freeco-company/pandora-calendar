<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DodoCheckin;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use App\Services\Dodo\DodoCheckinResponder;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DodoController extends Controller
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythmCalc,
        private readonly DodoCheckinResponder $responder,
    ) {}

    public function checkin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mood' => ['required', 'string', 'in:good,okay,bad'],
            'checked_on' => ['nullable', 'date'],
        ]);

        $userId = $request->user()->id;
        $checkedOn = isset($data['checked_on'])
            ? CarbonImmutable::parse($data['checked_on'])
            : CarbonImmutable::today();

        $prediction = $this->predictor->predict($userId, $checkedOn);
        $rhythm = $this->rhythmCalc->compute($prediction, $checkedOn);
        $response = $this->responder->respond($data['mood'], $rhythm);

        $checkin = DodoCheckin::updateOrCreate(
            ['user_id' => $userId, 'checked_on' => $checkedOn->toDateString()],
            [
                'mood' => $data['mood'],
                'phase_at_checkin' => $rhythm->phase,
                'cycle_day_at_checkin' => $rhythm->cycleDay,
                'dodo_response' => $response,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $checkin->id,
                'mood' => $checkin->mood,
                'phase' => $checkin->phase_at_checkin,
                'cycle_day' => $checkin->cycle_day_at_checkin,
                'dodo_response' => $checkin->dodo_response,
            ],
        ], 201);
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
