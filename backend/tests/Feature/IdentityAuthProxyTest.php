<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.pandora_core.base_url', 'https://id.test');
    config()->set('services.pandora_core.product_code', 'fairy-calendar');
});

it('proxies email register to Pandora Core', function () {
    Http::fake([
        'https://id.test/api/v1/auth/email/register' => Http::response([
            'status' => 'pending_verification', 'user_id' => 42,
        ], 201),
    ]);

    $this->postJson('/api/v1/auth/register', [
        'email' => 'demo@x.com',
        'password' => 'secret123',
        'display_name' => 'Demo',
    ])->assertStatus(201)
      ->assertJson(['status' => 'pending_verification', 'user_id' => 42]);

    Http::assertSent(function ($req) {
        $body = $req->data();

        return $req->method() === 'POST'
            && str_contains($req->url(), '/api/v1/auth/email/register')
            && $body['email'] === 'demo@x.com'
            && $body['password'] === 'secret123';
    });
});

it('proxies login and injects product_code', function () {
    Http::fake([
        'https://id.test/api/v1/auth/email/login' => Http::response([
            'access_token' => 'fake.jwt.token',
            'refresh_token' => 'rrr',
            'expires_in' => 900,
            'user' => ['id' => 1, 'email_canonical' => 'demo@x.com', 'display_name' => 'Demo'],
        ], 200),
        'https://id.test/api/v1/auth/public-key' => Http::response(['public_key' => '---broken---'], 200),
    ]);

    $resp = $this->postJson('/api/v1/auth/login', [
        'email' => 'demo@x.com',
        'password' => 'secret123',
    ])->assertOk()
      ->assertJsonPath('access_token', 'fake.jwt.token');

    Http::assertSent(function ($req) {
        if (! str_contains($req->url(), '/api/v1/auth/email/login')) {
            return false;
        }
        $body = $req->data();

        return $body['product_code'] === 'fairy-calendar';
    });
});

it('relays 401 from PC on bad credentials', function () {
    Http::fake([
        'https://id.test/api/v1/auth/email/login' => Http::response([
            'error' => 'login_failed', 'detail' => 'invalid credentials',
        ], 401),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'demo@x.com', 'password' => 'wrong',
    ])->assertStatus(401)
      ->assertJsonPath('error', 'login_failed');
});

it('returns 503 when base_url unset', function () {
    config()->set('services.pandora_core.base_url', '');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'demo@x.com', 'password' => 'x',
    ])->assertStatus(503);
});

it('returns OAuth redirect URL with product_code', function () {
    $this->getJson('/api/v1/auth/oauth/google/url')
        ->assertOk()
        ->assertJsonPath('redirect_url', 'https://id.test/api/v1/auth/oauth/google/redirect?product_code=fairy-calendar');
});

it('rejects unsupported OAuth provider', function () {
    $this->getJson('/api/v1/auth/oauth/twitter/url')->assertStatus(422);
});
