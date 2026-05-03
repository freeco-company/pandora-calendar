<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthSampleImporter;
use App\Services\Subscription\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthSampleController extends Controller
{
    public function __construct(
        private readonly HealthSampleImporter $importer,
        private readonly FeatureGate $gate,
    ) {}

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
