<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| ADR-009 P5.1：每分鐘 flush outbox 把 gamification / conversion / body_rhythm
| 事件推給集團服務。失敗 5 次自動 dead letter（attempts >= 5）。
*/
Schedule::command('pandora:outbox:flush')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/*
| P1 ADR-007 §6 — 每 10 分鐘從 Pandora Core 拉 users delta 同步 mirror
| 是 webhook 漏接的 safety net（PII-free response）。
*/
Schedule::command('identity:reconcile-users')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
| 每天早上 8:00（台北時區）對 push_opted_in 用戶寄 phase-based 推播。
| 觸發規則嚴：經期前一天 / 經期當天 / 排卵前一天，平日不寄。
*/
Schedule::command('push:send-daily-reminders')
    ->dailyAt('08:00')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping()
    ->runInBackground();

/*
| Daily — 清掉 7 天前的 user data export 檔（PDF / CSV）。
*/
Schedule::command('exports:purge --days=7')
    ->dailyAt('03:30')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping()
    ->runInBackground();
