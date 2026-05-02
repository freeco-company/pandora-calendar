<?php

namespace App\Http\Middleware;

use App\Services\Identity\IdentityClient;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P1 ADR-007 §2.3 — dual-auth middleware：先試 Pandora Core JWT 再 fallback sanctum。
 *
 * 解析順序：
 *   1. Authorization: Bearer header 走 IdentityClient::resolveFromJwt
 *   2. 失敗則 fallback sanctum guard（actingAs / personal access token）
 *   3. 都失敗 → 401
 *
 * Mirror of meal's SanctumOrPandoraJwt — 集團三 App 同一 pattern。
 */
class SanctumOrPandoraJwt
{
    public function __construct(
        private IdentityClient $identity,
        private AuthFactory $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // (1) Try Pandora Core JWT first（only if header present）
        $bearer = $this->extractBearer($request);
        if ($bearer !== null) {
            $resolved = $this->identity->resolveFromJwt($bearer);
            if ($resolved !== null) {
                $user = $resolved['user'];
                $request->setUserResolver(static fn () => $user);
                $request->attributes->set('pandora_jwt', $resolved['token']);
                $request->attributes->set('auth_strategy', 'pandora_jwt');

                return $next($request);
            }
        }

        // (2) Fallback to sanctum guard（actingAs / personal access tokens）
        $sanctumGuard = $this->auth->guard('sanctum');
        if ($sanctumGuard->check()) {
            $user = $sanctumGuard->user();
            $request->setUserResolver(static fn () => $user);
            $request->attributes->set('auth_strategy', 'sanctum');

            return $next($request);
        }

        // (3) Both failed
        throw new AuthenticationException('Unauthenticated.', ['sanctum']);
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
