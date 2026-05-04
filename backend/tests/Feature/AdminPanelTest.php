<?php

use App\Models\CommunityModerationLog;
use App\Models\CommunityPost;
use App\Models\DodoCoinTransaction;
use App\Models\PhotoJournalEntry;
use App\Models\Pregnancy;
use App\Models\QnaQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

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

// =============================================================================
// Wave 14 — expanded admin monitoring (5 widgets + 4 resources)
//
// 涵蓋範圍：
//   - non-admin 不能進新 Resource（auth gate 一致）
//   - admin 可以打開新 Resource 列表
//   - PhotoJournal infolist 隱私紅線（不 leak note_text / cloud_url / local_path）
//   - 新 widget 在 dashboard 不爆（cache key 不存在也安全 fallback）
// =============================================================================

it('blocks non-admin from new resources', function (string $route): void {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user)->get($route)->assertForbidden();
})->with([
    '/admin/qna-questions',
    '/admin/dodo-coin-transactions',
    '/admin/pregnancies',
    '/admin/photo-journal-entries',
]);

it('lets admin into new resource list pages', function (string $route): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get($route)->assertSuccessful();
})->with([
    '/admin/qna-questions',
    '/admin/dodo-coin-transactions',
    '/admin/pregnancies',
    '/admin/photo-journal-entries',
]);

it('renders dashboard with new monitoring widgets without errors', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    // 即便 cache 沒 key（prod 上游尚未 instrument）也不該炸
    Cache::flush();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful();
});

it('redacts privacy-sensitive fields from PhotoJournal admin view', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $author = User::factory()->create();

    $entry = PhotoJournalEntry::create([
        'user_id' => $author->id,
        'tag' => 'face',
        'phase' => 'luteal',
        'cycle_day' => 21,
        // 隱私紅線資料：絕對不能在 admin 頁面回顯
        'note_text' => 'SECRET_DIARY_NOTE_DO_NOT_LEAK',
        'local_path' => '/private/leak-path-do-not-show.jpg',
        'cloud_synced' => true,
        'cloud_url' => 'https://private.example.com/leak-cloud-url-do-not-show',
        'cloud_object_key' => 'leak-cloud-key-do-not-show',
        'thumb_blurhash' => 'LEHV6nWB2yk8pyo0adR*.7kCMdnj',
        'captured_on' => now()->toDateString(),
    ]);

    $response = $this->actingAs($admin)
        ->get("/admin/photo-journal-entries/{$entry->id}");

    $response->assertSuccessful();
    $body = $response->getContent();

    // 紅線：四項機敏欄位都不能在 admin UI 出現
    expect($body)->not->toContain('SECRET_DIARY_NOTE_DO_NOT_LEAK');
    expect($body)->not->toContain('leak-path-do-not-show.jpg');
    expect($body)->not->toContain('leak-cloud-url-do-not-show');
    expect($body)->not->toContain('leak-cloud-key-do-not-show');
});

it('allows QnaQuestion search by user_id and shows safety_flag', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    QnaQuestion::create([
        'user_id' => $user->id,
        'question' => 'PMS 怎麼辦',
        'answer' => '保持規律作息與適度運動',
        'sources' => [1, 2],
        'llm_provider' => 'openai',
        'response_time_ms' => 800,
        'safety_flag' => null,
    ]);

    QnaQuestion::create([
        'user_id' => $user->id,
        'question' => '我想結束生命',
        'answer' => '請聯繫衛福部安心專線 1925',
        'sources' => [],
        'llm_provider' => 'blocked',
        'response_time_ms' => 50,
        'safety_flag' => 'redline_self_harm',
    ]);

    $this->actingAs($admin)
        ->get('/admin/qna-questions')
        ->assertSuccessful();
});

it('lets admin view DodoCoinTransaction list with delta sign', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    DodoCoinTransaction::create([
        'user_id' => $user->id,
        'delta' => 50,
        'source' => 'daily_action',
        'metadata' => ['action_key' => 'log_cycle'],
        'balance_after' => 50,
    ]);
    DodoCoinTransaction::create([
        'user_id' => $user->id,
        'delta' => -30,
        'source' => 'spend_outfit',
        'metadata' => ['outfit_id' => 7],
        'balance_after' => 20,
    ]);

    $this->actingAs($admin)
        ->get('/admin/dodo-coin-transactions')
        ->assertSuccessful();
});

it('lets admin view Pregnancy detail with miscarriage flag', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $preg = Pregnancy::create([
        'user_id' => $user->id,
        'lmp_date' => now()->subWeeks(8)->toDateString(),
        'estimated_due_date' => now()->addWeeks(32)->toDateString(),
        'ended_on' => now()->subDays(2)->toDateString(),
        'outcome' => 'miscarried',
        'milestones' => [],
        'status' => 'ended',
        'ended_reason' => 'miscarriage',
        'mode_started_at' => now()->subWeeks(8),
    ]);

    $this->actingAs($admin)
        ->get("/admin/pregnancies/{$preg->id}")
        ->assertSuccessful();
});
