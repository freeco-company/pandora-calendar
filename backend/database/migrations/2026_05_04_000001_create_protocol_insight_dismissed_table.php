<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * protocol_insight_dismissed — 用戶 dismiss 過的 insight，7 天內不再出。
 *
 * insight_key 是 ProtocolInsightSurfacer 生成的識別字串（含 phase / action_key /
 * symptom 等決定性因子）；同 user 同 key 7 天內 dismissed_at 後不再 surface。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_insight_dismissed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('insight_key', 120);
            $table->timestamp('dismissed_at');
            $table->timestamps();

            $table->index(['user_id', 'insight_key'], 'pid_user_key_idx');
            $table->index(['user_id', 'dismissed_at'], 'pid_user_dismissed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_insight_dismissed');
    }
};
