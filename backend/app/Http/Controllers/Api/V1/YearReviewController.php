<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reports\YearReviewService;
use App\Services\Subscription\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 年度回顧（freemium 放寬 2026-05-04）：
 *   - free：4 卡 basic（cover / phase_distribution / top_mood / closing）
 *   - premium / trial：完整 12 卡
 */
class YearReviewController extends Controller
{
    private const FREE_CARD_IDS = ['cover', 'phase_distribution', 'top_mood', 'closing'];

    public function __construct(
        private readonly YearReviewService $service,
        private readonly FeatureGate $gate,
    ) {}

    public function show(Request $request, int $year): JsonResponse
    {
        $current = (int) now()->year;
        abort_if($year < $current - 5 || $year > $current, 404, 'year out of range');

        $user = $request->user();
        $isPremium = $this->gate->isPremium($user);
        $tier = $this->gate->effectiveTier($user);

        $result = $this->service->generate($user->id, $year);

        if ($isPremium) {
            return response()->json(['data' => $result, 'tier' => $tier]);
        }

        $cards = collect($result['cards'] ?? [])
            ->filter(fn ($c) => in_array($c['id'] ?? '', self::FREE_CARD_IDS, true))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'cards' => $cards,
                'stats' => $result['stats'] ?? [],
                'locked_card_ids' => array_values(array_diff(
                    array_map(fn ($c) => $c['id'], $result['cards'] ?? []),
                    self::FREE_CARD_IDS,
                )),
                'locked_features' => ['full_12_cards', 'historical_years', 'high_res_share'],
            ],
            'tier' => 'free',
        ]);
    }
}
