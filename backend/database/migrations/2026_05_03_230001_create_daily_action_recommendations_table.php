<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Action Engine — 每日推薦行動。
 *
 * 一個 user 一天一個 action_key 唯一（防同 cron / job 重推）。
 * 不存文案（活在 config('daily-actions')），只存 key + 完成狀態。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_action_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('recommended_on');
            $table->string('action_key', 80);
            $table->string('phase', 20);
            $table->unsignedSmallInteger('cycle_day')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'recommended_on', 'action_key'], 'dar_user_day_key_unique');
            $table->index(['user_id', 'recommended_on'], 'dar_user_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_action_recommendations');
    }
};
