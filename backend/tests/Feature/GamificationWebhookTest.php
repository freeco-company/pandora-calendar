<?php

use App\Models\GamificationWebhookNonce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('gamification.webhook_secret', 'test-secret');
    config()->set('gamification.webhook_window_seconds', 300);

    $this->user = User::factory()->create([
        'identity_uuid' => '11111111-1111-1111-1111-111111111111',
        'total_xp' => 0,
        'level' => 1,
    ]);
});

function signedHeaders(string $body, string $secret = 'test-secret'): array
{
    $ts = now()->toIso8601String();
    $nonce = bin2hex(random_bytes(16));
    $sig = 'sha256='.hash_hmac('sha256', "{$ts}.{$nonce}.{$body}", $secret);

    return [
        'X-Pandora-Timestamp' => $ts,
        'X-Pandora-Nonce' => $nonce,
        'X-Pandora-Signature' => $sig,
        'Content-Type' => 'application/json',
    ];
}

it('accepts valid level_up webhook and mirrors total_xp / level', function () {
    $body = json_encode([
        'event_id' => 'evt-1',
        'event_type' => 'gamification.level_up',
        'pandora_user_uuid' => $this->user->identity_uuid,
        'payload' => [
            'new_level' => 5,
            'total_xp' => 250,
        ],
    ]);

    $res = $this->call(
        'POST', '/api/v1/internal/webhooks/gamification',
        [], [], [],
        toServerHeaders(signedHeaders($body)),
        $body,
    );
    $res->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('mirrored', true);

    $this->user->refresh();
    expect($this->user->level)->toBe(5);
    expect($this->user->total_xp)->toBe(250);
});

it('rejects webhook with bad signature 401', function () {
    $body = json_encode(['event_id' => 'evt-2', 'event_type' => 'gamification.level_up', 'pandora_user_uuid' => $this->user->identity_uuid, 'payload' => ['new_level' => 2]]);

    $headers = signedHeaders($body);
    $headers['X-Pandora-Signature'] = 'sha256=ffffffffff';

    $res = $this->call('POST', '/api/v1/internal/webhooks/gamification', [], [], [], toServerHeaders($headers), $body);
    $res->assertStatus(401);
});

it('returns 404 when user_uuid not found', function () {
    $body = json_encode(['event_id' => 'evt-404', 'event_type' => 'gamification.level_up', 'pandora_user_uuid' => '00000000-dead-beef-0000-000000000000', 'payload' => ['new_level' => 2]]);
    $res = $this->call('POST', '/api/v1/internal/webhooks/gamification', [], [], [], toServerHeaders(signedHeaders($body)), $body);
    $res->assertStatus(404);
});

it('dedupes duplicate event_id with 200 short-circuit', function () {
    $body = json_encode(['event_id' => 'evt-dup', 'event_type' => 'gamification.level_up', 'pandora_user_uuid' => $this->user->identity_uuid, 'payload' => ['new_level' => 3, 'total_xp' => 90]]);

    $h1 = signedHeaders($body);
    $this->call('POST', '/api/v1/internal/webhooks/gamification', [], [], [], toServerHeaders($h1), $body)->assertOk();

    // Second send with same event_id (different timestamp + signature so fresh) → duplicate
    $h2 = signedHeaders($body);
    $res = $this->call('POST', '/api/v1/internal/webhooks/gamification', [], [], [], toServerHeaders($h2), $body);
    $res->assertOk()->assertJsonPath('status', 'duplicate');

    expect(GamificationWebhookNonce::count())->toBe(1);
});

it('caches achievement_awarded payload for pending pull', function () {
    $body = json_encode([
        'event_id' => 'evt-ach-1',
        'event_type' => 'gamification.achievement_awarded',
        'pandora_user_uuid' => $this->user->identity_uuid,
        'payload' => ['code' => 'first_cycle_logged', 'name' => '初次記錄', 'tier' => 'bronze'],
    ]);

    $this->call('POST', '/api/v1/internal/webhooks/gamification', [], [], [], toServerHeaders(signedHeaders($body)), $body)->assertOk();

    $cached = Cache::get("gamification:pending:{$this->user->identity_uuid}");
    expect($cached)->not->toBeNull();
    expect($cached['kind'])->toBe('achievement_unlocked');
    expect($cached['code'])->toBe('first_cycle_logged');
});

it('merges outfit codes into outfit_state.owned', function () {
    $this->user->update(['outfit_state' => ['owned' => ['default'], 'equipped' => 'default']]);

    $body = json_encode([
        'event_id' => 'evt-outfit-1',
        'event_type' => 'gamification.outfit_unlocked',
        'pandora_user_uuid' => $this->user->identity_uuid,
        'payload' => ['codes' => ['crown', 'angel_wings']],
    ]);

    $this->call('POST', '/api/v1/internal/webhooks/gamification', [], [], [], toServerHeaders(signedHeaders($body)), $body)
        ->assertOk()
        ->assertJsonPath('mirrored', 2);

    $this->user->refresh();
    expect($this->user->outfit_state['owned'])->toContain('crown');
    expect($this->user->outfit_state['owned'])->toContain('angel_wings');
    expect($this->user->outfit_state['owned'])->toContain('default');
});

it('returns 200 ignored for unknown event_type (forward-compat)', function () {
    $body = json_encode([
        'event_id' => 'evt-future-1',
        'event_type' => 'gamification.brand_new_thing',
        'pandora_user_uuid' => $this->user->identity_uuid,
        'payload' => ['foo' => 'bar'],
    ]);

    $this->call('POST', '/api/v1/internal/webhooks/gamification', [], [], [], toServerHeaders(signedHeaders($body)), $body)
        ->assertOk()
        ->assertJsonPath('status', 'ignored');
});

/**
 * Convert a regular header array into Laravel test "$server" array format
 * (uppercased + HTTP_ prefixed except for content-type).
 */
function toServerHeaders(array $headers): array
{
    $out = [];
    foreach ($headers as $k => $v) {
        $key = strtoupper(str_replace('-', '_', $k));
        if ($key === 'CONTENT_TYPE') {
            $out['CONTENT_TYPE'] = $v;
        } else {
            $out['HTTP_'.$key] = $v;
        }
    }

    return $out;
}
