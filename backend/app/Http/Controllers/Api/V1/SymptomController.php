<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CycleSymptom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SymptomController extends Controller
{
    public const ALLOWED_TAGS = [
        'cramp', 'headache', 'fatigue', 'bloating', 'breast_tender',
        'acne', 'mood_swing', 'craving_sweet', 'insomnia', 'back_pain',
    ];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = CycleSymptom::where('user_id', $request->user()->id)
            ->orderByDesc('logged_on');

        if (! empty($data['from'])) {
            $query->where('logged_on', '>=', $data['from']);
        }
        if (! empty($data['to'])) {
            $query->where('logged_on', '<=', $data['to']);
        }

        return response()->json([
            'data' => $query->limit(180)->get()->map(fn (CycleSymptom $s) => [
                'id' => $s->id,
                'logged_on' => $s->logged_on->toDateString(),
                'tags' => $s->tags,
                'mood' => $s->mood,
                'basal_temperature' => $s->basal_temperature,
                'note' => $s->note,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logged_on' => ['required', 'date'],
            'tags' => ['array'],
            'tags.*' => ['string', 'in:'.implode(',', self::ALLOWED_TAGS)],
            'mood' => ['nullable', 'string', 'in:good,okay,bad'],
            'basal_temperature' => ['nullable', 'numeric', 'between:34,42'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $symptom = CycleSymptom::updateOrCreate(
            ['user_id' => $request->user()->id, 'logged_on' => $data['logged_on']],
            $data + ['tags' => $data['tags'] ?? []],
        );

        return response()->json([
            'data' => [
                'id' => $symptom->id,
                'logged_on' => $symptom->logged_on->toDateString(),
                'tags' => $symptom->tags,
                'mood' => $symptom->mood,
            ],
        ], 201);
    }
}
