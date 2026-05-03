<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

dataset('premium_endpoints', [
    'PMS insight' => ['get', '/api/v1/insight/pms', null],
    'Week report latest' => ['get', '/api/v1/week-report/latest', null],
    'Week report generate' => ['post', '/api/v1/week-report/generate', []],
    'Pregnancy start' => ['post', '/api/v1/pregnancy', ['lmp_date' => '2026-01-01']],
    'Health sample import' => ['post', '/api/v1/health-samples/import', [
        'source' => 'healthkit',
        'samples' => [['metric' => 'basal_temp', 'value' => 36.5, 'recorded_on' => '2026-04-01']],
    ]],
    'BBT biphasic' => ['get', '/api/v1/bbt/biphasic', null],
    'Year review' => ['get', '/api/v1/year-review/2026', null],
    'Export PDF' => ['post', '/api/v1/export/pdf', []],
    'Export CSV' => ['post', '/api/v1/export/csv', []],
]);

it('returns 402 with paywall_redirect for free user on all premium endpoints', function (string $verb, string $url, $payload) {
    $res = $verb === 'get'
        ? $this->getJson($url)
        : $this->postJson($url, $payload ?? []);

    $res->assertStatus(402);
    expect($res->json('error'))->toBe('premium_required');
    expect($res->json('paywall_redirect'))->toBe('/subscription');
})->with('premium_endpoints');
