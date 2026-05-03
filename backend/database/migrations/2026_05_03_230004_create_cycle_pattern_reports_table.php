<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cycle pattern report — 每個 cycle 結束時生成的回顧。
 *
 * 一 cycle 一份；GeneratePatternReportsCommand daily 04:00 跑（也可手動）。
 * 文案存在 dodo_message（朵朵口吻 4-6 句），其餘 raw stats 在 phase_summary / top_actions / vs_previous。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_pattern_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('cycles')->cascadeOnDelete();
            $table->json('phase_summary');
            $table->json('top_actions');
            $table->json('vs_previous')->nullable();
            $table->text('dodo_message');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['user_id', 'cycle_id'], 'cpr_user_cycle_unique');
            $table->index(['user_id', 'generated_at'], 'cpr_user_generated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_pattern_reports');
    }
};
