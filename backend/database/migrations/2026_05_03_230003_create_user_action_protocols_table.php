<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用戶 protocol — feedback 聚合表。
 *
 * 每次 feedback 進來 recompute（非每筆 row 加總，是覆寫 sample_size + score）。
 * effectiveness_score = avg(helpful=1.0 / neutral=0.5 / unhelpful=0.0)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_action_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 20);
            $table->string('action_key', 80);
            $table->unsignedInteger('sample_size')->default(0);
            $table->float('effectiveness_score')->default(0.5);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'phase', 'action_key'], 'uap_user_phase_key_unique');
            $table->index(['user_id', 'phase'], 'uap_user_phase_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_action_protocols');
    }
};
