<?php

use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\DodoCheckin;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('deletes calendar data for authenticated user but keeps mirror row', function () {
    $user = User::query()->create([
        'identity_uuid' => 'aaaa-test-uuid',
        'name' => 'TestUser',
        'display_name' => 'Tester',
        'total_xp' => 200,
        'level' => 5,
        'pet_species' => 'cat',
        'pet_nickname' => '小喵',
    ]);
    Cycle::query()->create(['user_id' => $user->id, 'start_date' => '2026-04-01']);
    CycleSymptom::query()->create(['user_id' => $user->id, 'logged_on' => '2026-04-02', 'tags' => ['cramp'], 'mood' => 'okay']);
    DodoCheckin::query()->create(['user_id' => $user->id, 'checked_on' => '2026-04-02', 'mood' => 'good', 'phase' => 'menstrual', 'cycle_day' => 2, 'dodo_response' => 'hi']);
    OutboxEvent::query()->create([
        'aggregate_type' => 'user',
        'aggregate_id' => $user->id,
        'event_kind' => 'calendar.cycle_logged',
        'destination' => OutboxEvent::DEST_GAMIFICATION,
        'payload' => ['x' => 1],
        'occurred_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $resp = $this->deleteJson('/api/v1/me');

    $resp->assertOk()
         ->assertJsonPath('status', 'ok')
         ->assertJsonPath('identity_uuid', 'aaaa-test-uuid');

    expect(Cycle::query()->count())->toBe(0);
    expect(CycleSymptom::query()->count())->toBe(0);
    expect(DodoCheckin::query()->count())->toBe(0);
    expect(OutboxEvent::query()->count())->toBe(0);

    // Mirror row 仍在（webhook reconcile 之後會 recreate；保留以避免拉不出來），
    // 但 personal 欄位全清空
    $u = User::query()->where('identity_uuid', 'aaaa-test-uuid')->first();
    expect($u)->not->toBeNull();
    expect($u->name)->toBeNull();
    expect($u->display_name)->toBeNull();
    expect($u->pet_species)->toBeNull();
    expect($u->pet_nickname)->toBeNull();
    expect($u->total_xp)->toBe(0);
    expect($u->level)->toBe(1);
});

it('returns 401 when not authenticated', function () {
    $this->deleteJson('/api/v1/me')->assertStatus(401);
});
