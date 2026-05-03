<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 13 — Random event log（每天最多 1 個 event）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('random_event_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key', 64);
            $table->date('triggered_on');
            $table->timestamp('triggered_at')->useCurrent();
            $table->unsignedInteger('reward_coins')->default(0);
            $table->unsignedInteger('reward_xp')->default(0);
            $table->boolean('claimed')->default(false);
            $table->timestamp('claimed_at')->nullable();

            $table->unique(['user_id', 'triggered_on']);
            $table->index('event_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('random_event_logs');
    }
};
