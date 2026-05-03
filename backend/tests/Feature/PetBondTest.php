<?php

use App\Models\User;
use App\Services\Economy\DodoCoinService;
use App\Services\Pet\PetBondService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['pet_species' => 'cat']);
    Sanctum::actingAs($this->user);
    $this->bond = app(PetBondService::class);
    $this->coins = app(DodoCoinService::class);
});

it('creates bond row on first award', function () {
    $bond = $this->bond->award($this->user->id, 'cat', 50);
    expect($bond->bond_xp)->toBe(50);
    expect($bond->pet_species)->toBe('cat');
});

it('maps xp to level along gentle curve', function () {
    expect($this->bond->currentLevel(0))->toBe(1);
    expect($this->bond->currentLevel(100))->toBe(5);
    expect($this->bond->currentLevel(400))->toBe(10);
    expect($this->bond->currentLevel(1500))->toBe(20);
    expect($this->bond->currentLevel(12000))->toBe(50);
});

it('maps level to intimacy tier', function () {
    expect($this->bond->intimacyTier(5))->toBe(PetBondService::TIER_FRIENDLY);
    expect($this->bond->intimacyTier(15))->toBe(PetBondService::TIER_CLOSE);
    expect($this->bond->intimacyTier(30))->toBe(PetBondService::TIER_SOULMATE);
});

it('feed costs coins and respects daily limit', function () {
    $this->coins->earn($this->user->id, 100, DodoCoinService::SOURCE_DAILY_ACTION);

    $first = $this->bond->feed($this->user->id, 'cat');
    expect($first)->not->toBeNull();
    $this->bond->feed($this->user->id, 'cat');
    $this->bond->feed($this->user->id, 'cat');
    $fourth = $this->bond->feed($this->user->id, 'cat');
    expect($fourth)->toBeNull(); // daily limit = 3
});

it('feed refuses when insufficient coins', function () {
    $bond = $this->bond->feed($this->user->id, 'cat');
    expect($bond)->toBeNull();
});

it('pet head respects daily limit of 5', function () {
    for ($i = 0; $i < 5; $i++) {
        expect($this->bond->petHead($this->user->id, 'cat'))->not->toBeNull();
    }
    expect($this->bond->petHead($this->user->id, 'cat'))->toBeNull();
});

it('exposes bond endpoint', function () {
    $this->bond->award($this->user->id, 'cat', 200);
    $res = $this->getJson('/api/v1/me/pet/bond')->assertOk();
    expect($res->json('data.bond_xp'))->toBe(200);
    expect($res->json('data.species'))->toBe('cat');
});
