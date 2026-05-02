<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CycleController extends Controller
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythm,
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

        $cycle = Cycle::updateOrCreate(
            ['user_id' => $request->user()->id, 'start_date' => $data['start_date']],
            $data,
        );

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
}
