<?php

use App\Models\Cycle;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\Gamification\CalendarEventCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['identity_uuid' => '00000000-0000-0000-0000-000000000001']);
    Sanctum::actingAs($this->user);
});

it('writes first_cycle + cycle_logged outbox events on first cycle creation', function () {
    $this->postJson('/api/v1/cycles', [
        'start_date' => CarbonImmutable::today()->subDays(3)->toDateString(),
    ])->assertCreated();

    $events = OutboxEvent::where('aggregate_id', $this->user->id)
        ->where('destination', OutboxEvent::DEST_GAMIFICATION)
        ->pluck('event_kind')->all();

    expect($events)->toContain(CalendarEventCatalog::FIRST_CYCLE);
    expect($events)->toContain(CalendarEventCatalog::CYCLE_LOGGED);
});

it('does not emit first_cycle on second cycle', function () {
    Cycle::create(['user_id' => $this->user->id, 'start_date' => CarbonImmutable::today()->subDays(35)->toDateString()]);

    $this->postJson('/api/v1/cycles', [
        'start_date' => CarbonImmutable::today()->subDays(5)->toDateString(),
    ])->assertCreated();

    $firstCount = OutboxEvent::where('event_kind', CalendarEventCatalog::FIRST_CYCLE)->count();
    expect($firstCount)->toBe(0);
});

it('writes body_rhythm outbox event when cycle saved', function () {
    $this->postJson('/api/v1/cycles', [
        'start_date' => CarbonImmutable::today()->subDays(3)->toDateString(),
    ])->assertCreated();

    $br = OutboxEvent::where('destination', OutboxEvent::DEST_BODY_RHYTHM)->first();
    expect($br)->not->toBeNull();
    expect($br->payload['source_app'])->toBe('pandora_calendar');
    expect($br->payload['schema_version'])->toBe(1);
    expect($br->payload['user_uuid'])->toBe($this->user->identity_uuid);
});

it('rejects publishing unknown event_kind', function () {
    $publisher = app(\App\Services\Gamification\GamificationPublisher::class);

    expect(fn () => $publisher->publish($this->user, 'calendar.totally_made_up'))
        ->toThrow(InvalidArgumentException::class);
});
