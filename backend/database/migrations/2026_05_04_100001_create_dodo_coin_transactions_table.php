<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 13 — DodoCoin economy ledger。
 *
 * 紅線：
 *   - 朵朵幣只能賺 / 不能買（保 Premium 純度）
 *   - delta signed int（正 earn / 負 spend），balance_after 寫死避免重新 SUM 整張表
 *   - source enum 嚴格白名單；新 source 加在 EconomyController validation
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dodo_coin_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('delta'); // signed
            $table->string('source', 64); // daily_action / streak / achievement / random_event / solar_term / refund / spend_outfit / spend_story_chapter / spend_pet_item / spend_other
            $table->json('metadata')->nullable();
            $table->integer('balance_after'); // snapshot after this trans
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dodo_coin_transactions');
    }
};
