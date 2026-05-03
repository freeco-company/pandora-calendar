<?php

use App\Models\User;
use App\Services\Economy\DodoCoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->coins = app(DodoCoinService::class);
});

it('starts with zero balance', function () {
    expect($this->coins->balance($this->user->id))->toBe(0);
});

it('earns coins and updates balance_after snapshot', function () {
    $this->coins->earn($this->user->id, 50, DodoCoinService::SOURCE_DAILY_ACTION);
    $this->coins->earn($this->user->id, 30, DodoCoinService::SOURCE_STREAK);
    expect($this->coins->balance($this->user->id))->toBe(80);
});

it('refuses spend when balance insufficient', function () {
    $this->coins->earn($this->user->id, 20, DodoCoinService::SOURCE_DAILY_ACTION);
    $result = $this->coins->spend($this->user->id, 100, DodoCoinService::SOURCE_SPEND_OUTFIT);
    expect($result)->toBeNull();
    expect($this->coins->balance($this->user->id))->toBe(20);
});

it('spends successfully when enough', function () {
    $this->coins->earn($this->user->id, 200, DodoCoinService::SOURCE_DAILY_ACTION);
    $trans = $this->coins->spend($this->user->id, 100, DodoCoinService::SOURCE_SPEND_OUTFIT, ['outfit' => 'sakura']);
    expect($trans)->not->toBeNull();
    expect($this->coins->balance($this->user->id))->toBe(100);
    expect($trans->delta)->toBe(-100);
});

it('rejects invalid source', function () {
    expect(fn () => $this->coins->earn($this->user->id, 10, 'random_made_up_source'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects non-positive deltas', function () {
    expect(fn () => $this->coins->earn($this->user->id, 0, DodoCoinService::SOURCE_DAILY_ACTION))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->coins->spend($this->user->id, -5, DodoCoinService::SOURCE_SPEND_OUTFIT))
        ->toThrow(InvalidArgumentException::class);
});

it('exposes balance + history endpoints', function () {
    $this->coins->earn($this->user->id, 100, DodoCoinService::SOURCE_DAILY_ACTION);
    $this->coins->earn($this->user->id, 50, DodoCoinService::SOURCE_ACHIEVEMENT);

    $balance = $this->getJson('/api/v1/economy/balance')->assertOk();
    expect($balance->json('data.balance'))->toBe(150);

    $history = $this->getJson('/api/v1/economy/history')->assertOk();
    expect($history->json('data'))->toHaveCount(2);
});
