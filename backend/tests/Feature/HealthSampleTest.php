<?php

use App\Models\BbtReading;
use App\Models\Cycle;
use App\Models\HealthSample;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Health\HealthSampleImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makePremiumForHealth(User $user): void
{
    Subscription::create([
        'user_id' => $user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-h-'.$user->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
}

beforeEach(function () {
    RateLimiter::clear('health-sync');
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('HealthSampleImporter', function () {
    it('imports BBT and dedupes by date (overwrites same-day reading)', function () {
        $importer = app(HealthSampleImporter::class);
        $today = now()->toDateString();

        $r1 = $importer->import($this->user->id, 'bbt', [
            ['date' => $today, 'value' => 36.45, 'unit' => 'celsius'],
        ]);
        expect($r1['imported'])->toBe(1)->and($r1['errors'])->toBe([]);

        $r2 = $importer->import($this->user->id, 'bbt', [
            ['date' => $today, 'value' => 36.62, 'unit' => 'celsius'],
        ]);
        expect($r2['duplicates'])->toBe(1);

        expect(BbtReading::where('user_id', $this->user->id)->count())->toBe(1);
        expect((float) BbtReading::where('user_id', $this->user->id)->first()->temperature_c)->toBe(36.62);
    });

    it('imports steps as health_samples with steps metric', function () {
        $importer = app(HealthSampleImporter::class);
        $r = $importer->import($this->user->id, 'steps', [
            ['date' => '2026-05-01', 'value' => 8423, 'unit' => 'count'],
            ['date' => '2026-05-02', 'value' => 11200, 'unit' => 'count'],
        ]);
        expect($r['imported'])->toBe(2);
        expect(HealthSample::where('user_id', $this->user->id)->where('metric', 'steps')->count())->toBe(2);
    });

    it('imports sleep hours', function () {
        $importer = app(HealthSampleImporter::class);
        $r = $importer->import($this->user->id, 'sleep', [
            ['date' => '2026-05-01', 'value' => 7.5, 'unit' => 'hours'],
        ]);
        expect($r['imported'])->toBe(1);
        expect(HealthSample::where('metric', 'sleep_hours')->count())->toBe(1);
    });

    it('returns error for unsupported kind', function () {
        $importer = app(HealthSampleImporter::class);
        $r = $importer->import($this->user->id, 'heart_rate', [
            ['date' => '2026-05-01', 'value' => 72],
        ]);
        expect($r['imported'])->toBe(0);
        expect($r['errors'])->toContain('unsupported_kind:heart_rate');
    });

    it('creates a new cycle for menstrual_flow when ≥7d gap', function () {
        $importer = app(HealthSampleImporter::class);
        Cycle::create(['user_id' => $this->user->id, 'start_date' => '2026-04-01', 'end_date' => '2026-04-05']);

        $importer->import($this->user->id, 'menstrual_flow', [
            ['date' => '2026-04-29', 'value' => 2],
        ]);

        expect(Cycle::where('user_id', $this->user->id)->count())->toBe(2);
    });

    it('skips menstrual_flow inside an existing cycle window', function () {
        $importer = app(HealthSampleImporter::class);
        Cycle::create(['user_id' => $this->user->id, 'start_date' => '2026-04-01', 'end_date' => '2026-04-05']);

        $importer->import($this->user->id, 'menstrual_flow', [
            ['date' => '2026-04-03', 'value' => 3],
        ]);

        expect(Cycle::where('user_id', $this->user->id)->count())->toBe(1);
    });

    it('flags rows missing date', function () {
        $importer = app(HealthSampleImporter::class);
        $r = $importer->import($this->user->id, 'steps', [
            ['value' => 1000],
        ]);
        expect($r['imported'])->toBe(0);
        expect($r['errors'])->toContain('row_0:missing_date');
    });
});

describe('HealthSampleController', function () {
    it('blocks free user with 402 + paywall_redirect on /sync', function () {
        $this->postJson('/api/v1/health-samples/sync', [
            'kind' => 'bbt',
            'samples' => [['date' => now()->toDateString(), 'value' => 36.5]],
        ])->assertStatus(402)
            ->assertJsonPath('error', 'premium_required')
            ->assertJsonPath('paywall_redirect', '/subscription');
    });

    it('rejects unsupported kind with 422', function () {
        makePremiumForHealth($this->user);
        $this->postJson('/api/v1/health-samples/sync', [
            'kind' => 'heart_rate',
            'samples' => [['date' => '2026-05-01', 'value' => 72]],
        ])->assertStatus(422);
    });

    it('lets premium user import via /sync', function () {
        makePremiumForHealth($this->user);
        $this->postJson('/api/v1/health-samples/sync', [
            'kind' => 'sleep',
            'source' => 'healthkit',
            'samples' => [
                ['date' => '2026-05-01', 'value' => 7.2, 'unit' => 'hours'],
                ['date' => '2026-05-02', 'value' => 6.5, 'unit' => 'hours'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.errors', []);
    });
});
