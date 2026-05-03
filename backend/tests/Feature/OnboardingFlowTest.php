<?php

use App\Models\Cycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('completes onboarding and creates first cycle', function () {
    $res = $this->postJson('/api/v1/onboarding/complete', [
        'last_period_at' => now()->subDays(10)->toDateString(),
        'cycle_length' => 30,
        'goal' => 'tracking',
    ]);

    $res->assertOk()
        ->assertJsonPath('data.onboarded', true)
        ->assertJsonPath('data.preferences.cycle_length', 30)
        ->assertJsonPath('data.preferences.goal', 'tracking');

    expect(Cycle::where('user_id', $this->user->id)->count())->toBe(1);
});

it('returns onboarding status before complete', function () {
    $this->getJson('/api/v1/onboarding/status')
        ->assertOk()
        ->assertJsonPath('data.onboarded', false);
});

it('rejects future last_period_at', function () {
    $this->postJson('/api/v1/onboarding/complete', [
        'last_period_at' => now()->addDays(3)->toDateString(),
    ])->assertStatus(422);
});
