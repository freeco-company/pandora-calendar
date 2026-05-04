<?php

use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makePremium(User $user): void
{
    Subscription::create([
        'user_id' => $user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-prem-'.$user->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('blocks free user from generating week report', function () {
    $this->getJson('/api/v1/week-report/latest')->assertStatus(402);
});

it('lets premium user generate week report with summary', function () {
    makePremium($this->user);
    \App\Models\Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => CarbonImmutable::now()->startOfWeek()->addDays(2)->toDateString(),
    ]);

    $res = $this->postJson('/api/v1/week-report/generate')
        ->assertCreated();

    expect($res->json('data.summary.cycles_started'))->toBeGreaterThanOrEqual(1);
});

it('lets free user see PMS basic shape (freemium 2026-05-04 放寬，不再 402)', function () {
    $res = $this->getJson('/api/v1/insight/pms');
    $res->assertOk()->assertJsonPath('tier', 'free');
});

it('blocks free user from pregnancy', function () {
    $this->postJson('/api/v1/pregnancy', [
        'lmp_date' => now()->subDays(60)->toDateString(),
    ])->assertStatus(402);
});

it('lets premium user start a pregnancy and computes due date', function () {
    makePremium($this->user);

    $res = $this->postJson('/api/v1/pregnancy', [
        'lmp_date' => now()->subDays(30)->toDateString(),
    ])->assertCreated();

    expect($res->json('data.gestational_week'))->toBeGreaterThanOrEqual(4);
    expect($res->json('data.estimated_due_date'))->not->toBeEmpty();
});

it('blocks free user from health sample import', function () {
    $this->postJson('/api/v1/health-samples/import', [
        'source' => 'healthkit',
        'samples' => [['metric' => 'basal_temp', 'value' => 36.5, 'recorded_on' => now()->toDateString()]],
    ])->assertStatus(402);
});

it('lets premium user import health samples', function () {
    makePremium($this->user);

    $this->postJson('/api/v1/health-samples/import', [
        'source' => 'healthkit',
        'samples' => [
            ['metric' => 'basal_temp', 'value' => 36.5, 'recorded_on' => now()->toDateString()],
            ['metric' => 'sleep_hours', 'value' => 7.2, 'recorded_on' => now()->toDateString()],
        ],
    ])->assertCreated()->assertJsonPath('data.imported', 2);
});
