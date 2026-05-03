<?php

use App\Models\User;
use App\Services\Pet\PetPersonalityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'identity_uuid' => '33333333-3333-3333-3333-333333333333',
        'pet_species' => 'cat',
        'pet_nickname' => '小毛',
        'pet_onboarded_at' => now(),
        'level' => 3,
    ]);
    Sanctum::actingAs($this->user);
});

// === Bug fix regression：換 species 真的寫進 DB ===

it('PATCH /me/pet persists species change to DB', function () {
    $res = $this->patchJson('/api/v1/me/pet', [
        'species' => 'penguin',
        'nickname' => '小企',
    ])->assertOk();

    expect($res->json('data.species'))->toBe('penguin');
    expect($res->json('data.nickname'))->toBe('小企');

    $this->user->refresh();
    expect($this->user->pet_species)->toBe('penguin');
    expect($this->user->pet_nickname)->toBe('小企');
});

it('GET /me/pet reflects the new species after change', function () {
    $this->patchJson('/api/v1/me/pet', [
        'species' => 'bear',
        'nickname' => '抱抱',
    ])->assertOk();

    $this->getJson('/api/v1/me/pet')
        ->assertOk()
        ->assertJsonPath('data.species', 'bear')
        ->assertJsonPath('data.nickname', '抱抱');
});

it('rejects unknown species', function () {
    $this->patchJson('/api/v1/me/pet', [
        'species' => 'unicorn',
        'nickname' => 'a',
    ])->assertStatus(422);

    $this->user->refresh();
    expect($this->user->pet_species)->toBe('cat');
});

// === Personality matrix ===

it('GET /me/pet returns personality meta + species_catalog', function () {
    $res = $this->getJson('/api/v1/me/pet')->assertOk();

    expect($res->json('data.personality.personality'))->toBe('gentle_observer');
    expect($res->json('data.personality.description'))->not->toBeEmpty();

    $catalog = $res->json('data.species_catalog');
    expect($catalog)->toBeArray();
    expect($catalog['cat']['description'])->not->toBeEmpty();
    expect($catalog['penguin']['personality'])->toBe('calm_thinker');
    expect($catalog['dinosaur']['celebration_style'])->toBe('energetic');
});

it('PetPersonalityResolver returns species-flavored celebration message', function () {
    $resolver = app(PetPersonalityResolver::class);

    $catMsg = $resolver->resolve('cat', 'action_completed');
    $dinoMsg = $resolver->resolve('dinosaur', 'action_completed');
    $penguinMsg = $resolver->resolve('penguin', 'action_completed');

    expect($catMsg)->toBeString()->not->toBeEmpty();
    expect($dinoMsg)->toBeString()->not->toBeEmpty();
    expect($penguinMsg)->toBeString()->not->toBeEmpty();

    // 不同 personality 的 dialog pool 應有差別（隨機抽句子可能撞，跑 5 次只要任何一次不同即可）
    $hits = collect(range(1, 5))->map(fn () => [
        'cat' => $resolver->resolve('cat', 'action_completed'),
        'dinosaur' => $resolver->resolve('dinosaur', 'action_completed'),
    ]);
    $diff = $hits->filter(fn ($p) => $p['cat'] !== $p['dinosaur'])->count();
    expect($diff)->toBeGreaterThan(0);
});

it('PetPersonalityResolver fills context placeholders', function () {
    $resolver = app(PetPersonalityResolver::class);

    $msg = $resolver->resolve('robot', 'level_up', ['level' => 7, 'next' => 8]);

    expect($msg)->toContain('7');
    expect($msg)->toContain('8');
});

it('PetPersonalityResolver falls back gracefully for unknown event', function () {
    $resolver = app(PetPersonalityResolver::class);

    $msg = $resolver->resolve('cat', 'no_such_event');

    expect($msg)->toBeString()->not->toBeEmpty();
});

it('all species in config have required personality fields', function () {
    $required = ['name', 'personality', 'reaction_frequency', 'celebration_style', 'description'];
    $catalog = config('pet-species');
    expect($catalog)->toBeArray()->not->toBeEmpty();
    foreach ($catalog as $species => $meta) {
        foreach ($required as $key) {
            expect($meta)->toHaveKey($key);
            expect($meta[$key])->not->toBeEmpty();
        }
    }
});
