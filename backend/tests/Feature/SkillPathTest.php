<?php

use App\Models\User;
use App\Services\Gamification\SkillPathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->paths = app(SkillPathService::class);
});

it('starts with no path chosen', function () {
    expect($this->paths->current($this->user->id))->toBeNull();
});

it('chooses a path', function () {
    $row = $this->paths->choose($this->user->id, SkillPathService::PATH_FERTILITY);
    expect($row->path)->toBe('fertility');
});

it('rejects invalid path', function () {
    expect(fn () => $this->paths->choose($this->user->id, 'random'))
        ->toThrow(InvalidArgumentException::class);
});

it('blocks switching within cooldown window', function () {
    $this->paths->choose($this->user->id, SkillPathService::PATH_FERTILITY);
    expect(fn () => $this->paths->choose($this->user->id, SkillPathService::PATH_BEAUTY))
        ->toThrow(DomainException::class);
});

it('returns preferred action types based on path', function () {
    $this->paths->choose($this->user->id, SkillPathService::PATH_WELLNESS);
    $types = $this->paths->preferredActionTypes($this->user->id);
    expect($types)->toContain('sleep')->toContain('move');
});

it('marks quest completed once', function () {
    $this->paths->choose($this->user->id, SkillPathService::PATH_FERTILITY);
    expect($this->paths->markQuestCompleted($this->user->id, 'fertility_q1'))->toBeTrue();
    expect($this->paths->markQuestCompleted($this->user->id, 'fertility_q1'))->toBeFalse();
});

it('exposes endpoints', function () {
    $this->postJson('/api/v1/me/skill-path', ['path' => 'beauty'])->assertOk();
    $this->getJson('/api/v1/me/skill-path')->assertOk()
        ->assertJsonPath('data.path', 'beauty');
    $this->getJson('/api/v1/me/skill-path/quests')->assertOk()
        ->assertJsonCount(10, 'data');
});
