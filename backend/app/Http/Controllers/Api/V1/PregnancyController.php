<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pregnancy;
use App\Services\Pregnancy\PregnancyCalculator;
use App\Services\Pregnancy\PregnancyContentProvider;
use App\Services\Subscription\FeatureGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4 Pregnancy mode controller.
 *
 * Endpoints:
 *   POST /v1/pregnancy            start mode (Premium gate)
 *   GET  /v1/pregnancy/current    current state + computed (week / trimester / due / fetal_size / highlights)
 *   PATCH /v1/pregnancy/end       end mode with reason (birth / miscarriage / cancelled / false_alarm)
 *   GET  /v1/pregnancy/week/{w}   preview a specific week's content (for the week scrubber UI)
 */
class PregnancyController extends Controller
{
    public function __construct(
        private readonly FeatureGate $gate,
        private readonly PregnancyCalculator $calculator,
        private readonly PregnancyContentProvider $content,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $p = $this->activeFor($request->user()->id);

        if (! $p) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->calculator->summarize($p)]);
    }

    public function start(Request $request): JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => '孕期模式是 Premium 功能。',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        $existing = $this->activeFor($request->user()->id);
        if ($existing) {
            return response()->json([
                'error' => 'already_active',
                'message' => '妳已經在孕期模式中。',
                'data' => $this->calculator->summarize($existing),
            ], 409);
        }

        $data = $request->validate([
            'lmp_date' => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subDays(300)->toDateString()],
        ]);

        $lmp = CarbonImmutable::parse($data['lmp_date']);
        $due = $lmp->addDays(280);

        $p = Pregnancy::create([
            'user_id' => $request->user()->id,
            'lmp_date' => $lmp->toDateString(),
            'estimated_due_date' => $due->toDateString(),
            'status' => Pregnancy::STATUS_ACTIVE,
            'mode_started_at' => now(),
            'milestones' => [],
        ]);

        return response()->json(['data' => $this->calculator->summarize($p)], 201);
    }

    /**
     * End pregnancy mode. Two URL shapes supported for backward compatibility:
     *   PATCH /v1/pregnancy/end                  → end the current active record
     *   PATCH /v1/pregnancy/{pregnancy}/end      → end specified record (legacy callers)
     */
    public function end(Request $request, ?Pregnancy $pregnancy = null): JsonResponse
    {
        $p = $pregnancy?->exists ? $pregnancy : $this->activeFor($request->user()->id);

        if (! $p) {
            return response()->json(['error' => 'no_active_pregnancy'], 404);
        }

        abort_if($p->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'reason' => ['required', 'in:birth,miscarriage,cancelled,false_alarm'],
        ]);

        $reasonToOutcome = [
            Pregnancy::REASON_BIRTH => 'delivered',
            Pregnancy::REASON_MISCARRIAGE => 'miscarried',
            Pregnancy::REASON_CANCELLED => 'terminated',
            Pregnancy::REASON_FALSE_ALARM => 'terminated',
        ];

        $p->update([
            'status' => Pregnancy::STATUS_ENDED,
            'ended_reason' => $data['reason'],
            'ended_on' => now()->toDateString(),
            'outcome' => $reasonToOutcome[$data['reason']] ?? 'terminated',
        ]);

        return response()->json([
            'data' => [
                'id' => $p->id,
                'status' => $p->status,
                'ended_reason' => $p->ended_reason,
                'ended_on' => $p->ended_on->toDateString(),
            ],
        ]);
    }

    public function week(Request $request, int $week): JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        $week = max(1, min(42, $week));
        $entry = $this->content->forWeek($week);

        return response()->json([
            'data' => [
                'week' => $week,
                'trimester' => $this->calculator->getTrimester($week),
                'fetal_size' => [
                    'label' => $entry['size_comparison'] ?? '',
                    'emoji' => $entry['size_emoji'] ?? '',
                ],
                'dodo_message' => $entry['dodo_message'] ?? '',
                'suggested_actions' => $entry['suggested_actions'] ?? [],
            ],
        ]);
    }

    private function activeFor(int $userId): ?Pregnancy
    {
        return Pregnancy::query()
            ->where('user_id', $userId)
            ->whereNull('ended_on')
            ->where(function ($q) {
                $q->where('status', Pregnancy::STATUS_ACTIVE)
                    ->orWhereNull('status'); // backward compat for rows pre-migration
            })
            ->latest()
            ->first();
    }
}
