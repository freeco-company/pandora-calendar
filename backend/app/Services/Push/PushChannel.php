<?php

namespace App\Services\Push;

use App\Models\PushSubscription;

/**
 * Push channel 抽象。各 platform（FCM / APNs / WebPush）實作此介面。
 *
 * Contract:
 *  - send() 回 ['ok' => bool, 'status' => ?int, 'reason' => ?string]
 *  - 缺 credential 時 isConfigured() = false，dispatcher 會 log info 跳過（不報錯）
 *  - status 410 / 404 / InvalidRegistration → dispatcher 會自動清掉 sub
 */
interface PushChannel
{
    public function isConfigured(): bool;

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, status: ?int, reason: ?string}
     */
    public function send(PushSubscription $sub, string $title, string $body, array $data = []): array;
}
