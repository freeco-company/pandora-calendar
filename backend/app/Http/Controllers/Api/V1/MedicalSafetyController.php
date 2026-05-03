<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MedicalSafety\MedicalSafetyEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/medical-safety/evaluate?context=late_period&days_late=14&sexually_active=true ...
 *
 * Context whitelist 對齊 config/medical-safety.php top-level keys。
 * 變數名以 snake_case 透傳；boolean 字串「true」/「false」會轉 bool。
 */
class MedicalSafetyController extends Controller
{
    private const CONTEXTS = [
        'late_period',
        'heavy_bleeding',
        'severe_pain',
        'irregular_pattern',
        'bbt_no_shift_3_cycles',
        'spotting_between_periods',
    ];

    public function __construct(private readonly MedicalSafetyEvaluator $evaluator) {}

    public function evaluate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'context' => ['required', 'string', 'in:'.implode(',', self::CONTEXTS)],
        ]);

        $context = $data['context'];

        // 收集除 context 外所有 query 變數，做型別 coerce
        $vars = collect($request->query())
            ->except(['context'])
            ->map(fn ($v) => $this->coerce($v))
            ->all();

        $result = $this->evaluator->evaluate($context, $vars);

        return response()->json(['data' => $result]);
    }

    private function coerce(mixed $v): bool|float|int|string
    {
        if (is_string($v)) {
            if ($v === 'true') {
                return true;
            }
            if ($v === 'false') {
                return false;
            }
            if (is_numeric($v)) {
                return str_contains($v, '.') ? (float) $v : (int) $v;
            }
        }

        return $v;
    }
}
