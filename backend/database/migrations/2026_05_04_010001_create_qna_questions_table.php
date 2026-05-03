<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4 — 含金量 Q&A：朵朵 LLM + RAG 衛教文章。
 * 儲存使用者開放問答歷史（free 3/day · Premium 無限）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qna_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('question', 500);
            $table->text('answer');                 // <= 2000 chars，sanitizer 過後
            $table->json('sources')->nullable();    // 引用的 daily_insight ids
            $table->string('llm_provider', 24)->nullable(); // openai / claude / null / blocked
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('safety_flag', 24)->nullable();  // null / 'redline_self_harm' / 'redline_compliance'
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qna_questions');
    }
};
