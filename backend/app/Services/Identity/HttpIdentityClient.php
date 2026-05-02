<?php

namespace App\Services\Identity;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;

/**
 * P1+ 真實接 Pandora Core Identity Service（Laravel 13 + MariaDB）。
 *
 * - base URL 走 PANDORA_CORE_BASE_URL env
 * - JWT 走 PANDORA_CORE_INTERNAL_SECRET HMAC（與 mother / meal 一致）
 * - lookup result cache 5 min（避免 Identity 服務 hot path 壓力）
 */
final class HttpIdentityClient implements IdentityClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $secret,
    ) {}

    public function lookupByUuid(string $uuid): ?IdentityProfile
    {
        return Cache::remember("identity:uuid:$uuid", 300, function () use ($uuid) {
            $res = $this->http->withHeaders($this->headers())
                ->get("{$this->baseUrl}/internal/users/{$uuid}");

            if (! $res->ok()) {
                return null;
            }

            return IdentityProfile::fromArray($res->json('data'));
        });
    }

    public function lookupByEmail(string $email): ?IdentityProfile
    {
        $res = $this->http->withHeaders($this->headers())
            ->get("{$this->baseUrl}/internal/users", ['email' => $email]);

        if (! $res->ok() || ! $res->json('data')) {
            return null;
        }

        return IdentityProfile::fromArray($res->json('data'));
    }

    public function exchangeAccessToken(string $accessToken): ?IdentityProfile
    {
        $res = $this->http->withHeaders($this->headers() + ['X-Access-Token' => $accessToken])
            ->post("{$this->baseUrl}/internal/tokens/exchange");

        if (! $res->ok()) {
            return null;
        }

        return IdentityProfile::fromArray($res->json('data'));
    }

    public function listConsentScopes(string $uuid): array
    {
        $res = $this->http->withHeaders($this->headers())
            ->get("{$this->baseUrl}/internal/users/{$uuid}/consent");

        return $res->ok() ? ($res->json('data.scopes') ?? []) : [];
    }

    private function headers(): array
    {
        return [
            'X-Internal-Secret' => $this->secret,
            'X-Source-App' => 'pandora-calendar',
            'Accept' => 'application/json',
        ];
    }
}
