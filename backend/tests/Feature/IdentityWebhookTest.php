<?php

use App\Models\IdentityWebhookNonce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.pandora_core.webhook_secret', 'test-webhook-secret');
    config()->set('services.pandora_core.webhook_window_seconds', 300);
});

function signedIdentityHeaders(string $body, string $eventId, string $secret = 'test-webhook-secret', ?int $ts = null): array
{
    $ts = $ts ?? time();
    return [
        'X-Pandora-Event-Id' => $eventId,
        'X-Pandora-Timestamp' => (string) $ts,
        'X-Pandora-Signature' => hash_hmac('sha256', "{$ts}.{$eventId}.{$body}", $secret),
        'Content-Type' => 'application/json',
    ];
}

it('accepts valid user.upserted and creates mirror', function () {
    $body = json_encode([
        'type' => 'user.upserted',
        'data' => [
            'uuid' => '00000000-aaaa-7000-8000-000000000aaa',
            'display_name' => 'Webhook Tester',
            'subscription_tier' => 'premium',
        ],
    ]);

    $resp = $this->call('POST', '/api/v1/internal/webhooks/identity', [], [], [],
        $this->transformHeadersToServerVars(signedIdentityHeaders($body, 'evt-aaa-1')),
        $body
    );

    $resp->assertOk()
         ->assertJsonPath('status', 'ok')
         ->assertJsonPath('identity_uuid', '00000000-aaaa-7000-8000-000000000aaa');

    $u = User::query()->where('identity_uuid', '00000000-aaaa-7000-8000-000000000aaa')->first();
    expect($u)->not->toBeNull();
    expect($u->display_name)->toBe('Webhook Tester');
    expect($u->subscription_tier)->toBe('premium');
});

it('returns 200 duplicate when same event_id replayed', function () {
    IdentityWebhookNonce::create(['event_id' => 'replay-1']);

    $body = json_encode(['type' => 'user.upserted', 'data' => ['uuid' => 'foo']]);
    $resp = $this->call('POST', '/api/v1/internal/webhooks/identity', [], [], [],
        $this->transformHeadersToServerVars(signedIdentityHeaders($body, 'replay-1')),
        $body
    );

    $resp->assertOk()->assertJsonPath('status', 'duplicate');
});

it('returns 401 on signature mismatch', function () {
    $body = json_encode(['type' => 'user.upserted', 'data' => ['uuid' => 'x']]);
    $headers = signedIdentityHeaders($body, 'evt-bad', 'wrong-secret');

    $this->call('POST', '/api/v1/internal/webhooks/identity', [], [], [],
        $this->transformHeadersToServerVars($headers), $body
    )->assertStatus(401);
});

it('returns 401 on stale timestamp', function () {
    $body = json_encode(['type' => 'user.upserted', 'data' => ['uuid' => 'x']]);
    $headers = signedIdentityHeaders($body, 'evt-stale', 'test-webhook-secret', time() - 1000);

    $this->call('POST', '/api/v1/internal/webhooks/identity', [], [], [],
        $this->transformHeadersToServerVars($headers), $body
    )->assertStatus(401);
});

it('rejects unknown event type', function () {
    $body = json_encode(['type' => 'user.exploded', 'data' => ['uuid' => 'x']]);

    $this->call('POST', '/api/v1/internal/webhooks/identity', [], [], [],
        $this->transformHeadersToServerVars(signedIdentityHeaders($body, 'evt-unk')), $body
    )->assertStatus(422);
});

it('rejects missing uuid', function () {
    $body = json_encode(['type' => 'user.upserted', 'data' => []]);

    $this->call('POST', '/api/v1/internal/webhooks/identity', [], [], [],
        $this->transformHeadersToServerVars(signedIdentityHeaders($body, 'evt-no-uuid')), $body
    )->assertStatus(422);
});

it('does NOT mirror PII fields even if PC sends them', function () {
    $body = json_encode([
        'type' => 'user.upserted',
        'data' => [
            'uuid' => '00000000-aaaa-7000-8000-pii00000pii',
            'display_name' => 'OK',
            // Hostile payload — must NOT land in calendar DB
            'email' => 'leak@bad.com',
            'phone' => '0900000000',
            'password_hash' => '$2y$pwned',
        ],
    ]);

    $this->call('POST', '/api/v1/internal/webhooks/identity', [], [], [],
        $this->transformHeadersToServerVars(signedIdentityHeaders($body, 'evt-pii')), $body
    )->assertOk();

    $u = User::query()->where('identity_uuid', '00000000-aaaa-7000-8000-pii00000pii')->first();
    expect($u->email)->toBeNull();
    expect((string) ($u->phone ?? ''))->toBe('');
    // password column may exist; should not be populated by webhook
    expect($u->password)->toBeNull();
});
