<?php

use App\Models\User;
use App\Services\Identity\IdentityClient;
use App\Services\Identity\PlatformJwtVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;

uses(RefreshDatabase::class);

/**
 * Pandora Core JWT 認證 e2e — 驗 PlatformJwtVerifier + IdentityClient + middleware。
 *
 * 用 in-memory RSA 鍵簽 token，stub 公鑰 cache 直接放 public key。
 */
beforeEach(function () {
    config()->set('services.pandora_core.base_url', 'https://id.test');
    config()->set('services.pandora_core.jwt_issuer', 'pandora-core');
    config()->set('services.pandora_core.jwt_audience', 'fairy-calendar');

    // 產生 RSA 鍵組（每個 test 一組，乾淨）
    $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($res, $privateKey);
    $publicKey = openssl_pkey_get_details($res)['key'];

    $this->signingKey = $privateKey;
    $this->publicKey = $publicKey;

    // Cache 預先放好 public key，避免測試打外網
    cache()->put('identity:platform_jwk', $publicKey, 3600);
});

function makeJwt(string $sub, array $overrides = [], string $signingKey = null): string
{
    $signingKey = $signingKey ?? test()->signingKey;
    $config = Configuration::forAsymmetricSigner(
        new Sha256,
        InMemory::plainText($signingKey),
        InMemory::plainText('not-used-for-verify'),
    );

    $now = new DateTimeImmutable;
    $builder = $config->builder()
        ->issuedBy($overrides['iss'] ?? 'pandora-core')
        ->permittedFor($overrides['aud'] ?? 'fairy-calendar')
        ->relatedTo($sub)
        ->identifiedBy('test-jti-'.uniqid())
        ->issuedAt($overrides['iat'] ?? $now)
        ->canOnlyBeUsedAfter($overrides['nbf'] ?? $now)
        ->expiresAt($overrides['exp'] ?? $now->modify('+10 minutes'))
        ->withClaim('scopes', $overrides['scopes'] ?? ['profile:read']);

    return $builder->getToken($config->signer(), $config->signingKey())->toString();
}

it('verifies a well-formed Pandora Core JWT and creates mirror', function () {
    $uuid = '00000000-aaaa-7000-8000-000000000001';
    $jwt = makeJwt($uuid);

    expect(User::query()->where('identity_uuid', $uuid)->exists())->toBeFalse();

    $client = app(IdentityClient::class);
    $resolved = $client->resolveFromJwt($jwt);

    expect($resolved)->not->toBeNull();
    expect($resolved['user'])->toBeInstanceOf(User::class);
    expect($resolved['user']->identity_uuid)->toBe($uuid);
    expect(User::query()->where('identity_uuid', $uuid)->count())->toBe(1);
});

it('returns null when issuer does not match', function () {
    $jwt = makeJwt('00000000-aaaa-7000-8000-000000000002', ['iss' => 'wrong-issuer']);

    expect(app(IdentityClient::class)->resolveFromJwt($jwt))->toBeNull();
});

it('returns null when audience does not match', function () {
    $jwt = makeJwt('00000000-aaaa-7000-8000-000000000003', ['aud' => 'fairy-skin']);

    expect(app(IdentityClient::class)->resolveFromJwt($jwt))->toBeNull();
});

it('returns null when token is expired', function () {
    $past = (new DateTimeImmutable)->modify('-1 hour');
    $jwt = makeJwt('00000000-aaaa-7000-8000-000000000004', [
        'iat' => $past,
        'nbf' => $past,
        'exp' => (new DateTimeImmutable)->modify('-30 minutes'),
    ]);

    expect(app(IdentityClient::class)->resolveFromJwt($jwt))->toBeNull();
});

it('returns null when signed with different key', function () {
    $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($other, $otherPriv);

    $jwt = makeJwt('00000000-aaaa-7000-8000-000000000005', [], $otherPriv);

    expect(app(IdentityClient::class)->resolveFromJwt($jwt))->toBeNull();
});

it('allows protected route via valid JWT (auth.platform middleware)', function () {
    $uuid = '00000000-aaaa-7000-8000-000000000006';
    $jwt = makeJwt($uuid);

    $resp = $this->withHeader('Authorization', "Bearer {$jwt}")
        ->getJson('/api/v1/me');

    $resp->assertOk();
    expect($resp->json('data.identity_uuid'))->toBe($uuid);
});

it('rejects protected route with no auth (401)', function () {
    $this->getJson('/api/v1/me')->assertStatus(401);
});

it('rejects protected route with garbage bearer (401)', function () {
    $this->withHeader('Authorization', 'Bearer not-a-jwt')
        ->getJson('/api/v1/me')
        ->assertStatus(401);
});
