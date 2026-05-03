<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recommendation_id')
                ->constrained('daily_action_recommendations')
                ->cascadeOnDelete();
            $table->enum('feedback', ['helpful', 'neutral', 'unhelpful']);
            $table->text('body_note')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['user_id', 'submitted_at'], 'af_user_submitted_idx');
            $table->index('recommendation_id', 'af_recommendation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_feedback');
    }
};
