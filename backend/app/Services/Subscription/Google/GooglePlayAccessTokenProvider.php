<?php

namespace App\Services\Subscription\Google;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;

/**
 * Google service-account JWT exchange → OAuth 2.0 access token，給 Google Play Developer API 用。
 *
 * Flow:
 *   1. 從 service account JSON 拿 client_email + private_key
 *   2. 簽 JWT (RS256)
 *      claims:
 *        iss = client_email
 *        scope = https://www.googleapis.com/auth/androidpublisher
 *        aud = https://oauth2.googleapis.com/token
 *        iat / exp (1 hour)
 *   3. POST 到 https://oauth2.googleapis.com/token
 *      grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=<jwt>
 *   4. Cache access_token 直到 expires_in - 60s 緩衝
 *
 * 安全：service account JSON 內含 private key，**只能在 server**，絕不送到 client。
 * 透過 GOOGLE_PLAY_SERVICE_ACCOUNT_JSON env (絕對路徑 file:// 或內聯 JSON) 載入。
 */
class GooglePlayAccessTokenProvider
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';
    private const CACHE_KEY = 'google-play-access-token';

    public function __construct(private readonly HttpFactory $http) {}

    public function get(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        return $this->fetchAndCache();
    }

    public function refresh(): string
    {
        Cache::forget(self::CACHE_KEY);

        return $this->fetchAndCache();
    }

    private function fetchAndCache(): string
    {
        [$clientEmail, $privateKey] = $this->loadServiceAccount();

        $now = time();
        $exp = $now + 3600;

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $clientEmail,
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $exp,
        ];

        $signingInput = $this->b64url(json_encode($header)).'.'.$this->b64url(json_encode($claims));

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to sign Google service-account JWT');
        }
        $assertion = "$signingInput.".$this->b64url($signature);

        $res = $this->http->asForm()->timeout(8)->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if (! $res->successful()) {
            throw new \RuntimeException('Google token exchange failed: '.$res->status().' '.substr($res->body(), 0, 200));
        }

        $token = $res->json('access_token');
        $expiresIn = (int) $res->json('expires_in', 3600);
        if (! $token) {
            throw new \RuntimeException('Google response missing access_token');
        }

        Cache::put(self::CACHE_KEY, $token, max(60, $expiresIn - 60));

        return $token;
    }

    /**
     * @return array{0: string, 1: string} [client_email, private_key_pem]
     */
    private function loadServiceAccount(): array
    {
        $raw = (string) config('pandora.subscription.google_play_service_account_json', '');
        if (! $raw) {
            throw new \RuntimeException('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON not set');
        }

        // Allow either inline JSON or "file://path/to/key.json"
        if (str_starts_with($raw, 'file://')) {
            $path = substr($raw, 7);
            if (! is_readable($path)) {
                throw new \RuntimeException("Cannot read service account file: $path");
            }
            $raw = file_get_contents($path);
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new \RuntimeException('Service account JSON missing client_email or private_key');
        }

        return [$data['client_email'], $data['private_key']];
    }

    private function b64url(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
}
