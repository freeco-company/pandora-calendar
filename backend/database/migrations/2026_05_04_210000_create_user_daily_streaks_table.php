<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-cross-app-streak Phase 1.B (calendar) — per-App 每日連續登入 streak。
 *
 * 鏡像 pandora-meal Phase 1.A 的 schema（PR #171）。
 *
 * 一 user 一 row（unique user_id）；middleware 每 request 跑一次：
 *   - last_login_date == today  → no-op
 *   - last_login_date == yesterday → +1
 *   - else → reset to 1
 *
 * 日期一律走 Asia/Taipei。calendar 沒有「朵朵核心 streak」legacy 欄位，
 * 但保留獨立表是為了未來 Phase 5 集團 master streak overlay 可以乾淨抽離。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_daily_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_login_date')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_streaks');
    }
};
