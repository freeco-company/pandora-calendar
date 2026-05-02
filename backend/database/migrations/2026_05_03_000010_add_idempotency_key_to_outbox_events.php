<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P5.2：給 outbox_events 加 idempotency_key（unique）。
 *
 * Format：`calendar.{event}.{user_id}.{aggregate_id}.{date}` — 同 user 同事件
 * 同天同 aggregate 只送一次（py-service 那邊 catalog 也會 daily_cap，這裡是 publish 端的雙保險）。
 *
 * Nullable 是為了向後相容既存事件 + body_rhythm / conversion 等不需要去重的事件。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_events', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->after('event_kind');
            $table->unique('idempotency_key', 'outbox_events_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('outbox_events', function (Blueprint $table) {
            $table->dropUnique('outbox_events_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
