<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * 一次性產 VAPID keypair。輸出 base64url encoded public + private，
 * 貼進 backend .env 的 PUSH_VAPID_PUBLIC_KEY / PUSH_VAPID_PRIVATE_KEY，
 * 以及 frontend .env.production 的 VITE_VAPID_PUBLIC_KEY（同一把 public key）。
 */
class VapidKeyGen extends Command
{
    protected $signature = 'push:vapid-keygen';

    protected $description = '產 Web Push VAPID keypair';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('# Backend (.env)');
        $this->line('PUSH_VAPID_SUBJECT=mailto:support@js-store.com.tw');
        $this->line('PUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('PUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('');
        $this->line('# Frontend (.env.production — 同一把 public key)');
        $this->line('VITE_VAPID_PUBLIC_KEY='.$keys['publicKey']);

        return self::SUCCESS;
    }
}
