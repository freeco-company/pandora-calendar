<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3+ — calendar 端 achievement 解鎖記錄。
 * Catalog 鎖在 code（AchievementCatalog），這裡只記「誰、何時、解了什麼」。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('achievement_key', 64);
            $table->timestamp('unlocked_at');
            $table->unique(['user_id', 'achievement_key']);
            $table->index('achievement_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
