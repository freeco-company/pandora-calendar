<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Onboarding flow（P0+P1）。
 *
 * 寫 user.preferences JSON：
 *   - onboarded_at
 *   - cycle_length（預設 28）
 *   - goal: 'tracking' / 'pregnancy' / 'avoiding_pregnancy' / 'symptoms'
 *
 * 第一次提交也會自動建一個 cycle record（last_period_at 為 start_date），
 * 讓首頁立刻有預測 / body_rhythm 可顯示。
 */
class OnboardingController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'last_period_at' => ['required', 'date', 'before_or_equal:today'],
            'cycle_length' => ['nullable', 'integer', 'between:21,40'],
            'goal' => ['nullable', 'string', 'in:tracking,pregnancy,avoiding_pregnancy,symptoms'],
        ]);

        $user = $request->user();

        $prefs = is_array($user->preferences ?? null) ? $user->preferences : [];
        $prefs['onboarded_at'] = now()->toIso8601String();
        $prefs['cycle_length'] = $data['cycle_length'] ?? 28;
        $prefs['goal'] = $data['goal'] ?? 'tracking';
        $user->preferences = $prefs;
        $user->save();

        // 建首個 cycle（用 updateOrCreate 防 onboarding 二次提交產生重複）
        $startDate = CarbonImmutable::parse($data['last_period_at'])->toDateString();
        Cycle::updateOrCreate(
            ['user_id' => $user->id, 'start_date' => $startDate],
            [],
        );

        return response()->json([
            'data' => [
                'onboarded' => true,
                'preferences' => $prefs,
            ],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $prefs = $request->user()->preferences ?? [];

        return response()->json([
            'data' => [
                'onboarded' => isset($prefs['onboarded_at']),
                'preferences' => $prefs,
            ],
        ]);
    }
}
