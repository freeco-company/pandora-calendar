<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * P2-9 — Partner share mode。
 *
 * 用戶可以開 share token，伴侶用 token 透過匿名 URL 看到「她現在哪個 phase」+
 * 預估下次經期，但**不**看到症狀 / 心情 / 體溫等敏感細節。
 *
 * 隱私紅線：share endpoint 公開可訪問（auth 是 token），只回 phase + countdown，
 * 沒有任何 PII / 詳細記錄。
 */
class PartnerShareController extends Controller
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $calc,
    ) {}

    /**
     * GET /me/partner-share — 我自己看 share 狀態
     */
    public function show(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json([
            'data' => [
                'enabled' => $u->partner_share_enabled_at !== null,
                'token' => $u->partner_share_token,
                'enabled_at' => $u->partner_share_enabled_at,
                'share_url' => $u->partner_share_token
                    ? config('app.url').'/#/partner/'.$u->partner_share_token
                    : null,
            ],
        ]);
    }

    /**
     * POST /me/partner-share — 開啟 / 重新生成
     */
    public function enable(Request $request): JsonResponse
    {
        $u = $request->user();
        $u->partner_share_token = Str::random(32);
        $u->partner_share_enabled_at = now();
        $u->save();

        return response()->json([
            'data' => [
                'token' => $u->partner_share_token,
                'share_url' => config('app.url').'/#/partner/'.$u->partner_share_token,
            ],
        ]);
    }

    /**
     * DELETE /me/partner-share — 關閉
     */
    public function disable(Request $request): JsonResponse
    {
        $u = $request->user();
        $u->partner_share_token = null;
        $u->partner_share_enabled_at = null;
        $u->save();

        return response()->json(['status' => 'ok']);
    }

    /**
     * GET /partner/{token} — 公開（無 auth），給伴侶看的 anonymous view。
     * 只回 phase + countdown，沒 PII / 詳細記錄。
     */
    public function publicView(string $token): JsonResponse
    {
        $u = User::query()->where('partner_share_token', $token)->first();
        if (! $u) {
            return response()->json(['error' => 'invalid_or_expired_token'], 404);
        }

        $today = CarbonImmutable::today();
        $prediction = $this->predictor->predict($u->id, $today);
        $rhythm = $this->calc->compute($prediction, $today);

        // 給伴侶的 friendly 提示
        $hint = match ($rhythm->phase) {
            'menstrual' => '她正在經期，可能比較疲憊。給她空間 + 多一點溫柔。',
            'follicular' => '她精神狀態通常較好的階段。',
            'ovulation' => '她活力高的時段。',
            'luteal' => '黃體期情緒可能起伏，多包容一點，避免吵架敏感話題。',
            default => '尚無足夠資料判斷',
        };

        return response()->json([
            'data' => [
                'display_name' => $u->display_name ?: '她',
                'phase' => $rhythm->phase,
                'days_until_next_period' => $rhythm->days_until_next_period,
                'partner_hint' => $hint,
                // 故意不回傳 cycle_day, symptoms, mood, temperature
            ],
        ]);
    }
}
