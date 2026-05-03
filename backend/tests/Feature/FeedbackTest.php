<?php

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('stores feedback', function () {
    $this->postJson('/api/v1/feedback', [
        'category' => 'bug',
        'message' => '月曆按鈕點不到',
        'app_version' => '1.0.0',
        'device_info' => ['os' => 'iOS 17.5'],
    ])->assertCreated();

    expect(Feedback::count())->toBe(1);
    expect(Feedback::first()->user_id)->toBe($this->user->id);
});

it('validates category', function () {
    $this->postJson('/api/v1/feedback', [
        'category' => 'invalid',
        'message' => 'x',
    ])->assertStatus(422);
});

it('rate-limits at 5 per minute', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/feedback', ['category' => 'other', 'message' => "msg {$i}"])
            ->assertCreated();
    }

    $this->postJson('/api/v1/feedback', ['category' => 'other', 'message' => 'msg 6'])
        ->assertStatus(429);
});
