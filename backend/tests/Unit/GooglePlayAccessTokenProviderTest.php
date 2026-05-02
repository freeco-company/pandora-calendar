<?php

use App\Services\Subscription\Google\GooglePlayAccessTokenProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    [$this->serviceJson, $this->verifyKey] = makeFakeServiceAccount();
    config(['pandora.subscription.google_play_service_account_json' => $this->serviceJson]);
});

it('exchanges service-account JWT for access token and caches it', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.fake-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
    ]);

    $provider = app(GooglePlayAccessTokenProvider::class);

    expect($provider->get())->toBe('ya29.fake-token');
    expect(Cache::get('google-play-access-token'))->toBe('ya29.fake-token');

    // Second call should hit cache (no second HTTP request)
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'should-not-be-used', 'expires_in' => 3600], 200),
    ]);
    expect($provider->get())->toBe('ya29.fake-token');
});

it('refresh forces new token fetch', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::sequence()
            ->push(['access_token' => 'first', 'expires_in' => 3600], 200)
            ->push(['access_token' => 'second', 'expires_in' => 3600], 200),
    ]);

    $provider = app(GooglePlayAccessTokenProvider::class);

    expect($provider->get())->toBe('first');
    expect($provider->refresh())->toBe('second');
});

it('builds JWT with required claims and RS256 signature', function () {
    $captured = null;

    Http::fake(function ($req) use (&$captured) {
        $captured = $req;

        return Http::response(['access_token' => 'tk', 'expires_in' => 3600], 200);
    });

    app(GooglePlayAccessTokenProvider::class)->get();

    $body = $captured->body();
    parse_str($body, $form);

    expect($form['grant_type'])->toBe('urn:ietf:params:oauth:grant-type:jwt-bearer');
    expect($form['assertion'])->not->toBeEmpty();

    [$h, $p, $s] = explode('.', $form['assertion']);
    $header = json_decode(base64UrlDecodeForTest($h), true);
    $claims = json_decode(base64UrlDecodeForTest($p), true);

    expect($header['alg'])->toBe('RS256');
    expect($claims['iss'])->toBe('test-sa@project.iam.gserviceaccount.com');
    expect($claims['scope'])->toBe('https://www.googleapis.com/auth/androidpublisher');
    expect($claims['aud'])->toBe('https://oauth2.googleapis.com/token');
    expect($claims['exp'])->toBeGreaterThan(time());

    // Verify the signature against the public key
    $signingInput = "$h.$p";
    $sig = base64UrlDecodeForTest($s);
    $valid = openssl_verify($signingInput, $sig, $this->verifyKey, OPENSSL_ALGO_SHA256);
    expect($valid)->toBe(1);
});

it('throws when token endpoint fails', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response('boom', 500)]);

    expect(fn () => app(GooglePlayAccessTokenProvider::class)->get())
        ->toThrow(RuntimeException::class, 'Google token exchange failed');
});

it('throws when service account env not set', function () {
    config(['pandora.subscription.google_play_service_account_json' => null]);

    expect(fn () => app(GooglePlayAccessTokenProvider::class)->get())
        ->toThrow(RuntimeException::class, 'GOOGLE_PLAY_SERVICE_ACCOUNT_JSON not set');
});

// ── helpers ──────────────────────────────────────────────────────────────────

function makeFakeServiceAccount(): array
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    openssl_pkey_export($key, $privKeyPem);
    $details = openssl_pkey_get_details($key);
    $pubKeyPem = $details['key'];

    $serviceAccount = [
        'type' => 'service_account',
        'client_email' => 'test-sa@project.iam.gserviceaccount.com',
        'private_key' => $privKeyPem,
        'private_key_id' => 'fake',
        'project_id' => 'project',
    ];

    return [json_encode($serviceAccount), $pubKeyPem];
}

function base64UrlDecodeForTest(string $s): string
{
    $padded = str_pad(strtr($s, '-_', '+/'), strlen($s) + (4 - strlen($s) % 4) % 4, '=');

    return base64_decode($padded);
}
