<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox pattern (ADR-007 §4)：所有 publish 到集團（gamification / conversion / body_rhythm）
 * 的事件先寫到本 table，再由 worker job 推到 py-service / mother / Pandora Core。
 *
 * 同步失敗自動重試 + dead letter queue。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type', 32);  // user / cycle / subscription
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_kind', 64);      // pandora_calendar.first_cycle, ...
            $table->string('destination', 32);     // gamification / conversion / body_rhythm
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['destination', 'published_at', 'attempts']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
