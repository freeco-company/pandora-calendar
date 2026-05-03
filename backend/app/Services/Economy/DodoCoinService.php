<?php

namespace App\Services\Economy;

use App\Models\DodoCoinTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Wave 13 — DodoCoin economy。
 *
 * 紅線：
 *   - earn delta > 0；spend delta > 0（記入 DB 時 spend 寫成負數）
 *   - balance 不夠 spend → 回 false（不丟 exception，UI flow 友善）
 *   - 朵朵幣不能買 Premium 功能；只能換 outfit / story chapter / pet item
 *   - balance_after snapshot 寫死，不要 SUM 整張表（trans 多了會慢）
 *
 * 並發：DB transaction + lockForUpdate 抓最新 balance，避免 race 灌爆 / 透支。
 */
final class DodoCoinService
{
    public const SOURCE_DAILY_ACTION = 'daily_action';

    public const SOURCE_STREAK = 'streak';

    public const SOURCE_ACHIEVEMENT = 'achievement';

    public const SOURCE_RANDOM_EVENT = 'random_event';

    public const SOURCE_SOLAR_TERM = 'solar_term';

    public const SOURCE_REFUND = 'refund';

    public const SOURCE_SPEND_OUTFIT = 'spend_outfit';

    public const SOURCE_SPEND_STORY_CHAPTER = 'spend_story_chapter';

    public const SOURCE_SPEND_PET_ITEM = 'spend_pet_item';

    public const SOURCE_SPEND_OTHER = 'spend_other';

    public const VALID_EARN_SOURCES = [
        self::SOURCE_DAILY_ACTION,
        self::SOURCE_STREAK,
        self::SOURCE_ACHIEVEMENT,
        self::SOURCE_RANDOM_EVENT,
        self::SOURCE_SOLAR_TERM,
        self::SOURCE_REFUND,
    ];

    public const VALID_SPEND_SOURCES = [
        self::SOURCE_SPEND_OUTFIT,
        self::SOURCE_SPEND_STORY_CHAPTER,
        self::SOURCE_SPEND_PET_ITEM,
        self::SOURCE_SPEND_OTHER,
    ];

    /** Daily action coin reward by difficulty. */
    public const DAILY_ACTION_REWARDS = [
        'easy' => 5,
        'medium' => 10,
        'hard' => 15,
    ];

    /** Streak milestone bonus (days => coins). */
    public const STREAK_MILESTONES = [
        7 => 50,
        14 => 100,
        30 => 300,
        60 => 600,
    ];

    public function balance(int $userId): int
    {
        return Cache::remember(
            "dodo_coin:balance:{$userId}",
            now()->addHour(),
            fn () => (int) (DodoCoinTransaction::where('user_id', $userId)
                ->orderByDesc('id')
                ->value('balance_after') ?? 0),
        );
    }

    public function earn(int $userId, int $delta, string $source, array $metadata = []): DodoCoinTransaction
    {
        if ($delta <= 0) {
            throw new \InvalidArgumentException('earn delta must be positive');
        }
        if (! in_array($source, self::VALID_EARN_SOURCES, true)) {
            throw new \InvalidArgumentException("invalid earn source: {$source}");
        }

        return $this->writeTransaction($userId, $delta, $source, $metadata);
    }

    /**
     * Spend coins. Returns the trans on success, null if balance insufficient.
     */
    public function spend(int $userId, int $delta, string $source, array $metadata = []): ?DodoCoinTransaction
    {
        if ($delta <= 0) {
            throw new \InvalidArgumentException('spend delta must be positive');
        }
        if (! in_array($source, self::VALID_SPEND_SOURCES, true)) {
            throw new \InvalidArgumentException("invalid spend source: {$source}");
        }

        return DB::transaction(function () use ($userId, $delta, $source, $metadata) {
            $current = $this->lockedBalance($userId);
            if ($current < $delta) {
                return null;
            }

            return $this->writeTransactionLocked($userId, -$delta, $source, $metadata, $current);
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, DodoCoinTransaction>
     */
    public function history(int $userId, int $limit = 50): \Illuminate\Support\Collection
    {
        return DodoCoinTransaction::where('user_id', $userId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function writeTransaction(int $userId, int $delta, string $source, array $metadata): DodoCoinTransaction
    {
        return DB::transaction(function () use ($userId, $delta, $source, $metadata) {
            $current = $this->lockedBalance($userId);

            return $this->writeTransactionLocked($userId, $delta, $source, $metadata, $current);
        });
    }

    private function writeTransactionLocked(int $userId, int $delta, string $source, array $metadata, int $current): DodoCoinTransaction
    {
        $newBalance = max(0, $current + $delta);
        $trans = DodoCoinTransaction::create([
            'user_id' => $userId,
            'delta' => $delta,
            'source' => $source,
            'metadata' => $metadata,
            'balance_after' => $newBalance,
        ]);

        Cache::forget("dodo_coin:balance:{$userId}");

        return $trans;
    }

    private function lockedBalance(int $userId): int
    {
        $row = DodoCoinTransaction::where('user_id', $userId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        return $row ? (int) $row->balance_after : 0;
    }
}
