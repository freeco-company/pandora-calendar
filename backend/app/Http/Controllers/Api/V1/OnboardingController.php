<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Services\Subscription\PremiumTrialService;
use App\Support\Sentry\SentryHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * Onboarding flow（P0+P1）。
 *
 * 寫 user.preferences JSON：
 *   - onboarded_at
 *   - cycle_length（預設 28）
 *   - goal: 'tracking' / 'pregnancy' / 'avoiding_pregnancy' / 'symptoms'
 *
 * 第一次提交也會：
 *   1. 自動建一個 cycle record（last_period_at 為 start_date）
 *   2. 自動啟動 7 天 Premium trial（freemium funnel — 不需信用卡，無痛升級）
 */
class OnboardingController extends Controller
{
    public function __construct(
        private readonly PremiumTrialService $trialService,
    ) {}

    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'last_period_at' => ['required', 'date', 'before_or_equal:today'],
            'cycle_length' => ['nullable', 'integer', 'between:21,40'],
            'goal' => ['nullable', 'string', 'in:tracking,pregnancy,avoiding_pregnancy,symptoms'],
        ]);

        $user = $request->user();

        $prefs = is_array($user->preferences ?? null) ? $user->preferences : [];
        $isFirstOnboard = ! isset($prefs['onboarded_at']);
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

        // 自動啟動 7 天 Premium trial（一輩子一次；二次 onboarding 會被 trial_used 擋掉）
        $trialActivated = false;
        if ($isFirstOnboard) {
            try {
                $trialActivated = $this->trialService->startTrial(
                    $user->id,
                    PremiumTrialService::SOURCE_ONBOARDING,
                );
                if ($trialActivated) {
                    // 給未來 cross-app webhook 一個 hook 點（不阻塞）
                    Event::dispatch('pandora:trial-started', [
                        'user_id' => $user->id,
                        'source' => 'onboarding',
                    ]);
                }
            } catch (\Throwable $e) {
                SentryHelper::captureException($e, 'subscription', [
                    'action' => 'onboarding_trial_start',
                    'user_id' => $user->id,
                ]);
            }
        }

        $trialState = $this->trialService->trialState($user->id);

        return response()->json([
            'data' => [
                'onboarded' => true,
                'preferences' => $prefs,
                'trial' => $trialState,
                'trial_activated' => $trialActivated,
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
                'trial' => $this->trialService->trialState($request->user()->id),
            ],
        ]);
    }
}
