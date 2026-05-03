<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 13 — Story chapter unlocks（25 chapters）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_chapter_unlocks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('chapter');
            $table->string('unlock_source', 32)->default('cycle'); // cycle / coin / onboarding
            $table->timestamp('unlocked_at')->useCurrent();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'chapter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_chapter_unlocks');
    }
};
