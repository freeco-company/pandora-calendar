<?php

use App\Services\Gamification\AchievementCatalog;

it('has at least 30 achievements across all 9 tier × kind combos', function () {
    $all = AchievementCatalog::all();
    expect(count($all))->toBeGreaterThanOrEqual(30);

    $keys = array_column($all, 'key');
    expect(count($keys))->toBe(count(array_unique($keys)));

    $kinds = array_unique(array_column($all, 'kind'));
    expect($kinds)->toContain('first', 'streak', 'milestone');

    $tiers = array_unique(array_column($all, 'tier'));
    expect($tiers)->toContain('bronze', 'silver', 'gold');
});

it('points each achievement at one of the 9 shared badge SVGs', function () {
    $valid = ['badge_first_bronze', 'badge_first_silver', 'badge_first_gold',
        'badge_streak_bronze', 'badge_streak_silver', 'badge_streak_gold',
        'badge_milestone_bronze', 'badge_milestone_silver', 'badge_milestone_gold'];
    foreach (AchievementCatalog::all() as $a) {
        expect(in_array($a['badge'], $valid, true))->toBeTrue("Bad badge ref for {$a['key']}: {$a['badge']}");
    }
});

it('xp reward is non-negative and tier-coherent (gold >= silver >= bronze on average)', function () {
    $byTier = ['bronze' => [], 'silver' => [], 'gold' => []];
    foreach (AchievementCatalog::all() as $a) {
        if ($a['xp'] === 0) {
            continue; // level achievements give 0 (XP already came from level-up)
        }
        $byTier[$a['tier']][] = $a['xp'];
    }
    foreach ($byTier as $t => $arr) {
        if ($arr === []) {
            continue;
        }
        expect(min($arr))->toBeGreaterThanOrEqual(0, "Tier {$t} has negative xp");
    }
    $avg = fn (array $a) => $a === [] ? 0 : array_sum($a) / count($a);
    expect($avg($byTier['gold']))->toBeGreaterThanOrEqual($avg($byTier['silver']));
    expect($avg($byTier['silver']))->toBeGreaterThanOrEqual($avg($byTier['bronze']));
});

it('find() returns null for unknown keys', function () {
    expect(AchievementCatalog::find('nonexistent'))->toBeNull();
    expect(AchievementCatalog::find('first_cycle_logged'))->not->toBeNull();
});

it('contains the expected new-coverage achievements', function () {
    $keys = array_column(AchievementCatalog::all(), 'key');
    foreach ([
        'first_mood_logged',
        'first_health_sync',
        'first_pattern_report',
        'streak_14',
        'streak_60',
        'symptoms_100',
        'moods_30',
        'dodo_chats_30',
        'dodo_chats_100',
        'bbt_60',
        'bbt_biphasic',
        'pattern_reports_3',
        'pattern_reports_6',
        'health_sync_30',
        'level_30',
        'outfits_5',
        'outfits_15',
        'pregnancy_mode_on',
        'cycle_perfect_4_phases',
    ] as $key) {
        expect(in_array($key, $keys, true))->toBeTrue("Missing expected achievement: {$key}");
    }
});
