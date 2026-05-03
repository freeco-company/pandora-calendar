<?php

use App\Models\QnaQuestion;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\LLMClient;
use App\Services\AI\LLMProvider;
use App\Services\Qna\QnaConductor;
use App\Services\Qna\QnaRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    // 種 5 篇衛教文章（4 phase × 不同 day_offset）給 RAG 測
    DB::table('daily_insights')->insert([
        ['phase' => 'luteal',     'day_offset' => 0, 'title' => '黃體期常見的身體變化', 'body' => '黃體期妳可能會感覺到情緒起伏比較明顯，胸部脹脹的，這是身體節律的一部分。多喝溫水，記得早點睡。', 'created_at' => now(), 'updated_at' => now()],
        ['phase' => 'luteal',     'day_offset' => 5, 'title' => '經前情緒起伏怎麼辦',   'body' => 'PMS 階段情緒會像浪一樣起伏，這不是妳的錯。試著慢下來，給自己一點空間。', 'created_at' => now(), 'updated_at' => now()],
        ['phase' => 'menstrual',  'day_offset' => 0, 'title' => '經期第一天的陪伴',     'body' => '經期第一天身體在卸下一個週期，妳可以多休息、喝溫水。', 'created_at' => now(), 'updated_at' => now()],
        ['phase' => 'follicular', 'day_offset' => 3, 'title' => '濾泡期的能量回升',     'body' => '濾泡期妳的能量會慢慢回升，這是動起來的好時機。', 'created_at' => now(), 'updated_at' => now()],
        ['phase' => 'ovulation',  'day_offset' => 0, 'title' => '排卵期的身體訊號',     'body' => '排卵期妳可能會感覺到身體更輕盈，這是週期中的高點。', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

function fakeProvider(?string $reply, string $name = 'openai'): LLMProvider
{
    return new class($reply, $name) implements LLMProvider {
        public function __construct(private ?string $reply, private string $name) {}
        public function complete(string $sys, string $user, array $opts = []): ?string { return $this->reply; }
        public function name(): string { return $this->name; }
    };
}

// ============================================================
// Retriever
// ============================================================

it('retriever finds top relevant insights from daily_insights', function () {
    $retriever = app(QnaRetriever::class);
    $hits = $retriever->retrieve('黃體期情緒起伏怎麼辦', 'luteal');
    expect($hits)->not->toBeEmpty();
    expect($hits[0]['phase'])->toBe('luteal');
});

it('retriever returns empty for nonsense query', function () {
    $retriever = app(QnaRetriever::class);
    $hits = $retriever->retrieve('zzz', null);
    expect($hits)->toBe([]);
});

// ============================================================
// Conductor — happy path（fake LLM）
// ============================================================

it('returns LLM answer + source ids on happy path', function () {
    config(['llm.provider' => 'openai']);
    $stub = app(QnaConductor::class, [
        'provider' => fakeProvider('黃體期情緒比較浮動是常見的，妳並不孤單。多喝溫水、早點睡會幫助。如果情況持續或讓妳擔心，建議找婦產科聊聊。'),
    ]);

    $res = $stub->ask($this->user, '黃體期情緒怎麼辦');
    expect($res['answer'])->toContain('黃體期');
    expect($res['safety_flag'])->toBeNull();
    expect($res['sources'])->not->toBeEmpty();
    expect(QnaQuestion::count())->toBe(1);
});

// ============================================================
// Layer 1: self-harm redline 不送 LLM
// ============================================================

it('blocks self-harm question and returns 1925 hotline without calling LLM', function () {
    config(['llm.provider' => 'openai']);
    $llmCalled = false;
    $provider = new class($llmCalled) implements LLMProvider {
        public function __construct(public &$called) {}
        public function complete(string $s, string $u, array $o = []): ?string { $this->called = true; return 'should not be called'; }
        public function name(): string { return 'openai'; }
    };

    $stub = app(QnaConductor::class, ['provider' => $provider]);
    $res = $stub->ask($this->user, '我想自殺');

    expect($res['safety_flag'])->toBe('redline_self_harm');
    expect($res['answer'])->toContain('1925');
    expect($llmCalled)->toBeFalse();

    $row = QnaQuestion::first();
    expect($row->safety_flag)->toBe('redline_self_harm');
    expect($row->llm_provider)->toBe('blocked');
});

// ============================================================
// Layer 3: 紅線 sanitizer fallback
// ============================================================

it('falls back to safe response when LLM output hits compliance redline', function () {
    config(['llm.provider' => 'openai']);
    // 「療效」是 sanitizer 紅線詞 — LLM 不該寫但若漏網要被 catch
    $stub = app(QnaConductor::class, [
        'provider' => fakeProvider('這個產品的療效非常好，可以治療經痛，妳一定要試試。'),
    ]);
    $res = $stub->ask($this->user, 'PMS 怎麼辦');

    expect($res['safety_flag'])->toBe('redline_compliance');
    expect($res['answer'])->toContain('1925');
});

// ============================================================
// Premium gate: free 3/day
// ============================================================

it('blocks free user after 3 questions in a day with 402', function () {
    config(['llm.provider' => 'null']); // null provider 一樣會記 1 次

    $this->postJson('/api/v1/qna/ask', ['question' => '第一個問題'])->assertOk();
    $this->postJson('/api/v1/qna/ask', ['question' => '第二個問題'])->assertOk();
    $this->postJson('/api/v1/qna/ask', ['question' => '第三個問題'])->assertOk();

    $res = $this->postJson('/api/v1/qna/ask', ['question' => '第四個問題']);
    $res->assertStatus(402)->assertJsonPath('error', 'quota_exceeded');
});

it('lets premium user ask unlimited questions', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-q-1',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
    config(['llm.provider' => 'null']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/qna/ask', ['question' => "問題 $i"])->assertOk();
    }
});

// ============================================================
// LLM disabled fallback
// ============================================================

it('returns offline fallback when provider is null', function () {
    config(['llm.provider' => 'null']);
    $res = $this->postJson('/api/v1/qna/ask', ['question' => '經期遲到怎麼辦']);
    $res->assertOk()
        ->assertJsonPath('data.safety_flag', null);

    $row = QnaQuestion::first();
    expect($row->llm_provider)->toBe('null');
});

// ============================================================
// History + delete own
// ============================================================

it('returns own history and lets user delete own item', function () {
    config(['llm.provider' => 'null']);
    $this->postJson('/api/v1/qna/ask', ['question' => '我想記錄經期'])->assertOk();

    $hist = $this->getJson('/api/v1/qna/history')->assertOk();
    expect($hist->json('data'))->toHaveCount(1);
    $id = $hist->json('data.0.id');

    $this->deleteJson("/api/v1/qna/$id")->assertOk();
    expect(QnaQuestion::count())->toBe(0);
});

it('blocks deleting another user qna question', function () {
    $other = User::factory()->create();
    $row = QnaQuestion::create([
        'user_id' => $other->id,
        'question' => 'q', 'answer' => 'a', 'sources' => [],
        'llm_provider' => 'null', 'response_time_ms' => 1, 'safety_flag' => null,
    ]);
    $this->deleteJson("/api/v1/qna/{$row->id}")->assertStatus(404);
    expect(QnaQuestion::count())->toBe(1);
});

it('validates question length', function () {
    $this->postJson('/api/v1/qna/ask', ['question' => 'a'])->assertStatus(422);
    $this->postJson('/api/v1/qna/ask', ['question' => str_repeat('字', 600)])->assertStatus(422);
});
