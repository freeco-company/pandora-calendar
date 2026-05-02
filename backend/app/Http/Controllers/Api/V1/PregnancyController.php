<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pregnancy;
use App\Services\Subscription\FeatureGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PregnancyController extends Controller
{
    public function __construct(private readonly FeatureGate $gate) {}

    public function current(Request $request): JsonResponse
    {
        $p = Pregnancy::where('user_id', $request->user()->id)
            ->whereNull('ended_on')
            ->latest()
            ->first();

        if (! $p) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $p->id,
                'lmp_date' => $p->lmp_date->toDateString(),
                'estimated_due_date' => $p->estimated_due_date->toDateString(),
                'gestational_week' => $p->gestationalWeek(),
                'milestones' => $p->milestones,
            ],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => '孕期模式是 Premium 功能。',
            ], 402);
        }

        $data = $request->validate([
            'lmp_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $lmp = CarbonImmutable::parse($data['lmp_date']);
        $due = $lmp->addDays(280);

        $p = Pregnancy::create([
            'user_id' => $request->user()->id,
            'lmp_date' => $lmp->toDateString(),
            'estimated_due_date' => $due->toDateString(),
            'milestones' => [],
        ]);

        return response()->json([
            'data' => [
                'id' => $p->id,
                'estimated_due_date' => $p->estimated_due_date->toDateString(),
                'gestational_week' => $p->gestationalWeek(),
            ],
        ], 201);
    }

    public function end(Request $request, Pregnancy $pregnancy): JsonResponse
    {
        abort_if($pregnancy->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'outcome' => ['required', 'in:delivered,miscarried,terminated'],
            'ended_on' => ['required', 'date'],
        ]);

        $pregnancy->update($data);

        return response()->json(['data' => ['outcome' => $pregnancy->outcome]]);
    }
}
