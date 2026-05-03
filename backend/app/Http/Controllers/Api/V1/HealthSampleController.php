<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthSampleImporter;
use App\Services\Health\HealthSampleReflection;
use App\Services\Subscription\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HealthSampleController extends Controller
{
    public function __construct(
        private readonly HealthSampleImporter $importer,
        private readonly HealthSampleReflection $reflection,
        private readonly FeatureGate $gate,
    ) {}

    /**
     * GET /api/v1/health-samples/reflection/today — 朵朵口吻的當天健康反饋（Premium）。
     */
    public function reflectionToday(Request $request): JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => 'HealthKit 反饋是 Premium 功能。',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        $insight = $this->reflection->reflectToday((int) $request->user()->id);

        return response()->json(['data' => $insight]);
    }

    /**
     * 新版 import endpoint：依 kind 分派（前端 useHealthKit composable 用）。
     * Body: { kind: 'bbt'|'steps'|'sleep'|'menstrual_flow', source?, samples: [{date, value, unit?, datetime?}] }
     */
    public function import(Request $request): JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => '自動匯入 HealthKit / Health Connect 是 Premium 功能。',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        $data = $request->validate([
            'kind' => ['required', Rule::in(HealthSampleImporter::SUPPORTED_KINDS)],
            'source' => ['nullable', 'in:healthkit,health_connect,manual'],
            'samples' => ['required', 'array', 'min:1', 'max:500'],
            'samples.*.date' => ['nullable', 'date'],
            'samples.*.datetime' => ['nullable', 'date'],
            'samples.*.value' => ['required', 'numeric'],
            'samples.*.unit' => ['nullable', 'string', 'max:16'],
            'samples.*.meta' => ['nullable', 'array'],
        ]);

        $result = $this->importer->import(
            (int) $request->user()->id,
            $data['kind'],
            $data['samples'],
            $data['source'] ?? 'healthkit',
        );

        return response()->json(['data' => $result], 201);
    }

    /**
     * Legacy endpoint — 接受 explicit metric per-sample；保留向後相容。
     */
    public function importBatch(Request $request): JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => 'HealthKit / Health Connect 整合是 Premium 功能。',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        $data = $request->validate([
            'source' => ['required', 'in:healthkit,health_connect,manual'],
            'samples' => ['required', 'array', 'min:1', 'max:500'],
            'samples.*.metric' => ['required', 'string'],
            'samples.*.value' => ['required', 'numeric'],
            'samples.*.recorded_on' => ['required', 'date'],
            'samples.*.recorded_at' => ['nullable', 'date'],
            'samples.*.meta' => ['nullable', 'array'],
        ]);

        $count = $this->importer->importBatch($request->user(), $data['samples'], $data['source']);

        return response()->json(['data' => ['imported' => $count]], 201);
    }
}
