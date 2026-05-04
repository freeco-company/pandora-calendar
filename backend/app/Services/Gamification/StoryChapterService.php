<?php

namespace App\Services\Gamification;

use App\Models\Cycle;
use App\Models\StoryChapterUnlock;
use App\Services\Economy\DodoCoinService;
use Carbon\CarbonImmutable;

/**
 * Wave 13 — 故事章節服務。
 *
 * 解鎖規則：
 *   - chapter 1 = onboarding 完成自動解（unlock_source = onboarding）
 *   - chapter N (N >= 2) = 完成 N-1 個週期可解（cycle）
 *   - OR spend 100 coin 提前解（coin）
 */
final class StoryChapterService
{
    public function __construct(private readonly DodoCoinService $coins) {}

    public function chapters(): array
    {
        return (array) config('dodo-stories.chapters', []);
    }

    public function chapter(int $chapter): ?array
    {
        foreach ($this->chapters() as $c) {
            if ((int) $c['chapter'] === $chapter) {
                return $c;
            }
        }

        return null;
    }

    /** @return list<int> */
    public function unlocked(int $userId): array
    {
        return StoryChapterUnlock::where('user_id', $userId)
            ->orderBy('chapter')
            ->pluck('chapter')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function isUnlocked(int $userId, int $chapter): bool
    {
        return StoryChapterUnlock::where('user_id', $userId)->where('chapter', $chapter)->exists();
    }

    /**
     * Auto-unlock by cycle count（call from CycleController after store / Cycle complete event）。
     *
     * @return list<int> newly unlocked chapter numbers
     */
    public function autoUnlockByCycles(int $userId): array
    {
        $cycleCount = Cycle::query()->where('user_id', $userId)->count();
        $unlocked = $this->unlocked($userId);
        $new = [];

        foreach ($this->chapters() as $c) {
            $chapter = (int) $c['chapter'];
            $required = (int) ($c['unlock_cycle'] ?? max(0, $chapter - 1));
            if ($cycleCount >= $required && ! in_array($chapter, $unlocked, true)) {
                $this->doUnlock($userId, $chapter, 'cycle');
                $new[] = $chapter;
            }
        }

        return $new;
    }

    public function unlockOnboarding(int $userId): bool
    {
        if ($this->isUnlocked($userId, 1)) {
            return false;
        }
        $this->doUnlock($userId, 1, 'onboarding');

        return true;
    }

    /**
     * Spend coins to unlock early. Returns true on success, false if already unlocked or balance insufficient.
     */
    public function unlockWithCoins(int $userId, int $chapter): bool
    {
        return $this->unlockWithCoinsResult($userId, $chapter) === 'success';
    }

    /**
     * 同 unlockWithCoins 但回 string reason code（給 controller 給 friendly error）
     * 'success' | 'already_unlocked' | 'not_found' | 'insufficient_balance'
     */
    public function unlockWithCoinsResult(int $userId, int $chapter): string
    {
        if ($this->isUnlocked($userId, $chapter)) {
            return 'already_unlocked';
        }
        $meta = $this->chapter($chapter);
        if ($meta === null) {
            return 'not_found';
        }
        $cost = (int) ($meta['coin_cost'] ?? 100);
        if ($cost <= 0) {
            $this->doUnlock($userId, $chapter, 'coin');

            return 'success';
        }
        $spend = $this->coins->spend($userId, $cost, DodoCoinService::SOURCE_SPEND_STORY_CHAPTER, [
            'chapter' => $chapter,
        ]);
        if ($spend === null) {
            return 'insufficient_balance';
        }
        $this->doUnlock($userId, $chapter, 'coin');

        return 'success';
    }

    public function markRead(int $userId, int $chapter): bool
    {
        $row = StoryChapterUnlock::where('user_id', $userId)->where('chapter', $chapter)->first();
        if ($row === null) {
            return false;
        }
        if ($row->read_at !== null) {
            return false;
        }
        $row->read_at = CarbonImmutable::now();
        $row->save();

        return true;
    }

    private function doUnlock(int $userId, int $chapter, string $source): void
    {
        StoryChapterUnlock::firstOrCreate(
            ['user_id' => $userId, 'chapter' => $chapter],
            ['unlock_source' => $source, 'unlocked_at' => CarbonImmutable::now()],
        );
    }
}
