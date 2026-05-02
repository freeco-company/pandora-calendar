<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P5.3 ADR-009 §2.2：calendar 端 webhook idempotency 表。
 *
 * py-service push 來的每筆 webhook 帶 event_id（unique），收件 middleware
 * INSERT；duplicate → 200 short-circuit（publisher 已認為投遞成功，loop 沒
 * 意義）。同 meal repo 的 gamification_webhook_nonces 結構。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_webhook_nonces', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_id', 128)->unique();
            $table->string('event_type', 64);
            $table->timestamp('received_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_webhook_nonces');
    }
};
