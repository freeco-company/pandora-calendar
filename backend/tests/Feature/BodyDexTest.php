<?php

use App\Models\User;
use App\Services\Gamification\BodyDexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->dex = app(BodyDexService::class);
});

it('records new symptom as entry with count 1', function () {
    $entry = $this->dex->record($this->user->id, 'cramp');
    expect($entry->log_count)->toBe(1);
    expect($entry->symptom_key)->toBe('cramp');
});

it('increments count on repeat record', function () {
    $this->dex->record($this->user->id, 'fatigue');
    $this->dex->record($this->user->id, 'fatigue');
    $this->dex->record($this->user->id, 'fatigue');
    $collected = $this->dex->collected($this->user->id);
    expect($collected)->toHaveCount(1);
    expect($collected->first()->log_count)->toBe(3);
});

it('returns 30 catalog entries', function () {
    expect($this->dex->totalTarget())->toBe(30);
    expect($this->dex->catalog())->toHaveCount(30);
});

it('snapshot reports collected vs total', function () {
    $this->dex->record($this->user->id, 'cramp');
    $this->dex->record($this->user->id, 'headache');
    $snap = $this->dex->snapshot($this->user->id);
    expect($snap['collected_count'])->toBe(2);
    expect($snap['total_target'])->toBe(30);
});

it('exposes endpoint', function () {
    $this->dex->record($this->user->id, 'cramp');
    $res = $this->getJson('/api/v1/me/body-dex')->assertOk();
    expect($res->json('data.collected_count'))->toBe(1);
    expect($res->json('data.entries'))->toHaveCount(30);
});
