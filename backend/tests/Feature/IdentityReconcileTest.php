<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.pandora_core.base_url', 'https://id.test');
    config()->set('services.pandora_core.internal_secret', 'test-secret');
    Cache::flush();
});

it('upserts users from a single page reconcile response', function () {
    Http::fake([
        'https://id.test/api/internal/reconcile/users*' => Http::response([
            'users' => [
                ['id' => 'aaaa-uuid-1', 'display_name' => 'Alice', 'status' => 'active', 'updated_at' => '2026-05-03T00:00:00Z'],
                ['id' => 'bbbb-uuid-2', 'display_name' => 'Bob', 'status' => 'active', 'updated_at' => '2026-05-03T00:01:00Z'],
            ],
            'next_cursor' => null,
            'has_more' => false,
            'count' => 2,
        ], 200),
    ]);

    $exit = $this->artisan('identity:reconcile-users')->run();

    expect($exit)->toBe(0);
    expect(User::query()->count())->toBe(2);
    expect(User::where('identity_uuid', 'aaaa-uuid-1')->first()->display_name)->toBe('Alice');
});

it('paginates through has_more pages and persists cursor', function () {
    Http::fakeSequence()
        ->push([
            'users' => [['id' => 'p1-uuid', 'display_name' => 'P1', 'status' => 'active', 'updated_at' => '2026-05-03T00:00:00Z']],
            'next_cursor' => '2026-05-03T00:00:00Z',
            'has_more' => true,
            'count' => 1,
        ], 200)
        ->push([
            'users' => [['id' => 'p2-uuid', 'display_name' => 'P2', 'status' => 'active', 'updated_at' => '2026-05-03T00:05:00Z']],
            'next_cursor' => null,
            'has_more' => false,
            'count' => 1,
        ], 200);

    $this->artisan('identity:reconcile-users')->assertExitCode(0);

    expect(User::query()->count())->toBe(2);
    expect(Cache::get('identity:reconcile:cursor'))->toBe('2026-05-03T00:05:00Z');
});

it('updates display_name on subsequent runs', function () {
    User::query()->create([
        'identity_uuid' => 'ccc-uuid',
        'name' => 'placeholder',
        'display_name' => 'Old Name',
    ]);

    Http::fake([
        'https://id.test/api/internal/reconcile/users*' => Http::response([
            'users' => [['id' => 'ccc-uuid', 'display_name' => 'New Name', 'status' => 'active', 'updated_at' => '2026-05-03T01:00:00Z']],
            'next_cursor' => null,
            'has_more' => false,
            'count' => 1,
        ], 200),
    ]);

    $this->artisan('identity:reconcile-users')->assertExitCode(0);

    expect(User::where('identity_uuid', 'ccc-uuid')->first()->display_name)->toBe('New Name');
});

it('fails gracefully on HTTP error and does not advance cursor', function () {
    Cache::forever('identity:reconcile:cursor', '2026-05-01T00:00:00Z');

    Http::fake([
        'https://id.test/api/internal/reconcile/users*' => Http::response('boom', 500),
    ]);

    $this->artisan('identity:reconcile-users')->assertExitCode(1);

    expect(Cache::get('identity:reconcile:cursor'))->toBe('2026-05-01T00:00:00Z');
});

it('errors when base_url or secret missing', function () {
    config()->set('services.pandora_core.base_url', '');

    $this->artisan('identity:reconcile-users')->assertExitCode(1);
});

it('reset option clears cursor', function () {
    Cache::forever('identity:reconcile:cursor', '2026-05-02T00:00:00Z');
    Http::fake([
        'https://id.test/api/internal/reconcile/users*' => Http::response([
            'users' => [], 'next_cursor' => null, 'has_more' => false, 'count' => 0,
        ], 200),
    ]);

    $this->artisan('identity:reconcile-users', ['--reset' => true])->assertExitCode(0);

    Http::assertSent(function ($req) {
        return str_contains($req->url(), 'since=1970-01-01T00%3A00%3A00Z');
    });
});
