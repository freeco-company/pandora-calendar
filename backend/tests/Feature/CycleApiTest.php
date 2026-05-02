<?php

use App\Models\Cycle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('lists empty cycles cleanly', function () {
    $res = $this->getJson('/api/v1/cycles');

    $res->assertOk()
        ->assertJsonPath('prediction.sample_size', 0)
        ->assertJsonPath('prediction.confidence', 'none')
        ->assertJsonPath('body_rhythm.phase', 'unknown');
});

it('records a cycle and predicts next period', function () {
    $today = CarbonImmutable::today();

    foreach ([90, 60, 30] as $daysAgo) {
        $this->postJson('/api/v1/cycles', [
            'start_date' => $today->subDays($daysAgo)->toDateString(),
            'end_date' => $today->subDays($daysAgo - 4)->toDateString(),
            'peak_flow' => 3,
        ])->assertCreated();
    }

    $res = $this->getJson('/api/v1/cycles');

    $res->assertOk()
        ->assertJsonPath('prediction.sample_size', 3)
        ->assertJsonPath('prediction.avg_cycle_length', 30);

    expect($res->json('prediction.next_period_eta'))->toBe(
        $today->addDays(0)->toDateString()
    );
});

it('computes body rhythm phase based on most recent cycle', function () {
    $today = CarbonImmutable::today();

    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => $today->subDays(7)->toDateString(),
        'end_date' => $today->subDays(3)->toDateString(),
        'peak_flow' => 3,
    ]);

    $res = $this->getJson('/api/v1/body-rhythm/me');

    $res->assertOk()
        ->assertJsonPath('source', 'pandora-calendar')
        ->assertJsonPath('schema_version', 1);

    expect($res->json('data.phase'))->toBeIn(['follicular', 'menstrual']);
    expect($res->json('data.cycle_day'))->toBe(8);
});

it('records a symptom with allowed tags only', function () {
    $today = CarbonImmutable::today();

    $this->postJson('/api/v1/symptoms', [
        'logged_on' => $today->toDateString(),
        'tags' => ['cramp', 'fatigue'],
        'mood' => 'okay',
    ])->assertCreated();

    $this->postJson('/api/v1/symptoms', [
        'logged_on' => $today->toDateString(),
        'tags' => ['not_a_real_tag'],
    ])->assertStatus(422);
});

it('returns dodo response varying by mood', function () {
    $today = CarbonImmutable::today();

    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => $today->subDays(2)->toDateString(),
        'peak_flow' => 3,
    ]);

    $good = $this->postJson('/api/v1/dodo/checkin', ['mood' => 'good'])
        ->assertCreated()
        ->json('data.dodo_response');

    // re-checkin same day should update mood
    $bad = $this->postJson('/api/v1/dodo/checkin', ['mood' => 'bad'])
        ->assertCreated()
        ->json('data.dodo_response');

    expect($good)->not->toBe($bad);
    expect($good)->not->toBeEmpty();
});

it('rejects invalid mood', function () {
    $this->postJson('/api/v1/dodo/checkin', ['mood' => 'rage'])->assertStatus(422);
});

it('blocks deleting another users cycle', function () {
    $other = User::factory()->create();
    $cycle = Cycle::create([
        'user_id' => $other->id,
        'start_date' => CarbonImmutable::today()->subDays(5)->toDateString(),
    ]);

    $this->deleteJson("/api/v1/cycles/{$cycle->id}")->assertForbidden();
});
