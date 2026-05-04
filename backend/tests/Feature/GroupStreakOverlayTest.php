<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * Phase 5B — GET /api/streak/today 帶上集團 master streak overlay 的 contract test。
 *
 * 紅線：fail-soft — py-service 掛 / timeout / no uuid 都必須回 group=null，
 * 不能讓 streak overlay 拖垮整個 boot。
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 4, 9, 0, 0, 'Asia/Taipei'));
    Cache::flush();
    config([
        'gamification.group_streak_url' => 'https://core.pandora.test/api/v1',
        'gamification.group_streak_secret' => 'test-secret',
        'gamification.group_streak_cache_ttl' => 30,
        'gamification.group_streak_timeout' => 5,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('streak today response includes group when py-service returns 200', function () {
    Http::fake([
        'core.pandora.test/api/v1/internal/group-streak/*' => Http::response([
            'user_uuid' => '11111111-1111-1111-1111-111111111111',
            'current_streak' => 12,
            'longest_streak' => 30,
            'last_login_date' => '2026-05-04',
            'last_seen_app' => 'meal',
            'today_in_streak' => true,
        ], 200),
    ]);

    $user = User::factory()->create([
        'identity_uuid' => '11111111-1111-1111-1111-111111111111',
    ]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/streak/today');

    $res->assertOk()
        ->assertJsonPath('group.current_streak', 12)
        ->assertJsonPath('group.longest_streak', 30)
        ->assertJsonPath('group.last_seen_app', 'meal')
        ->assertJsonPath('group.today_in_streak', true);

    Http::assertSent(function ($req) {
        return str_contains($req->url(), '/internal/group-streak/11111111-1111-1111-1111-111111111111')
            && $req->header('X-Internal-Secret')[0] === 'test-secret';
    });
});

it('group is null when user has no identity_uuid (unbound)', function () {
    Http::fake();
    $user = User::factory()->create(['identity_uuid' => null]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/streak/today');

    $res->assertOk()->assertJsonPath('group', null);
    Http::assertNothingSent();
});

it('group is null fail-soft when py-service returns 500', function () {
    Http::fake([
        'core.pandora.test/api/v1/internal/group-streak/*' => Http::response([], 500),
    ]);

    $user = User::factory()->create([
        'identity_uuid' => '22222222-2222-2222-2222-222222222222',
    ]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/streak/today');
    $res->assertOk()->assertJsonPath('group', null);
});

it('group is null fail-soft on connection exception', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('timeout');
    });

    $user = User::factory()->create([
        'identity_uuid' => '33333333-3333-3333-3333-333333333333',
    ]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/streak/today');
    $res->assertOk()->assertJsonPath('group', null);
});

it('group cache short-circuits second call within TTL', function () {
    Http::fake([
        'core.pandora.test/api/v1/internal/group-streak/*' => Http::response([
            'user_uuid' => '44444444-4444-4444-4444-444444444444',
            'current_streak' => 5,
            'longest_streak' => 5,
            'last_login_date' => '2026-05-04',
            'last_seen_app' => 'calendar',
            'today_in_streak' => true,
        ], 200),
    ]);

    $user = User::factory()->create([
        'identity_uuid' => '44444444-4444-4444-4444-444444444444',
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/streak/today')->assertOk();
    $this->getJson('/api/streak/today')->assertOk();

    // 第二次走 cache，py-service 只被打一次
    Http::assertSentCount(1);
});

it('group is null when group_streak_url is not configured', function () {
    config([
        'gamification.group_streak_url' => null,
        'gamification.group_streak_secret' => null,
    ]);

    $user = User::factory()->create([
        'identity_uuid' => '55555555-5555-5555-5555-555555555555',
    ]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/streak/today');
    $res->assertOk()->assertJsonPath('group', null);
});
