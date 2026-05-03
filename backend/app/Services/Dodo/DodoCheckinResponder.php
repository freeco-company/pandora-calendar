<?php

namespace App\Services\Dodo;

use App\Models\User;
use App\Services\Calendar\BodyRhythm;

/**
 * 朵朵 check-in 回應器
 *
 * Phase 0：不接 LLM；改 inject {@see DodoDialogLibrary}，從 100+ 句變體
 * 隨機抽一句，避免「連用幾天就識破假 AI」的觀感。
 *
 * Phase 1（2026-05-03）：接 LLM 走 {@see DodoLLMResponder}（pluggable env-driven，
 * 無 key 時 fallback library，不阻塞上架）。新方法 `respondWithLLM()` 是新入口；
 * 既有 `respond()` / `streakMilestone()` 方法保留向後相容。
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

    public function __construct(
        private readonly DodoDialogLibrary $library,
        private readonly DodoLLMResponder $llm,
    ) {}

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

    /**
     * LLM-aware 回應（推薦入口；無 key 時 fallback library）。
     *
     * @return array{text: string, source: 'llm'|'library', provider?: string}
     */
    public function respondWithLLM(
        User $user,
        string $mood,
        BodyRhythm $rhythm,
        int $streakDays,
        ?int $daysLate = null,
    ): array {
        if (! in_array($mood, self::ALLOWED_MOODS, true)) {
            $mood = self::MOOD_OKAY;
        }

        return $this->llm->respond(
            $user,
            $mood,
            $rhythm->phase,
            $rhythm->cycleDay,
            $streakDays,
            $daysLate,
        );
    }
}
