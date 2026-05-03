<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 13 — User SkillPath（fertility / wellness / beauty）。
 * 每月最多切 1 次（service 內 enforce）；progress_json 記每 path 的 quest 狀態。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_skill_paths', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('path', 16); // fertility / wellness / beauty
            $table->timestamp('chosen_at')->useCurrent();
            $table->timestamp('last_changed_at')->nullable();
            $table->json('progress_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_skill_paths');
    }
};
