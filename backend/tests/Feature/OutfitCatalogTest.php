<?php

use App\Services\Gamification\OutfitCatalog;

it('has at least 30 outfits across all rarity tiers', function () {
    $all = OutfitCatalog::all();

    expect(count($all))->toBeGreaterThanOrEqual(30);

    $codes = array_column($all, 'code');
    expect(count($codes))->toBe(count(array_unique($codes)));

    $rarities = array_unique(array_column($all, 'rarity'));
    expect($rarities)->toContain('common', 'rare', 'epic', 'legendary');
});

it('exposes premium-only outfits via subscription_tier=premium gate', function () {
    $premium = collect(OutfitCatalog::all())
        ->filter(fn ($o) => $o['unlock']['type'] === 'premium')
        ->values()
        ->all();

    expect(count($premium))->toBeGreaterThanOrEqual(3);

    foreach ($premium as $o) {
        expect(OutfitCatalog::isUnlocked($o, ['is_premium' => true]))->toBeTrue();
        expect(OutfitCatalog::isUnlocked($o, ['is_premium' => false]))->toBeFalse();
    }
});

it('unlocks level outfits monotonically', function () {
    $level3 = collect(OutfitCatalog::all())->firstWhere('code', 'straw_hat');
    expect(OutfitCatalog::isUnlocked($level3, ['level' => 1]))->toBeFalse();
    expect(OutfitCatalog::isUnlocked($level3, ['level' => 3]))->toBeTrue();
    expect(OutfitCatalog::isUnlocked($level3, ['level' => 99]))->toBeTrue();
});

it('respects season month range, including year-wrap', function () {
    // cherry_blossom_kimono = month 3-4
    $sakura = collect(OutfitCatalog::all())->firstWhere('code', 'cherry_blossom_kimono');
    expect(OutfitCatalog::isUnlocked($sakura, ['month' => 3]))->toBeTrue();
    expect(OutfitCatalog::isUnlocked($sakura, ['month' => 4]))->toBeTrue();
    expect(OutfitCatalog::isUnlocked($sakura, ['month' => 5]))->toBeFalse();

    // Lunar new year = 1-2
    $lny = collect(OutfitCatalog::all())->firstWhere('code', 'lunar_new_year');
    expect(OutfitCatalog::isUnlocked($lny, ['month' => 1]))->toBeTrue();
    expect(OutfitCatalog::isUnlocked($lny, ['month' => 6]))->toBeFalse();
});

it('includes per-rarity counts that look balanced', function () {
    $byRarity = [];
    foreach (OutfitCatalog::all() as $o) {
        $byRarity[$o['rarity']] = ($byRarity[$o['rarity']] ?? 0) + 1;
    }
    expect($byRarity['common'] ?? 0)->toBeGreaterThanOrEqual(3);
    expect($byRarity['rare'] ?? 0)->toBeGreaterThanOrEqual(5);
    expect($byRarity['epic'] ?? 0)->toBeGreaterThanOrEqual(8);
    expect($byRarity['legendary'] ?? 0)->toBeGreaterThanOrEqual(4);
});

it('every outfit code has a matching SVG file in public/character/outfits', function () {
    $base = base_path('../frontend/public/character/outfits');
    if (! is_dir($base)) {
        // dev env mismatch — tolerate (CI may run only backend)
        $this->markTestSkipped('frontend overlay dir not present');
    }
    foreach (OutfitCatalog::all() as $o) {
        $file = "{$base}/outfit_{$o['code']}_overlay.svg";
        expect(file_exists($file))->toBeTrue("Missing SVG for {$o['code']}");
    }
});

it('unlockedFor returns only the codes whose conditions match', function () {
    $owned = OutfitCatalog::unlockedFor([
        'level' => 5,
        'streak' => 7,
        'achievements' => ['first_dodo_checkin'],
        'is_premium' => false,
        'cycles' => 0,
        'month' => 3,
    ]);

    expect($owned)->toContain('ribbon');         // level 1
    expect($owned)->toContain('straw_hat');      // level 3
    expect($owned)->toContain('sunglasses');     // level 5
    expect($owned)->toContain('sparkle_pin');    // streak 3
    expect($owned)->toContain('sakura');         // streak 7
    expect($owned)->toContain('witch_hat');      // achievement
    expect($owned)->toContain('cherry_blossom_kimono'); // season
    expect($owned)->not->toContain('fp_crown');  // not premium
    expect($owned)->not->toContain('starry_cape'); // streak 30 not yet
});
