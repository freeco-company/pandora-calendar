<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous community Q&A board (P5+)
 *
 * Why anonymous_handle stored vs derived: handle is hash(user_id + post_secret_salt)
 * computed at write time and persisted so admin moderation tools can show stable
 * pseudonyms without re-deriving (and without leaking user_id to clients).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            // user_id never returned to clients; only used for self-delete + moderator ops
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 12-char base32 token derived from hash(user_id + post.id-scoped salt)
            $table->string('anonymous_handle', 32)->index();
            $table->enum('category', ['question', 'experience', 'tip', 'support'])->index();
            $table->string('title', 120);
            $table->text('body');
            $table->enum('status', ['pending', 'published', 'hidden', 'removed'])
                ->default('published')
                ->index();
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('reply_count')->default(0);
            $table->unsignedInteger('reported_count')->default(0);
            $table->float('moderation_score')->default(0); // 0-1, higher = more risk
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'category', 'published_at']);
        });

        Schema::create('community_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('anonymous_handle', 32)->index();
            $table->text('body');
            $table->enum('status', ['pending', 'published', 'hidden', 'removed'])
                ->default('published')
                ->index();
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('reported_count')->default(0);
            $table->float('moderation_score')->default(0);
            $table->boolean('is_dodo')->default(false); // dodo auto-replies (suicide hotline etc.)
            $table->timestamps();

            $table->index(['post_id', 'status']);
        });

        Schema::create('community_reports', function (Blueprint $table) {
            $table->id();
            $table->enum('target_type', ['post', 'reply']);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('reason', ['spam', 'harassment', 'medical_advice', 'commercial', 'self_harm', 'other']);
            $table->text('message')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->unique(['target_type', 'target_id', 'reporter_user_id'], 'community_reports_unique');
        });

        Schema::create('community_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('target_type', ['post', 'reply']);
            $table->unsignedBigInteger('target_id');
            $table->enum('action', ['auto_block', 'auto_flag', 'approve', 'remove', 'restore', 'dodo_reply']);
            $table->string('reason', 255)->nullable();
            $table->json('matched_rules')->nullable(); // {"forbidden_terms": [...], "url": true}
            $table->foreignId('moderator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_moderation_logs');
        Schema::dropIfExists('community_reports');
        Schema::dropIfExists('community_replies');
        Schema::dropIfExists('community_posts');
    }
};
