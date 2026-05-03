<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WeekReport;
use App\Services\Reports\WeekReportGenerator;
use App\Services\Subscription\FeatureGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeekReportController extends Controller
{
    public function __construct(
        private readonly WeekReportGenerator $generator,
        private readonly FeatureGate $gate,
    ) {}

    public function latest(Request $request): JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => '每週報告是 Premium 功能。',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        $report = WeekReport::where('user_id', $request->user()->id)
            ->orderByDesc('week_start')
            ->first();

        if (! $report) {
            $report = $this->generator->generate($request->user());
        }

        return response()->json([
            'data' => [
                'week_start' => $report->week_start->toDateString(),
                'summary' => $report->summary,
                'generated_at' => $report->generated_at->toAtomString(),
            ],
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => '每週報告是 Premium 功能。',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        $report = $this->generator->generate($request->user());

        return response()->json([
            'data' => [
                'week_start' => $report->week_start->toDateString(),
                'summary' => $report->summary,
            ],
        ], 201);
    }
}
