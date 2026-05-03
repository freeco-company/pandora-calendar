<?php

namespace App\Services\Action;

use App\Models\ActionFeedback;
use App\Models\DailyActionRecommendation;
use App\Models\UserActionProtocol;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * 處理 user 對 daily action 的反饋。
 *
 * 寫入 action_feedback → 觸發 UserActionProtocol recompute（同 user / phase / action_key
 * 累積，effectiveness_score = avg(helpful=1.0 / neutral=0.5 / unhelpful=0.0)）。
 *
 * 也會把對應 recommendation 標為 is_completed（feedback 隱含完成）。
 */
class ActionFeedbackProcessor
{
    private const SCORE_MAP = [
        'helpful' => 1.0,
        'neutral' => 0.5,
        'unhelpful' => 0.0,
    ];

    public function record(int $recommendationId, string $feedback, ?string $bodyNote = null): ActionFeedback
    {
        if (! array_key_exists($feedback, self::SCORE_MAP)) {
            throw new \InvalidArgumentException('invalid feedback value');
        }

        $rec = DailyActionRecommendation::findOrFail($recommendationId);
        $now = CarbonImmutable::now();

        return DB::transaction(function () use ($rec, $feedback, $bodyNote, $now) {
            $entry = ActionFeedback::create([
                'user_id' => $rec->user_id,
                'recommendation_id' => $rec->id,
                'feedback' => $feedback,
                'body_note' => $bodyNote,
                'submitted_at' => $now,
            ]);

            // feedback 隱含完成
            if (! $rec->is_completed) {
                $rec->update([
                    'is_completed' => true,
                    'completed_at' => $rec->completed_at ?? $now,
                ]);
            }

            $this->recomputeProtocol($rec->user_id, $rec->phase, $rec->action_key);

            return $entry;
        });
    }

    /**
     * 把該 user / phase / action_key 的所有歷史 feedback 重算一次。
     * 採覆寫式（非增量）以避免 race；資料量級每用戶 phase × action 通常 < 100 筆。
     */
    private function recomputeProtocol(int $userId, string $phase, string $actionKey): void
    {
        $rows = ActionFeedback::query()
            ->join('daily_action_recommendations', 'daily_action_recommendations.id', '=', 'action_feedback.recommendation_id')
            ->where('action_feedback.user_id', $userId)
            ->where('daily_action_recommendations.phase', $phase)
            ->where('daily_action_recommendations.action_key', $actionKey)
            ->pluck('action_feedback.feedback')
            ->all();

        $count = count($rows);
        if ($count === 0) {
            return;
        }

        $sum = 0.0;
        foreach ($rows as $f) {
            $sum += self::SCORE_MAP[$f] ?? 0.5;
        }
        $score = $sum / $count;

        UserActionProtocol::updateOrCreate(
            [
                'user_id' => $userId,
                'phase' => $phase,
                'action_key' => $actionKey,
            ],
            [
                'sample_size' => $count,
                'effectiveness_score' => round($score, 3),
                'last_calculated_at' => CarbonImmutable::now(),
            ],
        );
    }
}
