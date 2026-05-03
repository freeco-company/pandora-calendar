<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Pushok\AuthProvider\Token as ApnsTokenAuth;
use Pushok\Client as ApnsClient;
use Pushok\Notification as ApnsNotification;
use Pushok\Payload as ApnsPayload;
use Pushok\Payload\Alert as ApnsAlert;
use Throwable;

/**
 * APNs HTTP/2 channel via edamov/pushok (JWT token auth, .p8 key)。
 *
 * 缺 APNS_TEAM_ID / APNS_KEY_ID / APNS_PRIVATE_KEY_PATH → isConfigured()=false → noop。
 */
class ApnsChannel implements PushChannel
{
    private ?ApnsClient $client = null;

    public function __construct(
        private readonly string $teamId = '',
        private readonly string $keyId = '',
        private readonly string $privateKeyPath = '',
        private readonly string $bundleId = '',
        private readonly bool $sandbox = false,
    ) {}

    public function isConfigured(): bool
    {
        return $this->teamId !== ''
            && $this->keyId !== ''
            && $this->privateKeyPath !== ''
            && $this->bundleId !== ''
            && file_exists($this->privateKeyPath);
    }

    public function send(PushSubscription $sub, string $title, string $body, array $data = []): array
    {
        if (! $this->isConfigured()) {
            Log::info('[ApnsChannel] not configured; skipping (noop)');

            return ['ok' => false, 'status' => null, 'reason' => 'not_configured'];
        }

        if (empty($sub->device_token)) {
            return ['ok' => false, 'status' => 400, 'reason' => 'missing_device_token'];
        }

        try {
            $alert = ApnsAlert::create()->setTitle($title)->setBody($body);
            $payload = ApnsPayload::create()
                ->setAlert($alert)
                ->setSound('default')
                ->setBadge(1);
            foreach ($data as $k => $v) {
                $payload->setCustomValue((string) $k, is_string($v) ? $v : json_encode($v));
            }

            $notification = new ApnsNotification($payload, $sub->device_token);

            $client = $this->client();
            $client->addNotifications([$notification]);
            $responses = $client->push();

            $resp = $responses[0] ?? null;
            if ($resp === null) {
                return ['ok' => false, 'status' => 500, 'reason' => 'no_response'];
            }
            $status = (int) $resp->getStatusCode();
            $ok = $status >= 200 && $status < 300;

            return [
                'ok' => $ok,
                'status' => $status,
                'reason' => $ok ? null : (string) $resp->getReasonPhrase(),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 500, 'reason' => $e->getMessage()];
        }
    }

    private function client(): ApnsClient
    {
        if ($this->client === null) {
            $auth = ApnsTokenAuth::create([
                'key_id' => $this->keyId,
                'team_id' => $this->teamId,
                'app_bundle_id' => $this->bundleId,
                'private_key_path' => $this->privateKeyPath,
                'private_key_secret' => null,
            ]);
            $this->client = new ApnsClient($auth, production: ! $this->sandbox);
        }

        return $this->client;
    }
}
