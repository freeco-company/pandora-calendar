<?php

use App\Models\User;
use App\Services\AI\LLMClient;
use App\Services\AI\LLMProvider;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\NullProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\Calendar\BodyRhythm;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Dodo\DodoCheckinResponder;
use App\Services\Dodo\DodoLLMResponder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * 給 DodoCheckinResponder->respondWithLLM 用的 fake rhythm helper
 */
function fakeRhythm(string $phase = BodyRhythmCalculator::PHASE_LUTEAL, int $cycleDay = 22): BodyRhythm
{
    return new BodyRhythm(
        date: CarbonImmutable::today(),
        phase: $phase,
        cycleDay: $cycleDay,
        nextPeriodEta: null,
        daysUntilNextPeriod: null,
    );
}

// ============================================================
// LLMClient factory
// ============================================================

it('factory returns NullProvider when provider=null', function () {
    config(['llm.provider' => 'null']);
    expect(LLMClient::make())->toBeInstanceOf(NullProvider::class);
});

it('factory returns OpenAIProvider when provider=openai', function () {
    config(['llm.provider' => 'openai']);
    expect(LLMClient::make())->toBeInstanceOf(OpenAIProvider::class);
});

it('factory returns ClaudeProvider when provider=claude', function () {
    config(['llm.provider' => 'claude']);
    expect(LLMClient::make())->toBeInstanceOf(ClaudeProvider::class);
});

it('factory falls back to NullProvider on unknown provider value', function () {
    config(['llm.provider' => 'gemini']); // 故意亂打
    expect(LLMClient::make())->toBeInstanceOf(NullProvider::class);
});

// ============================================================
// NullProvider 行為
// ============================================================

it('NullProvider always returns null', function () {
    $p = new NullProvider();
    expect($p->complete('sys', 'user'))->toBeNull();
    expect($p->name())->toBe('null');
});

// ============================================================
// DodoLLMResponder：LLM 成功 / 失敗 / 違規 / cap
// ============================================================

it('uses library when provider is null (no key configured)', function () {
    config(['llm.provider' => 'null']);

    $user = User::factory()->create();
    $reply = app(DodoLLMResponder::class)
        ->respond($user, 'tired', BodyRhythmCalculator::PHASE_LUTEAL, 22, 3);

    expect($reply['source'])->toBe('library');
    expect($reply['text'])->toBeString()->not->toBe('');
});

it('returns LLM source when provider returns clean text', function () {
    $user = User::factory()->create();

    // 注入 mock provider
    $mock = new class implements LLMProvider
    {
        public function complete(string $s, string $u, array $o = []): ?string
        {
            return '今天累的話就讓自己慢一點，朵朵陪妳。';
        }

        public function name(): string
        {
            return 'mock';
        }
    };

    $responder = new DodoLLMResponder(
        app(\App\Services\Dodo\DodoDialogLibrary::class),
        app(\Pandora\Shared\Compliance\LegalContentSanitizer::class),
        app(\App\Services\Subscription\FeatureGate::class),
        $mock,
    );

    config(['llm.provider' => 'mock']); // 跳過 NullProvider 早退
    $reply = $responder->respond($user, 'tired', BodyRhythmCalculator::PHASE_LUTEAL, 22, 3);

    expect($reply['source'])->toBe('llm');
    expect($reply['text'])->toContain('朵朵');
});

it('falls back to library when LLM returns null', function () {
    $user = User::factory()->create();

    $mock = new class implements LLMProvider
    {
        public function complete(string $s, string $u, array $o = []): ?string
        {
            return null;
        }

        public function name(): string
        {
            return 'mock';
        }
    };

    $responder = new DodoLLMResponder(
        app(\App\Services\Dodo\DodoDialogLibrary::class),
        app(\Pandora\Shared\Compliance\LegalContentSanitizer::class),
        app(\App\Services\Subscription\FeatureGate::class),
        $mock,
    );

    config(['llm.provider' => 'mock']);
    $reply = $responder->respond($user, 'tired', BodyRhythmCalculator::PHASE_LUTEAL, 22, 3);

    expect($reply['source'])->toBe('library');
});

it('falls back to library when LLM output hits red-line term', function () {
    $user = User::factory()->create();

    $mock = new class implements LLMProvider
    {
        public function complete(string $s, string $u, array $o = []): ?string
        {
            // 違規：含「您」「改善」
            return '您可以試試這個方法來改善經痛。';
        }

        public function name(): string
        {
            return 'mock';
        }
    };

    $responder = new DodoLLMResponder(
        app(\App\Services\Dodo\DodoDialogLibrary::class),
        app(\Pandora\Shared\Compliance\LegalContentSanitizer::class),
        app(\App\Services\Subscription\FeatureGate::class),
        $mock,
    );

    config(['llm.provider' => 'mock']);
    $reply = $responder->respond($user, 'tired', BodyRhythmCalculator::PHASE_LUTEAL, 22, 3);

    expect($reply['source'])->toBe('library');
});

it('falls back to library when LLM output is excessively long', function () {
    $user = User::factory()->create();

    $mock = new class implements LLMProvider
    {
        public function complete(string $s, string $u, array $o = []): ?string
        {
            return str_repeat('朵朵陪妳走過這一天。', 30); // > 200 字
        }

        public function name(): string
        {
            return 'mock';
        }
    };

    $responder = new DodoLLMResponder(
        app(\App\Services\Dodo\DodoDialogLibrary::class),
        app(\Pandora\Shared\Compliance\LegalContentSanitizer::class),
        app(\App\Services\Subscription\FeatureGate::class),
        $mock,
    );

    config(['llm.provider' => 'mock']);
    $reply = $responder->respond($user, 'tired', BodyRhythmCalculator::PHASE_LUTEAL, 22, 3);

    expect($reply['source'])->toBe('library');
});

it('returns library milestone text on streak day regardless of LLM', function () {
    $user = User::factory()->create();

    $mock = new class implements LLMProvider
    {
        public function complete(string $s, string $u, array $o = []): ?string
        {
            return '隨便 LLM 回的句子';
        }

        public function name(): string
        {
            return 'mock';
        }
    };

    $responder = new DodoLLMResponder(
        app(\App\Services\Dodo\DodoDialogLibrary::class),
        app(\Pandora\Shared\Compliance\LegalContentSanitizer::class),
        app(\App\Services\Subscription\FeatureGate::class),
        $mock,
    );

    config(['llm.provider' => 'mock']);
    $reply = $responder->respond($user, 'happy', BodyRhythmCalculator::PHASE_FOLLICULAR, 8, 7);

    // streak=7 是里程碑日，library 優先
    expect($reply['source'])->toBe('library');
});

// ============================================================
// OpenAIProvider HTTP（mock Http facade，不打真 API）
// ============================================================

it('OpenAIProvider parses successful response', function () {
    config([
        'llm.provider' => 'openai',
        'llm.openai.api_key' => 'sk-test',
        'llm.openai.base_url' => 'https://api.openai.com/v1',
        'llm.openai.model' => 'gpt-4o-mini',
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => '朵朵陪妳慢慢來。']],
            ],
        ], 200),
    ]);

    $p = new OpenAIProvider();
    expect($p->complete('sys', 'user'))->toBe('朵朵陪妳慢慢來。');
});

it('OpenAIProvider returns null when no api key', function () {
    config(['llm.openai.api_key' => '']);
    expect((new OpenAIProvider())->complete('sys', 'user'))->toBeNull();
});

it('OpenAIProvider returns null on http error', function () {
    config([
        'llm.openai.api_key' => 'sk-test',
        'llm.openai.base_url' => 'https://api.openai.com/v1',
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'rate limited'], 429),
    ]);

    expect((new OpenAIProvider())->complete('sys', 'user'))->toBeNull();
});

// ============================================================
// ClaudeProvider HTTP
// ============================================================

it('ClaudeProvider parses successful response', function () {
    config([
        'llm.claude.api_key' => 'ant-test',
        'llm.claude.base_url' => 'https://api.anthropic.com/v1',
        'llm.claude.model' => 'claude-haiku-4-5',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => '朵朵在這裡陪妳。'],
            ],
        ], 200),
    ]);

    $p = new ClaudeProvider();
    expect($p->complete('sys', 'user'))->toBe('朵朵在這裡陪妳。');
});

it('ClaudeProvider returns null when no api key', function () {
    config(['llm.claude.api_key' => '']);
    expect((new ClaudeProvider())->complete('sys', 'user'))->toBeNull();
});

// ============================================================
// DodoCheckinResponder 整合
// ============================================================

it('DodoCheckinResponder->respondWithLLM returns library shape on null provider', function () {
    config(['llm.provider' => 'null']);

    $user = User::factory()->create();
    $reply = app(DodoCheckinResponder::class)
        ->respondWithLLM($user, 'okay', fakeRhythm(), streakDays: 3);

    expect($reply)->toHaveKeys(['text', 'source']);
    expect($reply['source'])->toBe('library');
    expect($reply['text'])->toBeString()->not->toBe('');
});

it('DodoCheckinResponder backward-compat respond() still works', function () {
    $resp = app(DodoCheckinResponder::class)->respond('tired', fakeRhythm());
    expect($resp)->toBeString()->not->toBe('');
});
