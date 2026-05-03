<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

/**
 * FCM HTTP v1 channel via kreait/firebase-php (OAuth2 service account)。
 *
 * 缺 FCM_PROJECT_ID / FCM_CREDENTIALS_PATH → isConfigured()=false → noop（不報錯）。
 */
class FcmChannel implements PushChannel
{
    private ?Messaging $messaging = null;

    public function __construct(
        private readonly string $projectId = '',
        private readonly string $credentialsPath = '',
    ) {}

    public function isConfigured(): bool
    {
        return $this->projectId !== ''
            && $this->credentialsPath !== ''
            && file_exists($this->credentialsPath);
    }

    public function send(PushSubscription $sub, string $title, string $body, array $data = []): array
    {
        if (! $this->isConfigured()) {
            Log::info('[FcmChannel] not configured; skipping (noop)');

            return ['ok' => false, 'status' => null, 'reason' => 'not_configured'];
        }

        if (empty($sub->device_token)) {
            return ['ok' => false, 'status' => 400, 'reason' => 'missing_device_token'];
        }

        try {
            $msg = CloudMessage::withTarget('token', $sub->device_token)
                ->withNotification(FcmNotification::create($title, $body))
                ->withData($this->stringifyData($data));

            $this->messaging()->send($msg);

            return ['ok' => true, 'status' => 200, 'reason' => null];
        } catch (NotFound $e) {
            // Token invalid / unregistered → caller should delete sub
            return ['ok' => false, 'status' => 404, 'reason' => 'unregistered'];
        } catch (MessagingException $e) {
            $code = $this->guessStatus($e);

            return ['ok' => false, 'status' => $code, 'reason' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 500, 'reason' => $e->getMessage()];
        }
    }

    private function messaging(): Messaging
    {
        if ($this->messaging === null) {
            $factory = (new Factory)
                ->withServiceAccount($this->credentialsPath)
                ->withProjectId($this->projectId);
            $this->messaging = $factory->createMessaging();
        }

        return $this->messaging;
    }

    /**
     * FCM data payload 必須是 string-only。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $out[(string) $k] = is_string($v) ? $v : (string) json_encode($v);
        }

        return $out;
    }

    private function guessStatus(MessagingException $e): int
    {
        $msg = strtolower($e->getMessage());
        if (str_contains($msg, 'invalid') || str_contains($msg, 'unregistered')) {
            return 404;
        }
        if (str_contains($msg, 'quota')) {
            return 429;
        }

        return 500;
    }
}
