<?php

use App\Models\Pregnancy;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Pregnancy\PregnancyCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makePremiumForPreg(User $user): void
{
    Subscription::create([
        'user_id' => $user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-pregm-'.$user->id,
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

it('blocks free user from starting pregnancy mode', function () {
    $this->postJson('/api/v1/pregnancy/start', [
        'lmp_date' => now()->subDays(60)->toDateString(),
    ])->assertStatus(402);
});

it('starts pregnancy mode and computes week / trimester / due / fetal size', function () {
    makePremiumForPreg($this->user);

    $res = $this->postJson('/api/v1/pregnancy/start', [
        'lmp_date' => now()->subDays(56)->toDateString(), // ~ week 9
    ])->assertCreated();

    expect($res->json('data.gestational_week'))->toBeGreaterThanOrEqual(8);
    expect($res->json('data.gestational_week'))->toBeLessThanOrEqual(10);
    expect($res->json('data.trimester'))->toBe(1);
    expect($res->json('data.estimated_due_date'))->not->toBeEmpty();
    expect($res->json('data.fetal_size.label'))->not->toBeEmpty();
    expect($res->json('data.fetal_size.emoji'))->not->toBeEmpty();
    expect($res->json('data.this_week.dodo_message'))->not->toBeEmpty();
    expect($res->json('data.this_week.suggested_actions'))->toBeArray();
});

it('rejects double-start when already active', function () {
    makePremiumForPreg($this->user);

    $this->postJson('/api/v1/pregnancy/start', [
        'lmp_date' => now()->subDays(30)->toDateString(),
    ])->assertCreated();

    $this->postJson('/api/v1/pregnancy/start', [
        'lmp_date' => now()->subDays(30)->toDateString(),
    ])->assertStatus(409);
});

it('returns null when no active pregnancy', function () {
    $this->getJson('/api/v1/pregnancy/current')->assertOk()->assertJson(['data' => null]);
});

it('ends pregnancy with each valid reason', function () {
    foreach (['birth', 'miscarriage', 'cancelled', 'false_alarm'] as $reason) {
        $u = User::factory()->create();
        makePremiumForPreg($u);
        Sanctum::actingAs($u);

        $this->postJson('/api/v1/pregnancy/start', [
            'lmp_date' => now()->subDays(40)->toDateString(),
        ])->assertCreated();

        $res = $this->patchJson('/api/v1/pregnancy/end', ['reason' => $reason])->assertOk();
        expect($res->json('data.status'))->toBe('ended');
        expect($res->json('data.ended_reason'))->toBe($reason);

        // After end, current returns null
        $this->getJson('/api/v1/pregnancy/current')->assertJson(['data' => null]);
    }
});

it('rejects invalid end reason', function () {
    makePremiumForPreg($this->user);
    $this->postJson('/api/v1/pregnancy/start', [
        'lmp_date' => now()->subDays(20)->toDateString(),
    ])->assertCreated();

    $this->patchJson('/api/v1/pregnancy/end', ['reason' => 'unicorn'])->assertStatus(422);
});

it('previews any week content', function () {
    makePremiumForPreg($this->user);

    $res = $this->getJson('/api/v1/pregnancy/week/20')->assertOk();
    expect($res->json('data.week'))->toBe(20);
    expect($res->json('data.trimester'))->toBe(2);
    expect($res->json('data.fetal_size.label'))->not->toBeEmpty();
});

it('blocks free user from week preview', function () {
    $this->getJson('/api/v1/pregnancy/week/20')->assertStatus(402);
});

it('calculator: trimester boundaries', function () {
    /** @var PregnancyCalculator $calc */
    $calc = app(PregnancyCalculator::class);

    expect($calc->getTrimester(1))->toBe(1);
    expect($calc->getTrimester(13))->toBe(1);
    expect($calc->getTrimester(14))->toBe(2);
    expect($calc->getTrimester(27))->toBe(2);
    expect($calc->getTrimester(28))->toBe(3);
    expect($calc->getTrimester(40))->toBe(3);
});

it('calculator: gestational week from LMP', function () {
    $calc = app(PregnancyCalculator::class);

    $p = Pregnancy::create([
        'user_id' => $this->user->id,
        'lmp_date' => CarbonImmutable::now()->subDays(70)->toDateString(),
        'estimated_due_date' => CarbonImmutable::now()->addDays(210)->toDateString(),
        'status' => Pregnancy::STATUS_ACTIVE,
    ]);

    expect($calc->getCurrentWeek($p))->toBeGreaterThanOrEqual(10)
        ->toBeLessThanOrEqual(11);
});

it('calculator: fetal size present for every week 1-42', function () {
    $calc = app(PregnancyCalculator::class);
    for ($w = 1; $w <= 42; $w++) {
        $size = $calc->getFetalSize($w);
        expect($size['label'])->not->toBeEmpty("week $w label");
        expect($size['emoji'])->not->toBeEmpty("week $w emoji");
    }
});

it('rejects lmp date in the future', function () {
    makePremiumForPreg($this->user);
    $this->postJson('/api/v1/pregnancy/start', [
        'lmp_date' => now()->addDays(5)->toDateString(),
    ])->assertStatus(422);
});

it('rejects lmp date too far in the past', function () {
    makePremiumForPreg($this->user);
    $this->postJson('/api/v1/pregnancy/start', [
        'lmp_date' => now()->subDays(400)->toDateString(),
    ])->assertStatus(422);
});

it('cannot end other user pregnancy via legacy URL', function () {
    $otherUser = User::factory()->create();
    makePremiumForPreg($otherUser);
    $other = Pregnancy::create([
        'user_id' => $otherUser->id,
        'lmp_date' => now()->subDays(40)->toDateString(),
        'estimated_due_date' => now()->addDays(240)->toDateString(),
        'status' => Pregnancy::STATUS_ACTIVE,
    ]);

    // current $this->user attempts to end $other's pregnancy
    $this->patchJson("/api/v1/pregnancy/{$other->id}/end", ['reason' => 'cancelled'])
        ->assertStatus(403);
});
