<?php

namespace App\Services\Dodo;

use App\Services\Calendar\BodyRhythm;

/**
 * 朵朵 check-in 回應器
 *
 * Phase 0：不接 LLM；改 inject {@see DodoDialogLibrary}，從 100+ 句變體
 * 隨機抽一句，避免「連用幾天就識破假 AI」的觀感。
 *
 * 對外保留 MOOD_GOOD / MOOD_OKAY / MOOD_BAD 三個常數作為 API 入口
 * （前端既有 payload 不變），內部由 DodoDialogLibrary::LEGACY_MOOD_MAP
 * 對應到新 5-mood 模型。
 */
class DodoCheckinResponder
{
    public const MOOD_GOOD = 'good';
    public const MOOD_OKAY = 'okay';
    public const MOOD_BAD = 'bad';

    /** 新增（前端可漸進採用） */
    public const MOOD_TIRED = 'tired';
    public const MOOD_CRAMPING = 'cramping';

    public const ALLOWED_MOODS = [
        self::MOOD_GOOD,
        self::MOOD_OKAY,
        self::MOOD_BAD,
        self::MOOD_TIRED,
        self::MOOD_CRAMPING,
    ];

    public function __construct(private readonly DodoDialogLibrary $library)
    {
    }

    public function respond(string $mood, BodyRhythm $rhythm): string
    {
        if (! in_array($mood, self::ALLOWED_MOODS, true)) {
            $mood = self::MOOD_OKAY;
        }

        return $this->library->pickByMoodPhase($mood, $rhythm->phase);
    }

    /**
     * 連續打卡里程碑（7/14/30/60/90）— 若不是里程碑日回 null。
     */
    public function streakMilestone(int $days): ?string
    {
        return $this->library->pickStreakMilestone($days);
    }
}
