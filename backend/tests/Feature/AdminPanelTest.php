<?php

use App\Models\CommunityModerationLog;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Smoke + auth gate tests for the Filament admin panel.
 *
 * The full Resource UX (forms, bulk actions) is not exercised here — that
 * lives behind Livewire components and is best validated via Playwright.
 * Pest covers the security boundary and the moderation-logger contract.
 */

it('redirects guests to admin login', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('rejects non-admin users from the panel', function (): void {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('lets admin users into the dashboard', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful();
});

it('lets admin users access community posts list', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get('/admin/community-posts')
        ->assertSuccessful();
});

it('writes a moderation log entry via ModerationActionLogger', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $author = User::factory()->create();
    $post = CommunityPost::create([
        'user_id' => $author->id,
        'anonymous_handle' => '匿名朋友',
        'category' => 'experience',
        'title' => 'test',
        'body' => 'test body',
        'status' => 'published',
        'moderation_score' => 0.1,
    ]);

    \App\Services\Admin\ModerationActionLogger::log(
        'post', (int) $post->id, 'hide', $admin->id, 'spammy content',
    );

    expect(CommunityModerationLog::query()->count())->toBe(1);
    $log = CommunityModerationLog::query()->first();
    expect($log->action)->toBe('hide');
    expect($log->moderator_user_id)->toBe($admin->id);
    expect($log->reason)->toBe('spammy content');
});
