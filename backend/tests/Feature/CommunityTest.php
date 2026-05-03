<?php

use App\Models\CommunityModerationLog;
use App\Models\CommunityPost;
use App\Models\CommunityReply;
use App\Models\CommunityReport;
use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\User;
use App\Services\Community\AnonymousHandle;
use App\Services\Community\CommunityModerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Helper — create a user that satisfies the post gate (>= 14 days old + 5 records).
 */
function eligibleUser(): User
{
    $user = User::factory()->create([
        'created_at' => CarbonImmutable::now()->subDays(20),
    ]);
    foreach (range(1, 3) as $i) {
        Cycle::create([
            'user_id' => $user->id,
            'start_date' => CarbonImmutable::today()->subDays(28 * $i)->toDateString(),
            'end_date' => CarbonImmutable::today()->subDays(28 * $i - 4)->toDateString(),
            'peak_flow' => 3,
        ]);
    }
    foreach (range(1, 2) as $i) {
        CycleSymptom::create([
            'user_id' => $user->id,
            'logged_on' => CarbonImmutable::today()->subDays($i)->toDateString(),
            'tags' => ['cramp'],
            'mood' => 'tired',
        ]);
    }

    return $user->refresh();
}

it('blocks posting for users newer than 14 days', function () {
    $user = User::factory()->create(['created_at' => CarbonImmutable::now()->subDays(3)]);
    Sanctum::actingAs($user);

    $res = $this->postJson('/api/v1/community/posts', [
        'category' => 'question',
        'title' => '經期亂了怎麼辦',
        'body' => '想問問大家',
    ]);

    $res->assertStatus(422)
        ->assertJsonPath('errors.gate.0', 'not_yet_eligible');
});

it('blocks posting when user has too few records', function () {
    $user = User::factory()->create(['created_at' => CarbonImmutable::now()->subDays(20)]);
    // 0 cycles + 0 symptoms
    Sanctum::actingAs($user);

    $res = $this->postJson('/api/v1/community/posts', [
        'category' => 'question',
        'title' => 'hi',
        'body' => 'body',
    ]);

    $res->assertStatus(422)
        ->assertJsonPath('errors.gate.0', 'not_enough_records');
});

it('publishes a clean post for eligible user', function () {
    $user = eligibleUser();
    Sanctum::actingAs($user);

    $res = $this->postJson('/api/v1/community/posts', [
        'category' => 'experience',
        'title' => '經期第一天的儀式',
        'body' => '我會泡薑茶、用熱水袋，給自己一個安靜的下午。',
    ]);

    $res->assertCreated()
        ->assertJsonPath('data.category', 'experience')
        ->assertJsonMissingPath('data.user_id');

    expect(CommunityPost::count())->toBe(1);
    expect(CommunityPost::first()->status)->toBe('published');
});

it('auto-blocks posts containing therapeutic forbidden terms', function () {
    $user = eligibleUser();
    Sanctum::actingAs($user);

    $res = $this->postJson('/api/v1/community/posts', [
        'category' => 'tip',
        'title' => '經期保養分享',
        'body' => '吃這個可以治療經痛，加速代謝、幫妳排毒燃脂。',
    ]);

    $res->assertStatus(422)
        ->assertJsonPath('errors.moderation.0', 'forbidden_terms');

    $post = CommunityPost::first();
    expect($post->status)->toBe('removed');

    $log = CommunityModerationLog::where('target_id', $post->id)->first();
    expect($log->action)->toBe('auto_block');
});

it('auto-blocks posts containing commerce / line id', function () {
    $user = eligibleUser();
    Sanctum::actingAs($user);

    $res = $this->postJson('/api/v1/community/posts', [
        'category' => 'experience',
        'title' => '推薦好物',
        'body' => '想買的私訊我，加 line id abc123，限時優惠。',
    ]);

    $res->assertStatus(422);
    expect(CommunityPost::first()->status)->toBe('removed');
});

it('auto-blocks posts containing non-whitelisted urls', function () {
    $user = eligibleUser();
    Sanctum::actingAs($user);

    $res = $this->postJson('/api/v1/community/posts', [
        'category' => 'tip',
        'title' => '推薦網站',
        'body' => '看這個 https://evil-spam.example.com/promo 就懂了。',
    ]);

    $res->assertStatus(422);
});

it('allows posts referencing whitelisted government health urls', function () {
    $user = eligibleUser();
    Sanctum::actingAs($user);

    $res = $this->postJson('/api/v1/community/posts', [
        'category' => 'tip',
        'title' => '安心專線資訊',
        'body' => '若需要找人聊聊，可以參考 https://1925.mohw.gov.tw/ 的資訊。',
    ]);

    $res->assertCreated();
});

it('flags but publishes self-harm posts and auto-attaches dodo hotline reply', function () {
    $user = eligibleUser();
    Sanctum::actingAs($user);

    $res = $this->postJson('/api/v1/community/posts', [
        'category' => 'support',
        'title' => '最近真的好累',
        'body' => '有時候會想結束生命，不知道還能撐多久。',
    ]);

    $res->assertCreated();
    $post = CommunityPost::first();
    expect($post->status)->toBe('published');
    expect($post->moderation_score)->toBeGreaterThanOrEqual(0.7);

    $dodoReply = CommunityReply::where('post_id', $post->id)->where('is_dodo', true)->first();
    expect($dodoReply)->not->toBeNull();
    expect($dodoReply->body)->toContain('1925');
    expect($dodoReply->anonymous_handle)->toBe('dodo-team');

    $flagLog = CommunityModerationLog::where('target_type', 'post')
        ->where('target_id', $post->id)->first();
    expect($flagLog->action)->toBe('auto_flag');
});

it('toggles like on a post and is idempotent on repeat', function () {
    $user = eligibleUser();
    Sanctum::actingAs($user);

    $post = CommunityPost::create([
        'user_id' => $user->id,
        'anonymous_handle' => 'AB23CD45EF67',
        'category' => 'tip',
        'title' => 'hi',
        'body' => 'body',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $res1 = $this->postJson("/api/v1/community/posts/{$post->id}/like");
    $res1->assertOk()->assertJsonPath('data.liked', true);

    $res2 = $this->postJson("/api/v1/community/posts/{$post->id}/like");
    $res2->assertOk()->assertJsonPath('data.liked', false);

    $res3 = $this->postJson("/api/v1/community/posts/{$post->id}/like");
    $res3->assertOk()->assertJsonPath('data.liked', true);
});

it('rate-limits reports to 10 per day per user', function () {
    $user = eligibleUser();
    Sanctum::actingAs($user);

    // Seed 10 different post targets so unique constraint doesn't block test
    for ($i = 1; $i <= 11; $i++) {
        $p = CommunityPost::create([
            'user_id' => $user->id,
            'anonymous_handle' => 'X'.$i,
            'category' => 'tip',
            'title' => 't'.$i,
            'body' => 'b',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $res = $this->postJson('/api/v1/community/reports', [
            'target_type' => 'post',
            'target_id' => $p->id,
            'reason' => 'spam',
        ]);

        if ($i <= 10) {
            $res->assertOk();
        } else {
            $res->assertStatus(429);
        }
    }

    expect(CommunityReport::count())->toBe(10);
});

it('produces unique anonymous handles across users for the same post scope', function () {
    $handle = app(AnonymousHandle::class);

    $h1 = $handle->forPost(1, 100);
    $h2 = $handle->forPost(2, 100);
    $h3 = $handle->forPost(3, 100);

    expect($h1)->toHaveLength(12);
    expect([$h1, $h2, $h3])->toEqual(array_unique([$h1, $h2, $h3]));
});

it('produces same handle for same user within same post scope (OP continuity)', function () {
    $handle = app(AnonymousHandle::class);

    $a = $handle->forPost(7, 999);
    $b = $handle->forPost(7, 999);

    expect($a)->toBe($b);
});

it('produces different handles for same user across different post scopes', function () {
    $handle = app(AnonymousHandle::class);

    $a = $handle->forPost(7, 100);
    $b = $handle->forPost(7, 200);

    expect($a)->not->toBe($b);
});

it('hides user_id from list and detail responses', function () {
    $user = eligibleUser();
    Sanctum::actingAs($user);

    CommunityPost::create([
        'user_id' => $user->id,
        'anonymous_handle' => 'ZZAA11BB22CC',
        'category' => 'tip',
        'title' => 'visible',
        'body' => 'body',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $list = $this->getJson('/api/v1/community/posts');
    $list->assertOk()->assertJsonMissingPath('data.0.user_id');

    $id = CommunityPost::first()->id;
    $detail = $this->getJson("/api/v1/community/posts/{$id}");
    $detail->assertOk()->assertJsonMissingPath('data.user_id');
});

it('only allows owner to delete their own post', function () {
    $owner = eligibleUser();
    $other = eligibleUser();

    $post = CommunityPost::create([
        'user_id' => $owner->id,
        'anonymous_handle' => 'OWNER1234567',
        'category' => 'tip',
        'title' => 'mine',
        'body' => 'b',
        'status' => 'published',
        'published_at' => now(),
    ]);

    Sanctum::actingAs($other);
    $this->deleteJson("/api/v1/community/posts/{$post->id}")->assertStatus(403);

    Sanctum::actingAs($owner);
    $this->deleteJson("/api/v1/community/posts/{$post->id}")->assertOk();

    expect($post->fresh()->status)->toBe('removed');
});

it('moderator service correctly classifies clean text as publish', function () {
    $mod = app(CommunityModerator::class);
    $r = $mod->evaluate('經期分享', '昨天經期來了，肚子有點悶，泡了薑茶之後好多了。');

    expect($r['action'])->toBe(CommunityModerator::ACTION_PUBLISH);
});

it('moderator service blocks emoji spam', function () {
    $mod = app(CommunityModerator::class);
    $r = $mod->evaluate('hi', '看看這個🎉🎉🎉🎉🎉🎉');

    expect($r['action'])->toBe(CommunityModerator::ACTION_BLOCK);
});

it('moderator service flags medical advice patterns', function () {
    $mod = app(CommunityModerator::class);
    $r = $mod->evaluate('給妳的建議', '妳要去看婦產科醫師，必須服用一些藥才能好。');

    // 必須服用 hits forbidden_terms? no — but pattern catches it
    expect($r['action'])->toBeIn([CommunityModerator::ACTION_FLAG, CommunityModerator::ACTION_BLOCK]);
    expect($r['matched'])->toHaveKey('medical_advice');
});

it('does not allow seeing another tenant posts hidden field user_id via show', function () {
    $owner = eligibleUser();
    $viewer = eligibleUser();

    $post = CommunityPost::create([
        'user_id' => $owner->id,
        'anonymous_handle' => 'AAA',
        'category' => 'tip',
        'title' => 'x',
        'body' => 'b',
        'status' => 'published',
        'published_at' => now(),
    ]);

    Sanctum::actingAs($viewer);
    $res = $this->getJson("/api/v1/community/posts/{$post->id}");
    $res->assertOk()
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonPath('data.is_mine', false);
});
