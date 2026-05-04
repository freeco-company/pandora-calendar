<?php

namespace App\Services\Calendar\Streak;

use App\Models\User;
use App\Services\Gamification\GamificationPublisher;
use App\Services\Gamification\OutfitCatalog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SPEC-streak-milestone-rewards (calendar) — unlock outfits / cards / XP bonus
 * when user hits a streak milestone (1 / 3 / 7 / 14 / 21 / 30 / 60 / 100).
 *
 * Mirror of pandora-meal `App\Services\Dodo\Streak\StreakMilestoneRewardService`,
 * adapted for calendar's Vue frontend + outfit_state JSON column + lossy local
 * total_xp mirror (canonical XP/level lives in py-service; webhook reconciles).
 *
 * Called from DailyLoginStreakService::recordLogin() when is_milestone=true.
 *
 * Design:
 *   - Outfits persist to users.outfit_state.owned[] (real unlock; aligns with
 *     OutfitCatalog::all() codes). Unknown codes → fail-soft skip with
 *     outfit_skipped reported so frontend doesn't lie about an unlock.
 *   - "Cards" are descriptive labels returned to the frontend so the toast can
 *     show a reveal animation. Card collection state is owned by other systems.
 *   - XP bonus: writes a lossy local mirror to users.total_xp (won't decrease;
 *     webhook from py-service is authoritative and reconciles via
 *     GamificationWebhookController::applyLevelUp upper-bound merge).
 *   - Idempotent on outfits (already-owned skipped); XP bonus is idempotent
 *     because recordLogin's same-day no-op gates re-entry.
 *   - Publishes a fail-soft `calendar.streak_milestone_unlocked` event for
 *     observability — dropped when not in py-service catalog (warning logged).
 */
class StreakMilestoneRewardService
{
    /**
     * milestone day → { outfit_code?, cards: list<{code,label}>, xp_bonus? }
     *
     * Outfit codes must exist in OutfitCatalog::all(). Codes that don't exist
     * there fail-soft skip (no insert into outfit_state.owned).
     *
     * Streak-tier outfits in calendar OutfitCatalog (2026-05-03 catalog):
     *   3 → sparkle_pin (common)
     *   7 → sakura (rare)
     *   14 → star_clip (rare)
     *   30 → starry_cape (epic)
     *   60 → moon_tiara (epic)
     *   90 → angel_wings (legendary) — but we celebrate at 100 too with cards
     *
     * @var array<int, array{outfit_code?: ?string, cards: list<array{code:string,label:string}>, xp_bonus?: int}>
     */
    private const REWARDS = [
        1 => [
            'outfit_code' => null,
            'cards' => [
                ['code' => 'streak_1', 'label' => '初心徽章'],
            ],
        ],
        3 => [
            'outfit_code' => 'sparkle_pin',
            'cards' => [
                ['code' => 'streak_3', 'label' => '三日小步'],
            ],
        ],
        7 => [
            'outfit_code' => 'sakura',
            'cards' => [
                ['code' => 'streak_7', 'label' => '一週成就'],
            ],
        ],
        14 => [
            'outfit_code' => 'star_clip',
            'cards' => [
                ['code' => 'streak_14', 'label' => '兩週決心'],
            ],
        ],
        21 => [
            'outfit_code' => null,
            'cards' => [
                ['code' => 'streak_21', 'label' => '習慣養成'],
            ],
            'xp_bonus' => 50,
        ],
        30 => [
            'outfit_code' => 'starry_cape',
            'cards' => [
                ['code' => 'streak_30', 'label' => '一月里程'],
            ],
            'xp_bonus' => 100,
        ],
        60 => [
            'outfit_code' => 'moon_tiara',
            'cards' => [
                ['code' => 'streak_60', 'label' => '兩月堅持'],
            ],
        ],
        100 => [
            'outfit_code' => 'angel_wings',
            'cards' => [
                ['code' => 'streak_100', 'label' => '百日傳奇'],
            ],
            'xp_bonus' => 300,
        ],
    ];

    public function __construct(
        private readonly GamificationPublisher $publisher,
    ) {}

    /**
     * @return array{
     *   outfit_unlocked: ?string,
     *   outfit_skipped: ?string,
     *   cards_unlocked: list<array{code:string,label:string}>,
     *   xp_bonus: int,
     *   total_xp_after: ?int,
     * }
     */
    public function unlockForMilestone(User $user, int $streak): array
    {
        $reward = self::REWARDS[$streak] ?? null;
        if ($reward === null) {
            return [
                'outfit_unlocked' => null,
                'outfit_skipped' => null,
                'cards_unlocked' => [],
                'xp_bonus' => 0,
                'total_xp_after' => null,
            ];
        }

        $outfitUnlocked = null;
        $outfitSkipped = null;
        $code = $reward['outfit_code'] ?? null;
        if ($code !== null) {
            if ($this->outfitCodeKnown($code)) {
                $outfitUnlocked = $this->mergeOutfit($user, $code) ? $code : null;
            } else {
                // Catalog hasn't shipped this code yet — skip silently but report
                // so frontend doesn't lie about an unlock.
                $outfitSkipped = $code;
            }
        }

        $xpBonus = (int) ($reward['xp_bonus'] ?? 0);
        $totalXpAfter = null;
        if ($xpBonus > 0) {
            try {
                $totalXpAfter = $this->bumpLocalXp($user, $xpBonus);
            } catch (Throwable $e) {
                // XP write failure must not break the milestone reveal.
                Log::warning('[StreakMilestoneReward] xp bump failed (soft)', [
                    'user_id' => $user->id,
                    'streak' => $streak,
                    'xp_bonus' => $xpBonus,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->safePublish($user, $streak, $reward);

        return [
            'outfit_unlocked' => $outfitUnlocked,
            'outfit_skipped' => $outfitSkipped,
            'cards_unlocked' => $reward['cards'],
            'xp_bonus' => $xpBonus,
            'total_xp_after' => $totalXpAfter,
        ];
    }

    /**
     * Merge an outfit code into users.outfit_state.owned[]. Idempotent —
     * already-owned codes return false (no DB write, and the caller does NOT
     * report it as a fresh unlock so the toast doesn't repeat on the same day).
     */
    private function mergeOutfit(User $user, string $code): bool
    {
        $state = (array) ($user->outfit_state ?? []);
        $owned = (array) ($state['owned'] ?? []);
        if (in_array($code, $owned, true)) {
            return false;
        }
        $owned[] = $code;
        $state['owned'] = array_values(array_unique($owned));
        // equipped left untouched — only user toggles it via /me/outfit endpoint.
        $user->fill(['outfit_state' => $state]);
        $user->save();

        return true;
    }

    /**
     * Lossy local total_xp mirror — only goes up. Canonical XP / level lives
     * in py-service; this is a forward-compatible UX shortcut so the toast can
     * show "+N XP" without waiting for webhook round-trip. The webhook
     * (applyLevelUp) merges with upper-bound semantics so a later authoritative
     * value still wins.
     */
    private function bumpLocalXp(User $user, int $delta): int
    {
        $next = (int) ($user->total_xp ?? 0) + $delta;
        $user->total_xp = $next;
        $user->save();

        return $next;
    }

    private function outfitCodeKnown(string $code): bool
    {
        foreach (OutfitCatalog::all() as $o) {
            if ($o['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fail-soft publish — `calendar.streak_milestone_unlocked` is not in the
     * py-service catalog yet; publisher logs + drops unknown kinds, so this
     * call is a no-op until catalog catches up. Keeping the call site means
     * observability turns on without further code change once catalog adds it.
     */
    private function safePublish(User $user, int $streak, array $reward): void
    {
        if (empty($user->identity_uuid)) {
            return;
        }

        try {
            $this->publisher->publish(
                $user,
                'calendar.streak_milestone_unlocked',
                [
                    'streak' => $streak,
                    'outfit_code' => $reward['outfit_code'] ?? null,
                    'card_codes' => array_map(fn ($c) => $c['code'], $reward['cards'] ?? []),
                    'xp_bonus' => (int) ($reward['xp_bonus'] ?? 0),
                ],
                "calendar.streak_milestone_unlocked.{$user->identity_uuid}.{$streak}",
            );
        } catch (Throwable $e) {
            Log::warning('[StreakMilestoneReward] publish failed (soft)', [
                'user_id' => $user->id,
                'streak' => $streak,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
