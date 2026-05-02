<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 預留給其他 App（meal / 肌膚 / 學院）讀取 GroupUserProfile.bodyRhythm 的 endpoint。
 * Phase 0：本機計算回傳；Phase 3：寫入 Pandora Core GroupUserProfile，由 Core 對外發 webhook。
 */
class BodyRhythmController extends Controller
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythm,
    ) {}

    public function me(Request $request): JsonResponse
    {
        $on = $request->query('on')
            ? CarbonImmutable::parse($request->query('on'))
            : CarbonImmutable::today();

        $prediction = $this->predictor->predict($request->user()->id, $on);
        $rhythm = $this->rhythm->compute($prediction, $on);

        return response()->json([
            'data' => $rhythm->toArray(),
            'prediction' => $prediction->toArray(),
            'source' => 'pandora-calendar',
            'schema_version' => 1,
        ]);
    }
}
