<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('returns urgent for very late period (>= 60 days)', function () {
    $res = $this->getJson('/api/v1/medical-safety/evaluate?context=late_period&days_late=70')
        ->assertOk();

    expect($res->json('data.urgency'))->toBe('urgent');
    expect($res->json('data.find_doctor_url'))->not->toBeNull();
});

it('returns medium with suggest_test for sexually-active 1 week late', function () {
    $res = $this->getJson('/api/v1/medical-safety/evaluate?'.http_build_query([
        'context' => 'late_period',
        'days_late' => 9,
        'sexually_active' => 'true',
    ]))->assertOk();

    expect($res->json('data.urgency'))->toBe('medium');
    expect($res->json('data.suggest_test'))->toBeTrue();
});

it('returns low for mild late period (3-6 days)', function () {
    $res = $this->getJson('/api/v1/medical-safety/evaluate?context=late_period&days_late=4')
        ->assertOk();

    expect($res->json('data.urgency'))->toBe('low');
});

it('returns high for severe pain level >= 8', function () {
    $res = $this->getJson('/api/v1/medical-safety/evaluate?'.http_build_query([
        'context' => 'severe_pain',
        'pain_level' => 9,
    ]))->assertOk();

    expect($res->json('data.urgency'))->toBe('high');
});

it('returns urgent for pain with vomit', function () {
    $res = $this->getJson('/api/v1/medical-safety/evaluate?'.http_build_query([
        'context' => 'severe_pain',
        'pain_level' => 5,
        'pain_with_vomit' => 'true',
    ]))->assertOk();

    expect($res->json('data.urgency'))->toBe('urgent');
});

it('returns medium for irregular cycle pattern', function () {
    $res = $this->getJson('/api/v1/medical-safety/evaluate?'.http_build_query([
        'context' => 'irregular_pattern',
        'cycle_count_in_year' => 7,
    ]))->assertOk();

    expect($res->json('data.urgency'))->toBe('medium');
});

it('falls back to default when no rule matches', function () {
    $res = $this->getJson('/api/v1/medical-safety/evaluate?context=late_period&days_late=1')
        ->assertOk();

    expect($res->json('data.urgency'))->toBe('low');
    expect($res->json('data.rule_id'))->toBeNull();
});

it('validates context enum', function () {
    $this->getJson('/api/v1/medical-safety/evaluate?context=invalid')
        ->assertStatus(422);
});
