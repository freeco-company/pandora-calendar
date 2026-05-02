<?php

use App\Models\Cycle;
use App\Models\OutboxEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Gamification\CalendarEventCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'identity_uuid' => '00000000-0000-0000-0000-000000000010',
    ]);
    Sanctum::actingAs($this->user);
});

it('publishes app_opened on /me hit with idempotency key', function () {
    $this->getJson('/api/v1/me')->assertOk();

    $events = OutboxEvent::where('event_kind', CalendarEventCatalog::APP_OPENED)->get();
    expect($events)->toHaveCount(1);
    expect($events->first()->idempotency_key)->toContain(CalendarEventCatalog::APP_OPENED);
    expect($events->first()->idempotency_key)->toContain((string) $this->user->id);
});

it('app_opened idempotency dedupes same-day repeat hits', function () {
    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/v1/me')->assertOk();

    expect(OutboxEvent::where('event_kind', CalendarEventCatalog::APP_OPENED)->count())->toBe(1);
});

it('publishes symptom_logged when tags non-empty', function () {
    $this->postJson('/api/v1/symptoms', [
        'logged_on' => CarbonImmutable::today()->toDateString(),
        'tags' => ['cramp', 'fatigue'],
    ])->assertCreated();

    expect(OutboxEvent::where('event_kind', CalendarEventCatalog::SYMPTOM_LOGGED)->count())->toBe(1);
    expect(OutboxEvent::where('event_kind', CalendarEventCatalog::MOOD_LOGGED)->count())->toBe(0);
});

it('publishes mood_logged when mood set', function () {
    $this->postJson('/api/v1/symptoms', [
        'logged_on' => CarbonImmutable::today()->toDateString(),
        'tags' => ['cramp'],
        'mood' => 'okay',
    ])->assertCreated();

    expect(OutboxEvent::where('event_kind', CalendarEventCatalog::MOOD_LOGGED)->count())->toBe(1);
    expect(OutboxEvent::where('event_kind', CalendarEventCatalog::SYMPTOM_LOGGED)->count())->toBe(1);
});

it('publishes full_cycle_tracked when cycle has end_date', function () {
    $start = CarbonImmutable::today()->subDays(5)->toDateString();
    $end = CarbonImmutable::today()->subDays(1)->toDateString();

    $this->postJson('/api/v1/cycles', [
        'start_date' => $start,
        'end_date' => $end,
        'peak_flow' => 3,
    ])->assertCreated();

    expect(OutboxEvent::where('event_kind', CalendarEventCatalog::FULL_CYCLE_TRACKED)->count())->toBe(1);
});

it('does not publish full_cycle_tracked when end_date missing', function () {
    $this->postJson('/api/v1/cycles', [
        'start_date' => CarbonImmutable::today()->subDays(2)->toDateString(),
    ])->assertCreated();

    expect(OutboxEvent::where('event_kind', CalendarEventCatalog::FULL_CYCLE_TRACKED)->count())->toBe(0);
});

it('publishes track_7_days milestone after 7 distinct dated logs in a week', function () {
    $today = CarbonImmutable::today();
    // 6 symptom days
    for ($i = 1; $i <= 6; $i++) {
        $this->postJson('/api/v1/symptoms', [
            'logged_on' => $today->subDays($i)->toDateString(),
            'tags' => ['cramp'],
        ])->assertCreated();
    }
    // 7th = a cycle today
    $this->postJson('/api/v1/cycles', [
        'start_date' => $today->toDateString(),
    ])->assertCreated();

    expect(OutboxEvent::where('event_kind', CalendarEventCatalog::TRACK_7_DAYS)->count())->toBe(1);
});

it('publishes insight_read when premium pms endpoint returns a pattern', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-insight',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);

    // Seed enough symptoms across cycles to trip pattern detection
    $today = CarbonImmutable::today();
    for ($i = 0; $i < 3; $i++) {
        Cycle::create([
            'user_id' => $this->user->id,
            'start_date' => $today->subDays(28 * ($i + 1))->toDateString(),
            'end_date' => $today->subDays(28 * ($i + 1) - 4)->toDateString(),
            'peak_flow' => 3,
        ]);
        for ($d = 1; $d <= 5; $d++) {
            \App\Models\CycleSymptom::create([
                'user_id' => $this->user->id,
                'logged_on' => $today->subDays(28 * ($i + 1) + $d)->toDateString(),
                'tags' => ['cramp', 'fatigue'],
                'mood' => 'okay',
            ]);
        }
    }

    $res = $this->getJson('/api/v1/insight/pms')->assertOk();

    if ($res->json('data') !== null) {
        expect(OutboxEvent::where('event_kind', CalendarEventCatalog::INSIGHT_READ)->count())->toBe(1);
    }
});

it('publishes dodo_checkin with idempotency key for same day', function () {
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => CarbonImmutable::today()->subDays(2)->toDateString(),
    ]);

    $this->postJson('/api/v1/dodo/checkin', ['mood' => 'good'])->assertCreated();

    $event = OutboxEvent::where('event_kind', CalendarEventCatalog::DODO_CHECKIN)->first();
    expect($event)->not->toBeNull();
    expect($event->idempotency_key)->toContain(CalendarEventCatalog::DODO_CHECKIN);
});

it('publishes cycle_logged with idempotency key referencing cycle id', function () {
    $this->postJson('/api/v1/cycles', [
        'start_date' => CarbonImmutable::today()->subDays(3)->toDateString(),
    ])->assertCreated();

    $event = OutboxEvent::where('event_kind', CalendarEventCatalog::CYCLE_LOGGED)->first();
    expect($event)->not->toBeNull();
    expect($event->idempotency_key)->toContain(CalendarEventCatalog::CYCLE_LOGGED);
});

it('rejects publishing unknown event_kind via catalog guard', function () {
    $publisher = app(\App\Services\Gamification\GamificationPublisher::class);

    expect(fn () => $publisher->publish($this->user, 'calendar.totally_made_up'))
        ->toThrow(InvalidArgumentException::class);
});
