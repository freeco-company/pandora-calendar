<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1 ADR-007 — calendar 端 PC user.upserted webhook 防 replay。
 * 設計與 mother / meal 一致。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_webhook_nonces', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('event_id', 36)->unique();
            $table->timestamp('received_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_webhook_nonces');
    }
};
