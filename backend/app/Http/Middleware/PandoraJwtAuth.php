<?php

namespace App\Http\Middleware;

use App\Services\Identity\IdentityClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pandora Core JWT 驗證 middleware（P1 ADR-007）。
 *
 * 流程：
 *   1. 拿 Authorization: Bearer header
 *   2. IdentityClient::resolveFromJwt — RS256 verify + iss/aud/exp/nbf 全套
 *   3. firstOrCreate User mirror by identity_uuid
 *   4. setUserResolver 讓 $request->user() 直接拿到 User，與 sanctum 一樣
 *
 * 失敗回 401，不 fall through。需要混用 sanctum + JWT 的 routes 用
 * SanctumOrPandoraJwt middleware 而非這個。
 */
class PandoraJwtAuth
{
    public function __construct(private IdentityClient $client) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $this->extractBearer($request);
        if ($bearer === null) {
            return response()->json(['error' => 'missing bearer token'], 401);
        }

        $resolved = $this->client->resolveFromJwt($bearer);
        if ($resolved === null) {
            return response()->json(['error' => 'invalid token'], 401);
        }

        $request->setUserResolver(fn () => $resolved['user']);
        $request->attributes->set('pandora_jwt', $resolved['token']);

        return $next($request);
    }

    private function extractBearer(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }
}
