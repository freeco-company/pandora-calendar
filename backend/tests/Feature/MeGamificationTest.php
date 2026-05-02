<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'identity_uuid' => '22222222-2222-2222-2222-222222222222',
        'total_xp' => 120,
        'level' => 4,
        'outfit_state' => ['owned' => ['default', 'crown'], 'equipped' => 'crown'],
        'pet_species' => 'cat',
        'pet_nickname' => '小毛',
    ]);
    Sanctum::actingAs($this->user);
});

it('returns dodo level + total_xp + outfit + mood', function () {
    $res = $this->getJson('/api/v1/me/dodo')->assertOk();

    expect($res->json('data.level'))->toBe(4);
    expect($res->json('data.total_xp'))->toBe(120);
    expect($res->json('data.outfit_state.equipped'))->toBe('crown');
    expect($res->json('data.mood'))->toBe('celebrating');  // crown equipped
});

it('returns pet species + nickname + level', function () {
    $res = $this->getJson('/api/v1/me/pet')->assertOk();

    expect($res->json('data.species'))->toBe('cat');
    expect($res->json('data.nickname'))->toBe('小毛');
    expect($res->json('data.level'))->toBe(4);
});

it('mood falls through level thresholds when no celebratory outfit', function () {
    $this->user->update(['outfit_state' => null, 'level' => 12]);
    expect($this->getJson('/api/v1/me/dodo')->json('data.mood'))->toBe('cheerful');

    $this->user->update(['level' => 5]);
    expect($this->getJson('/api/v1/me/dodo')->json('data.mood'))->toBe('content');

    $this->user->update(['level' => 1]);
    expect($this->getJson('/api/v1/me/dodo')->json('data.mood'))->toBe('sleepy');
});

it('pending endpoint returns null when nothing cached', function () {
    Cache::forget("gamification:pending:{$this->user->identity_uuid}");
    $this->getJson('/api/v1/me/gamification/pending')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('pending endpoint returns cached payload then clears it', function () {
    Cache::put("gamification:pending:{$this->user->identity_uuid}", [
        'kind' => 'level_up',
        'level' => 5,
    ], 60);

    $this->getJson('/api/v1/me/gamification/pending')
        ->assertOk()
        ->assertJsonPath('data.kind', 'level_up')
        ->assertJsonPath('data.level', 5);

    // Subsequent pull → null（已 pull 清空）
    $this->getJson('/api/v1/me/gamification/pending')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('me endpoint exposes new gamification fields', function () {
    $res = $this->getJson('/api/v1/me')->assertOk();
    expect($res->json('data.total_xp'))->toBe(120);
    expect($res->json('data.level'))->toBe(4);
    expect($res->json('data.pet_species'))->toBe('cat');
});
