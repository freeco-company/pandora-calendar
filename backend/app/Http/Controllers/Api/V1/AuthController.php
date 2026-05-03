<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Identity\IdentityClient;
use App\Support\Sentry\SentryHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * P1 ADR-007 Auth proxy — calendar 端不自己存 password，全部 forward 給 Pandora Core。
 *
 * Endpoints（POST /api/v1/auth/*）：
 *   - register  → POST PC /api/v1/auth/email/register
 *   - login     → POST PC /api/v1/auth/email/login（自動帶 product_code=fairy-calendar）
 *   - refresh   → POST PC /api/v1/auth/refresh
 *   - logout    → POST PC /api/v1/auth/logout
 *   - oauth/{provider}/url → 回 PC OAuth redirect URL（給 Capacitor in-app browser）
 *
 * 401 / 422 透傳 PC 的 error / detail。
 *
 * `me` 由 SanctumOrPandoraJwt middleware 處理（既有 GET /api/v1/me）。
 */
class AuthController extends Controller
{
    public function __construct(private IdentityClient $client) {}

    public function register(Request $request): JsonResponse
    {
        return $this->forward('post', '/api/v1/auth/email/register', $request->only([
            'email', 'password', 'display_name',
        ]));
    }

    public function login(Request $request): JsonResponse
    {
        $body = $request->only(['email', 'password']);
        // 強制 product_code=fairy-calendar；前端不需要也不應該指定
        $body['product_code'] = (string) config('services.pandora_core.product_code', 'fairy-calendar');

        $resp = $this->forward('post', '/api/v1/auth/email/login', $body);

        // 登入成功時順手 firstOrCreate 本地 mirror，確保 user 立即可用
        if ($resp->getStatusCode() === 200) {
            $payload = json_decode((string) $resp->getContent(), true);
            $access = $payload['access_token'] ?? null;
            if (is_string($access) && $access !== '') {
                $this->client->resolveFromJwt($access);
            }
        }

        return $resp;
    }

    public function refresh(Request $request): JsonResponse
    {
        return $this->forward('post', '/api/v1/auth/refresh', $request->only(['refresh_token']));
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->forward('post', '/api/v1/auth/logout', $request->only(['refresh_token']));
    }

    public function oauthUrl(Request $request, string $provider): JsonResponse
    {
        $allowed = ['google', 'line', 'apple'];
        if (! in_array($provider, $allowed, true)) {
            return response()->json(['error' => 'unsupported_provider'], 422);
        }

        $base = $this->baseUrl();
        $product = (string) config('services.pandora_core.product_code', 'fairy-calendar');

        return response()->json([
            'redirect_url' => rtrim($base, '/').'/api/v1/auth/oauth/'.$provider.'/redirect?product_code='.$product,
        ]);
    }

    /**
     * Forward a request to Pandora Core, transparently relaying status + body.
     */
    private function forward(string $method, string $path, array $body): JsonResponse
    {
        $base = $this->baseUrl();
        if ($base === '') {
            return response()->json(['error' => 'identity_not_configured'], 503);
        }

        try {
            $resp = Http::timeout(8)
                ->acceptJson()
                ->{$method}(rtrim($base, '/').$path, $body);
        } catch (\Throwable $e) {
            SentryHelper::captureException($e, 'oauth', [
                'stage' => 'forward',
                'pc_path' => $path,
                'method' => $method,
                // 不送 $body — 含 password / refresh_token
            ]);
            return response()->json(['error' => 'identity_unreachable', 'detail' => $e->getMessage()], 502);
        }

        $json = $resp->json();
        if (! is_array($json)) {
            $json = ['raw' => (string) $resp->body()];
        }

        // PC 5xx → unexpected (4xx 是 user-input error，不報)
        if ($resp->status() >= 500) {
            SentryHelper::captureMessage(
                "Pandora Core forward 5xx: {$resp->status()} {$path}",
                'error',
                'oauth',
                [
                    'stage' => 'forward',
                    'pc_path' => $path,
                    'method' => $method,
                    'status' => $resp->status(),
                ],
            );
        }

        return response()->json($json, $resp->status());
    }

    private function baseUrl(): string
    {
        return (string) config('services.pandora_core.base_url', '');
    }
}
