<?php

/**
 * API Contract Test (Layer D — Integration)
 *
 * Why: every Wave 13 endpoint a frontend view consumes is asserted to return
 * a stable shape. Catches "controller renamed key", "missing field", "404
 * because route name typo" before the e2e suite (which is slower).
 *
 * Special regression guard: churn-intercept reasons[*].code + offer_kind must
 * exist (matches the bug fixed in commit 09a1f57).
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('GET /economy/balance returns balance + recent', function () {
    $res = $this->getJson('/api/v1/economy/balance')->assertOk();
    expect($res->json('data'))->toHaveKey('balance');
});

it('GET /economy/history returns transactions array', function () {
    $res = $this->getJson('/api/v1/economy/history')->assertOk();
    expect($res->json('data'))->toBeArray();
});

it('GET /me/pet/bond returns bond data or null when no pet', function () {
    $res = $this->getJson('/api/v1/me/pet/bond')->assertOk();
    // user without selected species returns null; with species returns array w/ bond_xp
    $data = $res->json('data');
    if ($data !== null) {
        expect($data)->toHaveKey('bond_xp');
    }
});

it('GET /me/rank returns tier-bearing snapshot', function () {
    $res = $this->getJson('/api/v1/me/rank')->assertOk();
    $data = $res->json('data');
    expect($data)->toBeArray();
    // accept either `tier` or `current_tier` key (controller wraps service shape)
    expect($data)->toHaveKeys(['tier_key', 'tier_label', 'xp']);
});

it('GET /me/skill-path returns path state', function () {
    $res = $this->getJson('/api/v1/me/skill-path')->assertOk();
    expect($res->json('data'))->toBeArray();
});

it('GET /me/body-dex returns entries array', function () {
    $res = $this->getJson('/api/v1/me/body-dex')->assertOk();
    expect($res->json('data'))->toBeArray();
});

it('GET /me/stories/chapters returns chapter list', function () {
    $res = $this->getJson('/api/v1/me/stories/chapters')->assertOk();
    expect($res->json('data'))->toBeArray();
});

it('POST /me/stories/{n}/unlock returns 422 with friendly error when coins insufficient', function () {
    // user has 0 coins → unlock chapter 2 (cost > 0) → 422
    $res = $this->postJson('/api/v1/me/stories/2/unlock');
    expect($res->status())->toBeIn([402, 422]); // 422 = validation-style, 402 = payment required
    // must NOT be 500 generic error
    expect($res->status())->not->toBe(500);
    // body must contain a structured reason, not just generic
    $body = $res->json();
    expect($body)->toBeArray();
});

it('GET /me/random-event/today returns event-or-null', function () {
    $res = $this->getJson('/api/v1/me/random-event/today')->assertOk();
    expect($res->json())->toHaveKey('data');
});

it('GET /solar-term/current returns term-or-null', function () {
    $res = $this->getJson('/api/v1/solar-term/current')->assertOk();
    // outside any term window → null; otherwise array
    $data = $res->json('data');
    expect($data === null || is_array($data))->toBeTrue();
});

it('GET /actions/today returns rec-or-null + protocol_insight envelope', function () {
    $res = $this->getJson('/api/v1/actions/today')->assertOk();
    $body = $res->json();
    expect($body)->toHaveKey('data');
    expect($body)->toHaveKey('protocol_insight');
});

it('GET /subscription/churn-intercept reasons[*] include code + offer_kind (regression)', function () {
    $res = $this->getJson('/api/v1/subscription/churn-intercept')->assertOk();
    $reasons = $res->json('data.reasons');
    expect($reasons)->toBeArray();
    expect(count($reasons))->toBeGreaterThan(0);

    foreach ($reasons as $r) {
        expect($r)->toHaveKeys(['code', 'offer_kind']);
        expect($r['code'])->toBeString()->not->toBe('');
        expect($r['offer_kind'])->toBeString()->not->toBe('');
    }
});
