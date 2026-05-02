<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Services\Identity\IdentityClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * P1 ADR-007 — calendar 端 PC user.upserted webhook receiver。
 *
 * 簽章 / replay 防護由 VerifyIdentityWebhookSignature middleware 處理；
 * 走到這層時 event_id 已 dedup、簽章已驗。本層只負責業務 upsert。
 *
 * 嚴守 §2.3：不接收 / 不存 PII（email / phone / address / password_hash）。
 * IdentityClient::syncFromPlatform 已 hard-code 只 fill minimal mirror 欄位。
 *
 * Reconcile worker 是 fallback；webhook 是即時 path。
 */
class IdentityWebhookController extends Controller
{
    public function __construct(private IdentityClient $client) {}

    public function __invoke(Request $request): JsonResponse
    {
        $type = (string) $request->input('type', '');
        $data = $request->input('data', []);

        if (! is_array($data) || ! isset($data['uuid']) || ! is_string($data['uuid']) || $data['uuid'] === '') {
            return response()->json(['error' => 'invalid payload: data.uuid missing'], 422);
        }

        if (! in_array($type, ['user.upserted', 'user.suspended', 'user.merged'], true)) {
            Log::warning('[IdentityWebhook] unknown event type', ['type' => $type]);

            return response()->json(['error' => "unknown event type: {$type}"], 422);
        }

        $user = $this->client->syncFromPlatform($data['uuid'], $data);

        return response()->json([
            'status' => 'ok',
            'identity_uuid' => $user->identity_uuid,
        ]);
    }
}
