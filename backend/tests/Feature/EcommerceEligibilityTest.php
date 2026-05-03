<?php

use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

function makeFullyEligibleUser(\App\Models\User $user): void
{
    $user->update([
        'mother_customer_id' => 9001,
        'mother_total_orders' => 2,
    ]);
    Subscription::create([
        'user_id' => $user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-elig-'.$user->id,
        'starts_at' => now()->subDays(120),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
    Cycle::create([
        'user_id' => $user->id,
        'start_date' => CarbonImmutable::today()->subDays(95)->toDateString(),
    ]);
}

it('eligibility endpoint returns not-linked + no-purchase + too-new for fresh user', function () {
    $res = $this->getJson('/api/v1/ecommerce/eligibility');

    $res->assertOk()
        ->assertJsonPath('data.eligible', false);
    $reasons = $res->json('data.reasons');
    expect($reasons)->toContain('not_linked');
    expect($reasons)->toContain('no_purchase');
    expect($reasons)->toContain('no_subscription');
    expect($reasons)->toContain('too_new');
});

it('eligibility endpoint marks no_subscription when mother + 90d but no premium', function () {
    $this->user->update(['mother_customer_id' => 5, 'mother_total_orders' => 1]);
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => CarbonImmutable::today()->subDays(120)->toDateString(),
    ]);

    $res = $this->getJson('/api/v1/ecommerce/eligibility');
    expect($res->json('data.reasons'))->toContain('no_subscription');
    expect($res->json('data.reasons'))->not->toContain('not_linked');
    expect($res->json('data.reasons'))->not->toContain('too_new');
});

it('eligibility endpoint marks too_new when premium + mother but only 30 days usage', function () {
    $this->user->update(['mother_customer_id' => 5, 'mother_total_orders' => 1, 'created_at' => now()->subDays(30)]);
    Subscription::create([
        'user_id' => $this->user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-too-new',
        'starts_at' => now()->subDays(20),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => CarbonImmutable::today()->subDays(30)->toDateString(),
    ]);

    expect($this->getJson('/api/v1/ecommerce/eligibility')->json('data.reasons'))->toContain('too_new');
});

it('eligibility endpoint passes only when all 4 conditions are met', function () {
    makeFullyEligibleUser($this->user);

    $res = $this->getJson('/api/v1/ecommerce/eligibility');
    $res->assertOk()
        ->assertJsonPath('data.eligible', true)
        ->assertJsonPath('data.reasons', []);
});

it('recommendations endpoint returns 403 when gate fails', function () {
    // 純新用戶，gate 全 fail
    $res = $this->getJson('/api/v1/ecommerce/recommendations');
    $res->assertStatus(403);
    expect($res->json('reasons'))->toBeArray()->not->toBeEmpty();
});

it('recommendations endpoint returns symptom-driven product list for eligible user', function () {
    makeFullyEligibleUser($this->user);

    // 過去 30 天內 3 次 bloating
    foreach (range(0, 2) as $i) {
        CycleSymptom::create([
            'user_id' => $this->user->id,
            'logged_on' => now()->subDays($i)->toDateString(),
            'tags' => ['bloating'],
        ]);
    }

    $res = $this->getJson('/api/v1/ecommerce/recommendations');
    $res->assertOk();
    $data = $res->json('data');
    expect($data)->toBeArray()->not->toBeEmpty();
    expect(collect($data)->pluck('product_slug'))->toContain('fp-burst-fiber');
    // 文案不能含療效詞（最後一道防線）
    foreach ($data as $row) {
        expect($row['message'])->not->toContain('改善')
            ->not->toContain('治療')
            ->not->toContain('排毒')
            ->not->toContain('低 GI');
    }
});

it('recommendations endpoint returns empty array when eligible but no triggering symptoms', function () {
    makeFullyEligibleUser($this->user);

    $res = $this->getJson('/api/v1/ecommerce/recommendations');
    $res->assertOk()
        ->assertJsonPath('data', []);
});

it('legacy /commerce/product-links endpoint stays functional after refactor', function () {
    makeFullyEligibleUser($this->user);

    foreach (range(0, 2) as $i) {
        CycleSymptom::create([
            'user_id' => $this->user->id,
            'logged_on' => now()->subDays($i)->toDateString(),
            'tags' => ['bloating'],
        ]);
    }

    $res = $this->getJson('/api/v1/commerce/product-links');
    $res->assertOk()->assertJsonPath('gate_passed', true);
    expect(collect($res->json('data'))->pluck('product_slug'))->toContain('fp-burst-fiber');
});
