<?php

use App\Models\Cycle;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makePremiumExport(User $user): void
{
    Subscription::create([
        'user_id' => $user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-export-'.$user->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('blocks free user from PDF export', function () {
    $this->postJson('/api/v1/export/pdf')
        ->assertStatus(402)
        ->assertJsonPath('paywall_redirect', '/subscription');
});

it('lets premium user export CSV with signed download URL', function () {
    makePremiumExport($this->user);
    Cycle::create(['user_id' => $this->user->id, 'start_date' => now()->subDays(28)->toDateString()]);

    $res = $this->postJson('/api/v1/export/csv')
        ->assertOk()
        ->assertJsonStructure(['data' => ['download_url', 'expires_at', 'filename']]);

    expect($res->json('data.download_url'))->toContain('signature=');
});

it('lets premium user export PDF', function () {
    makePremiumExport($this->user);

    $this->postJson('/api/v1/export/pdf', [
        'from' => now()->subDays(60)->toDateString(),
        'to' => now()->toDateString(),
    ])->assertOk();
});

it('rejects download from another user even with valid signature path', function () {
    makePremiumExport($this->user);
    $res = $this->postJson('/api/v1/export/csv')->assertOk();
    $url = $res->json('data.download_url');

    // Login as another user
    $other = User::factory()->create();
    Sanctum::actingAs($other);

    $relative = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    $this->get($relative)->assertForbidden();
});
